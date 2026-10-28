<?php

namespace App\Support;

use App\Enums\TestimonyType;
use App\Http\Controllers\Web\EventController;
use App\Models\Event;
use App\Models\LiveSession;
use App\Models\PrayerRequest;
use App\Models\Testimony;
use App\Services\PageShareImages;
use App\Services\TestimonyShareImage;

/**
 * Balises d'aperçu de partage (Open Graph, Twitter) des pages partagées, rendues par
 * <x-share-meta :meta="…" /> dans le <head>. Voir docs/fonctionnalites/apercu-de-partage.md
 *
 * Toutes les adresses sont absolues sur l'adresse publique (config app.share_url, https), comme share_url.
 * Contenu non visible sans compte (carnet, abonnés, en attente, masqué) : balises génériques du site,
 * rien de son titre ni de son texte.
 *
 * @phpstan-type Meta array{title: string, description: string, url: string, image: string, imageAlt: string, type: string, video?: ?array}
 */
class ShareMeta
{
    public const DESCRIPTION_LENGTH = 160;

    // ─── Contenus ───────────────────────────────────────────────────────────

    public static function testimony(Testimony $testimony): array
    {
        if (!TestimonyShareImage::isShareable($testimony)) {
            return self::site();
        }
        // Anonyme : « Témoignage anonyme » à la place du nom (docs/fonctionnalites/referencement.md).
        $author = $testimony->webAuthorName(null) ?: 'My Testimony';
        $isVideo = $testimony->type === TestimonyType::Video || $testimony->isYouTube();
        $title = trim((string) $testimony->title);
        $bunny = $testimony->bunny_video_id ? app(\App\Services\BunnyStream::class) : null;

        return [
            'title'       => $title . ' - ' . $author,
            // Description choisie, sinon début du résumé, du texte ou de la transcription.
            'description' => StructuredData::description($testimony),
            'url'         => $testimony->share_url,
            'image'       => self::absolute(route('share-images.testimony', [
                'id' => $testimony->id, 'v' => substr(app(TestimonyShareImage::class)->fingerprint($testimony), 0, 10),
            ], false)),
            'imageAlt'    => __('share.image_alt', ['title' => $title, 'name' => $author]),
            'type'        => $isVideo ? 'video.other' : 'article',
            'video'       => $testimony->isYouTube() ? self::youtube($testimony->youtube_id)
                : ($bunny ? ['url' => $bunny->embedUrl($testimony->bunny_video_id), 'type' => 'text/html', 'width' => 1280, 'height' => 720] : null),
        ];
    }

    public static function event(Event $event): array
    {
        if (!$event->isVisibleTo(null)) {
            return self::site();
        }
        $facts = collect([$event->typeLabel(), EventController::dateRange($event), EventController::place($event)])->filter()->implode(' · ');
        $text = self::excerpt($event->description, self::DESCRIPTION_LENGTH - mb_strlen($facts) - 3);

        return [
            'title'       => trim((string) $event->title),
            'description' => $text !== '' ? $facts . ' — ' . $text : ($facts ?: __('share.event_description', ['type' => __('share.event')])),
            'url'         => self::absolute(route('events.show', $event->id, false)),
            'image'       => self::absolute(route('share-images.event', [
                'id' => $event->id, 'v' => substr(app(PageShareImages::class)->eventFingerprint($event), 0, 10),
            ], false)),
            'imageAlt'    => __('share.event_alt', ['title' => trim((string) $event->title)]),
            'type'        => $event->youtube_live_id ? 'video.other' : 'article',
            'video'       => $event->youtube_live_id ? self::youtube($event->youtube_live_id) : null,
        ];
    }

    /** Requête publique seulement ; l'auteur n'apparaît jamais si elle est anonyme. */
    public static function prayer(PrayerRequest $prayer): array
    {
        if (!$prayer->isVisibleTo(null)) {
            return self::site();
        }
        $author = $prayer->is_anonymous ? null : $prayer->user?->display_name;

        return [
            'title'       => __('share.prayer_request') . ($author ? ' - ' . $author : ''),
            'description' => self::excerpt($prayer->body) ?: __('share.pray_with'),
            'url'         => self::absolute(route('prayer.requests.show', $prayer->id, false)),
            'image'       => self::absolute(route('share-images.prayer-request', [
                'id' => $prayer->id, 'v' => substr(app(PageShareImages::class)->prayerFingerprint($prayer), 0, 10),
            ], false)),
            'imageAlt'    => __('share.prayer_alt'),
            'type'        => 'article',
        ];
    }

    public static function live(LiveSession $live): array
    {
        if (!$live->isVisibleTo(null)) {
            return self::site();
        }
        $host = $live->host?->display_name ?: 'My Testimony';

        return [
            'title'       => trim((string) $live->title) . ' - ' . $host,
            'description' => self::excerpt($live->description) ?: __('share.live_description', ['name' => $host]),
            'url'         => self::absolute(route('lives.show', $live->id, false)),
            'image'       => self::absolute(route('share-images.live', [
                'id' => $live->id, 'v' => substr(app(PageShareImages::class)->liveFingerprint($live), 0, 10),
            ], false)),
            'imageAlt'    => __('share.live_alt', ['title' => trim((string) $live->title)]),
            'type'        => 'video.other',
        ];
    }

    /** Balises génériques du site (aucune information sur le contenu demandé). */
    /** Passage de la Bible (docs/fonctionnalites/liens-bible.md) : « Jean 3:16 (LSG) » et le texte des versets. */
    public static function biblePassage(string $title, string $text, string $url): array
    {
        return [...self::site(), 'title' => $title, 'description' => self::excerpt($text) ?: $title, 'url' => $url, 'type' => 'article'];
    }

    public static function site(): array
    {
        return [
            'title'       => __('share.site_title'),
            'description' => __('share.site_description'),
            'url'         => self::absolute(route('home', [], false)),
            'image'       => self::absolute(route('share-images.site', [], false)),
            'imageAlt'    => __('share.site_title'),
            'type'        => 'website',
        ];
    }

    // ─── Outils ─────────────────────────────────────────────────────────────

    /** Adresse absolue sur l'adresse publique https (SHARE_URL), comme le lien de partage. */
    public static function absolute(string $path): string
    {
        return rtrim((string) config('app.share_url'), '/') . '/' . ltrim($path, '/');
    }

    /** Texte brut : sans **gras**, *italique*, jetons de mention ni sauts de ligne. */
    public static function plain(?string $text): string
    {
        $plain = RichText::plain(\App\Services\Mentions::plain((string) $text));

        return trim(preg_replace('/\s+/u', ' ', $plain) ?? $plain);
    }

    /** Début du texte (160 caractères au plus), coupé entre deux mots, terminé par « … ». */
    public static function excerpt(?string $text, int $length = self::DESCRIPTION_LENGTH): string
    {
        $plain = self::plain($text);
        if ($plain === '' || $length < 20) {
            return '';
        }
        if (mb_strlen($plain) <= $length) {
            return $plain;
        }
        $cut = mb_substr($plain, 0, $length - 1);
        $space = mb_strrpos($cut, ' ');
        if ($space !== false && $space > $length * 0.6) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, " ,.;:-–—") . '…';
    }

    /** Lecteur intégré YouTube (Facebook, LinkedIn, Telegram lisent la vidéo dans l'aperçu). */
    private static function youtube(string $id): array
    {
        return ['url' => 'https://www.youtube.com/embed/' . $id, 'type' => 'text/html', 'width' => 1280, 'height' => 720];
    }

    /** og:locale de la page : fr_FR ou en_US. */
    public static function locale(): string
    {
        return app()->getLocale() === 'en' ? 'en_US' : 'fr_FR';
    }
}
