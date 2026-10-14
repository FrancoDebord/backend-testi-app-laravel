<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\TestimonyStatus;
use App\Enums\TestimonyVisibility;
use App\Enums\UserAccountStatus;
use App\Enums\VerificationStatus;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * « Comptes à suivre » (site et application) : comptes actifs que la personne ne suit pas encore.
 *
 * Score d'un compte :
 *   - organisation vérifiée (+3) ;
 *   - témoignages approuvés et publics, les récents comptant plus (moins de 30 jours ×3,
 *     moins de 180 jours ×2, plus anciens ×1), échelle logarithmique ;
 *   - engagement de ses témoignages (vues, prières, réactions), échelle logarithmique ;
 *   - suivi par des comptes que je suis (+3 chacun, 3 au plus) ; me suit déjà (+2) ;
 *   - centres d'intérêt communs (catégories de mes interactions, Recommendations::interests) (+3 au plus).
 * Visiteur : comptes vérifiés et les plus actifs seulement.
 * Classement mis en cache 5 minutes par personne ; les comptes suivis depuis sont retirés à la lecture.
 * Voir docs/fonctionnalites/abonnements.md
 */
class AccountSuggestions
{
    public const TYPES = ['all', 'organizations', 'people'];
    public const CACHE_SECONDS = 300;
    private const POOL = 300;
    private const RANKED = 60;

    public function __construct(private Recommendations $recommendations) {}

    /**
     * @return Collection<int, array{user: User, reason: string, score: float}>
     */
    public function for(?User $viewer, int $limit = 10, string $type = 'all'): Collection
    {
        $type = in_array($type, self::TYPES, true) ? $type : 'all';
        $limit = max(1, min(30, $limit));

        $ranked = Cache::remember('suggest:' . ($viewer?->id ?? 'guest') . ":{$type}", self::CACHE_SECONDS,
            fn () => $this->rank($viewer, $type));

        // Abonnements faits depuis la mise en cache : retirés tout de suite.
        $followed = $viewer
            ? DB::table('follows')->where('follower_id', $viewer->id)->pluck('following_id')->map(fn ($id) => strtolower($id))->flip()
            : collect();
        $ranked = array_values(array_filter($ranked, fn ($r) => !$followed->has(strtolower($r['id']))));
        $ranked = array_slice($ranked, 0, $limit);
        if ($ranked === []) {
            return collect();
        }

        $users = User::query()
            ->whereIn('id', array_column($ranked, 'id'))
            ->where('status', UserAccountStatus::Active->value)
            ->withPublishedTestimonyCount()
            ->get()
            ->keyBy('id');

        return collect($ranked)
            ->filter(fn ($r) => $users->has($r['id']))
            ->map(fn ($r) => ['user' => $users[$r['id']], 'reason' => $r['reason'], 'score' => $r['score']])
            ->values();
    }

    /** Forme JSON publique (API) : jamais d'e-mail ni de téléphone. */
    public static function toArray(User $u, string $reason): array
    {
        $isOrg = $u->account_type === AccountType::Organization;
        $testimonies = $u->publishedTestimonyCount();

        return [
            'id'                   => $u->id,
            'display_name'         => $u->display_name,
            'avatar_url'           => $u->avatar_url,
            'cover_url'            => $u->cover_url,
            'country'              => $u->country,
            'bio'                  => $u->bio,
            'account_type'         => $u->account_type?->value ?? 'individual',
            'organization_name'    => $u->organization_name,
            'organization_type'    => $u->organization_type?->value,
            'organization_city'    => $u->organization_city,
            'is_verified'          => $u->isVerified(),
            'follower_count'       => (int) $u->follower_count,
            'following_count'      => (int) $u->following_count,
            'testimony_count'      => $testimonies,
            'is_following'         => false,
            // Champs demandés par les écrans « Comptes à suivre »
            'displayName'          => $u->display_name,
            'avatarUrl'            => $u->avatar_url,
            'isFollowing'          => false,
            'testimonyCount'       => $testimonies,
            'followersCount'       => (int) $u->follower_count,
            'isVerified'           => $u->isVerified(),
            ...\App\Models\User::approvalFieldsFor($u), // badge-approuve.md
            'isOrganization'       => $isOrg,
            'reason'               => $reason,
        ];
    }

    /** Oublie le classement en cache (après un abonnement, par exemple). */
    public static function forget(?User $viewer): void
    {
        foreach (self::TYPES as $type) {
            Cache::forget('suggest:' . ($viewer?->id ?? 'guest') . ":{$type}");
        }
    }

    // ─── Classement ──────────────────────────────────────────────────────────

    /** @return array<int, array{id: string, reason: string, score: float}> */
    private function rank(?User $viewer, string $type): array
    {
        $myFollowing = $viewer
            ? DB::table('follows')->where('follower_id', $viewer->id)->limit(1000)->pluck('following_id')->all()
            : [];

        $base = fn () => User::query()
            ->where('status', UserAccountStatus::Active->value)
            ->when($viewer, fn ($q) => $q->whereKeyNot($viewer->id)->whereNotIn('id', $myFollowing))
            ->when($type === 'organizations', fn ($q) => $q->where('account_type', AccountType::Organization->value))
            ->when($type === 'people', fn ($q) => $q->where(fn ($w) => $w->whereNull('account_type')->orWhere('account_type', AccountType::Individual->value)))
            // Organisations refusées écartées ; personnes : au moins un témoignage publié.
            ->where(fn ($w) => $w
                ->where(fn ($o) => $o->where('account_type', AccountType::Organization->value)
                    ->where(fn ($v) => $v->whereNull('verification_status')->orWhere('verification_status', '!=', VerificationStatus::Rejected->value)))
                ->orWhereHas('testimonies', fn ($t) => $t->published()));

        // Réservoir : les plus actifs / vérifiés / suivis, plus les comptes suivis par mes abonnements.
        $pool = $base()
            ->withPublishedTestimonyCount()
            ->orderByRaw('CASE WHEN verification_status = ? THEN 0 ELSE 1 END', [VerificationStatus::Verified->value])
            ->orderByDesc('published_testimony_count')
            ->orderByDesc('follower_count')
            ->limit(self::POOL)
            ->pluck('id')->all();

        $viaFollowing = [];
        if ($myFollowing !== []) {
            $viaFollowing = DB::table('follows')
                ->whereIn('follower_id', $myFollowing)
                ->groupBy('following_id')
                ->selectRaw('following_id, COUNT(*) as total')
                ->orderByDesc('total')
                ->limit(self::POOL)
                ->pluck('total', 'following_id')->all();
        }
        $followsMe = $viewer
            ? array_flip(DB::table('follows')->where('following_id', $viewer->id)->limit(1000)->pluck('follower_id')->all())
            : [];

        $ids = array_values(array_unique([...$pool, ...array_keys($viaFollowing)]));
        if ($ids === []) {
            return [];
        }
        // Les comptes venus des abonnements passent aussi par les filtres (actif, pas moi, pas déjà suivi).
        $users = $base()->whereIn('id', $ids)->get(['id', 'display_name', 'account_type', 'verification_status', 'follower_count'])->keyBy('id');
        $ids = $users->keys()->all();

        // Témoignages publiés : récence pondérée, engagement, catégories.
        $now = now();
        $stats = DB::table('testimonies')
            ->whereIn('user_id', $ids)
            ->where('status', TestimonyStatus::Approved->value)
            ->where('visibility', TestimonyVisibility::Public->value)
            ->whereNull('deleted_at')
            ->groupBy('user_id')
            ->selectRaw('user_id,
                COUNT(*) as total,
                SUM(CASE WHEN COALESCE(approved_at, created_at) >= ? THEN 3 WHEN COALESCE(approved_at, created_at) >= ? THEN 2 ELSE 1 END) as weighted,
                SUM(CASE WHEN COALESCE(approved_at, created_at) >= ? THEN 1 ELSE 0 END) as recent,
                SUM(COALESCE(views_count, 0)) as views,
                SUM(COALESCE(prayer_count, 0) + COALESCE(like_count, 0)) as engagement',
                [$now->copy()->subDays(30), $now->copy()->subDays(180), $now->copy()->subDays(90)])
            ->get()->keyBy('user_id');

        $interests = $this->recommendations->interests($viewer);
        $categories = [];
        if ($interests !== []) {
            DB::table('testimonies')
                ->whereIn('user_id', $ids)
                ->where('status', TestimonyStatus::Approved->value)
                ->where('visibility', TestimonyVisibility::Public->value)
                ->whereNull('deleted_at')
                ->whereIn('category_slug', array_keys($interests))
                ->groupBy('user_id', 'category_slug')
                ->selectRaw('user_id, category_slug, COUNT(*) as total')
                ->get()
                ->each(function ($row) use (&$categories) {
                    $categories[$row->user_id][$row->category_slug] = (int) $row->total;
                });
        }

        // Noms des comptes suivis qui suivent chaque suggestion (raison « Suivi par X »).
        $followedBy = [];
        if ($viaFollowing !== []) {
            $names = User::whereIn('id', $myFollowing)->pluck('display_name', 'id');
            DB::table('follows')->whereIn('follower_id', $myFollowing)->whereIn('following_id', $ids)
                ->orderByDesc('created_at')->get(['follower_id', 'following_id'])
                ->each(function ($f) use (&$followedBy, $names) {
                    if (isset($names[$f->follower_id]) && count($followedBy[$f->following_id] ?? []) < 2) {
                        $followedBy[$f->following_id][] = $names[$f->follower_id];
                    }
                });
        }

        $rows = [];
        foreach ($users as $id => $u) {
            $s = $stats[$id] ?? null;
            $verified = $u->verification_status === VerificationStatus::Verified; // organisation ou personne vérifiée
            $common = (int) ($viaFollowing[$id] ?? 0);
            $interest = 0.0;
            foreach ($categories[$id] ?? [] as $slug => $count) {
                $interest += ($interests[$slug] ?? 0) * min(3, $count);
            }

            $score = ($verified ? 3 : 0)
                + 2 * log10(1 + (float) ($s->weighted ?? 0))
                + 0.5 * log10(1 + (float) ($s->views ?? 0) + 3 * (float) ($s->engagement ?? 0))
                + 0.3 * log10(1 + (float) $u->follower_count)
                + 3 * min(3, $common)
                + (isset($followsMe[$id]) ? 2 : 0)
                + min(3.0, $interest);

            $rows[] = [
                'id'     => $id,
                'score'  => round($score, 4),
                'reason' => $this->reason($u, $verified, $followedBy[$id] ?? [], $common, isset($followsMe[$id]), (int) ($s->recent ?? 0), (int) ($s->total ?? 0), $interest),
                'name'   => mb_strtolower((string) $u->display_name),
            ];
        }

        usort($rows, fn ($a, $b) => [$b['score'], $a['name']] <=> [$a['score'], $b['name']]);

        return array_map(fn ($r) => ['id' => $r['id'], 'reason' => $r['reason'], 'score' => $r['score']], array_slice($rows, 0, self::RANKED));
    }

    /** Raison affichée sous le nom, la plus personnelle d'abord. */
    private function reason(User $u, bool $verified, array $followedBy, int $common, bool $followsMe, int $recent, int $total, float $interest): string
    {
        if ($followedBy !== []) {
            $others = $common - 1;
            return 'Suivi par ' . $followedBy[0] . ($others > 0 ? ' et ' . $others . ' autre' . ($others > 1 ? 's' : '') : '') . ' que vous suivez';
        }
        if ($followsMe) return 'Vous suit';
        if ($verified) return $u->account_type === AccountType::Organization ? 'Organisation vérifiée' : 'Compte vérifié';
        if ($recent >= 3 || $total >= 5) return 'Publie souvent des témoignages';
        if ($interest > 0) return 'Témoigne sur des sujets qui vous intéressent';
        if ($u->account_type === AccountType::Organization) return 'Organisation de la communauté';

        return $total > 0 ? 'Partage des témoignages' : 'Membre de la communauté';
    }
}
