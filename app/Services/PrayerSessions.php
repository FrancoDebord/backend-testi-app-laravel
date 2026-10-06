<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\NotificationType;
use App\Enums\UserAccountStatus;
use App\Models\AppNotification;
use App\Models\Event;
use App\Models\LiveSession;
use App\Models\PrayerSession;
use App\Models\PrayerSessionParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Règles des sessions de prière, communes à l'API mobile et au site. La salle est un direct
 * (LiveService) rattaché par live_sessions.prayer_session_id. Voir docs/fonctionnalites/sessions-de-priere.md
 */
class PrayerSessions
{
    /** upcoming (à venir et en cours) · past · mine (que j'anime) · joined (où je suis inscrit) · event. */
    public const SCOPES = ['upcoming', 'past', 'mine', 'joined', 'event'];

    public const MIN_DURATION = 15;
    public const MAX_DURATION = 480;

    public const MESSAGES = [
        'title.required'            => 'Donnez un titre à la session.',
        'title.max'                 => '150 caractères au plus.',
        'starts_at.required'        => "Indiquez la date et l'heure.",
        'starts_at.date'            => "Indiquez la date et l'heure.",
        'starts_at.after'           => "La session doit être programmée dans le futur.",
        'duration_minutes.min'      => 'Durée : 15 minutes au moins.',
        'duration_minutes.max'      => 'Durée : 8 heures au plus.',
        'topics.max'                => '10 sujets de prière au plus.',
        'topics.*.max'              => 'Chaque sujet : 150 caractères au plus.',
        'visibility.in'             => 'Choisissez qui peut voir la session.',
    ];

    public function __construct(private readonly LiveService $lives) {}

    public static function rules(bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'title'            => [$req, 'string', 'max:150'],
            'description'      => ['sometimes', 'nullable', 'string', 'max:3000'],
            'topics'           => ['sometimes', 'nullable', 'array', 'max:' . PrayerSession::MAX_TOPICS],
            'topics.*'         => ['nullable', 'string', 'max:150'],
            'starts_at'        => [$req, 'date', 'after:now'],
            'duration_minutes' => ['sometimes', 'integer', 'min:' . self::MIN_DURATION, 'max:' . self::MAX_DURATION],
            'visibility'       => ['sometimes', Rule::in(PrayerSession::VISIBILITIES)],
            'event_id'         => ['sometimes', 'nullable', 'uuid'],
        ];
    }

    // ─── Lecture ─────────────────────────────────────────────────────────────

    public function list(?User $viewer, string $scope = 'upcoming', ?string $eventId = null): Builder
    {
        $q = PrayerSession::query()->with(['host', 'event', 'lives' => fn ($l) => $l->active()->latest()])
            ->withRegistrationOf($viewer)->visibleTo($viewer);

        match ($scope) {
            'past'   => $q->where(fn ($w) => $w->where('ends_at', '<', now())->orWhere('status', PrayerSession::CANCELLED))
                          ->orderByDesc('starts_at'),
            'mine'   => $viewer ? $q->where('host_id', $viewer->id)->orderByDesc('starts_at') : $q->whereRaw('1 = 0'),
            'joined' => $viewer
                ? $q->whereHas('participants', fn ($p) => $p->where('user_id', $viewer->id))->orderBy('starts_at')
                : $q->whereRaw('1 = 0'),
            'event'  => $eventId && Str::isUuid($eventId) ? $q->where('event_id', $eventId)->orderBy('starts_at') : $q->whereRaw('1 = 0'),
            // À venir et en cours (y compris une salle restée ouverte après l'heure prévue).
            default  => $q->where('status', PrayerSession::SCHEDULED)
                          ->where(fn ($w) => $w->where('ends_at', '>=', now())->orWhereHas('lives', fn ($l) => $l->active()))
                          ->orderBy('starts_at'),
        };

        return $q;
    }

    public function find(?User $viewer, string $id): PrayerSession
    {
        $session = Str::isUuid($id)
            ? PrayerSession::with(['host', 'event', 'lives' => fn ($l) => $l->active()->latest()])->withRegistrationOf($viewer)->find($id)
            : null;
        if (!$session || !$session->isVisibleTo($viewer)) {
            throw new PrayerActionException('Session de prière introuvable.', 404);
        }

        return $session;
    }

    /** Programmer une session : tout compte connecté et actif. */
    public function canCreate(?User $user): bool
    {
        return $user !== null && $user->status === UserAccountStatus::Active;
    }

    // ─── Écriture ────────────────────────────────────────────────────────────

    public function create(User $host, array $data): PrayerSession
    {
        if (!$this->canCreate($host)) {
            throw new PrayerActionException('Votre compte ne permet pas cette action.', 403);
        }
        $startsAt = Carbon::parse($data['starts_at']);
        $duration = (int) ($data['duration_minutes'] ?? 60);

        return PrayerSession::create([
            'host_id'          => $host->id,
            'event_id'         => $this->eventFor($host, $data['event_id'] ?? null)?->id,
            'title'            => trim($data['title']),
            'description'      => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'topics'           => $this->cleanTopics($data['topics'] ?? []),
            'starts_at'        => $startsAt,
            'duration_minutes' => $duration,
            'ends_at'          => $startsAt->copy()->addMinutes($duration),
            'visibility'       => $data['visibility'] ?? PrayerSession::PUBLIC,
        ])->load(['host', 'event']);
    }

    public function update(PrayerSession $session, User $user, array $data): PrayerSession
    {
        if (!$session->canBeEditedBy($user)) {
            throw new PrayerActionException('Seul l\'hôte peut modifier cette session, tant qu\'elle n\'est pas terminée.', 403);
        }

        $changes = collect($data)->only(['title', 'description', 'visibility', 'duration_minutes'])->all();
        if (array_key_exists('topics', $data))   $changes['topics'] = $this->cleanTopics($data['topics'] ?? []);
        if (array_key_exists('event_id', $data)) $changes['event_id'] = $this->eventFor($user, $data['event_id'])?->id;
        if (array_key_exists('starts_at', $data)) {
            $changes['starts_at'] = Carbon::parse($data['starts_at']);
            $changes['reminder_sent_at'] = null; // nouvel horaire : nouveau rappel
        }
        $session->fill($changes);
        $session->ends_at = $session->starts_at->copy()->addMinutes($session->duration_minutes);
        $session->save();

        return $session->refresh()->load(['host', 'event']);
    }

    /** Annuler (reste visible, « Annulée ») : l'hôte ou la modération ; ferme la salle ouverte. */
    public function cancel(PrayerSession $session, User $user): PrayerSession
    {
        if (!$session->canBeDeletedBy($user)) {
            throw new PrayerActionException('Vous ne pouvez pas annuler cette session.', 403);
        }
        $this->closeRoom($session, $user);
        $session->update(['status' => PrayerSession::CANCELLED]);

        return $session->refresh();
    }

    public function delete(PrayerSession $session, User $user): void
    {
        if (!$session->canBeDeletedBy($user)) {
            throw new PrayerActionException('Vous ne pouvez pas supprimer cette session.', 403);
        }
        $this->closeRoom($session, $user);
        $session->delete();
    }

    // ─── Inscriptions ────────────────────────────────────────────────────────

    /** « Je serai là ». */
    public function register(PrayerSession $session, User $user): void
    {
        if ($user->status !== UserAccountStatus::Active) {
            throw new PrayerActionException('Votre compte ne permet pas cette action.', 403);
        }
        if (in_array($session->phase(), ['ended', 'cancelled'], true)) {
            throw new PrayerActionException('Cette session est ' . ($session->phase() === 'ended' ? 'terminée.' : 'annulée.'), 409);
        }
        DB::transaction(function () use ($session, $user) {
            $row = PrayerSessionParticipant::firstOrCreate(['prayer_session_id' => $session->id, 'user_id' => $user->id]);
            if ($row->wasRecentlyCreated) {
                $session->increment('participant_count');
            }
        });
        $session->setAttribute('is_registered', true);
    }

    public function unregister(PrayerSession $session, User $user): void
    {
        DB::transaction(function () use ($session, $user) {
            $deleted = PrayerSessionParticipant::where('prayer_session_id', $session->id)->where('user_id', $user->id)->delete();
            if ($deleted) {
                PrayerSession::whereKey($session->id)->where('participant_count', '>', 0)->decrement('participant_count');
            }
        });
        $session->setAttribute('is_registered', false);
    }

    // ─── Salle (direct LiveKit) ──────────────────────────────────────────────

    /**
     * L'hôte ouvre la salle : crée le direct « en préparation » (ou reprend celui déjà ouvert).
     * Pas d'enregistrement (la prière n'est pas transformée en témoignage vidéo).
     * Les inscrits sont prévenus à la première ouverture.
     */
    public function start(PrayerSession $session, User $user): LiveSession
    {
        if (!$session->isHost($user)) {
            throw new PrayerActionException("Seul l'hôte peut ouvrir la salle de cette session.", 403);
        }
        if ($session->status === PrayerSession::CANCELLED) {
            throw new PrayerActionException('Cette session est annulée.', 409);
        }
        if ($live = $session->lives()->active()->latest()->first()) {
            return $live;
        }
        if (!$session->isInOpeningWindow()) {
            throw new PrayerActionException(now()->lessThan($session->starts_at)
                ? 'La salle pourra être ouverte ' . PrayerSession::OPEN_BEFORE_MINUTES . ' minutes avant l\'heure prévue.'
                : "L'horaire de cette session est passé.", 409);
        }

        try {
            $live = $this->lives->start($user, [
                'title'             => $session->title,
                'description'       => $session->description,
                'comments_enabled'  => true,
                'record'            => false,
                'prayer_session_id' => $session->id,
            ]);
        } catch (LiveActionException $e) {
            throw new PrayerActionException($e->getMessage(), $e->status());
        }

        $first = $session->opened_at === null;
        $session->update(['opened_at' => $session->opened_at ?? now()]);
        if ($first) {
            $this->notifyParticipants($session, NotificationType::PrayerSessionStarted,
                "a ouvert la salle de prière « {$session->title} ». Rejoignez-la !", $live);
        }

        return $live;
    }

    /** Ferme la salle active (fin de session, annulation, suppression). */
    private function closeRoom(PrayerSession $session, User $actor): void
    {
        $live = $session->lives()->active()->latest()->first();
        if ($live) {
            try {
                $this->lives->end($live, $actor, $live->isHost($actor) ? 'host' : 'moderator');
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    // ─── Rappels ─────────────────────────────────────────────────────────────

    /** Rappel aux inscrits 15 minutes avant (commande prayer-sessions:remind, toutes les 5 minutes). */
    public function sendReminders(): int
    {
        $sent = 0;
        PrayerSession::where('status', PrayerSession::SCHEDULED)->whereNull('reminder_sent_at')
            ->whereBetween('starts_at', [now(), now()->addMinutes(PrayerSession::REMINDER_MINUTES)])
            ->with('host')
            ->each(function (PrayerSession $session) use (&$sent) {
                // Marqué d'abord : deux passages simultanés n'envoient pas deux fois.
                $claimed = PrayerSession::whereKey($session->id)->whereNull('reminder_sent_at')->update(['reminder_sent_at' => now()]);
                if (!$claimed) return;
                $sent += $this->notifyParticipants($session, NotificationType::PrayerSessionReminder,
                    "La session de prière « {$session->title} » commence dans quelques minutes.", null, includeHost: true);
            });

        return $sent;
    }

    /** Notification + push à chaque inscrit (et à l'hôte pour le rappel). Ne bloque jamais l'action. */
    private function notifyParticipants(PrayerSession $session, NotificationType $type, string $message, ?LiveSession $live = null, bool $includeHost = false): int
    {
        $count = 0;
        try {
            $host = $session->host;
            $send = function (string $recipientId) use ($session, $type, $message, $live, $host, &$count) {
                AppNotification::create([
                    'recipient_id' => $recipientId,
                    'actor_id'     => $host?->id,
                    'actor_name'   => $type === NotificationType::PrayerSessionStarted ? $host?->display_name : null,
                    'actor_avatar' => $host?->avatar_url,
                    'type'         => $type->value,
                    'message'      => $message,
                    'payload'      => array_filter(['prayer_session_id' => $session->id, 'live_id' => $live?->id]),
                    'created_at'   => now(),
                ]);
                $count++;
            };
            if ($includeHost) {
                $send($session->host_id);
            }
            $session->participants()->where('user_id', '!=', $session->host_id)->select(['id', 'user_id'])
                ->chunkById(100, function ($rows) use ($send) {
                    foreach ($rows as $row) {
                        $send($row->user_id);
                    }
                });
        } catch (\Throwable $e) {
            report($e);
        }

        return $count;
    }

    // ─── Outils ──────────────────────────────────────────────────────────────

    /** @return list<string> */
    private function cleanTopics(?array $topics): array
    {
        return collect($topics ?? [])->map(fn ($t) => trim((string) $t))->filter()->unique()
            ->take(PrayerSession::MAX_TOPICS)->values()->all();
    }

    /** Session rattachée à un événement : réservée à ses gestionnaires, événement publié. */
    private function eventFor(User $user, ?string $eventId): ?Event
    {
        if (blank($eventId)) return null;
        $event = Str::isUuid($eventId) ? Event::find($eventId) : null;
        if (!$event || !$event->isVisibleTo($user)) {
            throw new PrayerActionException('Événement introuvable.', 422);
        }
        if (!$event->canBeManagedBy($user)) {
            throw new PrayerActionException("Seuls les gestionnaires de l'événement peuvent y rattacher une session de prière.", 403);
        }
        if ($event->status !== EventStatus::Published) {
            throw new PrayerActionException("L'événement doit être publié.", 422);
        }

        return $event;
    }
}
