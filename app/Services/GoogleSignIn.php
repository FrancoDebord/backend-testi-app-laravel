<?php

namespace App\Services;

use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Connexion avec Google, commune à l'API (Api\GoogleAuthController) et au site (Web\AuthController::google) :
 * vérification du jeton d'identité, puis compte retrouvé, lié ou créé.
 * Voir docs/fonctionnalites/connexion-google.md
 */
class GoogleSignIn
{
    private const ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    /**
     * @return array{0: User, 1: bool} le compte et s'il vient d'être créé
     * @throws GoogleSignInException jeton refusé (401), compte supprimé (403), e-mail lié à un autre compte Google (409)
     */
    public function authenticate(string $idToken, bool $acceptedPrivacy = false): array
    {
        $claims   = $this->verify($idToken);
        $googleId = (string) $claims['sub'];
        $email    = (string) $claims['email'];
        $avatar   = $claims['picture'] ?? null;

        // Comptes supprimés compris : leur e-mail et leur google_id restent réservés (index uniques).
        $user = User::withTrashed()->where('google_id', $googleId)->first()
             ?? User::withTrashed()->where('email', $email)->first();

        if ($user?->trashed()) {
            throw new GoogleSignInException(__('account.google.deleted'), 403);
        }
        if ($user && $user->google_id && $user->google_id !== $googleId) {
            throw new GoogleSignInException(__('account.google.email_linked'), 409);
        }

        if (!$user) {
            $user = DB::transaction(function () use ($claims, $googleId, $email, $avatar, $acceptedPrivacy) {
                $user = User::create([
                    'display_name'      => trim((string) ($claims['name'] ?? '')) ?: strstr($email, '@', true),
                    'email'             => $email,
                    'google_id'         => $googleId,
                    'avatar_url'        => $avatar,
                    'email_verified_at' => now(),
                    // Consentement à la politique de confidentialité (docs/fonctionnalites/confidentialite-et-suppression.md)
                    'accepted_privacy_at' => $acceptedPrivacy ? now() : null,
                    'role'              => UserRole::Utilisateur->value,
                    'status'            => UserAccountStatus::Active->value,
                    'is_active'         => true,
                ]);
                UserSetting::create(['user_id' => $user->id]);

                return $user;
            });

            return [$user, true];
        }

        if (!$user->google_id) {
            // Compte e-mail / mot de passe (ou téléphone) existant : on le lie, le mot de passe reste valable.
            $user->update(array_filter([
                'google_id'         => $googleId,
                'avatar_url'        => $user->avatar_url ? null : $avatar,
                'email_verified_at' => $user->email_verified_at ? null : now(),
            ]));
        }

        return [$user, false];
    }

    /** Revendications du jeton, vérifiées par Google. */
    private function verify(string $idToken): array
    {
        try {
            $response = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);
        } catch (\Throwable) {
            throw new GoogleSignInException(__('account.google.verify_failed'));
        }

        $claims = $response->ok() ? $response->json() : null;
        if (!is_array($claims) || empty($claims['sub'])) {
            throw new GoogleSignInException(__('account.google.invalid'));
        }
        if (!in_array($claims['aud'] ?? null, self::audiences(), true)) {
            throw new GoogleSignInException(__('account.google.wrong_audience'));
        }
        if (!in_array($claims['iss'] ?? null, self::ISSUERS, true)) {
            throw new GoogleSignInException(__('account.google.wrong_issuer'));
        }
        if (($claims['email_verified'] ?? null) !== 'true' || empty($claims['email'])) {
            throw new GoogleSignInException(__('account.google.email_unverified'));
        }

        return $claims;
    }

    /** GOOGLE_WEB_CLIENT_ID, puis les audiences héritées de GOOGLE_CLIENT_ID (liste séparée par des virgules). */
    public static function audiences(): array
    {
        $ids = [config('services.google.web_client_id'), ...explode(',', (string) config('services.google.client_id'))];

        return array_values(array_filter(array_map(fn ($id) => trim((string) $id), $ids)));
    }

    /** Identifiant client Web, utilisé par le bouton « Se connecter avec Google » du site ; null = bouton masqué. */
    public static function webClientId(): ?string
    {
        return trim((string) config('services.google.web_client_id')) ?: null;
    }
}
