<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\NotificationType;
use App\Enums\UserAccountStatus;
use App\Models\AppNotification;
use App\Models\Event;
use App\Models\PrayerRequest;
use App\Models\PrayerRequestMessage;
use App\Models\PrayerRequestPrayer;
use App\Models\PrayerRequestReport;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Règles des requêtes de prière, communes à l'API mobile et au site.
 * Voir docs/fonctionnalites/requetes-de-priere.md
 */
class PrayerRequests
{
    /** Listes : all (tout ce que je peux voir) · feed (publiques) · following (comptes suivis) · mine · event. */
    public const SCOPES = ['all', 'feed', 'following', 'mine', 'event'];

    public const MESSAGES = [
        'body.required'      => 'Écrivez votre requête de prière.',
        'body.min'           => 'Votre requête est trop courte (10 caractères au moins).',
        'body.max'           => '2 000 caractères au plus.',
        'visibility.in'      => 'Choisissez qui peut voir la requête.',
        'event_id.uuid'      => 'Événement introuvable.',
        'message.required'   => "Écrivez votre message d'encouragement.",
        'message.max'        => '1 000 caractères au plus.',
        'reason.required'    => 'Choisissez la raison du signalement.',
        'reason.in'          => 'Choisissez la raison du signalement.',
    ];

    public static function rules(bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'body'         => [$req, 'string', 'min:10', 'max:2000'],
            'visibility'   => ['sometimes', Rule::in(PrayerRequest::VISIBILITIES)],
            'is_anonymous' => ['sometimes', 'boolean'],
            'event_id'     => ['sometimes', 'nullable', 'uuid'],
            'testimony_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }

    public static function messageRules(): array
    {
        return [
            'message'         => ['required', 'string', 'min:2', 'max:1000'],
            'bible_reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    public static function reportRules(): array
    {
        return [
            'reason'  => ['required', Rule::in(array_keys(PrayerRequest::REPORT_REASONS))],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }

    // ─── Lecture ─────────────────────────────────────────────────────────────

    /** Requête de liste (les plus récentes d'abord). `status` = open | answered. */
    public function list(?User $viewer, string $scope = 'all', ?string $eventId = null, ?string $status = null): Builder
    {
        $q = PrayerRequest::query()->with(['user', 'event'])->withPrayedBy($viewer)->latest();

        match ($scope) {
            'feed'      => $q->where('visibility', PrayerRequest::PUBLIC)->whereNull('hidden_at'),
            // Comptes suivis : publiques et réservées aux abonnés, sans les anonymes (les lister
            // sous « comptes que je suis » réduirait trop l'anonymat).
            'following' => $viewer
                ? $q->whereIn('user_id', \App\Models\Follow::where('follower_id', $viewer->id)->select('following_id'))
                    ->whereIn('visibility', [PrayerRequest::PUBLIC, PrayerRequest::FOLLOWERS])
                    ->where('is_anonymous', false)->whereNull('hidden_at')
                : $q->whereRaw('1 = 0'),
            'mine'      => $viewer ? $q->where('user_id', $viewer->id) : $q->whereRaw('1 = 0'),
            'event'     => $eventId && Str::isUuid($eventId)
                ? $q->where('event_id', $eventId)->visibleTo($viewer)
                : $q->whereRaw('1 = 0'),
            default     => $q->visibleTo($viewer),
        };

        if (in_array($status, [PrayerRequest::OPEN, PrayerRequest::ANSWERED], true)) {
            $q->where('status', $status);
        }

        return $q;
    }

    /** Requête visible de $viewer, sinon 404. */
    public function find(?User $viewer, string $id): PrayerRequest
    {
        $request = Str::isUuid($id) ? PrayerRequest::with(['user', 'event'])->withPrayedBy($viewer)->find($id) : null;
        if (!$request || !$request->isVisibleTo($viewer)) {
            throw new PrayerActionException('Requête de prière introuvable.', 404);
        }

        return $request;
    }

    // ─── Écriture ────────────────────────────────────────────────────────────

    public function create(User $user, array $data): PrayerRequest
    {
        $this->ensureActive($user);

        return PrayerRequest::create([
            'user_id'      => $user->id,
            'body'         => trim($data['body']),
            'visibility'   => $data['visibility'] ?? PrayerRequest::PUBLIC,
            'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
            'event_id'     => $this->eventFor($user, $data['event_id'] ?? null)?->id,
        ])->load(['user', 'event']);
    }

    /** Modification par l'auteur : texte, visibilité, anonymat, événement, témoignage de l'exaucement. */
    public function update(PrayerRequest $request, User $user, array $data): PrayerRequest
    {
        $this->ensureAuthor($request, $user);

        $changes = [];
        if (array_key_exists('body', $data))         $changes['body'] = trim($data['body']);
        if (array_key_exists('visibility', $data))   $changes['visibility'] = $data['visibility'];
        if (array_key_exists('is_anonymous', $data)) $changes['is_anonymous'] = (bool) $data['is_anonymous'];
        if (array_key_exists('event_id', $data))     $changes['event_id'] = $this->eventFor($user, $data['event_id'])?->id;
        if (array_key_exists('testimony_id', $data)) {
            $testimony = $data['testimony_id'] ? Testimony::find($data['testimony_id']) : null;
            if ($data['testimony_id'] && (!$testimony || $testimony->user_id !== $user->id)) {
                throw new PrayerActionException('Ce témoignage ne vous appartient pas.', 422);
            }
            $changes['testimony_id'] = $testimony?->id;
            // Un témoignage rattaché : la requête est exaucée.
            if ($testimony && !$request->isAnswered()) {
                $changes['status'] = PrayerRequest::ANSWERED;
                $changes['answered_at'] = now();
            }
        }

        $request->update($changes);

        return $request->refresh()->load(['user', 'event']);
    }

    public function delete(PrayerRequest $request, User $user): void
    {
        if (!$request->canBeDeletedBy($user)) {
            throw new PrayerActionException('Vous ne pouvez pas supprimer cette requête.', 403);
        }
        $request->delete();
    }

    /** « Je prie » (true) ou retrait (false). Renvoie l'état final. */
    public function setPrayed(PrayerRequest $request, User $user, ?bool $prayed = null): bool
    {
        $this->ensureActive($user);
        if ($request->isHidden()) {
            throw new PrayerActionException('Cette requête a été retirée par la modération.', 409);
        }

        return DB::transaction(function () use ($request, $user, $prayed) {
            $existing = PrayerRequestPrayer::where('prayer_request_id', $request->id)->where('user_id', $user->id)->lockForUpdate()->first();
            $target   = $prayed ?? !$existing; // sans précision : bascule

            if ($target && !$existing) {
                PrayerRequestPrayer::create(['prayer_request_id' => $request->id, 'user_id' => $user->id]);
                $request->increment('prayer_count');
            } elseif (!$target && $existing) {
                $existing->delete();
                PrayerRequest::whereKey($request->id)->where('prayer_count', '>', 0)->decrement('prayer_count');
            }
            $request->setAttribute('has_prayed', $target);

            return $target;
        });
    }

    /** Exaucée (note facultative) ou de nouveau en attente. */
    public function setAnswered(PrayerRequest $request, User $user, bool $answered, ?string $note = null): PrayerRequest
    {
        $this->ensureAuthor($request, $user);
        if (!$answered && $request->testimony_id) {
            throw new PrayerActionException('Un témoignage est rattaché : la requête reste exaucée.', 409);
        }
        $request->update($answered
            ? ['status' => PrayerRequest::ANSWERED, 'answered_at' => $request->answered_at ?? now(), 'answer_note' => filled($note) ? trim($note) : $request->answer_note]
            : ['status' => PrayerRequest::OPEN, 'answered_at' => null, 'answer_note' => null]);

        return $request->refresh()->load(['user', 'event']);
    }

    // ─── Encouragements ──────────────────────────────────────────────────────

    public function addMessage(PrayerRequest $request, User $user, string $body, ?string $reference = null): PrayerRequestMessage
    {
        $this->ensureActive($user);
        if (!$request->acceptsMessagesFrom($user)) {
            throw new PrayerActionException("Les messages ne sont pas ouverts pour cette requête.", 403);
        }

        $message = DB::transaction(function () use ($request, $user, $body, $reference) {
            $message = PrayerRequestMessage::create([
                'prayer_request_id' => $request->id,
                'user_id'           => $user->id,
                'body'              => trim($body),
                'bible_reference'   => filled($reference) ? trim($reference) : null,
            ]);
            $request->increment('message_count');

            return $message;
        });
        $message->setRelation('user', $user);

        if (!$request->isAuthor($user)) {
            $this->notifyAuthor($request, $user);
        }

        return $message;
    }

    public function deleteMessage(PrayerRequest $request, PrayerRequestMessage $message, User $user): void
    {
        if (!$message->canBeDeletedBy($user, $request)) {
            throw new PrayerActionException('Vous ne pouvez pas supprimer ce message.', 403);
        }
        $message->delete();
        PrayerRequest::whereKey($request->id)->where('message_count', '>', 0)->decrement('message_count');
    }

    /** Prévient l'auteur d'un encouragement (notification + push, préférence « prières »). Jamais bloquant. */
    private function notifyAuthor(PrayerRequest $request, User $actor): void
    {
        try {
            AppNotification::create([
                'recipient_id' => $request->user_id,
                'actor_id'     => $actor->id,
                'actor_name'   => $actor->display_name,
                'actor_avatar' => $actor->avatar_url,
                'type'         => NotificationType::PrayerEncouragement->value,
                'message'      => 'vous a laissé un message d\'encouragement sous votre requête de prière.',
                'payload'      => ['prayer_request_id' => $request->id],
                'created_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // ─── Signalement et modération ───────────────────────────────────────────

    /** Un signalement par personne ; au 3e signalement distinct, la requête est retirée en attendant la modération. */
    public function report(PrayerRequest $request, User $user, string $reason, ?string $comment = null): void
    {
        $this->ensureActive($user);
        if ($request->isAuthor($user)) {
            throw new PrayerActionException('Vous ne pouvez pas signaler votre propre requête.', 422);
        }

        DB::transaction(function () use ($request, $user, $reason, $comment) {
            $report = PrayerRequestReport::firstOrNew(['prayer_request_id' => $request->id, 'reporter_id' => $user->id]);
            $isNew  = !$report->exists;
            $report->fill(['reason' => $reason, 'comment' => $comment, 'reviewed_at' => null])->save();

            if ($isNew) {
                $request->increment('report_count');
            }
            $pending = $request->reports()->whereNull('reviewed_at')->count();
            if (!$request->isHidden() && $pending >= PrayerRequest::AUTO_HIDE_REPORTS) {
                $request->update(['hidden_at' => now(), 'hidden_by' => null, 'hidden_reason' => 'Retirée après plusieurs signalements, en attente de la modération.']);
            }
        });
    }

    public function hide(PrayerRequest $request, User $moderator, ?string $reason = null): PrayerRequest
    {
        $this->ensureModerator($moderator);
        $request->update(['hidden_at' => now(), 'hidden_by' => $moderator->id, 'hidden_reason' => filled($reason) ? trim($reason) : 'Retirée par la modération.']);
        $request->reports()->whereNull('reviewed_at')->update(['reviewed_at' => now()]);

        return $request->refresh();
    }

    /** Rétablit la requête et classe les signalements. */
    public function restore(PrayerRequest $request, User $moderator): PrayerRequest
    {
        $this->ensureModerator($moderator);
        $request->update(['hidden_at' => null, 'hidden_by' => null, 'hidden_reason' => null]);
        $request->reports()->whereNull('reviewed_at')->update(['reviewed_at' => now()]);

        return $request->refresh();
    }

    /** File de modération : signalées non traitées et retirées, les plus signalées d'abord. */
    public function moderationQueue(string $filter = 'reported'): Builder
    {
        $q = PrayerRequest::query()->with(['user', 'event', 'reports.reporter'])
            ->withCount(['reports as pending_reports_count' => fn ($r) => $r->whereNull('reviewed_at')]);

        return $filter === 'hidden'
            ? $q->whereNotNull('hidden_at')->latest('hidden_at')
            : $q->whereHas('reports', fn ($r) => $r->whereNull('reviewed_at'))->orderByDesc('pending_reports_count')->latest();
    }

    // ─── Outils ──────────────────────────────────────────────────────────────

    /** Événement de rattachement : publié et visible (ou géré par l'auteur). */
    private function eventFor(User $user, ?string $eventId): ?Event
    {
        if (blank($eventId)) return null;
        $event = Str::isUuid($eventId) ? Event::find($eventId) : null;
        if (!$event || !$event->isVisibleTo($user)) {
            throw new PrayerActionException('Événement introuvable.', 422);
        }
        if ($event->status !== EventStatus::Published && !$event->canBeManagedBy($user)) {
            throw new PrayerActionException("Cet événement n'accepte pas de requêtes de prière.", 422);
        }

        return $event;
    }

    private function ensureAuthor(PrayerRequest $request, User $user): void
    {
        if (!$request->isAuthor($user)) {
            throw new PrayerActionException('Seul l\'auteur peut modifier cette requête.', 403);
        }
    }

    private function ensureModerator(User $user): void
    {
        if (!$user->canModerate()) {
            throw new PrayerActionException('Action réservée à la modération.', 403);
        }
    }

    private function ensureActive(User $user): void
    {
        if ($user->status !== UserAccountStatus::Active) {
            throw new PrayerActionException('Votre compte ne permet pas cette action.', 403);
        }
    }
}
