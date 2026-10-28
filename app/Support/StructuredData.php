<?php

namespace App\Support;

use App\Enums\TestimonyType;
use App\Models\Category;
use App\Models\LiveSession;
use App\Models\Testimony;
use App\Services\BunnyStream;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * Données structurées schema.org (JSON-LD) des pages publiques : Organization et WebSite partout,
 * VideoObject ou Article pour un témoignage, BroadcastEvent pour un direct, CollectionPage pour une
 * catégorie, BreadcrumbList. Adresses absolues sur l'adresse publique (SHARE_URL).
 * Voir docs/fonctionnalites/referencement.md
 */
final class StructuredData
{
    public const SITE_NAME = 'My Testimony';

    /** <script type="application/ld+json"> (balises « </ » neutralisées). */
    public static function script(array $data): HtmlString
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_PRETTY_PRINT);

        return new HtmlString('<script type="application/ld+json">' . $json . '</script>');
    }

    public static function organization(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            '@id'      => ShareMeta::absolute('/#organization'),
            'name'     => self::SITE_NAME,
            'alternateName' => 'ARISE & SHINE Krea',
            'url'      => ShareMeta::absolute('/'),
            'logo'     => ShareMeta::absolute('/icons/arise-shine-krea-star.png'),
        ];
    }

    public static function website(): array
    {
        return [
            '@context'  => 'https://schema.org',
            '@type'     => 'WebSite',
            '@id'       => ShareMeta::absolute('/#website'),
            'name'      => self::SITE_NAME,
            'url'       => ShareMeta::absolute('/'),
            'inLanguage' => 'fr',
            'publisher' => ['@id' => ShareMeta::absolute('/#organization')],
        ];
    }

    /** Fil d'Ariane : [[libellé, adresse], …] (dernière étape : la page). */
    public static function breadcrumb(array $items): array
    {
        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => array_values(array_map(fn ($item, $i) => [
                '@type' => 'ListItem', 'position' => $i + 1, 'name' => $item[0], 'item' => $item[1],
            ], $items, array_keys($items))),
        ];
    }

    /** Témoignage vidéo ou audio : VideoObject ; témoignage écrit : Article. */
    public static function testimony(Testimony $testimony): array
    {
        $url = $testimony->share_url;
        $description = self::description($testimony);
        $published = ($testimony->approved_at ?? $testimony->created_at)?->toIso8601String();
        $author = ['@type' => 'Person', 'name' => $testimony->webAuthorName(null)];
        $image = self::image($testimony);

        if (self::isVideo($testimony)) {
            $guid = $testimony->bunny_video_id;
            $bunny = app(BunnyStream::class);

            return array_filter([
                '@context'     => 'https://schema.org',
                '@type'        => 'VideoObject',
                'name'         => trim((string) $testimony->title),
                'description'  => $description,
                'thumbnailUrl' => $image ? [$image] : null,
                'uploadDate'   => $published,
                'duration'     => self::isoDuration((int) ($testimony->duration_sec ?: $testimony->duration ?: 0)),
                'embedUrl'     => $guid ? $bunny->embedUrl($guid) : ($testimony->youtube_id ? 'https://www.youtube.com/embed/' . $testimony->youtube_id : null),
                'contentUrl'   => $guid && $bunny->cdnHost() !== '' ? $bunny->mp4Url($guid, 720) : ($testimony->media_url ?: null),
                'transcript'   => $testimony->transcription ? RichText::plain($testimony->transcription) : null,
                'inLanguage'   => 'fr',
                'url'          => $url,
                'author'       => $author,
                'publisher'    => ['@id' => ShareMeta::absolute('/#organization')],
            ], fn ($v) => $v !== null && $v !== '');
        }

        return array_filter([
            '@context'      => 'https://schema.org',
            '@type'         => 'Article',
            'headline'      => mb_substr(trim((string) $testimony->title), 0, 110),
            'description'   => $description,
            'datePublished' => $published,
            'dateModified'  => $testimony->updated_at?->toIso8601String(),
            'author'        => $author,
            'image'         => $image ? [$image] : null,
            'inLanguage'    => 'fr',
            'mainEntityOfPage' => $url,
            'articleSection' => $testimony->category?->displayName(),
            'publisher'     => ['@id' => ShareMeta::absolute('/#organization')],
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** Direct en cours : VideoObject + BroadcastEvent (isLiveBroadcast) ; direct terminé (replay) : VideoObject. */
    public static function live(LiveSession $live): array
    {
        $start = ($live->started_at ?? $live->created_at)?->toIso8601String();
        $ended = !$live->isActive();

        return array_filter([
            '@context'     => 'https://schema.org',
            '@type'        => 'VideoObject',
            'name'         => trim((string) $live->title),
            'description'  => ShareMeta::excerpt($live->description) ?: __('share.live_description', ['name' => $live->host?->display_name ?: self::SITE_NAME]),
            'thumbnailUrl' => [ShareMeta::live($live)['image']],
            'uploadDate'   => $start,
            'url'          => ShareMeta::absolute(route('lives.show', $live->id, false)),
            'publication'  => $ended ? null : array_filter([
                '@type'           => 'BroadcastEvent',
                'isLiveBroadcast' => true,
                'startDate'       => $start,
            ]),
            'duration'     => $ended && $live->started_at && $live->ended_at ? self::isoDuration((int) $live->started_at->diffInSeconds($live->ended_at)) : null,
            'publisher'    => ['@id' => ShareMeta::absolute('/#organization')],
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /** Page d'une catégorie : CollectionPage avec la liste de ses témoignages. */
    public static function collection(Category $category, string $url, Collection $testimonies): array
    {
        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'CollectionPage',
            'name'        => Seo::categoryTitle($category),
            'description' => $category->intro ? ShareMeta::excerpt($category->intro) : __('seo.category.description', ['category' => $category->displayName()]),
            'url'         => $url,
            'inLanguage'  => 'fr',
            'mainEntity'  => [
                '@type'           => 'ItemList',
                'itemListElement' => $testimonies->values()->map(fn (Testimony $t, int $i) => [
                    '@type' => 'ListItem', 'position' => $i + 1, 'url' => $t->share_url, 'name' => trim((string) $t->title),
                ])->all(),
            ],
        ];
    }

    // ─── Outils ─────────────────────────────────────────────────────────────

    public static function isVideo(Testimony $testimony): bool
    {
        return $testimony->type === TestimonyType::Video || $testimony->isYouTube() || filled($testimony->bunny_video_id);
    }

    /** Description d'un témoignage : meta_description, sinon début du résumé, du texte ou de la transcription. */
    public static function description(Testimony $testimony): string
    {
        foreach ([$testimony->meta_description, $testimony->resume, $testimony->body_text, $testimony->transcription] as $text) {
            if (filled($text) && ($excerpt = ShareMeta::excerpt($text, 155)) !== '') return $excerpt;
        }

        // Sans texte : « Témoignage de Marie sur My Testimony » (« Témoignage anonyme » si anonyme).
        return __('share.testimony_by', ['name' => $testimony->webAuthorName(null) ?: 'My Testimony']);
    }

    /** Vignette : Bunny, couverture, sinon image générée du témoignage (aperçus de partage). */
    public static function image(Testimony $testimony): ?string
    {
        if ($testimony->bunny_video_id && app(BunnyStream::class)->cdnHost() !== '') {
            return app(BunnyStream::class)->thumbnailUrl($testimony->bunny_video_id);
        }

        return ShareMeta::testimony($testimony)['image'] ?? null;
    }

    /** Secondes → « PT1H2M3S ». */
    public static function isoDuration(int $seconds): ?string
    {
        if ($seconds <= 0) return null;
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return 'PT' . ($h ? "{$h}H" : '') . ($m ? "{$m}M" : '') . ($s || (!$h && !$m) ? "{$s}S" : '');
    }
}
