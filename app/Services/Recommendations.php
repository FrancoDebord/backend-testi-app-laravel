<?php

namespace App\Services;

use App\Models\ContentLocation;
use App\Models\Testimony;
use App\Models\User;
use App\Support\Place;
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
    public const RANKED_MAX   = 200; // longueur par défaut de la liste classée (config recommendations.ranked_max)
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

    /**
     * Goûts au-delà des catégories : auteurs et types de contenus aimés, priés (réactions),
     * enregistrés ou commentés (180 derniers jours), ramenés entre 0 et 1.
     * @return array{authors: array<string, float>, types: array<string, float>}
     */
    public function tastes(?User $user): array
    {
        if (!$user) return ['authors' => [], 'types' => []];

        return Cache::remember("reco:tastes:{$user->id}", self::CACHE_SECONDS, function () use ($user) {
            $since = now()->subDays(self::SIGNAL_DAYS);
            $authors = [];
            $types = [];
            foreach ([['saved_testimonies', 'saved_at', 4], ['reactions', 'created_at', 3], ['comments', 'created_at', 2]] as [$table, $date, $weight]) {
                DB::table($table)->join('testimonies', 'testimonies.id', '=', "$table.testimony_id")
                    ->where("$table.user_id", $user->id)->where("$table.$date", '>=', $since)
                    ->where('testimonies.user_id', '!=', $user->id)
                    ->groupBy('testimonies.user_id', 'testimonies.type')
                    ->selectRaw('testimonies.user_id as author, testimonies.type as type, COUNT(*) as total')
                    ->get()
                    ->each(function ($r) use (&$authors, &$types, $weight) {
                        $authors[$r->author] = ($authors[$r->author] ?? 0) + $weight * $r->total;
                        $types[$r->type] = ($types[$r->type] ?? 0) + $weight * $r->total;
                    });
            }
            $normalize = function (array $scores) {
                $max = max([1, ...array_values($scores)]);
                return array_map(fn ($v) => round($v / $max, 3), $scores);
            };

            return ['authors' => $normalize($authors), 'types' => $normalize($types)];
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
     * Fil personnel : TOUS les témoignages publiés, les siens compris, dans cet ordre, sans doublon :
     *   1. tête : témoignages publiés et partages internes (« X a partagé ») des comptes suivis et
     *      de la personne elle-même (raison `mine`) sur les PERSONAL_RECENT_DAYS derniers jours,
     *      du plus proche au plus lointain (zone),
     *      puis du plus récent au plus ancien
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
        $others  = fn () => Testimony::query()->published()->whereNotIn('id', $headIds);
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
                $reasons[$t->id] = $t->user_id === $viewer->id ? 'mine' : (isset($followed[$t->user_id]) ? 'following' : 'suggested');
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
        // Comptes suivis et soi-même : ses propres publications et partages figurent aussi dans Mon fil.
        $authors  = [...$followed, $viewer->id];

        Testimony::query()->published()->whereIn('user_id', $authors)
            ->whereRaw('COALESCE(approved_at, created_at) >= ?', [$since])
            ->latestPublished()->limit(self::PERSONAL_RECENT_MAX)
            ->get(['id', 'user_id', 'approved_at', 'created_at'])
            ->each(function ($t) use (&$timeline, $viewer) {
                $timeline[] = [$t->id, $t->user_id === $viewer->id ? 'mine' : 'following', null, ($t->approved_at ?? $t->created_at)?->timestamp ?? 0];
            });
        \App\Models\TestimonyRepost::query()->whereIn('user_id', $authors)->where('created_at', '>=', $since)
            ->whereHas('testimony', fn ($t) => $t->published())
            ->latest()->limit(self::PERSONAL_RECENT_MAX)
            ->get(['id', 'testimony_id', 'created_at'])
            ->each(function ($r) use (&$timeline) {
                $timeline[] = [$r->testimony_id, 'repost', $r->id, $r->created_at?->timestamp ?? 0];
            });

        // Du plus proche au plus lointain (zone de la personne), puis du plus récent au plus ancien ;
        // à égalité, le partage d'abord. Un témoignage n'apparaît qu'une fois.
        $place = $this->locations()->placeFor($viewer);
        $levels = [];
        if (!$place->isEmpty() && $timeline) {
            $authors = Testimony::whereIn('id', array_unique(array_column($timeline, 0)))->get(['id', 'user_id']);
            foreach ($this->locations()->contentPlaces(ContentLocation::TESTIMONY, $authors) as $id => $p) {
                $levels[$id] = $place->levelTo($p);
            }
        }
        usort($timeline, fn ($a, $b) => [$levels[$b[0]] ?? 0, $b[3], $b[1] === 'repost'] <=> [$levels[$a[0]] ?? 0, $a[3], $a[1] === 'repost']);
        $head = [];
        $seen = [];
        foreach (array_slice($timeline, 0, self::PERSONAL_RECENT_MAX) as [$id, $reason, $repostId]) {
            if (isset($seen[$id])) continue;
            $seen[$id] = true;
            $head[] = [$id, $reason, $repostId];
        }

        // Puis le classement « Pour vous », sans ce qui précède (ses témoignages plus anciens y figurent aussi).
        $ranked = array_values(array_filter(
            $this->rankedIds($viewer, fn ($q) => $q->published(), null, null),
            fn ($id) => !isset($seen[$id])
        ));
        $authors = $ranked ? Testimony::whereIn('id', $ranked)->pluck('user_id', 'id')->all() : [];
        $followedSet = array_flip($followed);
        foreach ($ranked as $id) {
            $author = $authors[$id] ?? null;
            if ($author === null) continue;
            $head[] = [$id, $author === $viewer->id ? 'mine' : (isset($followedSet[$author]) ? 'following' : 'suggested'), null];
        }

        return $head;
    }

    /**
     * Liste classée des identifiants (mise en cache 10 minutes par personne, zone et filtre).
     *
     * Score (poids : config/recommendations.php) =
     *     proximité × niveau de zone (5 même village/quartier ou < 5 km … 1 même continent, 0 monde)
     *   + suivi (compte suivi) + catégorie × intérêt + auteur × goût pour l'auteur + type × goût pour le type
     *   + fraîcheur × (2 | 1 | 0) + popularité × log des vues + déjà vu (négatif).
     * Candidats : 300 plus récents, 60 plus vus, 80 des comptes suivis, plus une présélection par zone
     * (même ville, même région, même pays, même continent ; index sur content_locations).
     * Sans zone ni signal : un récent, un très vu… (comme avant).
     */
    private function rankedIds(?User $viewer, \Closure $filter, ?string $type, ?string $category): array
    {
        $place    = $this->locations()->placeFor($viewer);
        $newcomer = $this->isNewcomer($viewer);
        $max      = (int) config('recommendations.ranked_max', self::RANKED_MAX);
        $key = 'reco:feed:' . ($newcomer ? 'newcomer' : $viewer->id) . ':' . $place->key() . ':' . ($type ?? '-') . ':' . ($category ?? '-');

        return Cache::remember($key, self::CACHE_SECONDS, function () use ($viewer, $filter, $newcomer, $place, $max) {
            if ($newcomer && $place->isEmpty()) {
                // Nouveau venu sans zone connue : un récent, un très vu, et ainsi de suite (sans doublon).
                $recent  = $filter(Testimony::query())->latestPublished()->limit($max)->pluck('id')->all();
                $popular = $filter(Testimony::query())->orderByDesc('views_count')->limit($max)->pluck('id')->all();
                $mixed = [];
                for ($i = 0; $i < $max && (isset($recent[$i]) || isset($popular[$i])); $i++) {
                    foreach ([$recent[$i] ?? null, $popular[$i] ?? null] as $id) {
                        if ($id !== null && !in_array($id, $mixed, true)) $mixed[] = $id;
                    }
                }
                return array_slice($mixed, 0, $max);
            }

            $followed = $this->followedIds($viewer);
            $columns  = ['id', 'user_id', 'type', 'category_slug', 'views_count', 'approved_at', 'created_at'];
            $pool = collect()
                ->concat($filter(Testimony::query())->latestPublished()->limit(300)->get($columns))
                ->concat($filter(Testimony::query())->orderByDesc('views_count')->limit(60)->get($columns))
                ->concat($followed ? $filter(Testimony::query())->whereIn('user_id', $followed)->latestPublished()->limit(80)->get($columns) : [])
                ->concat($this->zonePool($filter, $place, $columns))
                ->unique('id');

            $scorer = $this->scorer($viewer, $place, $pool);

            return $pool
                ->map(fn ($t) => ['id' => $t->id, 'score' => $scorer($t), 'at' => ($t->approved_at ?? $t->created_at)?->timestamp ?? 0])
                ->sortByDesc(fn ($r) => [$r['score'], $r['at']])
                ->take($max)
                ->pluck('id')->all();
        });
    }

    /** Présélection par zone : témoignages de la même ville, région, pays et continent (les plus récents). */
    private function zonePool(\Closure $filter, Place $place, array $columns): Collection
    {
        if ($place->isEmpty()) return collect();

        $sizes = config('recommendations.zone_pool');
        $zone = fn (\Closure $where, int $limit) => $filter(Testimony::query())
            ->whereIn('id', ContentLocation::query()->select('subject_id')->where('subject_type', ContentLocation::TESTIMONY)->where($where))
            ->latestPublished()->limit($limit)->get($columns);

        $out = collect();
        if ($place->countryCode && $place->city) {
            $out = $out->concat($zone(fn ($q) => $q->where('country_code', $place->countryCode)->where('city', $place->city), $sizes['city'] ?? 150));
        }
        if ($place->countryCode && $place->region) {
            $out = $out->concat($zone(fn ($q) => $q->where('country_code', $place->countryCode)->where('region', $place->region), $sizes['region'] ?? 150));
        }
        if ($place->countryCode) {
            $out = $out->concat($zone(fn ($q) => $q->where('country_code', $place->countryCode), $sizes['country'] ?? 200));
        }
        if ($place->continent) {
            $out = $out->concat($zone(fn ($q) => $q->where('continent', $place->continent), $sizes['continent'] ?? 100));
        }

        return $out;
    }

    /**
     * Fonction de score d'un témoignage pour la personne (zone puis goûts, fraîcheur, popularité).
     * @return \Closure(Testimony): float
     */
    private function scorer(?User $viewer, Place $place, Collection $pool): \Closure
    {
        $w         = config('recommendations.weights');
        $interests = $this->interests($viewer);
        $tastes    = $this->tastes($viewer);
        $followed  = array_flip($this->followedIds($viewer));
        $seen      = array_flip($this->seenIds($viewer));
        $places    = $place->isEmpty() ? [] : $this->locations()->contentPlaces(ContentLocation::TESTIMONY, $pool);

        return fn ($t) => round(
            ($w['proximity'] ?? 1.6) * $place->levelTo($places[$t->id] ?? null)
            + (isset($followed[$t->user_id]) ? ($w['followed'] ?? 3) : 0)
            + ($w['category'] ?? 3) * ($interests[$t->category_slug] ?? 0)
            + ($w['author'] ?? 2) * ($tastes['authors'][$t->user_id] ?? 0)
            + ($w['type'] ?? 1) * ($tastes['types'][$t->type instanceof \BackedEnum ? $t->type->value : $t->type] ?? 0)
            + ($w['popularity'] ?? 1) * $this->popularity($t)
            + ($w['freshness'] ?? 1.5) * $this->freshness($t)
            + (isset($seen[$t->id]) ? ($w['seen'] ?? -3) : 0),
            3
        );
    }

    private function locations(): Locations
    {
        return app(Locations::class);
    }

    // ─── Zone de la personne : mention en tête des fils et étiquettes des cartes ─

    /** Mention « Près de chez vous · Cotonou » (meta.place des fils), ou null sans zone connue. */
    public function placeMeta(?User $viewer): ?array
    {
        $place = $this->locations()->placeFor($viewer);
        if ($place->isEmpty()) return null;

        return ['label' => $place->label(), 'precision' => $place->precision(), 'source' => $place->source];
    }

    /**
     * Étiquettes discrètes des cartes proches : [identifiant => « À Cotonou » | « Au Bénin »].
     * Seulement la zone commune avec la personne (jamais plus précis que la ville).
     */
    public function nearbyLabels(?User $viewer, iterable $testimonies): array
    {
        $place = $this->locations()->placeFor($viewer);
        if ($place->isEmpty()) return [];

        $labels = [];
        foreach ($this->locations()->contentPlaces(ContentLocation::TESTIMONY, $testimonies) as $id => $p) {
            $label = $p->nearbyLabelFor($place->levelTo($p));
            if ($label) $labels[$id] = $label;
        }

        return $labels;
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
