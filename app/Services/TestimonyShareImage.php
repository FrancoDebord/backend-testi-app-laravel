<?php

namespace App\Services;

use App\Enums\TestimonyType;
use App\Models\Testimony;
use App\Support\Locales;
use App\Support\YouTube;

/**
 * Image d'aperçu de partage d'un témoignage (1200 × 630 JPEG) : docs/fonctionnalites/apercu-de-partage.md
 *
 *  - vidéo (Bunny, YouTube, fichier) : miniature recadrée + bouton lecture + durée si connue ;
 *  - texte / audio avec couverture : la couverture (audio : pastille « Audio ») ;
 *  - sans image (ou miniature indisponible) : carte de la marque avec titre, auteur et catégorie.
 *
 * Témoignage non public (carnet, abonnés, en attente, vidéo pas prête) : aucune image (404).
 * Servie par GET /partage/temoignages/{id}.jpg (share-images.testimony).
 */
class TestimonyShareImage
{
    public function __construct(private ShareImage $images) {}

    /** Visible de tous, sans compte (les robots des réseaux sociaux ne sont pas connectés). */
    public static function isShareable(Testimony $testimony): bool
    {
        return $testimony->isVisibleTo(null);
    }

    /** Change dès que le titre, l'image, la durée, l'auteur ou la catégorie changent. */
    public function fingerprint(Testimony $testimony): string
    {
        return ShareImage::fingerprint([
            $testimony->updated_at?->getTimestamp(), $testimony->title, $testimony->type?->value,
            $testimony->cover_url, $testimony->youtube_id, $testimony->bunny_video_id, $testimony->video_status,
            $testimony->duration_sec, $testimony->duration,
            $testimony->user?->display_name, $testimony->category?->name ?? $testimony->category_slug,
        ]);
    }

    /** @return array{path: string, fallback: bool} */
    public function file(Testimony $testimony): array
    {
        return $this->images->cached('testimonies', $testimony->id, $this->fingerprint($testimony),
            fn () => Locales::using(config('app.locale'), fn () => $this->render($testimony)));
    }

    /** @return array{0: string, 1: bool} JPEG et « complet » (false : miniature attendue mais indisponible). */
    public function render(Testimony $testimony): array
    {
        $isVideo = $testimony->type === TestimonyType::Video || $testimony->isYouTube();
        $isAudio = !$isVideo && $testimony->type === TestimonyType::Audio;
        $sources = $this->sources($testimony);
        $photo = $sources ? $this->images->photo($sources) : null;

        if ($photo) {
            if ($isVideo) {
                $this->images->dim($photo, 0.1);
                $this->images->playButton($photo);
                if ($duration = $this->duration($testimony)) {
                    $this->images->durationBadge($photo, $duration);
                }
            } elseif ($isAudio) {
                $this->images->pillBadge($photo, __('share.audio'));
            }

            return [$this->images->jpeg($photo), true];
        }

        $author = $testimony->user?->display_name;
        $category = $testimony->category?->name;
        $label = collect([$isVideo ? __('share.video') : ($isAudio ? __('share.audio') : __('share.testimony')), $category])
            ->filter()->implode(' · ');
        $card = $this->images->brandCard([
            'label' => $label,
            'title' => (string) $testimony->title,
            'lines' => array_values(array_filter([$author ? __('share.by', ['name' => $author]) : null])),
            'side'  => $isVideo ? 230 : 0,
        ]);
        if ($isVideo) {
            $this->images->playButton($card, ShareImage::WIDTH - 170, 330, 140);
        } elseif ($isAudio) {
            $this->images->pillBadge($card, __('share.audio'));
        }

        return [$this->images->jpeg($card), $sources === []];
    }

    /** Adresses candidates, de la meilleure à la plus sûre. */
    public function sources(Testimony $testimony): array
    {
        if ($testimony->isYouTube()) {
            return [
                'https://i.ytimg.com/vi/' . $testimony->youtube_id . '/maxresdefault.jpg',
                YouTube::thumbnailUrl($testimony->youtube_id),
            ];
        }
        $urls = [];
        if (filled($testimony->cover_url)) {
            $urls[] = $testimony->cover_url;
        }
        // Vidéo Bunny prête sans couverture enregistrée : miniature du CDN.
        $bunny = app(BunnyStream::class);
        if ($testimony->bunny_video_id && $testimony->video_status === Testimony::VIDEO_READY && filled($bunny->cdnHost())) {
            $urls[] = $bunny->thumbnailUrl($testimony->bunny_video_id);
        }

        return array_values(array_unique($urls));
    }

    private function duration(Testimony $testimony): ?string
    {
        $seconds = (int) ($testimony->duration_sec ?: $testimony->duration);
        if ($seconds <= 0) {
            return null;
        }
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
    }
}
