<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Enums\VerificationStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Page « Communauté » (site et application) : comptes à suivre.
 * - Organisations : actives et non refusées ; vérifiées d'abord, puis les plus suivies.
 * - Personnes : actives, avec au moins un témoignage publié ; les plus suivies d'abord.
 * La personne connectée n'y figure jamais. Voir docs/fonctionnalites/abonnements.md
 *
 * Les témoignages sont comptés en direct (`published_testimony_count`) : le compteur
 * `users.testimony_count` n'est pas tenu à jour partout (suppressions, imports, anciens comptes).
 */
class CommunityDirectory
{
    public const PER_PAGE = 24;

    public const TABS = [
        'organizations' => 'Organisations',
        'people'        => 'Personnes',
    ];

    public function search(string $tab, ?string $q, ?User $viewer, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $query = User::query()
            ->where('status', UserAccountStatus::Active->value)
            ->when($viewer, fn ($query) => $query->whereKeyNot($viewer->id))
            ->withFollowState($viewer)
            ->withPublishedTestimonyCount();

        if ($tab === 'people') {
            $query->where(fn ($q) => $q->whereNull('account_type')->orWhere('account_type', AccountType::Individual->value))
                ->whereHas('testimonies', fn ($t) => $t->published())
                ->orderByDesc('follower_count')
                ->orderByDesc('published_testimony_count');
        } else {
            $query->where('account_type', AccountType::Organization->value)
                ->where(fn ($q) => $q->whereNull('verification_status')
                    ->orWhere('verification_status', '!=', VerificationStatus::Rejected->value))
                // Vérifiées d'abord (tri portable MySQL / SQLite).
                ->orderByRaw('CASE WHEN verification_status = ? THEN 0 ELSE 1 END', [VerificationStatus::Verified->value])
                ->orderByDesc('follower_count');
        }

        $this->filter($query, $q);

        return $query->orderBy('display_name')->paginate($perPage)->withQueryString();
    }

    /**
     * « Mes abonnements » : comptes suivis par $viewer, les plus récents d'abord.
     * Comptes suspendus ou supprimés exclus. Voir docs/fonctionnalites/abonnements.md
     */
    public function following(User $viewer, ?string $q = null, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $query = $viewer->following()
            ->where('users.status', UserAccountStatus::Active->value)
            ->withFollowState($viewer)
            ->withPublishedTestimonyCount()
            ->orderByDesc('follows.created_at')
            ->orderBy('users.display_name');

        $this->filter($query, $q);

        return $query->paginate($perPage)->withQueryString();
    }

    private function filter($query, ?string $q): void
    {
        $term = mb_substr(trim((string) $q), 0, 100);
        if ($term === '') return;

        $like = '%' . addcslashes($term, '\%_') . '%';
        $query->where(fn ($w) => $w->where('users.display_name', 'like', $like)
            ->orWhere('users.organization_name', 'like', $like)
            ->orWhere('users.organization_city', 'like', $like)
            ->orWhere('users.country', 'like', $like));
    }
}
