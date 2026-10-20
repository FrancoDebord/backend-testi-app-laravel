<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AccountDeletion;
use App\Services\AccountDeletionRequests;
use App\Services\PrivacyPolicy;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Pages publiques, sans connexion : politique de confidentialité (/confidentialite, /privacy) et
 * suppression de compte (/suppression-compte, /delete-account), exigées par Google Play.
 * Voir docs/fonctionnalites/confidentialite-et-suppression.md
 */
class LegalController extends Controller
{
    public function privacy(Request $request, string $default): View
    {
        $locale = $this->locale($request, $default);

        return view('legal.privacy', [
            'locale'      => $locale,
            'title'       => PrivacyPolicy::title($locale),
            'updatedAt'   => PrivacyPolicy::updatedAt($locale),
            'html'        => $this->webHtml(PrivacyPolicy::html($locale)),
            'otherUrl'    => $this->otherUrl($locale, 'legal.privacy', 'legal.privacy.en'),
            'deletionUrl' => route($locale === 'en' ? 'account-deletion.en' : 'account-deletion'),
        ]);
    }

    public function accountDeletion(Request $request, string $default): View
    {
        $locale = $this->locale($request, $default);
        $user   = Auth::user();

        return view('legal.account-deletion', [
            'locale'     => $locale,
            'user'       => $user,
            'canDelete'  => $user && AccountDeletion::allowed($user),
            'otherUrl'   => $this->otherUrl($locale, 'account-deletion', 'account-deletion.en'),
            'privacyUrl' => PrivacyPolicy::url($locale),
        ]);
    }

    /** Formulaire sans connexion : demande enregistrée, super-administrateurs prévenus. */
    public function requestDeletion(Request $request, AccountDeletionRequests $requests): RedirectResponse
    {
        $this->locale($request, $request->input('lang', Locales::DEFAULT));

        // Champ piège invisible : rempli par les robots seulement. Réponse identique, rien n'est enregistré.
        if (filled($request->input('website'))) {
            return back()->with('success', __('privacy.deletion.request_sent'));
        }

        $data = $request->validate([
            'contact' => ['required', 'string', 'max:190', function ($attribute, $value, $fail) {
                $isEmail = filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false;
                $isPhone = preg_match('/^\+?[\d\s().-]{8,20}$/', trim($value)) === 1;
                if (!$isEmail && !$isPhone) {
                    $fail(__('privacy.deletion.contact_invalid'));
                }
            }],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $requests->submit($data['contact'], $data['message'] ?? null, $request->ip());

        // Même message, qu'un compte corresponde ou non : la page ne révèle pas quels comptes existent.
        return back()->with('success', __('privacy.deletion.request_sent'));
    }

    /** Personne connectée : suppression immédiate et définitive, après confirmation. */
    public function destroyAccount(Request $request, AccountDeletion $deletion): RedirectResponse
    {
        $this->locale($request, $request->input('lang', Locales::DEFAULT));
        // Pages réservées au titulaire : Auth::user() est la personne réelle, jamais un compte administré.
        $user = $request->user();

        if (!AccountDeletion::allowed($user)) {
            return back()->with('error', __('privacy.deletion.super_admin_forbidden'));
        }
        if (!empty($user->password)) {
            $request->validate(['password' => 'required|string']);
            if (!password_verify($request->password, $user->password)) {
                throw ValidationException::withMessages(['password' => __('privacy.deletion.wrong_password')]);
            }
        } else {
            $request->validate(['confirm' => 'accepted']);
        }

        $locale = app()->getLocale();
        Auth::logout();
        $deletion->delete($user);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($locale === 'en' ? 'account-deletion.en' : 'account-deletion', ['lang' => $locale])
            ->with('success', __('privacy.deletion.done'));
    }

    /**
     * Langue de la page : ?lang=, puis choix fait avec le sélecteur (session), puis langue du compte,
     * puis celle de l'adresse (/confidentialite : fr, /privacy : en). Appliquée sans être enregistrée.
     */
    private function locale(Request $request, string $default): string
    {
        $locale = Locales::normalize($request->query('lang'))
            ?? ($request->hasSession() ? Locales::normalize($request->session()->get(Locales::SESSION_KEY)) : null)
            ?? Locales::normalize($request->user()?->locale)
            ?? PrivacyPolicy::locale($default);
        Locales::apply($locale);

        return $locale;
    }

    /**
     * HTML de la page : la ligne « Dernière mise à jour » du début est retirée (affichée sous le titre) et
     * les tableaux larges défilent dans leur cadre, jamais la page (comme la documentation).
     */
    private function webHtml(string $html): string
    {
        $html = preg_replace('#\A\s*<p><strong>(?:Dernière mise à jour|Last updated)[^<]*</strong></p>\s*#u', '', $html, 1);

        return str_replace(['<table>', '</table>'], ['<div class="doc-table"><table>', '</table></div>'], $html);
    }

    private function otherUrl(string $locale, string $frRoute, string $enRoute): string
    {
        return $locale === 'en' ? route($frRoute, ['lang' => 'fr']) : route($enRoute, ['lang' => 'en']);
    }
}
