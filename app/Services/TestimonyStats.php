<?php

namespace App\Services;

use App\Models\Testimony;
use App\Models\TestimonyDailyStat;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Statistiques des témoignages (docs/fonctionnalites/gestion-des-temoignages.md) :
 * - événements bruts (testimony_events) : vue dédoublonnée (App\Services\ViewCounter), partage,
 *   secondes de vidéo regardées ;
 * - agrégat quotidien (testimony_daily_stats), calculé par testimonies:aggregate-stats pour la veille
 *   (jour du calendrier config('testimonies.stats_timezone')). Réactions et commentaires sont repris de
 *   leurs tables (date de création). Le jour en cours est calculé à la volée.
 */
class TestimonyStats
{
    public const METRICS = ['views', 'unique_viewers', 'reactions', 'comments', 'shares', 'video_watch_seconds'];

    public static function timezone(): string
    {
        return (string) config('testimonies.stats_timezone', 'Africa/Porto-Novo');
    }

    public static function today(): string
    {
        return CarbonImmutable::now(self::timezone())->toDateString();
    }

    /**
     * Empreinte de la personne (jamais stockée en clair) : compte, sinon appareil (en-tête X-Device-Id
     * ou champ device_id de l'application), sinon session du site, sinon IP + navigateur.
     */
    public static function viewerKey(Request $request): string
    {
        $user = $request->user() ?? $request->user('sanctum');
        $device = $request->header('X-Device-Id') ?: $request->input('device_id');
        $raw = match (true) {
            $user !== null                                  => 'u:' . $user->id,
            is_string($device) && trim($device) !== ''      => 'd:' . mb_substr(trim($device), 0, 120),
            $request->hasSession() && $request->session()->getId() => 's:' . $request->session()->getId(),
            default                                         => 'ip:' . $request->ip() . '|' . mb_substr((string) $request->userAgent(), 0, 200),
        };

        return hash('sha256', $raw);
    }

    // ─── Événements ──────────────────────────────────────────────────────────

    public function recordView(Testimony $testimony, string $viewerKey): void
    {
        $this->event($testimony, 'view', $viewerKey);
    }

    public function recordShare(Testimony $testimony, Request $request): void
    {
        $this->event($testimony, 'share', self::viewerKey($request));
    }

    public function recordWatch(Testimony $testimony, Request $request, int $seconds): void
    {
        if ($seconds > 0) {
            $this->event($testimony, 'watch', self::viewerKey($request), min($seconds, 86400));
        }
    }

    private function event(Testimony $testimony, string $type, ?string $viewerKey, int $seconds = 0): void
    {
        DB::table('testimony_events')->insert([
            'testimony_id' => $testimony->id,
            'type'         => $type,
            'viewer_key'   => $viewerKey,
            'seconds'      => $seconds,
            'created_at'   => now(),
        ]);
    }

    // ─── Agrégation ──────────────────────────────────────────────────────────

    /** Calcule et enregistre les statistiques d'un jour (AAAA-MM-JJ). Peut être relancée. Renvoie le nombre de lignes. */
    public function aggregate(string $date): int
    {
        $rows = $this->compute($date);

        DB::transaction(function () use ($date, $rows) {
            TestimonyDailyStat::where('date', $date)->delete();
            $existing = DB::table('testimonies')->whereIn('id', $rows->keys())->pluck('id')->flip();
            $now = now();
            $insert = $rows->filter(fn ($r, $id) => $existing->has($id))
                ->map(fn ($r, $id) => ['testimony_id' => $id, 'date' => $date, ...$r, 'created_at' => $now, 'updated_at' => $now])
                ->values();
            foreach ($insert->chunk(200) as $chunk) {
                DB::table('testimony_daily_stats')->insert($chunk->all());
            }
        });

        return $rows->count();
    }

    /**
     * Statistiques d'un jour, par témoignage (sans enregistrer).
     * @return Collection<string, array<string, int>>
     */
    public function compute(string $date, ?string $testimonyId = null): Collection
    {
        [$from, $to] = self::range($date);
        $scope = fn ($q) => $testimonyId ? $q->where('testimony_id', $testimonyId) : $q;

        $events = $scope(DB::table('testimony_events')->whereBetween('created_at', [$from, $to]))
            ->selectRaw("testimony_id,
                SUM(CASE WHEN type = 'view' THEN 1 ELSE 0 END) AS views,
                COUNT(DISTINCT CASE WHEN type = 'view' THEN viewer_key END) AS unique_viewers,
                SUM(CASE WHEN type = 'share' THEN 1 ELSE 0 END) AS shares,
                SUM(CASE WHEN type = 'watch' THEN seconds ELSE 0 END) AS video_watch_seconds")
            ->groupBy('testimony_id')->get()->keyBy('testimony_id');
        $reactions = $scope(DB::table('reactions')->whereBetween('created_at', [$from, $to]))
            ->selectRaw('testimony_id, COUNT(*) AS n')->groupBy('testimony_id')->pluck('n', 'testimony_id');
        $comments = $scope(DB::table('comments')->whereBetween('created_at', [$from, $to]))
            ->selectRaw('testimony_id, COUNT(*) AS n')->groupBy('testimony_id')->pluck('n', 'testimony_id');

        return $events->keys()->merge($reactions->keys())->merge($comments->keys())->unique()
            ->mapWithKeys(fn ($id) => [$id => [
                'views'               => (int) ($events[$id]->views ?? 0),
                'unique_viewers'      => (int) ($events[$id]->unique_viewers ?? 0),
                'reactions'           => (int) ($reactions[$id] ?? 0),
                'comments'            => (int) ($comments[$id] ?? 0),
                'shares'              => (int) ($events[$id]->shares ?? 0),
                'video_watch_seconds' => (int) ($events[$id]->video_watch_seconds ?? 0),
            ]]);
    }

    /** Début et fin (UTC, format de la base) d'un jour du calendrier local. */
    public static function range(string $date): array
    {
        $start = CarbonImmutable::parse($date, self::timezone())->startOfDay();

        return [$start->utc()->toDateTimeString(), $start->endOfDay()->utc()->toDateTimeString()];
    }

    // ─── Lecture (back-office) ───────────────────────────────────────────────

    /**
     * Série des $days derniers jours (aujourd'hui compris, calculé à la volée) pour un témoignage,
     * ou pour tous si $testimony est null.
     * @return array{days: array<int, array>, totals: array<string, int>, previous: array<string, int>}
     */
    public function series(?Testimony $testimony, int $days): array
    {
        $today = CarbonImmutable::parse(self::today());
        $start = $today->subDays($days - 1);
        $prevStart = $start->subDays($days);

        $stored = TestimonyDailyStat::query()
            ->when($testimony, fn ($q) => $q->where('testimony_id', $testimony->id))
            ->whereBetween('date', [$prevStart->toDateString(), $today->subDay()->toDateString()])
            ->selectRaw('date, ' . collect(self::METRICS)->map(fn ($m) => "SUM({$m}) AS {$m}")->implode(', '))
            ->groupBy('date')->get()
            ->keyBy(fn ($r) => substr((string) $r->getRawOriginal('date'), 0, 10));

        $live = $this->compute($today->toDateString(), $testimony?->id);
        $todayRow = collect(self::METRICS)->mapWithKeys(fn ($m) => [$m => (int) $live->sum($m)])->all();

        $row = fn (string $d) => $d === $today->toDateString()
            ? $todayRow
            : collect(self::METRICS)->mapWithKeys(fn ($m) => [$m => (int) ($stored[$d]->{$m} ?? 0)])->all();

        $list = [];
        for ($d = $start; $d->lte($today); $d = $d->addDay()) {
            $list[] = ['date' => $d->toDateString(), ...$row($d->toDateString())];
        }
        $previous = array_fill_keys(self::METRICS, 0);
        for ($d = $prevStart; $d->lt($start); $d = $d->addDay()) {
            foreach ($row($d->toDateString()) as $m => $v) {
                $previous[$m] += $v;
            }
        }

        return [
            'days'     => $list,
            'totals'   => collect(self::METRICS)->mapWithKeys(fn ($m) => [$m => array_sum(array_column($list, $m))])->all(),
            'previous' => $previous,
        ];
    }

    /** Témoignages les plus vus sur la période (agrégats + aujourd'hui), hors corbeille (consultable dans l'onglet Corbeille). */
    public function top(int $days, int $limit = 10): Collection
    {
        $from = CarbonImmutable::parse(self::today())->subDays($days - 1)->toDateString();
        $views = TestimonyDailyStat::where('date', '>=', $from)
            ->selectRaw('testimony_id, SUM(views) AS views, SUM(reactions) AS reactions, SUM(comments) AS comments, SUM(shares) AS shares')
            ->groupBy('testimony_id')->get()->keyBy('testimony_id')
            ->map(fn ($r) => ['views' => (int) $r->views, 'reactions' => (int) $r->reactions, 'comments' => (int) $r->comments, 'shares' => (int) $r->shares]);
        foreach ($this->compute(self::today()) as $id => $r) {
            $base = $views[$id] ?? ['views' => 0, 'reactions' => 0, 'comments' => 0, 'shares' => 0];
            $views[$id] = ['views' => $base['views'] + $r['views'], 'reactions' => $base['reactions'] + $r['reactions'],
                'comments' => $base['comments'] + $r['comments'], 'shares' => $base['shares'] + $r['shares']];
        }
        $ranked = $views->sortByDesc(fn ($r) => $r['views'] * 1000000 + $r['reactions'])->take($limit);
        $models = Testimony::with('user')->whereIn('id', $ranked->keys())->get()->keyBy('id');

        return $ranked->map(fn ($r, $id) => $models->has($id) ? ['testimony' => $models[$id], ...$r] : null)->filter()->values();
    }

    /** Évolution en % par rapport à la période précédente (null si rien avant). */
    public static function change(int $current, int $previous): ?int
    {
        return $previous > 0 ? (int) round(($current - $previous) / $previous * 100) : null;
    }
}
