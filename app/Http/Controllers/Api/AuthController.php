<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\PhoneAuthRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\OrganizationAccounts;
use App\Services\PhoneLogin;
use App\Support\PhoneNumber;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(LoginRequest $request): JsonResponse
    {
        // E-mail ou téléphone de contact + mot de passe (docs/fonctionnalites/telephone.md). Même message pour un
        // identifiant inconnu, un mauvais mot de passe ou un compte sans mot de passe (Google, SMS) : pas d'énumération.
        $user = filled($request->email)
            ? User::where('email', $request->email)->first()
            : PhoneLogin::findUser($request->phone_country, $request->phone);

        if (!$user || !$user->password || !Hash::check($request->password, $user->password)) {
            // Message selon l'identifiant saisi (toujours le même, que le compte existe ou non).
            return $this->error(__(filled($request->email) ? 'account.api.invalid_credentials' : 'account.api.invalid_phone_credentials'), 401);
        }

        if ($user->status->value === 'banned') {
            return $this->error(__('account.api.banned'), 403);
        }

        $token = $user->createToken('mobile-app')->plainTextToken;

        return $this->success([
            'user'          => UserResource::owner($user),
            'access_token'  => $token,
            'token_type'    => 'Bearer',
        ], __('account.api.login_success'));
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        // Téléphone de contact, non vérifié : il permet la connexion avec le mot de passe, jamais par SMS.
        $phone = PhoneNumber::toE164($request->phone_country, $request->phone);

        $user = User::create([
            'display_name' => $request->display_name,
            'email'        => $request->email,
            'password'     => $request->password,
            'country'      => $request->country,
            'phone'        => $phone,
            'phone_country' => $phone ? strtolower($request->phone_country) : null,
            'accepted_privacy_at' => now(),
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
        ], __('account.api.registered'));
    }

    public function phoneAuth(PhoneAuthRequest $request): JsonResponse
    {
        // Validate Firebase token and extract UID
        $firebaseData = $this->verifyFirebaseToken($request->firebase_token);

        if (!$firebaseData) {
            return $this->error(__('account.api.invalid_firebase_token'), 401);
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
                ], __('account.api.profile_required'));
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
                // Case cochée dans l'application (facultatif ici) : docs/fonctionnalites/confidentialite-et-suppression.md
                'accepted_privacy_at' => $request->boolean('accepted_privacy') ? now() : null,
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
        ], __('account.api.login_success'));
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadExists(['testimonies as has_conversion_testimony' => fn ($t) => $t->conversion()->published()]);
        $data = UserResource::owner($user)->resolve($request);
        // Au nom d'un compte administré : ni e-mail ni téléphone du titulaire
        // (docs/fonctionnalites/administrateurs-de-compte.md).
        if ($actor = $request->attributes->get(\App\Services\AccountDelegation::ACTOR_ATTRIBUTE)) {
            unset($data['email'], $data['phone'], $data['phone_country']);
            $data['acting_as'] = true;
            $data['acting_actor'] = ['id' => $actor->id, 'display_name' => $actor->display_name];
        }

        return $this->success($data, '');
    }

    /**
     * Déconnexion : jeton de session supprimé, ainsi que les jetons push de cet appareil — ceux enregistrés avec
     * cette session, et celui envoyé dans { device_token } (ou { token }). docs/fonctionnalites/notifications-push.md
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $deviceToken = $request->input('device_token', $request->input('token'));
        \App\Models\DeviceToken::where('user_id', $user->id)
            ->where(function ($q) use ($request, $deviceToken) {
                $q->whereRaw('1 = 0');
                if ($id = DeviceTokenController::accessTokenId($request)) {
                    $q->orWhere('access_token_id', $id);
                }
                if (is_string($deviceToken) && $deviceToken !== '') {
                    $q->orWhere('token', $deviceToken);
                }
            })->delete();

        $user->currentAccessToken()->delete();
        return $this->success(null, __('account.api.logged_out'));
    }

    public function refresh(Request $request): JsonResponse
    {
        $request->validate(['refresh_token' => 'required|string']);

        // Renouvelle la session de CET appareil seulement : supprimer toutes les sessions de la personne
        // déconnectait ses autres appareils et rattachait tous ses jetons push à une seule session
        // (perdus à la déconnexion de celle-ci) — docs/fonctionnalites/notifications-push.md.
        $user    = $request->user();
        $current = $user->currentAccessToken();
        $oldId   = $current && method_exists($current, 'getKey') ? $current->getKey() : null;
        $newToken = $user->createToken('mobile-app');
        $token = $newToken->plainTextToken;
        if ($oldId !== null) {
            // Jetons push de l'appareil rattachés à la nouvelle session (supprimés à sa déconnexion).
            \App\Models\DeviceToken::where('access_token_id', $oldId)->update(['access_token_id' => $newToken->accessToken->getKey()]);
            $user->tokens()->whereKey($oldId)->delete();
        }

        return $this->success([
            'access_token' => $token,
            'token_type'   => 'Bearer',
        ], __('account.api.token_refreshed'));
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password'      => 'required|string',
            'new_password'          => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!$user->password || !Hash::check($request->current_password, $user->password)) {
            return $this->error(__('account.api.wrong_current_password'), 403);
        }

        $user->update(['password' => $request->new_password]);

        // Révoquer tous les autres tokens pour forcer une reconnexion sur les autres appareils
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return $this->success(null, __('account.api.password_updated'));
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);
        // In production, dispatch a password reset email here
        return $this->success(null, __('account.api.reset_link_sent'));
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
