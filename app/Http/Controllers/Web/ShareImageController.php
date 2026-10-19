<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\LiveSession;
use App\Models\PrayerRequest;
use App\Models\Testimony;
use App\Services\PageShareImages;
use App\Services\TestimonyShareImage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Images d'aperçu de partage (og:image), 1200 × 630 JPEG : GET /partage/… — publiques, sans session,
 * générées au premier appel puis servies depuis storage/app/share-images (jamais par /storage, qui
 * répond 403 en production). Contenu non visible sans compte : 404.
 * Voir docs/fonctionnalites/apercu-de-partage.md
 */
class ShareImageController extends Controller
{
    public function testimony(TestimonyShareImage $images, string $id): BinaryFileResponse
    {
        $testimony = Testimony::with(['user:id,display_name', 'category:id,name'])->find($id);
        abort_if(!$testimony || !TestimonyShareImage::isShareable($testimony), 404);

        return $this->send($images->file($testimony));
    }

    public function event(PageShareImages $images, string $id): BinaryFileResponse
    {
        $event = Event::with('images')->find($id);
        abort_if(!$event || !$event->isVisibleTo(null), 404);

        return $this->send($images->event($event));
    }

    public function prayerRequest(PageShareImages $images, string $id): BinaryFileResponse
    {
        $prayer = PrayerRequest::with('user:id,display_name')->find($id);
        abort_if(!$prayer || !$prayer->isVisibleTo(null), 404);

        return $this->send($images->prayer($prayer));
    }

    public function live(PageShareImages $images, string $id): BinaryFileResponse
    {
        $live = LiveSession::with('host:id,display_name')->find($id);
        abort_if(!$live || !$live->isVisibleTo(null), 404);

        return $this->send($images->live($live));
    }

    public function site(PageShareImages $images): BinaryFileResponse
    {
        return $this->send($images->site());
    }

    /** L'adresse change avec le contenu (?v=…) : cache long ; image de secours : une heure. */
    private function send(array $file): BinaryFileResponse
    {
        return response()->file($file['path'], [
            'Content-Type'  => 'image/jpeg',
            'Cache-Control' => $file['fallback'] ? 'public, max-age=3600' : 'public, max-age=2592000',
        ]);
    }
}
