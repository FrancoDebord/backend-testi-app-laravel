<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\PhoneAuthRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Requests\Api\Auth\SocialAuthRequest;
use App\Http\Resources\UserResource;
use App\Models\SocialAuthProvider;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\OrganizationAccounts;
use App\Support\PhoneNumber;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->error('Email ou mot de passe incorrect', 401);
        }

        if ($user->status->value === 'banned') {
            return $this->error('Votre compte a été banni', 403);
        }

        $token = $user->createToken('mobile-app')->plainTextToken;

        return $this->success([
            'user'          => UserResource::owner($user),
            'access_token'  => $token,
            'token_type'    => 'Bearer',
        ], 'Connexion réussie');
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        // Téléphone de contact, non vérifié : il ne permet pas la connexion par téléphone.
        $phone = PhoneNumber::toE164($request->phone_country, $request->phone);

        $user = User::create([
            'display_name' => $request->display_name,
            'email'        => $request->email,
            'password'     => $request->password,
            'country'      => $request->country,
            'phone'        => $phone,
            'phone_country' => $phone ? strtolower($request->phone_country) : null,
            'role'         => UserRole::Utilisateur->value,
            // Explicite : sans cela, UserResource lisait un statut null (valeur par défaut SQL non rechargée).
            'status'       => UserAccountStatus::Active->value,
        ] + OrganizationAccounts::registrationAttributes($request->validated())); // organisation : en attente de vérification

        UserSetting::create(['user_id' => $user->id]);

        $token = $user->createToken('mobile-app')->plainTextToken;

        return $this->created([
            'user'          => UserResource::owner($user),
            'access_token'  => $token,
            'token_type'    => 'Bearer',
            'is_new_user'   => true,
        ], 'Compte créé avec succès');
    }

    public function phoneAuth(PhoneAuthRequest $request): JsonResponse
    {
        // Validate Firebase token and extract UID
        $firebaseData = $this->verifyFirebaseToken($request->firebase_token);

        if (!$firebaseData) {
            return $this->error('Token Firebase invalide', 401);
        }

        $uid   = $firebaseData['uid'];
        $phone = $request->phone;

        // Seul un numéro vérifié par SMS désigne un compte : un numéro de contact saisi sur le site ou dans
        // le profil ne permet jamais d'entrer dans le compte qui l'a déclaré (docs/fonctionnalites/telephone.md).
        $user = User::where('firebase_uid', $uid)
                    ->orWhere(fn ($q) => $q->where('phone', $phone)->whereNotNull('phone_verified_at'))
                    ->first();

        $isNewUser = false;

        if (!$user) {
            // New user - check if profile data provided
            if (!$request->first_name) {
                return $this->success([
                    'is_new_user'    => true,
                    'firebase_token' => $request->firebase_token,
                    'phone'          => $phone,
                ], 'Nouveau profil requis');
            }

            // Le numéro appartient à la personne qui vient de le confirmer par SMS : il est retiré d'un
            // éventuel compte qui l'avait seulement déclaré (sinon la création échouerait : numéro unique).
            User::where('phone', $phone)->whereNull('phone_verified_at')
                ->update(['phone' => null, 'phone_country' => null]);

            $user = User::create([
                'display_name' => trim($request->first_name . ' ' . $request->last_name),
                'phone'        => $phone,
                'phone_verified_at' => now(),
                'firebase_uid' => $uid,
                'country'      => $request->country,
                'role'         => UserRole::Utilisateur->value,
                'status'       => UserAccountStatus::Active->value,
            ]);

            UserSetting::create(['user_id' => $user->id]);
            $isNewUser = true;
        } else {
            $user->update(['firebase_uid' => $uid]);
        }

        $token = $user->createToken('mobile-app')->plainTextToken;

        return $this->success([
            'user'          => UserResource::owner($user),
            'access_token'  => $token,
            'token_type'    => 'Bearer',
            'is_new_user'   => $isNewUser,
        ], 'Connexion réussie');
    }

    public function socialAuth(SocialAuthRequest $request): JsonResponse
    {
        $provider = $request->provider; // 'google'
        $idToken  = $request->id_token;

        // Vérification du token auprès de Google
        $googleUser = $this->verifyGoogleToken($idToken);

        if (!$googleUser) {
            return $this->error('Token Google invalide ou expiré', 401);
        }

        // Validation optionnelle du client ID (sécurité renforcée)
        $clientId = config('services.google.client_id');
        if ($clientId && ($googleUser['aud'] ?? null) !== $clientId) {
            return $this->error('Token non autorisé pour cette application', 401);
        }

        $googleId      = $googleUser['sub'];                    // ID stable Google
        $email         = $googleUser['email'] ?? null;
        $name          = $googleUser['name'] ?? $email ?? 'Utilisateur';
        $avatarUrl     = $googleUser['picture'] ?? null;
        $emailVerified = ($googleUser['email_verified'] ?? false) === true
                      || ($googleUser['email_verified'] ?? false) === 'true';

        // Chercher le lien social existant
        $socialLink = SocialAuthProvider::where('provider', $provider)
                                        ->where('provider_id', $googleId)
                                        ->with('user')
                                        ->first();

        if ($socialLink) {
            $user = $socialLink->user;
            // Mettre à jour l'avatar si Google en a un nouveau
            if ($avatarUrl && $user->avatar_url !== $avatarUrl) {
                $user->update(['avatar_url' => $avatarUrl]);
            }
        } else {
            // Créer ou retrouver l'utilisateur par email
            $user = $email
                ? User::firstOrCreate(
                    ['email' => $email],
                    [
                        'display_name'      => $name,
                        'avatar_url'        => $avatarUrl,
                        'email_verified_at' => $emailVerified ? now() : null,
                        'role'              => UserRole::Utilisateur->value,
                        'status'            => UserAccountStatus::Active->value,
                        'is_active'         => true,
                    ]
                )
                : User::create([
                    'display_name' => $name,
                    'avatar_url'   => $avatarUrl,
                    'role'         => UserRole::Utilisateur->value,
                    'status'       => UserAccountStatus::Active->value,
                    'is_active'    => true,
                ]);

            // Lier le compte Google
            SocialAuthProvider::create([
                'user_id'      => $user->id,
                'provider'     => $provider,
                'provider_id'  => $googleId,
                'access_token' => $idToken,
            ]);

            if ($user->wasRecentlyCreated) {
                UserSetting::create(['user_id' => $user->id]);
            }
        }

        if ($user->status->value === 'banned') {
            return $this->error('Votre compte a été banni', 403);
        }

        $token = $user->createToken('mobile-app')->plainTextToken;

        return $this->success([
            'user'         => UserResource::owner($user),
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'is_new_user'  => $user->wasRecentlyCreated,
        ], 'Connexion réussie');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(UserResource::owner($request->user()), '');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return $this->success(null, 'Déconnexion réussie');
    }

    public function refresh(Request $request): JsonResponse
    {
        $request->validate(['refresh_token' => 'required|string']);

        // Sanctum doesn't use refresh tokens by default — reuse current token logic
        $user  = $request->user();
        $user->tokens()->delete();
        $token = $user->createToken('mobile-app')->plainTextToken;

        return $this->success([
            'access_token' => $token,
            'token_type'   => 'Bearer',
        ], 'Token rafraîchi');
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password'      => 'required|string',
            'new_password'          => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!$user->password || !Hash::check($request->current_password, $user->password)) {
            return $this->error('Mot de passe actuel incorrect', 403);
        }

        $user->update(['password' => $request->new_password]);

        // Révoquer tous les autres tokens pour forcer une reconnexion sur les autres appareils
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return $this->success(null, 'Mot de passe mis à jour');
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);
        // In production, dispatch a password reset email here
        return $this->success(null, 'Si l\'adresse email existe, un lien de réinitialisation sera envoyé');
    }

    /**
     * Vérifie un Google ID token via l'endpoint officiel Google.
     * Retourne les claims (sub, email, name, picture…) ou null si invalide.
     */
    private function verifyGoogleToken(string $idToken): ?array
    {
        try {
            $response = Http::timeout(5)->get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $idToken,
            ]);

            if (!$response->ok()) {
                return null;
            }

            $data = $response->json();

            // Le champ 'sub' est l'identifiant stable de l'utilisateur Google
            return isset($data['sub']) ? $data : null;

        } catch (\Throwable) {
            return null;
        }
    }

    private function verifyFirebaseToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        try {
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            return [
                'uid'   => $payload['sub'] ?? $payload['uid'] ?? Str::uuid()->toString(),
                'email' => $payload['email'] ?? null,
                'phone' => $payload['phone_number'] ?? null,
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
