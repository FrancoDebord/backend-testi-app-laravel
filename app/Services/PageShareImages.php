<?php

namespace App\Services;

use App\Enums\LiveStatus;
use App\Http\Controllers\Web\EventController;
use App\Models\Event;
use App\Models\LiveSession;
use App\Models\PrayerRequest;
use App\Support\Locales;
use App\Support\YouTube;

/**
 * Images d'aperçu de partage des autres pages (1200 × 630 JPEG) : événements, requêtes de prière,
 * directs et image générique du site. Voir docs/fonctionnalites/apercu-de-partage.md
 * (témoignages : App\Services\TestimonyShareImage).
 */
class PageShareImages
{
    public function __construct(private ShareImage $images) {}

    // ─── Événements ─────────────────────────────────────────────────────────

    public function eventFingerprint(Event $event): string
    {
        return ShareImage::fingerprint([
            $event->updated_at?->getTimestamp(), $event->title, $event->type, $event->status?->value,
            $event->coverUrl(), $event->youtube_live_id, $event->starts_at?->getTimestamp(), $event->ends_at?->getTimestamp(),
            $event->date_tbd, $event->location, $event->city, $event->country,
        ]);
    }

    /** Couverture (+ lecture si direct YouTube), miniature YouTube + lecture, sinon carte titre / date / lieu. */
    public function event(Event $event): array
    {
        return $this->images->cached('events', $event->id, $this->eventFingerprint($event), fn () => Locales::using(config('app.locale'), function () use ($event) {
            $youtube = $event->youtube_live_id;
            $sources = array_values(array_filter([
                $event->coverUrl(),
                $youtube ? 'https://i.ytimg.com/vi/' . $youtube . '/maxresdefault.jpg' : null,
                $youtube ? YouTube::thumbnailUrl($youtube) : null,
            ]));
            if ($photo = $sources ? $this->images->photo($sources) : null) {
                if ($youtube) {
                    $this->images->dim($photo, 0.1);
                    $this->images->playButton($photo);
                }

                return [$this->images->jpeg($photo), true];
            }

            $card = $this->images->brandCard([
                'label' => __('share.event') . ' · ' . $event->typeLabel(),
                'title' => (string) $event->title,
                'lines' => array_values(array_filter([EventController::dateRange($event), EventController::place($event)])),
                'side'  => $youtube ? 230 : 0,
            ]);
            if ($youtube) {
                $this->images->playButton($card, ShareImage::WIDTH - 170, 330, 140);
            }

            return [$this->images->jpeg($card), $sources === []];
        }));
    }

    // ─── Requêtes de prière ─────────────────────────────────────────────────

    public function prayerFingerprint(PrayerRequest $prayer): string
    {
        return ShareImage::fingerprint([
            $prayer->updated_at?->getTimestamp(), $prayer->body, $prayer->is_anonymous, $prayer->status,
            $prayer->is_anonymous ? null : $prayer->user?->display_name,
        ]);
    }

    /** Carte : « Requête de prière », extrait entre guillemets, auteur (jamais s'il est anonyme). */
    public function prayer(PrayerRequest $prayer): array
    {
        return $this->images->cached('prayer-requests', $prayer->id, $this->prayerFingerprint($prayer), fn () => Locales::using(config('app.locale'), function () use ($prayer) {
            $author = $prayer->is_anonymous ? null : $prayer->user?->display_name;
            $card = $this->images->brandCard([
                'label' => __('share.prayer_request'),
                'title' => \Illuminate\Support\Str::limit(\App\Support\ShareMeta::plain($prayer->body), 220),
                'quote' => true,
                'lines' => array_values(array_filter([$author ? __('share.by', ['name' => $author]) : null, __('share.pray_with')])),
            ]);

            return [$this->images->jpeg($card), true];
        }));
    }

    // ─── Directs ────────────────────────────────────────────────────────────

    public function liveFingerprint(LiveSession $live): string
    {
        return ShareImage::fingerprint([
            $live->updated_at?->getTimestamp(), $live->title, $live->status?->value, $live->host?->display_name,
        ]);
    }

    /** Carte : « En direct » / « Direct terminé », titre, hôte et bouton lecture. */
    public function live(LiveSession $live): array
    {
        return $this->images->cached('lives', $live->id, $this->liveFingerprint($live), fn () => Locales::using(config('app.locale'), function () use ($live) {
            $label = match ($live->status) {
                LiveStatus::Live  => __('share.live'),
                LiveStatus::Ended => __('share.live_ended'),
                default           => __('share.live_upcoming'),
            };
            $card = $this->images->brandCard([
                'label' => $label,
                'title' => (string) $live->title,
                'lines' => array_values(array_filter([$live->host ? __('share.by', ['name' => $live->host->display_name]) : null])),
                'side'  => 230,
            ]);
            $this->images->playButton($card, ShareImage::WIDTH - 170, 330, 140);
            if ($live->status === LiveStatus::Live) {
                $this->images->pillBadge($card, __('share.live'), 'live');
            }

            return [$this->images->jpeg($card), true];
        }));
    }

    // ─── Site ───────────────────────────────────────────────────────────────

    /** Image générique (pages sans aperçu propre, contenus non publics). */
    public function site(): array
    {
        $locale = (string) config('app.locale');

        return $this->images->cached('site', 'default-' . $locale, ShareImage::fingerprint([$locale]), fn () => Locales::using($locale, fn () => [
            $this->images->jpeg($this->images->brandCard([
                'label' => 'ARISE & SHINE Krea',
                'title' => __('share.site_card'),
                'lines' => [__('share.site_card_line')],
            ])),
            true,
        ]));
    }
}
