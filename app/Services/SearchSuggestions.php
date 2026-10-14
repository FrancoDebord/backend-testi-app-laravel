<?php

namespace App\Services;

use App\Enums\UserAccountStatus;
use App\Models\BibleBook;
use App\Models\Category;
use App\Models\Event;
use App\Models\PrayerSession;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Autocomplétion des barres de recherche (application et site) : GET /api/v1/search/suggest.
 *
 * - Suggestions de texte : termes ou expressions qui complètent la saisie, tirés des titres de
 *   témoignages publiés, des catégories, des noms de comptes actifs, des titres d'événements
 *   publiés, des livres de la Bible, des sessions de prière publiques et des recherches populaires.
 * - Résultats directs typés (témoignage, compte, événement, catégorie, livre, session de prière).
 *
 * Insensible aux accents et à la casse (pliage Str::ascii + minuscules) ; début de texte d'abord,
 * puis début de mot, puis « contient ». Contenu public seulement : jamais d'e-mail ni de téléphone,
 * ni de compte suspendu, banni ou privé, ni de témoignage non publié. Les comptes, témoignages,
 * événements et sessions proposés sont revérifiés en base à chaque calcul (le lexique en cache peut
 * avoir quelques minutes). Réponse identique pour tous (aucune donnée personnelle) : cache de 60 s
 * par préfixe normalisé. Voir docs/fonctionnalites/recherche.md
 */
class SearchSuggestions
{
    public const SCOPES = ['all', 'testimonies', 'users', 'events', 'prayer', 'bible'];

    public const MIN_LENGTH = 2;
    public const MAX_LENGTH = 60;
    public const DEFAULT_LIMIT = 8;
    public const MAX_LIMIT = 15;
    /** Une recherche n'est « populaire » qu'à partir de ce nombre de validations (évite d'exposer une saisie isolée). */
    public const POPULAR_MIN_HITS = 3;

    private const RESULT_CACHE_SECONDS = 60;
    private const LEXICON_CACHE_SECONDS = 600;
    private const LEXICON_CAP = 3000;
    private const RESULTS_MAX = 6;
    private const RESULTS_PER_TYPE = 3;
    private const CACHE_VERSION = 'v1';

    /** Sources consultées par portée. */
    private const SOURCES = [
        'all'         => ['popular', 'category', 'testimony', 'user', 'event', 'bible', 'prayer_session'],
        'testimonies' => ['popular', 'category', 'testimony'],
        'users'       => ['user'],
        'events'      => ['event'],
        'prayer'      => ['prayer_session'],
        'bible'       => ['bible'],
    ];

    /** Ordre d'affichage des résultats directs à rang égal. */
    private const TYPE_ORDER = ['category' => 0, 'user' => 1, 'event' => 2, 'testimony' => 3, 'prayer_session' => 4, 'bible' => 5];

    /**
     * @return array{query: string, scope: string, suggestions: list<array>, results: list<array>}
     */
    public function suggest(string $q, string $scope = 'all', int $limit = self::DEFAULT_LIMIT): array
    {
        $q = mb_substr(trim(preg_replace('/\s+/u', ' ', $q)), 0, self::MAX_LENGTH);
        $scope = in_array($scope, self::SCOPES, true) ? $scope : 'all';
        $limit = max(1, min(self::MAX_LIMIT, $limit));
        $needle = self::normalize($q);

        $empty = ['query' => $q, 'scope' => $scope, 'suggestions' => [], 'results' => []];
        if (mb_strlen($needle) < self::MIN_LENGTH) {
            return $empty;
        }

        $key = 'search:suggest:' . self::CACHE_VERSION . ":{$scope}:{$limit}:" . md5($needle);

        return ['query' => $q] + Cache::remember($key, self::RESULT_CACHE_SECONDS, fn () => $this->compute($needle, $scope, $limit));
    }

    /**
     * Enregistre une recherche validée, sans aucun identifiant de personne. Ignore les saisies qui
     * ressemblent à une adresse e-mail ou à un numéro de téléphone.
     */
    public static function record(string $q, ?string $ip = null): void
    {
        $q = mb_strtolower(mb_substr(trim(preg_replace('/\s+/u', ' ', $q)), 0, self::MAX_LENGTH));
        $normalized = self::normalize($q);
        if (mb_strlen($normalized) < 3 || str_contains($q, '@') || preg_match('/\d[\d\s.+()-]{5,}/', $q)) {
            return;
        }
        // Limite par adresse (le hachage seul sert de clé, rien n'est conservé).
        if ($ip !== null && !RateLimiter::attempt('search-record:' . sha1($ip), 30, fn () => true, 60)) {
            return;
        }
        if (!Schema::hasTable('search_queries')) {
            return;
        }

        $now = now();
        $updated = DB::table('search_queries')->where('normalized', $normalized)
            ->update(['hits' => DB::raw('hits + 1'), 'query' => $q, 'last_searched_at' => $now, 'updated_at' => $now]);
        if ($updated === 0) {
            DB::table('search_queries')->insertOrIgnore([
                'normalized' => $normalized, 'query' => $q, 'hits' => 1,
                'last_searched_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    // ─── Calcul ──────────────────────────────────────────────────────────────

    private function compute(string $needle, string $scope, int $limit): array
    {
        $sources = self::SOURCES[$scope];

        // 1. Candidats : lexique en cache + recherche en base (pour les grandes tables).
        $candidates = [];
        foreach ($sources as $source) {
            foreach ($this->lexicon($source) as $entry) {
                $candidates[$entry['t'] . ':' . $entry['id']] = $entry;
            }
            foreach ($this->databaseCandidates($source, $needle) as $entry) {
                $candidates[$entry['t'] . ':' . $entry['id']] ??= $entry;
            }
        }

        // 2. Correspondances (début de texte, début de mot, contient).
        $matches = [];
        foreach ($candidates as $entry) {
            $rank = self::rank($entry['n'], $needle);
            if ($rank !== null) {
                $matches[] = $entry + ['rank' => $rank];
            }
        }
        usort($matches, fn ($a, $b) => [$a['rank'], -$a['w'], mb_strlen($a['l'])] <=> [$b['rank'], -$b['w'], mb_strlen($b['l'])]);

        // 3. Revérification en base de ce qui peut avoir changé (comptes, publications).
        $matches = $this->verify(array_slice($matches, 0, 200));

        return [
            'scope'       => $scope,
            'suggestions' => $this->buildSuggestions($matches, $needle, $limit),
            'results'     => $this->buildResults($matches, $needle),
        ];
    }

    /** 0 : le texte commence par la saisie ; 1 : un mot commence par la saisie ; 2 : contient ; null sinon. */
    private static function rank(string $haystack, string $needle): ?int
    {
        if (str_starts_with($haystack, $needle)) return 0;
        if (str_contains(' ' . $haystack, ' ' . $needle)) return 1;
        if (mb_strlen($needle) >= 3 && str_contains($haystack, $needle)) return 2;

        return null;
    }

    private function buildSuggestions(array $matches, string $needle, int $limit): array
    {
        $out = [];
        foreach ($matches as $m) {
            if ($m['rank'] === 2 && $m['t'] !== 'popular') {
                continue; // une complétion part d'un début de mot
            }
            $text = $this->completion($m, $needle);
            if ($text === null) continue;
            $norm = self::normalize($text);
            if ($norm === $needle || isset($out[$norm])) continue;

            $out[$norm] = [
                'text'      => $text,
                'source'    => $m['t'],
                'highlight' => self::highlight($text, $needle),
            ];
            if (count($out) >= $limit) break;
        }

        return array_values($out);
    }

    /** Texte proposé : le libellé entier s'il commence par la saisie (et reste court), sinon l'expression qui la complète. */
    private function completion(array $m, string $needle): ?string
    {
        $label = $m['l'];
        if ($m['t'] === 'popular') {
            return $label;
        }
        if (in_array($m['t'], ['user', 'category', 'bible'], true)) {
            return $label; // noms propres : toujours le nom complet
        }
        if ($m['rank'] === 0 && mb_strlen($label) <= 48) {
            return $label;
        }

        [$norm, $map] = self::fold($label);
        $pos = $m['rank'] === 0 ? 0 : self::wordPosition($norm, $needle);
        if ($pos === null) return null;

        // Fin du mot en cours, puis deux ou trois mots de plus.
        $end = $pos + strlen($needle);
        $extra = $m['rank'] === 0 ? 3 : 2;
        $end = self::wordEnd($norm, $end);
        for ($i = 0; $i < $extra && $end < strlen($norm); $i++) {
            $end = self::wordEnd($norm, $end + 1);
        }
        $start = $map[$pos];
        $stop = $map[max($pos, $end - 1)] + 1;
        $text = trim(mb_substr($label, $start, $stop - $start), " \t\n\r\0\x0B,.;:!?«»\"'()-–—");
        if ($text === '') return null;

        return $m['rank'] === 0 ? $text : mb_strtolower($text);
    }

    private function buildResults(array $matches, string $needle): array
    {
        $rows = array_values(array_filter($matches, fn ($m) => $m['t'] !== 'popular' && ($m['rank'] < 2 || in_array($m['t'], ['user', 'event', 'testimony'], true))));
        usort($rows, fn ($a, $b) => [$a['rank'], self::TYPE_ORDER[$a['t']] ?? 9, -$a['w']] <=> [$b['rank'], self::TYPE_ORDER[$b['t']] ?? 9, -$b['w']]);

        $out = [];
        $perType = [];
        foreach ($rows as $m) {
            if (($perType[$m['t']] ?? 0) >= self::RESULTS_PER_TYPE) continue;
            $perType[$m['t']] = ($perType[$m['t']] ?? 0) + 1;
            $out[] = $this->result($m, $needle);
            if (count($out) >= self::RESULTS_MAX) break;
        }

        return $out;
    }

    private function result(array $m, string $needle): array
    {
        $x = $m['x'] ?? [];
        $base = [
            'type'      => $m['t'],
            'id'        => (string) $m['id'],
            'label'     => $m['l'],
            'sublabel'  => $x['sub'] ?? null,
            'avatar'    => $x['avatar'] ?? null,
            'verified'  => (bool) ($x['verified'] ?? false),
            'highlight' => self::highlight($m['l'], $needle),
        ];

        return $base + match ($m['t']) {
            'testimony'      => ['url' => route('testimonies.show', $m['id']), 'route' => '/testimony/' . $m['id']],
            'user'           => ['url' => route('profiles.show', $m['id']), 'route' => '/users/' . $m['id']],
            'event'          => ['url' => route('events.show', $m['id']), 'route' => '/events/' . $m['id']],
            'prayer_session' => ['url' => route('prayer.sessions.show', $m['id']), 'route' => '/prayer-sessions/' . $m['id']],
            'category'       => ['url' => route('explore', ['category' => $m['id']]), 'route' => '/explore/category/' . $m['id']],
            'bible'          => [
                'url'         => route('bible.reader', ['translation' => $x['translation'] ?? 'LSG', 'book' => $x['book'] ?? 1]),
                'route'       => '/bible/' . ($x['translation'] ?? 'LSG') . '?book=' . ($x['book'] ?? 1),
                'book'        => (int) ($x['book'] ?? 1),
                'translation' => $x['translation'] ?? 'LSG',
            ],
        };
    }

    // ─── Vérification ────────────────────────────────────────────────────────

    /** Retire ce qui n'est plus visible (compte suspendu, témoignage retiré…) et complète les sous-titres. */
    private function verify(array $matches): array
    {
        $ids = [];
        foreach ($matches as $m) {
            $ids[$m['t']][] = $m['id'];
        }
        $ok = [];

        if (!empty($ids['user'])) {
            foreach ($this->visibleUsers()->whereIn('id', $ids['user'])
                ->get(['id', 'display_name', 'avatar_url', 'account_type', 'organization_name', 'organization_city', 'country', 'verification_status']) as $u) {
                $ok['user:' . $u->id] = [
                    'sub'      => $u->isOrganization() ? trim('Organisation' . ($u->organization_city ? ' · ' . $u->organization_city : '')) : 'Compte',
                    'avatar'   => $u->avatar_url,
                    'verified' => $u->isVerified(),
                    ...\App\Models\User::approvalFieldsFor($u), // badge-approuve.md
                ];
            }
        }
        if (!empty($ids['testimony'])) {
            foreach (Testimony::published()->whereIn('testimonies.id', $ids['testimony'])
                ->whereIn('user_id', $this->visibleUsers()->select('id'))
                ->with('user:id,display_name')->get(['id', 'user_id', 'title', 'type', 'cover_url']) as $t) {
                $ok['testimony:' . $t->id] = [
                    'sub'    => 'Témoignage' . ($t->user?->display_name ? ' · ' . $t->user->display_name : ''),
                    'avatar' => $t->cover_url,
                ];
            }
        }
        if (!empty($ids['event'])) {
            foreach (Event::published()->whereIn('id', $ids['event'])->get(['id', 'title', 'starts_at', 'city', 'date_tbd']) as $e) {
                $when = $e->date_tbd ? 'Date à confirmer' : $e->starts_at?->translatedFormat('j M Y');
                $ok['event:' . $e->id] = ['sub' => implode(' · ', array_filter(['Événement', $when, $e->city]))];
            }
        }
        if (!empty($ids['prayer_session'])) {
            foreach ($this->visibleSessions()->whereIn('id', $ids['prayer_session'])->get(['id', 'starts_at']) as $s) {
                $ok['prayer_session:' . $s->id] = ['sub' => 'Session de prière · ' . $s->starts_at?->translatedFormat('j M Y, H\hi')];
            }
        }

        $out = [];
        foreach ($matches as $m) {
            if (in_array($m['t'], ['user', 'testimony', 'event', 'prayer_session'], true)) {
                $k = $m['t'] . ':' . $m['id'];
                if (!isset($ok[$k])) continue;
                $m['x'] = array_merge($m['x'] ?? [], $ok[$k]);
            }
            $out[] = $m;
        }

        return $out;
    }

    private function visibleUsers()
    {
        return User::query()
            ->where('status', UserAccountStatus::Active->value)
            ->whereNotIn('id', DB::table('user_settings')->where('is_private_account', true)->select('user_id'));
    }

    private function visibleSessions()
    {
        return PrayerSession::query()
            ->where('visibility', PrayerSession::PUBLIC)
            ->where('status', '!=', PrayerSession::CANCELLED)
            ->where('starts_at', '>=', now()->subDay())
            ->whereIn('host_id', $this->visibleUsers()->select('id'));
    }

    // ─── Lexiques ────────────────────────────────────────────────────────────

    /** @return list<array{t: string, id: string, l: string, n: string, w: int, x?: array}> */
    private function lexicon(string $source): array
    {
        return Cache::remember('search:lexicon:' . self::CACHE_VERSION . ':' . $source, self::LEXICON_CACHE_SECONDS, function () use ($source) {
            $rows = match ($source) {
                'testimony' => Testimony::published()->latest('updated_at')->limit(self::LEXICON_CAP)
                    ->get(['id', 'title', 'like_count', 'views_count'])
                    ->map(fn ($t) => self::entry('testimony', $t->id, $t->title, 1 + min(50, (int) $t->like_count + intdiv((int) $t->views_count, 20)))),
                'user' => $this->visibleUsers()->orderByDesc('follower_count')->limit(self::LEXICON_CAP)
                    ->get(['id', 'display_name', 'follower_count'])
                    ->map(fn ($u) => self::entry('user', $u->id, $u->display_name, 2 + min(60, (int) $u->follower_count))),
                'event' => Event::published()->orderByDesc('starts_at')->limit(1500)
                    ->get(['id', 'title', 'going_count', 'ends_at'])
                    ->map(fn ($e) => self::entry('event', $e->id, $e->title, 1 + min(30, (int) $e->going_count) + ($e->ends_at && $e->ends_at->isFuture() ? 10 : 0))),
                'category' => Category::active()->get(['slug', 'name', 'testimony_count'])
                    ->map(fn ($c) => self::entry('category', $c->slug, $c->name, 20 + min(60, (int) $c->testimony_count), ['sub' => 'Catégorie'])),
                'bible' => $this->bibleBooks(),
                'prayer_session' => $this->visibleSessions()->orderBy('starts_at')->limit(500)
                    ->get(['id', 'title', 'participant_count'])
                    ->map(fn ($s) => self::entry('prayer_session', $s->id, $s->title, 1 + min(30, (int) $s->participant_count))),
                'popular' => Schema::hasTable('search_queries')
                    ? DB::table('search_queries')->where('hits', '>=', self::POPULAR_MIN_HITS)
                        ->orderByDesc('hits')->limit(500)->get(['id', 'query', 'hits'])
                        ->map(fn ($r) => self::entry('popular', (string) $r->id, $r->query, 30 + min(100, (int) $r->hits)))
                    : collect(),
                default => collect(),
            };

            return $rows->filter(fn ($e) => $e['n'] !== '')->values()->all();
        });
    }

    private function bibleBooks()
    {
        $translation = BibleBook::where('translation', 'LSG')->exists()
            ? 'LSG'
            : BibleBook::query()->value('translation');
        if (!$translation) return collect();

        return BibleBook::where('translation', $translation)->orderBy('number')
            ->get(['number', 'name', 'abbreviation', 'chapters_count', 'testament'])
            ->map(fn ($b) => self::entry('bible', (string) $b->number, $b->name, 15, [
                'sub'         => 'Bible · ' . $b->chapters_count . ' chapitre' . ($b->chapters_count > 1 ? 's' : ''),
                'book'        => (int) $b->number,
                'translation' => $translation,
            ]));
    }

    /** Recherche en base (début de texte ou de mot) pour ne pas dépendre du plafond du lexique. */
    private function databaseCandidates(string $source, string $needle): array
    {
        if (!in_array($source, ['testimony', 'user', 'event'], true)) {
            return [];
        }
        $terms = array_unique([$needle]);
        $apply = function ($query, string $column) use ($terms) {
            $query->where(function ($w) use ($column, $terms) {
                foreach ($terms as $t) {
                    $e = addcslashes($t, '%_\\');
                    $w->orWhere($column, 'like', $e . '%')->orWhere($column, 'like', '% ' . $e . '%');
                }
            });
        };

        $rows = match ($source) {
            'testimony' => tap(Testimony::published(), fn ($q) => $apply($q, 'title'))->latest('updated_at')->limit(30)
                ->get(['id', 'title', 'like_count'])->map(fn ($t) => self::entry('testimony', $t->id, $t->title, 1 + min(50, (int) $t->like_count))),
            'user' => tap($this->visibleUsers(), fn ($q) => $apply($q, 'display_name'))->orderByDesc('follower_count')->limit(30)
                ->get(['id', 'display_name', 'follower_count'])->map(fn ($u) => self::entry('user', $u->id, $u->display_name, 2 + min(60, (int) $u->follower_count))),
            'event' => tap(Event::published(), fn ($q) => $apply($q, 'title'))->orderByDesc('starts_at')->limit(30)
                ->get(['id', 'title', 'going_count'])->map(fn ($e) => self::entry('event', $e->id, $e->title, 1 + min(30, (int) $e->going_count))),
        };

        return $rows->all();
    }

    private static function entry(string $type, string $id, ?string $label, int $weight, array $extra = []): array
    {
        $label = trim(preg_replace('/\s+/u', ' ', (string) $label));

        return ['t' => $type, 'id' => $id, 'l' => $label, 'n' => self::normalize($label), 'w' => $weight, 'x' => $extra];
    }

    // ─── Normalisation ───────────────────────────────────────────────────────

    /** Minuscules, sans accents, ponctuation remplacée par des espaces, espaces simples. */
    public static function normalize(string $text): string
    {
        return self::fold($text)[0];
    }

    /**
     * Texte plié et table de correspondance : position (octet ASCII) dans le texte plié → position
     * (caractère) dans le texte d'origine ; la dernière entrée vaut la longueur d'origine.
     *
     * @return array{0: string, 1: list<int>}
     */
    public static function fold(string $text): array
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = '';
        $map = [];
        foreach ($chars as $i => $c) {
            $f = strtolower(Str::ascii($c));
            $f = preg_replace('/[^a-z0-9]/', ' ', $f === '' ? ' ' : $f);
            foreach (str_split($f) as $ch) {
                if ($ch === ' ' && ($out === '' || str_ends_with($out, ' '))) continue;
                $out .= $ch;
                $map[] = $i;
            }
        }
        if (str_ends_with($out, ' ')) {
            $out = substr($out, 0, -1);
            array_pop($map);
        }
        $map[] = count($chars);

        return [$out, $map];
    }

    /**
     * Partie du texte qui correspond à la saisie : [début, longueur] en caractères, ou null.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function highlight(string $text, string $needle): ?array
    {
        [$norm, $map] = self::fold($text);
        $pos = str_starts_with($norm, $needle) ? 0 : self::wordPosition($norm, $needle);
        $pos ??= ($p = strpos($norm, $needle)) === false ? null : $p;
        if ($pos === null) return null;

        $start = $map[$pos];
        $end = $map[$pos + strlen($needle) - 1] + 1;

        return [$start, $end - $start];
    }

    private static function wordPosition(string $norm, string $needle): ?int
    {
        if (str_starts_with($norm, $needle)) return 0;
        $p = strpos($norm, ' ' . $needle);

        return $p === false ? null : $p + 1;
    }

    private static function wordEnd(string $norm, int $from): int
    {
        $p = strpos($norm, ' ', min($from, strlen($norm)));

        return $p === false ? strlen($norm) : $p;
    }
}
