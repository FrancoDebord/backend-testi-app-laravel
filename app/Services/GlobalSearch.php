<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\LiveStatus;
use App\Enums\UserAccountStatus;
use App\Enums\VerificationStatus;
use App\Models\Event;
use App\Models\PrayerRequest;
use App\Models\PrayerSession;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Recherche globale du site (/recherche, barre de l'en-tête) : témoignages, comptes, requêtes de prière,
 * événements et sessions de prière, avec les mêmes règles de visibilité que leurs listes.
 * Voir docs/fonctionnalites/recherche.md
 */
class GlobalSearch
{
    /** Onglets (clé d'URL ?onglet=) dans l'ordre d'affichage. */
    public const TABS = ['temoignages', 'comptes', 'prieres', 'evenements', 'sessions'];

    public const MIN_LENGTH = 2;

    /** Requête de l'onglet, filtrée par le texte $q (déjà nettoyé, au moins MIN_LENGTH caractères). */
    public function query(string $tab, string $q, ?User $viewer): Builder
    {
        $like = '%' . addcslashes($q, '\\%_') . '%';

        return match ($tab) {
            'comptes'    => $this->accounts($like, $viewer),
            'prieres'    => PrayerRequest::query()->with(['user', 'event', 'sourceTestimony:id,title'])->withPrayedBy($viewer)
                ->visibleTo($viewer)->where('body', 'like', $like)->latest(),
            'evenements' => Event::query()->with(['organizer', 'images', 'lives' => fn ($l) => $l->where('status', LiveStatus::Live->value)->latest()])
                ->published()
                ->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('description', 'like', $like)
                    ->orWhere('city', 'like', $like)->orWhere('location', 'like', $like))
                ->orderByDesc('starts_at'),
            'sessions'   => PrayerSession::query()->with(['host', 'event', 'lives' => fn ($l) => $l->active()->latest()])
                ->withRegistrationOf($viewer)->visibleTo($viewer)
                ->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('description', 'like', $like))
                ->orderByDesc('starts_at'),
            default      => Testimony::query()->with(['user', 'category'])->published()
                ->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('body_text', 'like', $like))
                ->latestPublished(),
        };
    }

    /** Nombre de résultats par onglet (affiché sur les onglets). */
    public function counts(string $q, ?User $viewer): array
    {
        $counts = [];
        foreach (self::TABS as $tab) {
            $counts[$tab] = $this->query($tab, $q, $viewer)->toBase()->getCountForPagination();
        }

        return $counts;
    }

    /**
     * Comptes actifs et non privés (comme l'autocomplétion), organisations refusées exclues (comme la page
     * Communauté), par nom, nom d'organisation ou ville. État d'abonnement chargé pour le bouton « Suivre ».
     */
    private function accounts(string $like, ?User $viewer): Builder
    {
        return User::query()
            ->withFollowState($viewer)
            ->where('status', UserAccountStatus::Active->value)
            ->whereNotIn('id', DB::table('user_settings')->where('is_private_account', true)->select('user_id'))
            ->where(fn ($w) => $w->whereNull('account_type')
                ->orWhere('account_type', '!=', AccountType::Organization->value)
                ->orWhereNull('verification_status')
                ->orWhere('verification_status', '!=', VerificationStatus::Rejected->value))
            ->where(fn ($w) => $w->where('display_name', 'like', $like)
                ->orWhere('organization_name', 'like', $like)
                ->orWhere('organization_city', 'like', $like))
            ->orderByRaw('CASE WHEN verification_status = ? THEN 0 ELSE 1 END', [VerificationStatus::Verified->value])
            ->orderByDesc('follower_count')
            ->orderBy('display_name');
    }
}
