<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AccountDeletion;
use App\Services\PrivacyPolicy;
use App\Support\Locales;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Politique de confidentialité (sans connexion) et suppression du compte connecté.
 * Voir docs/fonctionnalites/confidentialite-et-suppression.md
 */
class LegalController extends Controller
{
    use ApiResponse;

    /** GET legal/privacy?lang=fr|en (sinon Accept-Language) : { title, updated_at, html, url }. */
    public function privacy(Request $request): JsonResponse
    {
        $locale = Locales::normalize($request->query('lang')) ?? app()->getLocale();

        return $this->success(PrivacyPolicy::payload($locale))
            ->header('Content-Language', PrivacyPolicy::locale($locale));
    }

    /**
     * DELETE me (et users/me) : suppression définitive du compte et de ses données.
     * Confirmation : { password } pour un compte avec mot de passe, sinon { confirm: true }.
     * Refusée (403) pour un super-administrateur ou au nom d'un compte administré (X-Act-As).
     */
    public function destroyMe(Request $request, AccountDeletion $deletion): JsonResponse
    {
        $user = $request->user();

        if (!AccountDeletion::allowed($user)) {
            return $this->error(__('privacy.deletion.super_admin_forbidden'), 403);
        }
        // Compte créé par téléphone ou Google/Facebook : pas de mot de passe, confirmation explicite.
        if (empty($user->password)) {
            $request->validate(['confirm' => 'accepted'], ['confirm.accepted' => __('profile.api.confirm_delete')]);
        } else {
            $request->validate(['password' => 'required|string']);
            if (!password_verify($request->password, $user->password)) {
                return $this->error(__('profile.api.wrong_password'), 403);
            }
        }

        $deletion->delete($user);

        return $this->success(null, __('privacy.deletion.api.deleted'));
    }
}
