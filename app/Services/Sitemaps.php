<?php

namespace App\Services;

use App\Enums\LiveStatus;
use App\Models\Category;
use App\Models\DailyVerse;
use App\Models\LiveSession;
use App\Models\Testimony;
use App\Models\ThemeSemaine;
use App\Support\ShareMeta;
use App\Support\StructuredData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Sitemaps XML (sans dépendance) : index /sitemap.xml, puis pages fixes, catégories, témoignages, vidéos
 * (balises video:video), directs et versets / thèmes. Seuls les contenus publiés sur le web y figurent
 * (Testimony::published + accord publication_web). Gardés en cache jusqu'à minuit (Africa/Porto-Novo) et
 * vidés à chaque publication, modification ou suppression (Sitemaps::forget). Voir docs/fonctionnalites/referencement.md
 */
class Sitemaps
{
    public const NAMES = ['pages', 'categories', 'testimonies', 'videos', 'lives', 'verses'];

    private const VERSION_KEY = 'sitemaps:version';

    /** Contenu d'un fichier : « index » ou l'un de NAMES. */
    public function xml(string $name): string
    {
        $version = Cache::rememberForever(self::VERSION_KEY, fn () => (string) Str::uuid());
        $midnight = CarbonImmutable::now(VerseDesignOfTheDay::timezone())->addDay()->startOfDay();

        return Cache::remember("sitemaps:{$version}:{$name}", $midnight, fn () => match ($name) {
            'index'       => $this->index(),
            'pages'       => $this->urlset($this->pages()),
            'categories'  => $this->urlset($this->categories()),
            'testimonies' => $this->urlset($this->testimonies()),
            'videos'      => $this->videos(),
            'lives'       => $this->urlset($this->lives()),
            'verses'      => $this->urlset($this->verses()),
        });
    }

    /** Vide les sitemaps : recalculés à la prochaine demande (publication, suppression, retrait du web…). */
    public static function forget(): void
    {
        Cache::forever(self::VERSION_KEY, (string) Str::uuid());
    }

    /** Témoignages publiés sur le web (règle explicite : indépendante du contexte de la requête). */
    public static function testimonyQuery()
    {
        return Testimony::query()->published()->where('publication_web', true)->with('category');
    }

    // ─── Fichiers ───────────────────────────────────────────────────────────

    private function index(): string
    {
        $now = now()->toAtomString();
        $items = collect(self::NAMES)->map(fn ($n) => '<sitemap><loc>' . self::e(ShareMeta::absolute("/sitemap-{$n}.xml")) . "</loc><lastmod>{$now}</lastmod></sitemap>")->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . $items . '</sitemapindex>';
    }

    /** @return list<array{0: string, 1: ?string}> [adresse absolue, lastmod] */
    private function pages(): array
    {
        return array_map(fn ($route) => [ShareMeta::absolute(route($route, [], false)), null],
            ['home', 'videos.index', 'lives.index', 'daily-verse.show', 'daily-verse.history', 'testimonies.conversions', 'events.index']);
    }

    private function categories(): array
    {
        return Category::query()->where('is_active', true)->orderBy('display_order')->get()
            ->map(fn (Category $c) => [ShareMeta::absolute(route('testimonies.category', $c->slug, false)), $c->updated_at?->toAtomString()])->all();
    }

    private function testimonies(): array
    {
        return self::testimonyQuery()->latest('updated_at')->limit((int) config('seo.sitemap_max_urls', 45000))->get()
            ->map(fn (Testimony $t) => [$t->share_url, $t->updated_at?->toAtomString()])->all();
    }

    private function videos(): string
    {
        $bunny = app(BunnyStream::class);
        $items = self::testimonyQuery()->where(fn ($q) => $q->whereNotNull('bunny_video_id')->orWhereNotNull('youtube_id')->orWhere('type', 'video'))
            ->latest('updated_at')->limit((int) config('seo.sitemap_max_urls', 45000))->get()
            ->map(function (Testimony $t) use ($bunny) {
                $player = $t->bunny_video_id ? $bunny->embedUrl($t->bunny_video_id)
                    : ($t->youtube_id ? 'https://www.youtube.com/embed/' . $t->youtube_id : null);
                $thumbnail = StructuredData::image($t);
                if (!$player && !$t->media_url) return '';
                $duration = (int) ($t->duration_sec ?: $t->duration ?: 0);

                return '<url><loc>' . self::e($t->share_url) . '</loc><video:video>'
                    . '<video:thumbnail_loc>' . self::e((string) $thumbnail) . '</video:thumbnail_loc>'
                    . '<video:title>' . self::e(Str::limit(trim((string) $t->title), 95)) . '</video:title>'
                    . '<video:description>' . self::e(Str::limit(StructuredData::description($t), 2000)) . '</video:description>'
                    . ($player ? '<video:player_loc>' . self::e($player) . '</video:player_loc>' : '<video:content_loc>' . self::e((string) $t->media_url) . '</video:content_loc>')
                    . ($duration > 0 ? '<video:duration>' . min($duration, 28800) . '</video:duration>' : '')
                    . '<video:publication_date>' . ($t->approved_at ?? $t->created_at)?->toAtomString() . '</video:publication_date>'
                    . '<video:family_friendly>yes</video:family_friendly>'
                    . '</video:video></url>';
            })->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">'
            . $items . '</urlset>';
    }

    private function lives(): array
    {
        // Directs publics (ni en préparation, ni salle d'une session de prière), les plus récents.
        return LiveSession::query()->whereIn('status', [LiveStatus::Live, LiveStatus::Ended])->whereNull('prayer_session_id')
            ->latest('updated_at')->limit(5000)->get()
            ->map(fn (LiveSession $l) => [ShareMeta::absolute(route('lives.show', $l->id, false)), ($l->ended_at ?? $l->updated_at)?->toAtomString()])->all();
    }

    private function verses(): array
    {
        // Jours passés et aujourd'hui seulement (jamais les versets à venir).
        $today = VerseDesignOfTheDay::todayDate();
        $days = DailyVerse::query()->where('is_active', true)->whereNotNull('scheduled_date')->whereDate('scheduled_date', '<=', $today)
            ->orderByDesc('scheduled_date')->limit(3000)->get(['scheduled_date', 'updated_at'])
            ->map(fn (DailyVerse $v) => [ShareMeta::absolute(route('daily-verse.show', ['date' => $v->scheduled_date->toDateString()], false)), $v->updated_at?->toAtomString()]);
        $themes = ThemeSemaine::query()->whereDate('date_debut', '<=', $today)->whereNotNull('slug')->orderByDesc('date_debut')->get()
            ->unique('slug')
            ->map(fn (ThemeSemaine $t) => [ShareMeta::absolute(route('themes.show', $t->slug, false)), $t->updated_at?->toAtomString()]);

        return $days->concat($themes)->values()->all();
    }

    // ─── Outils ─────────────────────────────────────────────────────────────

    private function urlset(array $urls): string
    {
        $items = collect($urls)->map(fn ($u) => '<url><loc>' . self::e($u[0]) . '</loc>' . ($u[1] ? '<lastmod>' . $u[1] . '</lastmod>' : '') . '</url>')->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . $items . '</urlset>';
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
