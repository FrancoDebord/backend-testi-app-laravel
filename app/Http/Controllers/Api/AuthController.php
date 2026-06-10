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
            'user'          => new UserResource($user),
            'access_token'  => $token,
            'token_type'    => 'Bearer',
        ], 'Connexion réussie');
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'display_name' => $request->display_name,
            'email'        => $request->email,
            'password'     => $request->password,
            'country'      => $request->country,
            'role'         => UserRole::Utilisateur->value,
        ]);

        UserSetting::create(['user_id' => $user->id]);

        $token = $user->createToken('mobile-app')->plainTextToken;

        return $this->created([
            'user'          => new UserResource($user),
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

        $user = User::where('firebase_uid', $uid)
                    ->orWhere('phone', $phone)
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

            $user = User::create([
                'display_name' => trim($request->first_name . ' ' . $request->last_name),
                'phone'        => $phone,
                'firebase_uid' => $uid,
                'country'      => $request->country,
                'role'         => UserRole::Utilisateur->value,
            ]);

            UserSetting::create(['user_id' => $user->id]);
            $isNewUser = true;
        } else {
            $user->update(['firebase_uid' => $uid]);
        }

        $token = $user->createToken('mobile-app')->plainTextToken;

        return $this->success([
            'user'          => new UserResource($user),
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
            'user'         => new UserResource($user),
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'is_new_user'  => $user->wasRecentlyCreated,
        ], 'Connexion réussie');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(new UserResource($request->user()), '');
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
