<?php

namespace App\Services;

use App\Enums\LiveStatus;
use App\Enums\NotificationType;
use App\Enums\UserAccountStatus;
use App\Models\LiveBan;
use App\Models\LiveComment;
use App\Models\LiveSession;
use App\Models\User;
use App\Services\LiveKit\LiveKitClient;
use App\Services\LiveKit\LiveKitException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Règles métier des témoignages en direct, partagées par le site web et l'API mobile.
 * Voir docs/fonctionnalites/lives.md
 */
class LiveService
{
    public function __construct(
        private readonly LiveKitClient $livekit,
        private readonly LiveStage $stage,
    ) {}

    public function isConfigured(): bool
    {
        return $this->livekit->isConfigured();
    }

    /** Enregistrement possible : LiveKit + stockage S3 + adresse de lecture des vidéos. */
    public function isRecordingConfigured(): bool
    {
        $c = config('livekit.recording');

        return $this->isConfigured() && $c['enabled']
            && filled($c['s3']['access_key']) && filled($c['s3']['secret']) && filled($c['s3']['bucket'])
            && filled($c['public_url']);
    }

    // ─── Démarrage / arrêt ───────────────────────────────────────────────────

    /** Crée un direct « en préparation » et sa salle LiveKit. */
    public function start(User $host, array $data): LiveSession
    {
        $this->ensureConfigured();
        $this->ensureActiveAccount($host);
        // Direct d'un événement : son organisateur (organisation vérifiée) peut le diffuser,
        // même sans être modérateur. Voir docs/fonctionnalites/evenements.md
        $event = null;
        $prayer = null; // session de prière (voir plus bas)
        if (!empty($data['event_id'])) {
            $event = \App\Models\Event::find($data['event_id']);
            if (!$event || !$event->canBeManagedBy($host)) {
                throw new LiveActionException("Seuls l'organisateur et les administrateurs peuvent diffuser cet événement.", 403);
            }
            if ($event->status !== \App\Enums\EventStatus::Published) {
                throw new LiveActionException("L'événement doit être publié (et non annulé) pour être diffusé.", 422);
            }
            // L'événement doit appartenir à une organisation encore vérifiée (ou à un administrateur).
            $organizer = $event->organizer;
            if (!$host->canModerate() && !($organizer?->isVerified() || $organizer?->isAdmin())) {
                throw new LiveActionException("Seules les organisations vérifiées peuvent diffuser leurs événements.", 403);
            }
        } elseif (!empty($data['prayer_session_id'])) {
            // Salle d'une session de prière : ouverte par son hôte, même simple utilisateur.
            // Jamais passé par les formulaires des directs : seul App\Services\PrayerSessions l'envoie.
            // Voir docs/fonctionnalites/sessions-de-priere.md
            $prayer = \App\Models\PrayerSession::find($data['prayer_session_id']);
            if (!$prayer || !$prayer->isHost($host)) {
                throw new LiveActionException("Seul l'hôte peut ouvrir la salle de cette session de prière.", 403);
            }
        } elseif (!$host->canModerate()) {
            throw new LiveActionException('Seuls les modérateurs et les administrateurs peuvent diffuser en direct.', 403);
        }

        $existing = LiveSession::active()->where('host_id', $host->id)->first();
        if ($existing) {
            throw new LiveActionException('Vous avez déjà un direct en cours. Terminez-le avant d\'en lancer un autre.', 409, $existing);
        }

        $room = 'live-' . Str::uuid();
        try {
            $this->livekit->createRoom($room, (int) config('livekit.empty_timeout'), (int) config('livekit.max_participants'));
        } catch (LiveKitException $e) {
            throw new LiveActionException('Le service vidéo est injoignable pour le moment. Réessayez dans quelques instants.', 503);
        }

        $source = $data['source'] ?? 'browser';

        $live = LiveSession::create([
            'source'           => $source,
            'camera_url'       => $source === 'url' ? ($data['camera_url'] ?? null) : null,
            'host_id'          => $host->id,
            'event_id'         => $event?->id,
            'prayer_session_id' => $prayer?->id,
            'title'            => $data['title'],
            'description'      => $data['description'] ?? null,
            'category_slug'    => $data['category_slug'] ?? null,
            'comments_enabled' => $data['comments_enabled'] ?? true,
            'record'           => ($data['record'] ?? true) && $this->isRecordingConfigured(),
            'room_name'        => $room,
            'status'           => LiveStatus::Preparing,
        ]);

        if ($live->usesExternalCamera()) {
            $this->connectCamera($live, $host);
        }

        return $live;
    }

    /**
     * Caméra IP / encodeur : crée le point d'entrée LiveKit (Ingress). RTMP : adresse et clé à saisir
     * dans la caméra ; URL : LiveKit lit l'adresse du flux. Voir docs/fonctionnalites/lives-camera-ip.md
     */
    private function connectCamera(LiveSession $live, User $host): void
    {
        try {
            $info = $this->livekit->createIngress(
                $live->room_name,
                $live->source === 'url' ? 'URL_INPUT' : 'RTMP_INPUT',
                $live->cameraIdentity(),
                $host->display_name . ' (caméra)',
                $live->source === 'url' ? $live->camera_url : null,
            );
        } catch (LiveKitException $e) {
            $live->update(['status' => LiveStatus::Ended, 'ended_at' => now(), 'end_reason' => 'camera']);
            try { $this->livekit->deleteRoom($live->room_name); } catch (\Throwable) {}
            throw new LiveActionException($live->source === 'url'
                ? "Le service vidéo n'accepte pas cette adresse de flux. Vérifiez-la (HLS, HTTP, SRT, RTMP) ou utilisez le mode RTMP."
                : "Le point d'entrée de la caméra n'a pas pu être créé. Réessayez dans quelques instants.", 503);
        }

        $live->update([
            'ingress_id'         => $info['ingress_id'] ?? $info['ingressId'] ?? null,
            'ingress_url'        => $info['url'] ?? null,
            'ingress_stream_key' => $info['stream_key'] ?? $info['streamKey'] ?? null,
        ]);
    }

    /** Ferme le point d'entrée de la caméra (fin du direct). */
    private function disconnectCamera(LiveSession $live): void
    {
        if (!$live->ingress_id) return;
        try {
            $this->livekit->deleteIngress($live->ingress_id);
        } catch (\Throwable $e) {
            // Déjà supprimé : sans conséquence.
        }
    }

    /** Le diffuseur publie sa caméra : le direct devient visible de tous. */
    public function goLive(LiveSession $live, User $user): LiveSession
    {
        if (!$live->isHost($user)) {
            throw new LiveActionException('Seul le diffuseur peut lancer ce direct.', 403);
        }
        if ($live->status === LiveStatus::Ended) {
            throw new LiveActionException('Ce direct est terminé.', 410);
        }
        if ($live->status === LiveStatus::Preparing) {
            // Passage conditionnel : l'appel « go-live » de l'application et le webhook LiveKit
            // (track_published, éventuellement rejoué) peuvent arriver ensemble ; un seul gagne.
            $switched = LiveSession::whereKey($live->id)
                ->where('status', LiveStatus::Preparing)
                ->update(['status' => LiveStatus::Live, 'started_at' => now()]);
            $live->refresh();

            if ($switched) {
                $this->broadcast($live, ['type' => 'status', 'status' => LiveStatus::Live->value]);
                $this->notifyFollowers($live);
            }
        }
        $this->startRecording($live);

        return $live;
    }

    /** Prévient les abonnés du diffuseur (notification + push « live_started »). Un échec n'empêche jamais le direct. */
    private function notifyFollowers(LiveSession $live): void
    {
        try {
            $host = $live->host;
            if (!$host) {
                return;
            }
            FollowerNotifications::notify(
                $host,
                NotificationType::LiveStarted->value,
                ['live_id' => $live->id, 'live_title' => $live->title],
                "{$host->display_name} est en direct : {$live->title}",
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // ─── Enregistrement → témoignage vidéo ───────────────────────────────────

    /** Lance l'enregistrement au passage à l'antenne. Un échec n'empêche jamais le direct. */
    public function startRecording(LiveSession $live): void
    {
        if (!$live->record || $live->egress_id || !$live->isOnAir() || !$this->isRecordingConfigured()) {
            return;
        }

        $c = config('livekit.recording');
        $path = trim($c['path_prefix'], '/') . '/' . now()->format('Y/m') . "/{$live->id}.mp4";

        try {
            $info = new \App\Services\LiveKit\EgressInfo(
                $this->livekit->startRoomRecording($live->room_name, $path, $c['s3'], $c['layout'], $c['preset'])
            );
            $live->update(['egress_id' => $info->id(), 'recording_path' => $path, 'recording_status' => 'recording', 'recording_error' => null]);
            $this->broadcast($live, ['type' => 'recording', 'status' => 'recording']);
        } catch (\Throwable $e) {
            Log::warning('Enregistrement du direct impossible', ['live' => $live->id, 'error' => $e->getMessage()]);
            $live->update(['recording_status' => 'failed', 'recording_error' => 'Démarrage impossible : ' . $e->getMessage()]);
        }
    }

    /**
     * Traite l'état d'un enregistrement (webhook « egress_* » ou vérification périodique).
     * À la fin, crée le témoignage vidéo EN ATTENTE DE RELECTURE (circuit de modération habituel).
     */
    public function handleEgress(\App\Services\LiveKit\EgressInfo $info): ?LiveSession
    {
        $live = $info->id() ? LiveSession::where('egress_id', $info->id())->first() : null;
        if (!$live || in_array($live->recording_status, ['ready', 'too_short'], true)) {
            return $live;
        }

        if ($info->isInProgress()) {
            if ($info->status() === 'EGRESS_ENDING' && $live->recording_status === 'recording') {
                $live->update(['recording_status' => 'processing']);
            }
            return $live;
        }

        // Terminé (y compris « limite atteinte » ou arrêt, s'il y a bien un fichier).
        if (!$info->hasFile() || $info->status() === 'EGRESS_FAILED') {
            $live->update(['recording_status' => 'failed', 'recording_error' => $info->error() ?? $info->status()]);
            return $live;
        }

        $duration = $info->durationSeconds();
        if ($duration < (int) config('livekit.recording.min_duration')) {
            $live->update(['recording_status' => 'too_short', 'recording_duration' => $duration]);
            return $live;
        }

        $media = null;
        DB::transaction(function () use ($live, $duration, $info, &$media) {
            $slug = $live->category_slug ?: 'autre';
            $testimony = \App\Models\Testimony::create([
                'user_id'       => $live->host_id,
                'category_id'   => \App\Models\Category::where('slug', $slug)->value('id'),
                'category_slug' => $slug,
                'title'         => $live->title,
                'type'          => 'video',
                'body_text'     => $live->description,
                'media_url'     => $this->recordingUrl($live->recording_path),
                'duration_sec'  => $duration,
                'tags'          => ['direct'],
                'visibility'    => 'public',
                'status'        => \App\Enums\TestimonyStatus::Pending->value,
            ]);
            $live->update(['recording_status' => 'ready', 'recording_duration' => $duration, 'testimony_id' => $testimony->id]);

            // Versions allégées (240p, 360p…) comme pour un envoi : docs/fonctionnalites/qualites-media.md
            if ($live->recording_path) {
                $media = \App\Models\MediaFile::forRecording(
                    $live->recording_path, $live->host_id, $testimony->media_url, $duration, $info->sizeBytes()
                );
            }
        });

        // Après validation de la transaction : la tâche doit trouver la ligne media_files.
        $media?->queueTranscoding();

        return $live->fresh();
    }

    public function recordingUrl(?string $path): ?string
    {
        return $path ? \App\Models\MediaFile::urlFor(\App\Models\MediaFile::RECORDINGS_DISK, $path) : null;
    }

    public function end(LiveSession $live, ?User $actor, string $reason): LiveSession
    {
        if ($actor && !$live->canBeModeratedBy($actor)) {
            throw new LiveActionException('Vous ne pouvez pas arrêter ce direct.', 403);
        }
        if ($live->status === LiveStatus::Ended) {
            return $live;
        }

        $live->update([
            'status'     => LiveStatus::Ended,
            'ended_at'   => now(),
            'ended_by'   => $actor?->id,
            'end_reason' => $reason,
        ]);

        $this->stage->closeAll($live);
        $this->broadcast($live, ['type' => 'ended', 'reason' => $reason]);

        // Arrêt propre de l'enregistrement : LiveKit finalise le fichier puis prévient par webhook.
        if ($live->egress_id && $live->recording_status === 'recording') {
            try {
                $this->livekit->stopEgress($live->egress_id);
            } catch (\Throwable $e) {
                // Déjà arrêté (salle fermée) : le résultat arrivera quand même.
            }
            $live->update(['recording_status' => 'processing']);
        }

        $this->disconnectCamera($live);

        try {
            $this->livekit->deleteRoom($live->room_name);
        } catch (\Throwable $e) {
            // La salle peut déjà avoir disparu (diffuseur déconnecté) : sans conséquence.
        }

        return $live;
    }

    // ─── Accès vidéo ─────────────────────────────────────────────────────────

    /** @return array{url: string, token: string, identity: string} */
    public function hostCredentials(LiveSession $live, User $user): array
    {
        $this->ensureConfigured();
        $this->ensureActiveAccount($user);
        if (!$live->isHost($user)) {
            throw new LiveActionException('Seul le diffuseur peut ouvrir le studio de ce direct.', 403);
        }
        if ($live->status === LiveStatus::Ended) {
            throw new LiveActionException('Ce direct est terminé.', 410);
        }

        $identity = 'host-' . $user->id;

        return [
            'url'      => $this->livekit->url(),
            'identity' => $identity,
            'token'    => $this->livekit->accessToken($identity, $user->display_name, $live->room_name, [
                'canPublish'        => true,
                'canPublishSources' => ['camera', 'microphone'],
                'canSubscribe'      => true,
                'canPublishData'    => false, // commentaires et réactions passent par le serveur
            ], (int) config('livekit.host_token_ttl'), ['role' => 'host']),
        ];
    }

    /** Jeton spectateur : lecture seule, invisible des autres participants. */
    public function viewerCredentials(LiveSession $live, ?User $user): array
    {
        $this->ensureConfigured();
        if (!$live->isVisibleTo($user)) {
            throw new LiveActionException('Ce direct n\'a pas encore commencé.', 404);
        }
        if ($live->status === LiveStatus::Ended) {
            throw new LiveActionException('Ce direct est terminé.', 410);
        }

        $identity = ($user ? 'user-' . $user->id : 'guest') . '-' . Str::lower(Str::random(8));

        return [
            'url'      => $this->livekit->url(),
            'identity' => $identity,
            'token'    => $this->livekit->accessToken($identity, $user?->display_name ?? 'Invité', $live->room_name, [
                'canPublish'     => false,
                'canSubscribe'   => true,
                'canPublishData' => false,
                'hidden'         => true,
            ], (int) config('livekit.viewer_token_ttl'), ['role' => 'viewer']),
        ];
    }

    // ─── Commentaires ────────────────────────────────────────────────────────

    public function postComment(LiveSession $live, User $user, string $body): LiveComment
    {
        $this->ensureCanInteract($live, $user);
        // Le diffuseur et les modérateurs peuvent toujours écrire (annonces,
        // messages à épingler), même si le public ne peut pas commenter.
        if (!$live->comments_enabled && !$live->canBeModeratedBy($user)) {
            throw new LiveActionException('Les commentaires sont désactivés pour ce direct.', 403);
        }

        $body = trim(preg_replace('/\s+/u', ' ', $body));
        if ($body === '') {
            throw new LiveActionException('Le commentaire est vide.', 422);
        }
        if (mb_strlen($body) > config('livekit.comment_max_length')) {
            throw new LiveActionException('Le commentaire ne doit pas dépasser ' . config('livekit.comment_max_length') . ' caractères.', 422);
        }

        $this->throttle("live-comment:{$live->id}:{$user->id}", config('livekit.comments_per_window'), config('livekit.comments_window'),
            'Vous commentez trop vite. Patientez quelques secondes.');

        $comment = DB::transaction(function () use ($live, $user, $body) {
            $comment = LiveComment::create(['live_session_id' => $live->id, 'user_id' => $user->id, 'body' => $body]);
            $live->increment('comment_count');
            return $comment;
        });
        $comment->setRelation('user', $user);

        $this->broadcast($live, ['type' => 'comment', 'comment' => $comment->toPayload()]);

        return $comment;
    }

    public function hideComment(LiveComment $comment, User $actor): void
    {
        $live = $comment->liveSession;
        if (!$live->canBeModeratedBy($actor)) {
            throw new LiveActionException('Vous ne pouvez pas modérer ce direct.', 403);
        }
        if ($comment->is_hidden) {
            return;
        }
        if ($comment->user_id === $live->host_id && !$live->isHost($actor)) {
            throw new LiveActionException('Les messages du diffuseur ne peuvent pas être masqués.', 403);
        }

        $comment->update(['is_hidden' => true, 'hidden_by' => $actor->id]);
        $live->decrement('comment_count');
        $this->broadcast($live, ['type' => 'comment_hidden', 'id' => $comment->id]);

        if ($live->pinned_comment_id === $comment->id) {
            $this->clearPin($live);
        }
    }

    // ─── Commentaire épinglé ─────────────────────────────────────────────────

    /** Épingle un commentaire visible en haut du direct (remplace l'éventuel précédent). */
    public function pinComment(LiveComment $comment, User $actor): LiveComment
    {
        $live = $comment->liveSession;
        if (!$live->canBeModeratedBy($actor)) {
            throw new LiveActionException('Seuls le diffuseur et les modérateurs peuvent épingler un commentaire.', 403);
        }
        if ($live->status === LiveStatus::Ended) {
            throw new LiveActionException('Ce direct est terminé.', 410);
        }
        if ($comment->is_hidden) {
            throw new LiveActionException('Ce commentaire a été masqué.', 422);
        }
        $this->ensureCanReplacePin($live, $actor);

        $live->update(['pinned_comment_id' => $comment->id]);
        $comment->loadMissing('user');
        $this->broadcast($live, ['type' => 'comment_pinned', 'comment' => $comment->toPayload()]);

        return $comment;
    }

    public function unpinComment(LiveSession $live, User $actor): void
    {
        if (!$live->canBeModeratedBy($actor)) {
            throw new LiveActionException('Seuls le diffuseur et les modérateurs peuvent désépingler un commentaire.', 403);
        }
        if ($live->pinned_comment_id) {
            $this->ensureCanReplacePin($live, $actor);
            $this->clearPin($live);
        }
    }

    /**
     * Le message épinglé par le diffuseur (écrit par lui) ne peut être retiré ou remplacé que par lui :
     * un modérateur qui regarde le direct est un spectateur. docs/fonctionnalites/lives.md
     */
    private function ensureCanReplacePin(LiveSession $live, User $actor): void
    {
        if (!$live->pinned_comment_id || $live->isHost($actor)) {
            return;
        }
        $pinnedAuthor = LiveComment::whereKey($live->pinned_comment_id)->value('user_id');
        if ($pinnedAuthor === $live->host_id) {
            throw new LiveActionException('Seul le diffuseur peut retirer ou remplacer son message épinglé.', 403);
        }
    }

    private function clearPin(LiveSession $live): void
    {
        $live->update(['pinned_comment_id' => null]);
        $this->broadcast($live, ['type' => 'comment_unpinned']);
    }

    /** Prive une personne de commentaires et de réactions pour ce direct, et masque ses commentaires. */
    public function ban(LiveSession $live, User $target, User $actor): void
    {
        if (!$live->canBeModeratedBy($actor)) {
            throw new LiveActionException('Vous ne pouvez pas modérer ce direct.', 403);
        }
        if ($live->isHost($target) || $target->canModerate()) {
            throw new LiveActionException('Le diffuseur et les modérateurs ne peuvent pas être exclus.', 422);
        }

        LiveBan::firstOrCreate(['live_session_id' => $live->id, 'user_id' => $target->id], ['banned_by' => $actor->id]);

        $hidden = $live->comments()->where('user_id', $target->id)->where('is_hidden', false)
            ->update(['is_hidden' => true, 'hidden_by' => $actor->id]);
        if ($hidden) {
            $live->decrement('comment_count', $hidden);
        }

        $this->broadcast($live, ['type' => 'user_banned', 'userId' => $target->id]);
        $this->stage->dropUser($live, $target);

        if ($live->pinned_comment_id
            && $live->comments()->whereKey($live->pinned_comment_id)->where('user_id', $target->id)->exists()) {
            $this->clearPin($live);
        }
    }

    // ─── Réactions ───────────────────────────────────────────────────────────

    /** @return array<string, int> compteurs à jour */
    public function react(LiveSession $live, User $user, string $type): array
    {
        if (!in_array($type, LiveSession::REACTIONS, true)) {
            throw new LiveActionException('Réaction inconnue.', 422);
        }
        $this->ensureCanInteract($live, $user);
        $this->throttle("live-reaction:{$live->id}:{$user->id}", config('livekit.reactions_per_window'), config('livekit.reactions_window'),
            'Doucement ! Patientez quelques secondes avant de réagir à nouveau.');

        $live->increment("{$type}_count");
        $counts = $live->fresh()->reactionCounts();
        $this->broadcast($live, ['type' => 'reaction', 'reaction' => $type, 'counts' => $counts]);

        return $counts;
    }

    // ─── Audience ────────────────────────────────────────────────────────────

    /**
     * Personnes qui regardent le direct, visibles de tous : comptes connectés (nom, photo) et nombre de
     * visiteurs non connectés. Le diffuseur n'y figure pas. Liste relue au plus toutes les 10 s.
     * Voir docs/fonctionnalites/lives.md
     *
     * @return array{total: int, people: list<array>, anonymous: int}
     */
    public function viewers(LiveSession $live): array
    {
        if (!$live->isOnAir() || !$this->isConfigured()) {
            return ['total' => 0, 'people' => [], 'anonymous' => 0];
        }

        return Cache::remember("live-viewer-list:{$live->id}", 10, function () use ($live) {
            try {
                $participants = $this->livekit->listParticipants($live->room_name);
            } catch (\Throwable $e) {
                return ['total' => 0, 'people' => [], 'anonymous' => 0];
            }

            $userIds = [];
            $anonymous = 0;
            foreach ($participants as $p) {
                $identity = (string) ($p['identity'] ?? '');
                if (str_starts_with($identity, 'host-')) {
                    continue;
                }
                // Jeton spectateur : « user-{uuid}-{aléa} » (connecté) ou « guest-{aléa} ».
                if (preg_match('/^user-([0-9a-f-]{36})-/i', $identity, $m)) {
                    $userIds[$m[1]] = true; // plusieurs onglets : une seule fois
                } else {
                    $anonymous++;
                }
            }

            $people = User::whereIn('id', array_keys($userIds))
                ->where('status', \App\Enums\UserAccountStatus::Active->value)
                ->orderBy('display_name')->limit(200)
                ->get(['id', 'display_name', 'avatar_url', 'account_type', 'verification_status'])
                ->map(fn (User $u) => [
                    'id'          => $u->id,
                    'displayName' => $u->display_name,
                    'initials'    => $u->initials,
                    'avatarUrl'   => $u->avatar_url,
                    'isVerified'  => $u->isVerified(),
                ])->values()->all();

            return ['total' => count($people) + $anonymous, 'people' => $people, 'anonymous' => $anonymous];
        });
    }

    /** @return array{viewers: int, peakViewers: int, commentCount: int, reactions: array<string,int>, status: string} */
    public function stats(LiveSession $live): array
    {
        $viewers = 0;
        if ($live->isOnAir() && $this->isConfigured()) {
            $viewers = Cache::remember("live-viewers:{$live->id}", 10, function () use ($live) {
                try {
                    $participants = $this->livekit->listParticipants($live->room_name);
                } catch (\Throwable $e) {
                    return 0;
                }
                return collect($participants)->reject(fn ($p) => str_starts_with($p['identity'] ?? '', 'host-'))->count();
            });
            if ($viewers > $live->peak_viewers) {
                $live->update(['peak_viewers' => $viewers]);
            }
        }

        return [
            'status'       => $live->status->value,
            'viewers'      => $viewers,
            'peakViewers'  => $live->peak_viewers,
            'commentCount' => $live->comment_count,
            'reactions'    => $live->reactionCounts(),
            'pinnedComment' => $live->pinnedCommentPayload(),
            'stage'        => $this->stage->summary($live),
        ];
    }

    // ─── Outils ──────────────────────────────────────────────────────────────

    /** Clôture les directs abandonnés (préparation trop longue) ou dont la salle a disparu. */
    public function cleanup(): int
    {
        $closed = 0;

        LiveSession::where('status', LiveStatus::Preparing)
            ->where('created_at', '<', now()->subMinutes(config('livekit.preparing_timeout')))
            ->each(function (LiveSession $live) use (&$closed) {
                $this->end($live, null, 'abandoned');
                $closed++;
            });

        if ($this->isConfigured()) {
            LiveSession::onAir()->each(function (LiveSession $live) use (&$closed) {
                try {
                    $participants = $this->livekit->listParticipants($live->room_name);
                } catch (LiveKitException $e) {
                    // Salle inexistante : le diffuseur est parti depuis plus de empty_timeout.
                    if (in_array($e->getCode(), [404, 400], true)) {
                        $this->end($live, null, 'connection');
                        $closed++;
                    }
                    return;
                }
                $hostPresent = collect($participants)->contains(fn ($p) => str_starts_with($p['identity'] ?? '', 'host-'));
                $this->stage->reconcile($live, collect($participants)->pluck('identity')->filter()->values()->all());
                if (!$hostPresent && $live->updated_at < now()->subSeconds((int) config('livekit.empty_timeout'))) {
                    $this->end($live, null, 'connection');
                    $closed++;
                }
            });

            // Secours si le webhook n'arrive pas : état des enregistrements des directs terminés.
            LiveSession::where('status', LiveStatus::Ended)->whereNotNull('egress_id')
                ->whereIn('recording_status', ['recording', 'processing'])
                ->each(function (LiveSession $live) {
                    try {
                        $info = $this->livekit->getEgress($live->egress_id);
                        if ($info) {
                            $this->handleEgress(new \App\Services\LiveKit\EgressInfo($info));
                        }
                    } catch (\Throwable $e) {
                        // nouvel essai au prochain passage
                    }
                });
        }

        return $closed;
    }

    private function broadcast(LiveSession $live, array $payload): void
    {
        if (!$this->isConfigured()) {
            return;
        }
        try {
            $this->livekit->sendData($live->room_name, $payload);
        } catch (\Throwable $e) {
            // L'action est enregistrée ; les clients la verront au prochain rafraîchissement.
            Log::info('Diffusion temps réel impossible', ['live' => $live->id, 'type' => $payload['type'] ?? null]);
        }
    }

    private function ensureCanInteract(LiveSession $live, User $user): void
    {
        $this->ensureActiveAccount($user);
        if (!$live->isOnAir() && !($live->status === LiveStatus::Preparing && $live->canBeModeratedBy($user))) {
            throw new LiveActionException($live->status === LiveStatus::Ended ? 'Ce direct est terminé.' : 'Ce direct n\'a pas encore commencé.', 409);
        }
        if ($live->isBanned($user)) {
            throw new LiveActionException('Vous ne pouvez plus commenter ni réagir pendant ce direct.', 403);
        }
    }

    private function ensureActiveAccount(User $user): void
    {
        if ($user->status !== UserAccountStatus::Active) {
            throw new LiveActionException('Votre compte ne permet pas cette action.', 403);
        }
    }

    private function ensureConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new LiveActionException('Le direct n\'est pas encore configuré sur ce serveur.', 503);
        }
    }

    private function throttle(string $key, int $max, int $window, string $message): void
    {
        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw new LiveActionException($message, 429);
        }
        RateLimiter::hit($key, $window);
    }
}
