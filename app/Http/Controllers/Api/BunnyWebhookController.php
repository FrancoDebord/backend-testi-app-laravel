<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Testimony;
use App\Services\BunnyStream;
use App\Services\BunnyVideoSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook Bunny Stream (public, sans authentification ni CSRF) : POST /api/v1/webhooks/bunny,
 * { VideoLibraryId, VideoGuid, Status }. Voir docs/fonctionnalites/videos-bunny-stream.md
 *
 * Statuts du webhook : 3 Finished, 4 ResolutionFinished, 5 Failed (les autres : progression).
 * Bunny ne signe pas ses appels : l'état est relu par l'API Bunny (App\Services\BunnyVideoSync, même
 * logique que la commande videos:sync-bunny-status), pendant la requête pour que la réponse le dise.
 *
 * Réponse toujours 200, en JSON : { ok: true, action: updated | ignored | not_found }. Chaque appel est
 * journalisé (laravel.log) avec le GUID, le statut reçu, l'action et la raison.
 */
class BunnyWebhookController extends Controller
{
    public function __invoke(Request $request, BunnyStream $bunny, BunnyVideoSync $sync): JsonResponse
    {
        $libraryId = trim((string) $request->input('VideoLibraryId'));
        $guid      = $request->input('VideoGuid');
        $status    = $request->input('Status');

        [$action, $reason, $extra] = match (true) {
            !is_string($guid) || trim($guid) === ''   => ['ignored', 'GUID absent', []],
            !$bunny->isConfigured()                   => ['ignored', 'Bunny Stream non configuré (BUNNY_STREAM_LIBRARY_ID, BUNNY_STREAM_API_KEY, BUNNY_STREAM_CDN_HOST)', []],
            $libraryId !== $bunny->libraryId()        => ['ignored', 'bibliothèque différente de BUNNY_STREAM_LIBRARY_ID', ['configured_library' => $bunny->libraryId()]],
            default                                   => $this->sync(trim($guid), $sync),
        };

        Log::info('Bunny Stream : webhook', [
            'guid' => is_string($guid) ? $guid : null, 'status' => $status, 'library_id' => $libraryId,
            'action' => $action, 'reason' => $reason, ...$extra,
        ]);

        return response()->json(['ok' => true, 'action' => $action]);
    }

    /** @return array{0: string, 1: string, 2: array} [action, raison, détails] */
    private function sync(string $guid, BunnyVideoSync $sync): array
    {
        $testimony = Testimony::withTrashed()->where('bunny_video_id', $guid)->first();
        if (!$testimony) {
            return ['not_found', 'aucun témoignage avec ce bunny_video_id', []];
        }

        $result = $sync->syncFromBunny($testimony);
        $details = ['testimony_id' => $testimony->id, 'bunny_status' => $result['bunny_status'], 'video_status' => $result['video_status']];

        return match ($result['action']) {
            BunnyVideoSync::UPDATED => ['updated', 'état relu chez Bunny', $details],
            BunnyVideoSync::ERROR   => ['ignored', 'API Bunny injoignable ou refusée : ' . ($result['error'] ?? ''), $details],
            default                 => ['ignored', 'aucun changement', $details],
        };
    }
}
