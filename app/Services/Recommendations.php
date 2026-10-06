<?php

namespace App\Services;

use App\Models\Testimony;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Recommandations automatiques et fil « Pour vous » (site et application).
 *
 * Signaux de la personne connectée (180 derniers jours) :
 *   - centres d'intérêt : catégories des témoignages sauvegardés (×4), des réactions (×3),
 *     commentés (×2), regardés (×1 par lecture, 3 au plus) et publiés (×1) ;
 *   - comptes suivis ;
 *   - déjà vu (historique testimony_views).
 * Sans signal (visiteur, nouveau compte) : les plus récents et les plus vus, en alternance.
 * Voir docs/fonctionnalites/recommandations.md
 */
class Recommendations
{
    public const RANKED_MAX   = 200; // longueur de la liste classée du fil, ensuite ordre chronologique
    public const SIGNAL_DAYS  = 180;
    public const CACHE_SECONDS = 600;

    // ─── Signaux ─────────────────────────────────────────────────────────────

    /** Centres d'intérêt : [slug de catégorie => poids entre 0 et 1]. */
    public function interests(?User $user): array
    {
        if (!$user) return [];

        return Cache::remember("reco:interests:{$user->id}", self::CACHE_SECONDS, function () use ($user) {
            $since = now()->subDays(self::SIGNAL_DAYS);
            $scores = [];
            $add = function (iterable $rows, float $weight) use (&$scores) {
                foreach ($rows as $slug => $count) {
                    if ($slug === null || $slug === '') continue;
                    $scores[$slug] = ($scores[$slug] ?? 0) + $weight * $count;
                }
            };
            $byCategory = fn (string $table, string $dateColumn, string $valueSql = 'COUNT(*)') => DB::table($table)
                ->join('testimonies', 'testimonies.id', '=', "$table.testimony_id")
                ->where("$table.user_id", $user->id)
                ->where("$table.$dateColumn", '>=', $since)
                ->groupBy('testimonies.category_slug')
                ->selectRaw("testimonies.category_slug as slug, $valueSql as total")
                ->pluck('total', 'slug');

            $add($byCategory('saved_testimonies', 'saved_at'), 4);
            $add($byCategory('reactions', 'created_at'), 3);
            $add($byCategory('comments', 'created_at'), 2);
            $add($byCategory('testimony_views', 'last_viewed_at', 'SUM(CASE WHEN view_count > 3 THEN 3 ELSE view_count END)'), 1);
            $add(Testimony::where('user_id', $user->id)->where('created_at', '>=', $since)
                ->groupBy('category_slug')->selectRaw('category_slug as slug, COUNT(*) as total')->pluck('total', 'slug'), 1);

            $max = max([1, ...array_values($scores)]);
            return array_map(fn ($v) => round($v / $max, 3), $scores);
        });
    }

    /** Identifiants des comptes suivis. */
    public function followedIds(?User $user): array
    {
        if (!$user) return [];

        return Cache::remember("reco:follows:{$user->id}", self::CACHE_SECONDS, fn () =>
            DB::table('follows')->where('follower_id', $user->id)->limit(1000)->pluck('following_id')->all());
    }

    /** Témoignages déjà regardés (identifiants), les plus récents d'abord. */
    public function seenIds(?User $user): array
    {
        if (!$user) return [];

        return DB::table('testimony_views')->where('user_id', $user->id)
            ->orderByDesc('last_viewed_at')->limit(500)->pluck('testimony_id')->all();
    }

    /** Aucun signal : visiteur ou nouveau compte. */
    public function isNewcomer(?User $user): bool
    {
        return !$user || ($this->interests($user) === [] && $this->followedIds($user) === []);
    }

    /** Historique de lecture (appelé à chaque lecture comptée, connecté seulement). */
    public static function recordView(?User $user, Testimony $testimony): void
    {
        if (!$user || $testimony->isInJournal()) return;

        $updated = DB::table('testimony_views')->where('user_id', $user->id)->where('testimony_id', $testimony->id)
            ->update(['view_count' => DB::raw('view_count + 1'), 'last_viewed_at' => now()]);
        if (!$updated) {
            DB::table('testimony_views')->insertOrIgnore([
                'user_id' => $user->id, 'testimony_id' => $testimony->id, 'view_count' => 1, 'last_viewed_at' => now(),
            ]);
        }
    }

    // ─── Page d'un témoignage : « À regarder également » ─────────────────────

    /**
     * Témoignages proches du témoignage en cours, adaptés à la personne :
     * même catégorie, même auteur, comptes suivis, centres d'intérêt, même type, mots-clés
     * communs, popularité et fraîcheur ; les témoignages déjà vus passent après.
     */
    public function forTestimony(Testimony $current, ?User $viewer, int $limit = 10): Collection
    {
        $interests = $this->interests($viewer);
        $followed  = $this->followedIds($viewer);
        $seen      = array_flip($this->seenIds($viewer));
        $topInterests = array_slice(array_keys(collect($interests)->sortDesc()->all()), 0, 3);

        $base = fn () => Testimony::with(['user', 'category'])->published()->whereKeyNot($current->id);
        $pool = collect()
            ->concat($base()->where('category_slug', $current->category_slug)->latestPublished()->limit(40)->get())
            ->concat($base()->where('user_id', $current->user_id)->latestPublished()->limit(15)->get())
            ->concat($followed ? $base()->whereIn('user_id', $followed)->latestPublished()->limit(40)->get() : [])
            ->concat($topInterests ? $base()->whereIn('category_slug', $topInterests)->latestPublished()->limit(40)->get() : [])
            ->concat($base()->orderByDesc('views_count')->limit(30)->get())
            ->concat($base()->latestPublished()->limit(30)->get())
            ->unique('id');

        $tags = collect($current->tags ?? [])->map(fn ($t) => mb_strtolower(trim($t)))->filter()->all();

        return $pool
            ->map(function (Testimony $t) use ($current, $interests, $followed, $seen, $tags) {
                $score = 0.0;
                if ($t->category_slug === $current->category_slug) $score += 4;
                if ($t->user_id === $current->user_id) $score += 3;
                if (in_array($t->user_id, $followed, true)) $score += 3;
                $score += 3 * ($interests[$t->category_slug] ?? 0);
                if ($t->type === $current->type) $score += 1.5;
                $common = count(array_intersect($tags, collect($t->tags ?? [])->map(fn ($x) => mb_strtolower(trim($x)))->all()));
                $score += min(2, $common);
                $score += $this->popularity($t) + $this->freshness($t);
                if (isset($seen[$t->id])) $score -= 4;
                $t->setAttribute('reco_score', round($score, 3));
                return $t;
            })
            ->sortByDesc(fn (Testimony $t) => [$t->reco_score, $t->publishedAt()?->timestamp ?? 0])
            ->take($limit)
            ->values();
    }

    // ─── Fil « Pour vous » ────────────────────────────────────────────────────

    /**
     * Fil paginé : liste classée (RANKED_MAX au plus), puis les autres témoignages du plus récent au plus ancien.
     * Filtres facultatifs : type, catégorie (appliqués avant le classement).
     */
    public function feed(?User $viewer, int $page = 1, int $perPage = 20, ?string $type = null, ?string $category = null): LengthAwarePaginator
    {
        $page    = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $filter  = fn ($q) => $q->published()
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($category, fn ($q) => $q->where('category_slug', $category));

        $ranked = $this->rankedIds($viewer, $filter, $type, $category);
        $total  = $filter(Testimony::query())->count();
        $offset = ($page - 1) * $perPage;

        $ids = array_slice($ranked, $offset, $perPage);
        $items = Testimony::with(['user', 'category'])->whereIn('id', $ids)->get()
            ->sortBy(fn ($t) => array_search($t->id, $ids, true))->values();

        // Au-delà de la liste classée : ordre chronologique, sans doublon.
        if (count($ids) < $perPage) {
            $restOffset = max(0, $offset - count($ranked));
            $rest = $filter(Testimony::with(['user', 'category']))
                ->whereNotIn('id', $ranked)
                ->latestPublished()
                ->skip($restOffset)->take($perPage - count($ids))->get();
            $items = $items->concat($rest)->values();
        }

        return new LengthAwarePaginator($items, $total, $perPage, $page);
    }

    // ─── Mon fil : comme l'accueil, les comptes suivis d'abord ──────────────

    /** Fenêtre et taille de la tête de « Mon fil » (témoignages et partages récents des comptes suivis). */
    public const PERSONAL_RECENT_DAYS = 30;
    public const PERSONAL_RECENT_MAX  = 100;

    public static function personalCacheKey(string $userId): string
    {
        return "reco:personal:v2:{$userId}";
    }

    /**
     * Fil personnel : TOUS les témoignages publiés (sauf les siens), dans cet ordre, sans doublon :
     *   1. tête : témoignages publiés et partages internes (« X a partagé ») des comptes suivis
     *      sur les PERSONAL_RECENT_DAYS derniers jours, du plus récent au plus ancien
     *      (un témoignage partagé n'apparaît qu'une fois, au moment le plus récent) ;
     *   2. puis le classement « Pour vous » (rankedIds) sans ce qui précède ;
     *   3. puis tout le reste, du plus récent au plus ancien.
     * Les étapes 1-2 sont mises en cache 10 minutes (pagination stable) ; l'étape 3 est paginée
     * par décalage, comme feed().
     *
     * @return array{0: LengthAwarePaginator, 1: array<string, string>, 2: array<string, \App\Models\TestimonyRepost>}
     *         page, raison par identifiant (`following` | `suggested` | `repost`) et partage par identifiant
     */
    public function personalFeed(User $viewer, int $page = 1, int $perPage = 20): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset  = ($page - 1) * $perPage;

        // [[testimony_id, raison, repost_id|null], …]
        $head    = Cache::remember(self::personalCacheKey($viewer->id), self::CACHE_SECONDS, fn () => $this->personalHead($viewer));
        $headIds = array_column($head, 0);
        $followed = array_flip($this->followedIds($viewer));
        $others  = fn () => Testimony::query()->published()->where('user_id', '!=', $viewer->id)->whereNotIn('id', $headIds);
        $total   = count($head) + $others()->count();

        $slice    = array_slice($head, $offset, $perPage);
        $ids      = array_column($slice, 0);
        $reasons  = array_column($slice, 1, 0);
        $repostIds = array_filter(array_column($slice, 2, 0));
        $reposts  = $repostIds
            ? \App\Models\TestimonyRepost::with('user')->whereIn('id', array_values($repostIds))->get()->keyBy('testimony_id')
            : collect();
        // Témoignage retiré du public, supprimé ou partage annulé depuis la mise en cache : il disparaît.
        $items = Testimony::with(['user', 'category'])->published()->whereIn('id', $ids)->get()
            ->filter(fn ($t) => !isset($repostIds[$t->id]) || $reposts->has($t->id))
            ->sortBy(fn ($t) => array_search($t->id, $ids, true))->values();

        if (count($slice) < $perPage) {
            $rest = $others()->with(['user', 'category'])->latestPublished()
                ->skip(max(0, $offset - count($head)))->take($perPage - count($slice))->get();
            foreach ($rest as $t) {
                $reasons[$t->id] = isset($followed[$t->user_id]) ? 'following' : 'suggested';
            }
            $items = $items->concat($rest)->values();
        }

        return [new LengthAwarePaginator($items, $total, $perPage, $page), $reasons, $reposts->all()];
    }

    /** Étapes 1 et 2 de personalFeed() : liste d'entrées [testimony_id, raison, repost_id|null]. */
    private function personalHead(User $viewer): array
    {
        $followed = $this->followedIds($viewer);
        $since    = now()->subDays(self::PERSONAL_RECENT_DAYS);
        $timeline = [];

        if ($followed) {
            Testimony::query()->published()->whereIn('user_id', $followed)
                ->whereRaw('COALESCE(approved_at, created_at) >= ?', [$since])
                ->latestPublished()->limit(self::PERSONAL_RECENT_MAX)
                ->get(['id', 'approved_at', 'created_at'])
                ->each(function ($t) use (&$timeline) {
                    $timeline[] = [$t->id, 'following', null, ($t->approved_at ?? $t->created_at)?->timestamp ?? 0];
                });
            \App\Models\TestimonyRepost::query()->whereIn('user_id', $followed)->where('created_at', '>=', $since)
                ->whereHas('testimony', fn ($t) => $t->published()->where('user_id', '!=', $viewer->id))
                ->latest()->limit(self::PERSONAL_RECENT_MAX)
                ->get(['id', 'testimony_id', 'created_at'])
                ->each(function ($r) use (&$timeline) {
                    $timeline[] = [$r->testimony_id, 'repost', $r->id, $r->created_at?->timestamp ?? 0];
                });
        }

        // Du plus récent au plus ancien ; à égalité, le partage d'abord. Un témoignage n'apparaît qu'une fois.
        usort($timeline, fn ($a, $b) => [$b[3], $b[1] === 'repost'] <=> [$a[3], $a[1] === 'repost']);
        $head = [];
        $seen = [];
        foreach (array_slice($timeline, 0, self::PERSONAL_RECENT_MAX) as [$id, $reason, $repostId]) {
            if (isset($seen[$id])) continue;
            $seen[$id] = true;
            $head[] = [$id, $reason, $repostId];
        }

        // Puis le classement « Pour vous », sans ses propres témoignages ni ce qui précède.
        $ranked = array_values(array_filter(
            $this->rankedIds($viewer, fn ($q) => $q->published(), null, null),
            fn ($id) => !isset($seen[$id])
        ));
        $authors = $ranked ? Testimony::whereIn('id', $ranked)->pluck('user_id', 'id')->all() : [];
        $followedSet = array_flip($followed);
        foreach ($ranked as $id) {
            $author = $authors[$id] ?? null;
            if ($author === null || $author === $viewer->id) continue;
            $head[] = [$id, isset($followedSet[$author]) ? 'following' : 'suggested', null];
        }

        return $head;
    }

    /** Liste classée des identifiants (mise en cache 10 minutes par personne et par filtre). */
    private function rankedIds(?User $viewer, \Closure $filter, ?string $type, ?string $category): array
    {
        $newcomer = $this->isNewcomer($viewer);
        $key = 'reco:feed:' . ($newcomer ? 'newcomer' : $viewer->id) . ':' . ($type ?? '-') . ':' . ($category ?? '-');

        return Cache::remember($key, self::CACHE_SECONDS, function () use ($viewer, $filter, $newcomer) {
            if ($newcomer) {
                // Nouveau venu : un récent, un très vu, et ainsi de suite (sans doublon).
                $recent  = $filter(Testimony::query())->latestPublished()->limit(self::RANKED_MAX)->pluck('id')->all();
                $popular = $filter(Testimony::query())->orderByDesc('views_count')->limit(self::RANKED_MAX)->pluck('id')->all();
                $mixed = [];
                for ($i = 0; $i < self::RANKED_MAX && (isset($recent[$i]) || isset($popular[$i])); $i++) {
                    foreach ([$recent[$i] ?? null, $popular[$i] ?? null] as $id) {
                        if ($id !== null && !in_array($id, $mixed, true)) $mixed[] = $id;
                    }
                }
                return array_slice($mixed, 0, self::RANKED_MAX);
            }

            $interests = $this->interests($viewer);
            $followed  = $this->followedIds($viewer);
            $seen      = array_flip($this->seenIds($viewer));
            $pool = collect()
                ->concat($filter(Testimony::query())->latestPublished()->limit(300)->get(['id', 'user_id', 'category_slug', 'views_count', 'approved_at', 'created_at']))
                ->concat($filter(Testimony::query())->orderByDesc('views_count')->limit(60)->get(['id', 'user_id', 'category_slug', 'views_count', 'approved_at', 'created_at']))
                ->concat($followed ? $filter(Testimony::query())->whereIn('user_id', $followed)->latestPublished()->limit(80)
                    ->get(['id', 'user_id', 'category_slug', 'views_count', 'approved_at', 'created_at']) : [])
                ->unique('id');

            return $pool
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'score' => (in_array($t->user_id, $followed, true) ? 3 : 0)
                        + 3 * ($interests[$t->category_slug] ?? 0)
                        + $this->popularity($t) + 1.5 * $this->freshness($t)
                        - (isset($seen[$t->id]) ? 3 : 0),
                    'at' => ($t->approved_at ?? $t->created_at)?->timestamp ?? 0,
                ])
                ->sortByDesc(fn ($r) => [$r['score'], $r['at']])
                ->take(self::RANKED_MAX)
                ->pluck('id')->all();
        });
    }

    /** Popularité : 0 à ~4 (échelle logarithmique des vues). */
    private function popularity(Testimony $t): float
    {
        return 0.8 * log10(max(0, (int) $t->views_count) + 10) - 0.8;
    }

    /** Fraîcheur : 2 (moins de 7 jours), 1 (moins de 30 jours), 0 sinon. */
    private function freshness(Testimony $t): float
    {
        $at = $t->approved_at ?? $t->created_at;
        if (!$at) return 0;
        $days = $at->diffInDays(now());
        return $days < 7 ? 2 : ($days < 30 ? 1 : 0);
    }
}
