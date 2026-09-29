<?php

namespace App\Services;

use App\Enums\LiveStatus;
use App\Enums\UserAccountStatus;
use App\Models\LiveSession;
use App\Models\LiveSpeaker;
use App\Models\User;
use App\Services\LiveKit\LiveKitClient;
use App\Services\LiveKit\LiveKitException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Intervenants d'un direct : file d'attente, invitation, passage à l'antenne — une personne à la fois.
 *
 * Aucun jeton « intervenant » n'est délivré : à l'acceptation, Laravel ouvre le droit de publier
 * (micro, caméra) à la connexion de spectateur déjà établie (LiveKit UpdateParticipant), puis le retire
 * à la fin, ce qui coupe aussitôt micro et caméra. Voir docs/fonctionnalites/lives-intervenants.md
 */
class LiveStage
{
    /** Droits d'un spectateur : lecture seule, invisible (identiques au jeton spectateur). */
    private const VIEWER_PERMISSION = [
        'can_subscribe'    => true,
        'can_publish'      => false,
        'can_publish_data' => false,
        'hidden'           => true,
    ];

    /** Droits d'un intervenant à l'antenne : micro et caméra seulement, pas de partage d'écran ni de données. */
    private const SPEAKER_PERMISSION = [
        'can_subscribe'       => true,
        'can_publish'         => true,
        'can_publish_sources' => ['CAMERA', 'MICROPHONE'],
        'can_publish_data'    => false,
        'hidden'              => false,
    ];

    public function __construct(private readonly LiveKitClient $livekit) {}

    // ─── Lecture ─────────────────────────────────────────────────────────────

    /**
     * État de la scène : ouvert ou non, intervenant actuel, file.
     * La file détaillée (avec les sujets annoncés) n'est donnée qu'au diffuseur et aux modérateurs ;
     * chacun voit sa propre demande et sa position.
     */
    public function state(LiveSession $live, ?User $viewer): array
    {
        $this->expireInvitation($live);

        $open    = LiveSpeaker::with('user')->where('live_session_id', $live->id)->open()->orderBy('created_at')->get();
        $current = $open->first(fn ($s) => in_array($s->status, LiveSpeaker::STAGE, true));
        $queue   = $open->where('status', LiveSpeaker::WAITING)->values();
        $staff   = $live->canBeModeratedBy($viewer);

        $mine = $viewer ? $open->firstWhere('user_id', $viewer->id) : null;
        $minePosition = $mine?->status === LiveSpeaker::WAITING ? $queue->search(fn ($s) => $s->id === $mine->id) + 1 : null;
        $refusal = $this->refusalReason($live, $viewer, $mine);

        return [
            'enabled'       => (bool) $live->speakers_enabled,
            'current'       => $current?->toPayload($staff || $current->user_id === $viewer?->id),
            'queueCount'    => $queue->count(),
            'queue'         => $staff ? $queue->map(fn ($s, $i) => $s->toPayload(true, $i + 1))->all() : null,
            'mine'          => $mine?->toPayload(true, $minePosition),
            'canRequest'    => $refusal === null,
            'refusal'       => $refusal, // raison affichable quand canRequest est faux
            'inviteTimeout' => (int) config('livekit.stage_invite_timeout'),
        ];
    }

    /** Résumé public (statistiques du direct, rafraîchies toutes les 15 s). */
    public function summary(LiveSession $live): array
    {
        $current = LiveSpeaker::with('user')->where('live_session_id', $live->id)
            ->whereIn('status', LiveSpeaker::STAGE)->first();

        return [
            'enabled'    => (bool) $live->speakers_enabled,
            'current'    => $current?->toPayload(),
            'queueCount' => LiveSpeaker::where('live_session_id', $live->id)->where('status', LiveSpeaker::WAITING)->count(),
        ];
    }

    // ─── Spectateur ──────────────────────────────────────────────────────────

    /** Demander à intervenir : entrée dans la file. */
    public function request(LiveSession $live, User $user, ?string $message): LiveSpeaker
    {
        $mine = LiveSpeaker::where('live_session_id', $live->id)->where('user_id', $user->id)->open()->first();
        if ($reason = $this->refusalReason($live, $user, $mine)) {
            throw new LiveActionException($reason, $mine ? 409 : 403);
        }

        $message = $message !== null ? trim(preg_replace('/\s+/u', ' ', $message)) : null;
        if ($message !== null && mb_strlen($message) > (int) config('livekit.stage_message_length')) {
            throw new LiveActionException('Le sujet ne doit pas dépasser ' . config('livekit.stage_message_length') . ' caractères.', 422);
        }

        $key = "live-stage:{$live->id}:{$user->id}";
        if (RateLimiter::tooManyAttempts($key, (int) config('livekit.stage_requests_per_window'))) {
            throw new LiveActionException('Vous avez fait plusieurs demandes en peu de temps. Patientez quelques minutes.', 429);
        }
        RateLimiter::hit($key, (int) config('livekit.stage_requests_window'));

        $speaker = LiveSpeaker::create([
            'live_session_id' => $live->id,
            'user_id'         => $user->id,
            'status'          => LiveSpeaker::WAITING,
            'message'         => $message ?: null,
        ]);
        $speaker->setRelation('user', $user);

        $this->broadcast($live, 'requested', $speaker);

        return $speaker;
    }

    /** Retirer sa demande, refuser l'invitation reçue, ou quitter l'antenne. */
    public function withdraw(LiveSession $live, User $user): void
    {
        $speaker = LiveSpeaker::with('user')->where('live_session_id', $live->id)->where('user_id', $user->id)->open()->first();
        if (!$speaker) {
            return;
        }

        if ($speaker->status === LiveSpeaker::ON_STAGE) {
            $this->finish($live, $speaker, 'left', null);
            return;
        }

        $speaker->update(['status' => LiveSpeaker::CANCELLED, 'ended_at' => now()]);
        $this->broadcast($live, 'cancelled', $speaker);
    }

    /**
     * Accepter l'invitation : micro (et caméra si choisie) ouverts sur la connexion déjà établie.
     * $identity : participant LiveKit du spectateur (renvoyé par viewer-token), qui doit être le sien.
     */
    public function accept(LiveSession $live, User $user, string $identity, bool $camera): LiveSpeaker
    {
        $this->ensureOnAir($live);

        $speaker = LiveSpeaker::with('user')->where('live_session_id', $live->id)->where('user_id', $user->id)
            ->where('status', LiveSpeaker::INVITED)->first();
        if (!$speaker) {
            throw new LiveActionException('Aucune invitation en cours : elle a peut-être été annulée.', 409);
        }
        if ($speaker->isInvitationExpired()) {
            $this->expireInvitation($live);
            throw new LiveActionException('L\'invitation a expiré. Vous pouvez refaire une demande.', 410);
        }
        if (!str_starts_with($identity, "user-{$user->id}-")) {
            throw new LiveActionException('Connexion au direct invalide. Rechargez la page.', 422);
        }

        try {
            $this->livekit->updateParticipant($live->room_name, $identity, self::SPEAKER_PERMISSION);
        } catch (LiveKitException $e) {
            throw new LiveActionException('Votre connexion au direct a été perdue. Rechargez la page, puis acceptez à nouveau.', 409);
        }

        $speaker->update([
            'status'     => LiveSpeaker::ON_STAGE,
            'identity'   => $identity,
            'camera'     => $camera,
            'started_at' => now(),
        ]);
        $this->broadcast($live, 'on_stage', $speaker);

        return $speaker;
    }

    // ─── Diffuseur et modérateurs ────────────────────────────────────────────

    /** Invite une personne de la file. Refusé si quelqu'un est déjà invité ou à l'antenne. */
    public function invite(LiveSession $live, string $speakerId, User $actor): LiveSpeaker
    {
        $this->ensureStaff($live, $actor);
        $this->ensureOnAir($live);
        $this->expireInvitation($live);

        return DB::transaction(function () use ($live, $speakerId, $actor) {
            // Verrou sur le direct : deux modérateurs ne peuvent pas inviter deux personnes à la fois.
            LiveSession::whereKey($live->id)->lockForUpdate()->first();

            $busy = LiveSpeaker::where('live_session_id', $live->id)->whereIn('status', LiveSpeaker::STAGE)->exists();
            if ($busy) {
                throw new LiveActionException('Une personne est déjà invitée ou à l\'antenne. Terminez son intervention avant d\'inviter la suivante.', 409);
            }

            $speaker = LiveSpeaker::with('user')->where('live_session_id', $live->id)->findOrFail($speakerId);
            if ($speaker->status !== LiveSpeaker::WAITING) {
                throw new LiveActionException('Cette demande n\'est plus dans la file.', 409);
            }
            if ($live->isBanned($speaker->user) || $speaker->user->status !== UserAccountStatus::Active) {
                throw new LiveActionException('Cette personne ne peut plus intervenir pendant ce direct.', 422);
            }

            $speaker->update(['status' => LiveSpeaker::INVITED, 'invited_at' => now(), 'handled_by' => $actor->id]);
            $this->broadcast($live, 'invited', $speaker);

            return $speaker;
        });
    }

    /** Refuser une demande de la file, ou annuler une invitation restée sans réponse. */
    public function decline(LiveSession $live, string $speakerId, User $actor): void
    {
        $this->ensureStaff($live, $actor);

        $speaker = LiveSpeaker::with('user')->where('live_session_id', $live->id)->findOrFail($speakerId);
        if (!in_array($speaker->status, [LiveSpeaker::WAITING, LiveSpeaker::INVITED], true)) {
            throw new LiveActionException('Cette demande n\'est plus en attente.', 409);
        }

        $speaker->update(['status' => LiveSpeaker::DECLINED, 'ended_at' => now(), 'handled_by' => $actor->id]);
        $this->broadcast($live, 'declined', $speaker);
    }

    /** Retirer l'intervenant de l'antenne (fin de son témoignage ou modération). */
    public function remove(LiveSession $live, string $speakerId, User $actor): void
    {
        $this->ensureStaff($live, $actor);

        $speaker = LiveSpeaker::with('user')->where('live_session_id', $live->id)->findOrFail($speakerId);
        if ($speaker->status !== LiveSpeaker::ON_STAGE) {
            throw new LiveActionException('Cette personne n\'est pas à l\'antenne.', 409);
        }

        $this->finish($live, $speaker, 'removed', $actor);
    }

    /** Ouvrir ou fermer les demandes. Fermer ne retire pas l'intervenant en cours ni la file. */
    public function setEnabled(LiveSession $live, User $actor, bool $enabled): void
    {
        $this->ensureStaff($live, $actor);
        $live->update(['speakers_enabled' => $enabled]);
        $this->broadcast($live, 'settings');
    }

    // ─── Événements du direct ────────────────────────────────────────────────

    /** Personne exclue du direct : sortie de la file et de l'antenne. */
    public function dropUser(LiveSession $live, User $user): void
    {
        LiveSpeaker::with('user')->where('live_session_id', $live->id)->where('user_id', $user->id)->open()->get()
            ->each(function (LiveSpeaker $speaker) use ($live) {
                if ($speaker->status === LiveSpeaker::ON_STAGE) {
                    $this->finish($live, $speaker, 'banned', null);
                    return;
                }
                $speaker->update(['status' => LiveSpeaker::DECLINED, 'ended_at' => now(), 'ended_reason' => 'banned']);
                $this->broadcast($live, 'declined', $speaker);
            });
    }

    /** Fin du direct : toutes les demandes sont closes (la salle est fermée, pas besoin de retirer les droits). */
    public function closeAll(LiveSession $live): void
    {
        LiveSpeaker::where('live_session_id', $live->id)->where('status', LiveSpeaker::ON_STAGE)
            ->update(['status' => LiveSpeaker::DONE, 'ended_at' => now(), 'ended_reason' => 'live_ended']);
        LiveSpeaker::where('live_session_id', $live->id)->whereIn('status', [LiveSpeaker::WAITING, LiveSpeaker::INVITED])
            ->update(['status' => LiveSpeaker::CANCELLED, 'ended_at' => now(), 'ended_reason' => 'live_ended']);
    }

    /** Webhook « participant_left » : l'intervenant a perdu la connexion ou fermé la page. */
    public function participantLeft(LiveSession $live, string $identity): void
    {
        $speaker = LiveSpeaker::with('user')->where('live_session_id', $live->id)
            ->where('status', LiveSpeaker::ON_STAGE)->where('identity', $identity)->first();
        if ($speaker) {
            $this->finish($live, $speaker, 'disconnected', null, revoke: false);
        }
    }

    /**
     * Secours périodique (lives:cleanup) : invitations expirées, intervenants partis sans webhook.
     * @param array<int, string> $identities participants présents dans la salle
     */
    public function reconcile(LiveSession $live, array $identities): void
    {
        $this->expireInvitation($live);

        LiveSpeaker::with('user')->where('live_session_id', $live->id)->where('status', LiveSpeaker::ON_STAGE)->get()
            ->reject(fn (LiveSpeaker $s) => in_array($s->identity, $identities, true))
            ->each(fn (LiveSpeaker $s) => $this->finish($live, $s, 'disconnected', null, revoke: false));
    }

    // ─── Outils ──────────────────────────────────────────────────────────────

    private function finish(LiveSession $live, LiveSpeaker $speaker, string $reason, ?User $actor, bool $revoke = true): void
    {
        if ($revoke && $speaker->identity) {
            try {
                $this->livekit->updateParticipant($live->room_name, $speaker->identity, self::VIEWER_PERMISSION);
            } catch (\Throwable $e) {
                // Participant déjà parti : il n'a plus aucun droit dans la salle.
            }
        }

        $speaker->update([
            'status'       => LiveSpeaker::DONE,
            'ended_at'     => now(),
            'ended_reason' => $reason,
            'handled_by'   => $actor?->id ?? $speaker->handled_by,
        ]);
        $this->broadcast($live, 'ended', $speaker, ['reason' => $reason]);
    }

    /** Invitation sans réponse dans le délai : la place est libérée pour la personne suivante. */
    private function expireInvitation(LiveSession $live): void
    {
        LiveSpeaker::with('user')->where('live_session_id', $live->id)->where('status', LiveSpeaker::INVITED)
            ->where('invited_at', '<', now()->subSeconds((int) config('livekit.stage_invite_timeout')))
            ->get()
            ->each(function (LiveSpeaker $speaker) use ($live) {
                $speaker->update(['status' => LiveSpeaker::EXPIRED, 'ended_at' => now()]);
                $this->broadcast($live, 'expired', $speaker);
            });
    }

    /** Raison pour laquelle cette personne ne peut pas demander à intervenir, ou null. */
    private function refusalReason(LiveSession $live, ?User $user, ?LiveSpeaker $open): ?string
    {
        return match (true) {
            $user === null                               => 'Connectez-vous pour demander à intervenir.',
            $live->status === LiveStatus::Ended          => 'Ce direct est terminé.',
            !$live->isOnAir()                            => 'Ce direct n\'a pas encore commencé.',
            $live->canBeModeratedBy($user)               => 'Le diffuseur et les modérateurs n\'ont pas besoin de demander.',
            !$live->speakers_enabled                     => 'Les demandes d\'intervention sont fermées pour le moment.',
            $user->status !== UserAccountStatus::Active  => 'Votre compte ne permet pas cette action.',
            $live->isBanned($user)                       => 'Vous ne pouvez plus intervenir pendant ce direct.',
            $open !== null                               => 'Vous avez déjà une demande en cours.',
            default                                      => null,
        };
    }

    private function ensureStaff(LiveSession $live, User $actor): void
    {
        if (!$live->canBeModeratedBy($actor)) {
            throw new LiveActionException('Seuls le diffuseur et les modérateurs gèrent les intervenants.', 403);
        }
    }

    private function ensureOnAir(LiveSession $live): void
    {
        if (!$live->isOnAir()) {
            throw new LiveActionException($live->status === LiveStatus::Ended ? 'Ce direct est terminé.' : 'Ce direct n\'a pas encore commencé.', 409);
        }
    }

    /**
     * Message temps réel « stage » : les applications rechargent l'état (GET …/stage) à sa réception.
     * Le sujet annoncé n'y figure jamais (message reçu par tous les spectateurs).
     */
    private function broadcast(LiveSession $live, string $event, ?LiveSpeaker $speaker = null, array $extra = []): void
    {
        if (!$this->livekit->isConfigured()) {
            return;
        }
        try {
            $this->livekit->sendData($live->room_name, array_merge([
                'type'    => 'stage',
                'event'   => $event,
                'speaker' => $speaker?->toPayload(),
            ], $extra));
        } catch (\Throwable $e) {
            Log::info('Diffusion temps réel impossible', ['live' => $live->id, 'type' => "stage:{$event}"]);
        }
    }
}
