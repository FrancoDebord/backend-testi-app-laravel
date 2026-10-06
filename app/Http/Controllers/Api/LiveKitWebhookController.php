<?php

namespace App\Http\Controllers\Api;

use App\Enums\LiveStatus;
use App\Http\Controllers\Controller;
use App\Models\LiveSession;
use App\Services\LiveKit\LiveKitClient;
use App\Services\LiveService;
use App\Services\LiveStage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Événements envoyés par LiveKit (URL à déclarer dans le projet LiveKit) :
 * passage à l'antenne quand la caméra du diffuseur est publiée, clôture quand la salle se ferme,
 * fin d'enregistrement (création du témoignage vidéo à relire).
 */
class LiveKitWebhookController extends Controller
{
    public function __invoke(Request $request, LiveKitClient $livekit, LiveService $lives, LiveStage $stage): Response
    {
        $body = $request->getContent();
        if (!$livekit->verifyWebhook($body, $request->header('Authorization'))) {
            return response('Signature invalide', 401);
        }

        $event = json_decode($body, true) ?? [];

        // Enregistrement : egress_started / egress_updated / egress_ended → témoignage vidéo à la fin.
        $egress = $event['egressInfo'] ?? $event['egress_info'] ?? null;
        if (is_array($egress) && str_starts_with((string) ($event['event'] ?? ''), 'egress_')) {
            $lives->handleEgress(new \App\Services\LiveKit\EgressInfo($egress));
            return response('', 204);
        }

        $room  = $event['room']['name'] ?? null;
        $live  = $room ? LiveSession::where('room_name', $room)->first() : null;
        if (!$live) {
            return response('', 204);
        }

        $identity = $event['participant']['identity'] ?? '';

        match ($event['event'] ?? null) {
            // Flux d'une caméra IP (« host-camera-… ») : aperçu dans le studio, le diffuseur lance lui-même.
            'track_published' => str_starts_with($identity, 'host-') && !str_starts_with($identity, 'host-camera-')
                && $live->status === LiveStatus::Preparing
                ? $lives->goLive($live, $live->host)
                : null,
            'room_finished'   => $lives->end($live, null, 'connection'),
            // Intervenant parti (page fermée, connexion perdue) : la place à l'antenne est libérée.
            'participant_left' => $identity !== '' ? $stage->participantLeft($live, $identity) : null,
            default           => null,
        };

        return response('', 204);
    }
}
