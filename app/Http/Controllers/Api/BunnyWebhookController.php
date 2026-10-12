<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Testimony;
use App\Services\BunnyStream;
use App\Services\BunnyStreamVideos;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Webhook Bunny Stream (public) : POST /api/v1/webhooks/bunny, { VideoLibraryId, VideoGuid, Status }.
 * Voir docs/fonctionnalites/videos-bunny-stream.md
 *
 * Bunny ne signe pas ses appels : le contenu n'est jamais cru tel quel, l'état est relu par l'API
 * (BunnyStreamVideos::syncFromBunny), après la réponse. Réponse toujours 200, même pour un appel ignoré.
 */
class BunnyWebhookController extends Controller
{
    public function __invoke(Request $request, BunnyStream $bunny): Response
    {
        $libraryId = (string) $request->input('VideoLibraryId');
        $guid      = $request->input('VideoGuid');

        if (!is_string($guid) || $guid === '' || $libraryId === '' || $libraryId !== $bunny->libraryId()) {
            return response('', 200);
        }

        $testimony = Testimony::where('bunny_video_id', $guid)->first();
        if (!$testimony) {
            return response('', 200);
        }

        $testimonyId = $testimony->id;
        dispatch(function () use ($testimonyId) {
            $testimony = Testimony::find($testimonyId);
            if (!$testimony) {
                return;
            }
            try {
                app(BunnyStreamVideos::class)->syncFromBunny($testimony);
            } catch (Throwable $e) {
                Log::warning('Bunny Stream : état de la vidéo illisible.', ['testimony_id' => $testimonyId, 'error' => $e->getMessage()]);
            }
        })->afterResponse();

        return response('', 200);
    }
}
