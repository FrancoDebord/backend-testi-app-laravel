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

    // ─── Mon fil : comptes suivis, complétés de suggestions ─────────────────

    /**
     * Fil personnel : les témoignages des comptes suivis (du plus récent au plus ancien),
     * avec une suggestion tous les trois — témoignages proches de ses centres d'intérêt
     * venant de comptes qu'elle ne suit pas. Sans abonnement : suggestions seulement.
     *
     * @return array{0: LengthAwarePaginator, 1: array<string, string>} page et raison par
     *         identifiant (`following` | `suggested`)
     */
    public function personalFeed(User $viewer, int $page = 1, int $perPage = 20): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(50, $perPage));

        $list = Cache::remember("reco:personal:{$viewer->id}", self::CACHE_SECONDS, function () use ($viewer) {
            $followed = $this->followedIds($viewer);
            $fromFollowed = $followed
                ? Testimony::query()->published()->whereIn('user_id', $followed)
                    ->latestPublished()->limit(300)->pluck('id')->all()
                : [];

            $interests = $this->interests($viewer);
            $seen      = array_flip($this->seenIds($viewer));
            $exclude   = [...$followed, $viewer->id];
            $columns   = ['id', 'user_id', 'category_slug', 'views_count', 'approved_at', 'created_at'];
            $suggested = collect()
                ->concat(Testimony::query()->published()->whereNotIn('user_id', $exclude)->latestPublished()->limit(300)->get($columns))
                ->concat(Testimony::query()->published()->whereNotIn('user_id', $exclude)->orderByDesc('views_count')->limit(60)->get($columns))
                ->unique('id')
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'score' => 3 * ($interests[$t->category_slug] ?? 0) + $this->popularity($t)
                        + 1.5 * $this->freshness($t) - (isset($seen[$t->id]) ? 3 : 0),
                ])
                ->sortByDesc('score')->take(150)->pluck('id')->all();

            // 3 témoignages suivis, 1 suggestion, et ainsi de suite.
            $out = [];
            $f = 0;
            $s = 0;
            while ($f < count($fromFollowed) || $s < count($suggested)) {
                for ($k = 0; $k < 3 && $f < count($fromFollowed); $k++) {
                    $out[] = [$fromFollowed[$f++], 'following'];
                }
                if ($s < count($suggested)) {
                    $out[] = [$suggested[$s++], 'suggested'];
                }
            }

            return $out;
        });

        $slice   = array_slice($list, ($page - 1) * $perPage, $perPage);
        $ids     = array_column($slice, 0);
        $reasons = array_column($slice, 1, 0);
        $items   = Testimony::with(['user', 'category'])->whereIn('id', $ids)->get()
            ->sortBy(fn ($t) => array_search($t->id, $ids, true))->values();

        return [new LengthAwarePaginator($items, count($list), $perPage, $page), $reasons];
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
