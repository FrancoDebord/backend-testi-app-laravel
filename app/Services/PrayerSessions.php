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
use App\Support\NotificationText;
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

    /** Messages de validation dans la langue courante (lang/{fr,en}/prayer.php › validation). */
    public static function messages(): array
    {
        return [
            'title.required'       => __('prayer.validation.title_required'),
            'title.max'            => __('prayer.validation.title_max'),
            'starts_at.required'   => __('prayer.validation.starts_at_required'),
            'starts_at.date'       => __('prayer.validation.starts_at_required'),
            'starts_at.after'      => __('prayer.validation.starts_at_future'),
            'duration_minutes.min' => __('prayer.validation.duration_min'),
            'duration_minutes.max' => __('prayer.validation.duration_max'),
            'topics.max'           => __('prayer.validation.topics_max'),
            'topics.*.max'         => __('prayer.validation.topic_max'),
            'visibility.in'        => __('prayer.validation.visibility_session'),
        ];
    }

    public function __construct(private readonly LiveService $lives) {}

    public static function rules(bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'title'            => [$req, 'string', 'max:150'],
            'description'      => ['sometimes', 'nullable', 'string', 'max:3000'],
            'topics'           => ['sometimes', 'nullable', 'array', 'max:' . PrayerSession::MAX_TOPICS],
            'topics.*'         => ['nullable', 'string', 'max:150'],
            // Modification : l'horaire déjà fixé peut être renvoyé tel quel (session commencée) ;
            // un nouvel horaire doit être dans le futur (contrôle dans update()).
            'starts_at'        => $partial ? ['sometimes', 'date'] : ['required', 'date', 'after:now'],
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
            throw new PrayerActionException(__('prayer.errors.session_not_found'), 404);
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
            throw new PrayerActionException(__('prayer.errors.account_inactive'), 403);
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
            throw new PrayerActionException(__('prayer.errors.host_only_edit'), 403);
        }

        $changes = collect($data)->only(['title', 'description', 'visibility', 'duration_minutes'])->all();
        if (array_key_exists('topics', $data))   $changes['topics'] = $this->cleanTopics($data['topics'] ?? []);
        if (array_key_exists('event_id', $data)) $changes['event_id'] = $this->eventFor($user, $data['event_id'])?->id;
        if (array_key_exists('starts_at', $data)) {
            $startsAt = Carbon::parse($data['starts_at']);
            // Même horaire (à la seconde près, l'application renvoie tout le formulaire) : rien ne change.
            if ($startsAt->getTimestamp() !== $session->starts_at->getTimestamp()) {
                if ($startsAt->isPast()) {
                    throw new PrayerActionException(__('prayer.validation.starts_at_future'), 422);
                }
                $changes['starts_at'] = $startsAt;
                $changes['reminder_sent_at'] = null; // nouvel horaire : nouveau rappel
            }
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
            throw new PrayerActionException(__('prayer.errors.cannot_cancel_session'), 403);
        }
        $this->closeRoom($session, $user);
        $session->update(['status' => PrayerSession::CANCELLED]);

        return $session->refresh();
    }

    public function delete(PrayerSession $session, User $user): void
    {
        if (!$session->canBeDeletedBy($user)) {
            throw new PrayerActionException(__('prayer.errors.cannot_delete_session'), 403);
        }
        $this->closeRoom($session, $user);
        $session->delete();
    }

    // ─── Inscriptions ────────────────────────────────────────────────────────

    /** « Je serai là ». */
    public function register(PrayerSession $session, User $user): void
    {
        if ($user->status !== UserAccountStatus::Active) {
            throw new PrayerActionException(__('prayer.errors.account_inactive'), 403);
        }
        if (in_array($session->phase(), ['ended', 'cancelled'], true)) {
            throw new PrayerActionException($session->phase() === 'ended' ? __('prayer.errors.session_ended') : __('prayer.errors.session_cancelled'), 409);
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
            throw new PrayerActionException(__('prayer.errors.host_only_open'), 403);
        }
        if ($session->status === PrayerSession::CANCELLED) {
            throw new PrayerActionException(__('prayer.errors.session_cancelled'), 409);
        }
        if ($live = $session->lives()->active()->latest()->first()) {
            return $live;
        }
        if (!$session->isInOpeningWindow()) {
            throw new PrayerActionException(now()->lessThan($session->starts_at)
                ? __('prayer.errors.room_too_early', ['minutes' => PrayerSession::OPEN_BEFORE_MINUTES])
                : __('prayer.errors.room_too_late'), 409);
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
                NotificationText::fields('prayer.notify.session_started', ['title' => $session->title]), $live);
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
                    NotificationText::fields('prayer.notify.session_reminder', ['title' => $session->title]), null, includeHost: true);
            });

        return $sent;
    }

    /**
     * Notification + push à chaque inscrit (et à l'hôte pour le rappel). Ne bloque jamais l'action.
     * $text : NotificationText::fields() (texte traduit dans la langue du destinataire).
     *
     * @param array{message: string, payload: array<string, mixed>} $text
     */
    private function notifyParticipants(PrayerSession $session, NotificationType $type, array $text, ?LiveSession $live = null, bool $includeHost = false): int
    {
        $count = 0;
        try {
            $host = $session->host;
            $send = function (string $recipientId) use ($session, $type, $text, $live, $host, &$count) {
                AppNotification::create([
                    'recipient_id' => $recipientId,
                    'actor_id'     => $host?->id,
                    'actor_name'   => $type === NotificationType::PrayerSessionStarted ? $host?->display_name : null,
                    'actor_avatar' => $host?->avatar_url,
                    'type'         => $type->value,
                    'message'      => $text['message'],
                    'payload'      => array_filter(['prayer_session_id' => $session->id, 'live_id' => $live?->id]) + $text['payload'],
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
            throw new PrayerActionException(__('prayer.errors.event_not_found'), 422);
        }
        if (!$event->canBeManagedBy($user)) {
            throw new PrayerActionException(__('prayer.errors.event_managers_only'), 403);
        }
        if ($event->status !== EventStatus::Published) {
            throw new PrayerActionException(__('prayer.errors.event_not_published'), 422);
        }

        return $event;
    }
}
