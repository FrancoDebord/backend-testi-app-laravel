<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\NotificationType;
use App\Enums\UserAccountStatus;
use App\Models\AppNotification;
use App\Models\Event;
use App\Models\EventParticipation;
use App\Models\EventReport;
use App\Models\EventReview;
use App\Models\User;
use App\Support\NotificationText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Événements : signalements et modération (masquer / rétablir / classer), sondage d'après
 * l'événement (« A-t-il vraiment eu lieu ? ») et suivi des événements non confirmés.
 * Partagé par le site et l'API. Voir docs/fonctionnalites/evenements.md
 */
class EventModeration
{
    // ─── Validation ──────────────────────────────────────────────────────────

    public static function reportRules(): array
    {
        return [
            'reason'  => ['required', Rule::in(array_keys(Event::reportReasons()))],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }

    public static function reviewRules(): array
    {
        return [
            'attended' => ['required', Rule::in(array_keys(EventReview::attendedLabels()))],
            'rating'   => ['nullable', 'integer', 'between:1,5', 'required_if:attended,' . EventReview::YES],
            'body'     => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** Messages de validation (signalement et sondage), dans la langue courante. */
    public static function messages(): array
    {
        return [
            'reason.required'      => __('events.moderation_validation.reason_required'),
            'reason.in'            => __('events.moderation_validation.reason_in'),
            'comment.max'          => __('events.moderation_validation.comment_max'),
            'attended.required'    => __('events.moderation_validation.attended_required'),
            'attended.in'          => __('events.moderation_validation.attended_in'),
            'rating.required_if'   => __('events.moderation_validation.rating_required_if'),
            'rating.between'       => __('events.moderation_validation.rating_between'),
            'body.max'             => __('events.moderation_validation.body_max'),
        ];
    }

    // ─── Signalements ────────────────────────────────────────────────────────

    /**
     * Un signalement par personne (409 ensuite). Les gestionnaires ne signalent pas leur
     * événement. Au 3e signalement non traité, l'événement est masqué en attendant la modération.
     */
    public function report(Event $event, User $user, string $reason, ?string $comment = null): EventReport
    {
        $this->ensureActive($user);
        if ($event->canBeManagedBy($user)) {
            throw new EventActionException(__('events.errors.report_own'), 422);
        }
        if ($event->status === EventStatus::Draft) {
            throw new EventActionException(__('events.errors.not_found'), 404);
        }
        if ($event->reports()->where('reporter_id', $user->id)->exists()) {
            throw new EventActionException(__('events.errors.already_reported'), 409);
        }

        return DB::transaction(function () use ($event, $user, $reason, $comment) {
            $report = $event->reports()->create([
                'reporter_id' => $user->id,
                'reason'      => $reason,
                'comment'     => filled($comment) ? trim($comment) : null,
            ]);
            $event->increment('report_count');

            $pending = $event->reports()->whereNull('reviewed_at')->count();
            if (!$event->isHidden() && $pending >= Event::AUTO_HIDE_REPORTS) {
                $event->update([
                    'hidden_at' => now(), 'hidden_by' => null,
                    'hidden_reason' => 'Masqué après plusieurs signalements, en attente de la modération.',
                ]);
            }

            return $report;
        });
    }

    public function hasReported(Event $event, ?User $user): bool
    {
        return $user !== null && $event->reports()->where('reporter_id', $user->id)->exists();
    }

    // ─── Modération ──────────────────────────────────────────────────────────

    /** Masque l'événement (listes et page publique) et classe les signalements. */
    public function hide(Event $event, User $moderator, ?string $reason = null): Event
    {
        $this->ensureModerator($moderator);
        $event->update([
            'hidden_at' => now(), 'hidden_by' => $moderator->id,
            'hidden_reason' => filled($reason) ? trim($reason) : 'Masqué par la modération.',
            'unconfirmed_reviewed_at' => $event->unconfirmed_at ? now() : $event->unconfirmed_reviewed_at,
        ]);
        $event->reports()->whereNull('reviewed_at')->update(['reviewed_at' => now()]);

        return $event->refresh();
    }

    /** Rétablit l'événement masqué, classe les signalements et le suivi « non confirmé ». */
    public function restore(Event $event, User $moderator): Event
    {
        $this->ensureModerator($moderator);
        $event->update([
            'hidden_at' => null, 'hidden_by' => null, 'hidden_reason' => null,
            'unconfirmed_reviewed_at' => $event->unconfirmed_at ? now() : $event->unconfirmed_reviewed_at,
        ]);
        $event->reports()->whereNull('reviewed_at')->update(['reviewed_at' => now()]);

        return $event->refresh();
    }

    /**
     * Rejette les signalements (et le suivi « non confirmé ») : l'événement reste en ligne ;
     * s'il avait été masqué automatiquement, il est rétabli.
     */
    public function dismiss(Event $event, User $moderator): Event
    {
        $this->ensureModerator($moderator);
        $event->reports()->whereNull('reviewed_at')->update(['reviewed_at' => now()]);
        $attrs = ['unconfirmed_reviewed_at' => $event->unconfirmed_at ? now() : $event->unconfirmed_reviewed_at];
        if ($event->isHidden() && $event->hidden_by === null) {
            $attrs += ['hidden_at' => null, 'hidden_reason' => null];
        }
        $event->update($attrs);

        return $event->refresh();
    }

    /**
     * File de modération : `reported` (signalements non traités, les plus signalés d'abord),
     * `unconfirmed` (majorité de « Non » au sondage, pas encore examinés), `hidden` (masqués).
     */
    public function queue(string $filter = 'reported'): Builder
    {
        $q = Event::query()->with(['organizer', 'images', 'reports' => fn ($r) => $r->with('reporter')->latest()])
            ->withCount(['reports as pending_reports_count' => fn ($r) => $r->whereNull('reviewed_at')]);

        return match ($filter) {
            'hidden'      => $q->whereNotNull('hidden_at')->latest('hidden_at'),
            'unconfirmed' => $q->whereNotNull('unconfirmed_at')->whereNull('unconfirmed_reviewed_at')->latest('unconfirmed_at'),
            default       => $q->whereHas('reports', fn ($r) => $r->whereNull('reviewed_at'))
                ->orderByDesc('pending_reports_count')->latest(),
        };
    }

    /** Nombre d'éléments à traiter (signalés + non confirmés), pour le menu de modération. */
    public static function pendingCount(): int
    {
        return Event::query()->where(fn ($w) => $w
            ->whereHas('reports', fn ($r) => $r->whereNull('reviewed_at'))
            ->orWhere(fn ($u) => $u->whereNotNull('unconfirmed_at')->whereNull('unconfirmed_reviewed_at')))->count();
    }

    // ─── Sondage d'après l'événement ─────────────────────────────────────────

    /** Réponse de la personne (une par événement), ou null. */
    public function reviewOf(Event $event, ?User $user): ?EventReview
    {
        return $user ? $event->reviews()->where('user_id', $user->id)->first() : null;
    }

    /** Peut répondre : connecté, actif, pas gestionnaire, sondage ouvert, réponse encore modifiable. */
    public function canReview(Event $event, ?User $user, ?EventReview $mine = null): bool
    {
        return $user !== null && $user->status === UserAccountStatus::Active
            && $event->acceptsReviews() && !$event->canBeManagedBy($user)
            && ($mine === null || $mine->isEditable());
    }

    /**
     * Enregistre (ou modifie, pendant 30 jours) la réponse au sondage, puis recalcule les
     * résultats et le suivi « non confirmé ».
     */
    public function review(Event $event, User $user, array $data): EventReview
    {
        $this->ensureActive($user);
        if ($event->canBeManagedBy($user)) {
            throw new EventActionException(__('events.errors.review_own'), 403);
        }
        if (!$event->acceptsReviews()) {
            throw new EventActionException($event->phase() === 'past'
                ? __('events.errors.survey_not_open')
                : __('events.errors.survey_opens_at_end'), 422);
        }
        $review = $event->reviews()->firstOrNew(['user_id' => $user->id]);
        if ($review->exists && !$review->isEditable()) {
            throw new EventActionException(__('events.errors.review_locked'), 422);
        }

        $attended = $data['attended'];
        $review->fill([
            'attended' => $attended,
            'rating'   => $attended === EventReview::YES ? (int) $data['rating'] : null,
            'body'     => filled($data['body'] ?? null) ? trim($data['body']) : null,
        ])->save();

        $this->recountSurvey($event);

        return $review->load('user');
    }

    /** Résultats agrégés et suivi des faux événements (majorité de « Non » après 3 réponses). */
    public function recountSurvey(Event $event): void
    {
        $row = $event->reviews()->toBase()->selectRaw(
            "SUM(CASE WHEN attended = 'yes' THEN 1 ELSE 0 END) as yes,
             SUM(CASE WHEN attended = 'no' THEN 1 ELSE 0 END) as no,
             SUM(CASE WHEN attended = 'absent' THEN 1 ELSE 0 END) as absent,
             COUNT(rating) as rating_count,
             COALESCE(SUM(rating), 0) as rating_sum"
        )->first();

        $yes = (int) ($row->yes ?? 0);
        $no  = (int) ($row->no ?? 0);
        $unconfirmed = $yes + $no >= Event::SURVEY_MIN_ANSWERS && $no > $yes;

        $attrs = [
            'survey_yes_count'    => $yes,
            'survey_no_count'     => $no,
            'survey_absent_count' => (int) ($row->absent ?? 0),
            'rating_count'        => (int) ($row->rating_count ?? 0),
            'rating_sum'          => (int) ($row->rating_sum ?? 0),
        ];
        if ($unconfirmed && !$event->unconfirmed_at) {
            // Non confirmé : badge public et file de modération.
            $attrs += ['unconfirmed_at' => now(), 'unconfirmed_reviewed_at' => null];
        } elseif (!$unconfirmed && $event->unconfirmed_at) {
            $attrs += ['unconfirmed_at' => null];
        }
        $event->update($attrs);
    }

    /**
     * Invitations au sondage (commande planifiée `events:send-surveys`, toutes les heures) : une
     * seule fois par événement (`survey_sent_at`), aux personnes qui ont répondu « Je participe »
     * et à celles qui ont commenté, sauf les gestionnaires. Événements terminés depuis moins de 7 jours.
     */
    public function sendSurveys(): int
    {
        $sent = 0;
        Event::query()->where('status', EventStatus::Published->value)
            ->whereNull('hidden_at')->whereNull('survey_sent_at')
            ->where('ends_at', '<', now())
            ->where('ends_at', '>=', now()->subDays(Event::SURVEY_INVITE_DAYS))
            ->with(['organizer', 'managers'])
            ->each(function (Event $event) use (&$sent) {
                // Marqué d'abord : deux passages simultanés n'envoient pas deux fois.
                $claimed = Event::whereKey($event->id)->whereNull('survey_sent_at')->update(['survey_sent_at' => now()]);
                if (!$claimed) return;

                $recipients = $event->participations()->where('status', EventParticipation::GOING)->pluck('user_id')
                    ->merge($event->comments()->distinct()->pluck('user_id'))
                    ->unique()->values()->all();
                $exclude = [
                    $event->organizer_id,
                    ...$event->managers->pluck('id'),
                    ...($event->organizer?->organizationManagers()->pluck('users.id') ?? []),
                ];
                $sent += self::notify($event, $recipients, NotificationType::EventSurvey,
                    'events.notify_survey', ['title' => $event->title], $exclude);
            });

        return $sent;
    }

    // ─── Notifications ───────────────────────────────────────────────────────

    /**
     * Notification dans l'application (et push via AppNotificationObserver) à chaque destinataire.
     * Ne bloque jamais l'action qui la déclenche. Texte : clé de traduction et paramètres
     * (NotificationText), rendu dans la langue du lecteur.
     *
     * @param  list<string>  $recipientIds
     * @param  array<string, scalar|null>  $params
     * @param  list<string>  $exclude
     */
    public static function notify(Event $event, array $recipientIds, NotificationType $type, string $key, array $params = [], array $exclude = []): int
    {
        $count = 0;
        try {
            $organizer = $event->organizer;
            foreach (array_unique(array_diff($recipientIds, $exclude)) as $recipientId) {
                AppNotification::create([
                    'recipient_id' => $recipientId,
                    'actor_id'     => $event->organizer_id,
                    'actor_name'   => null,
                    'actor_avatar' => $organizer?->avatar_url,
                    'type'         => $type->value,
                    ...NotificationText::fields($key, $params, ['event_id' => $event->id]),
                    'created_at'   => now(),
                ]);
                $count++;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $count;
    }

    // ─── Outils ──────────────────────────────────────────────────────────────

    private function ensureModerator(User $user): void
    {
        if (!$user->canModerate()) {
            throw new EventActionException(__('events.errors.moderators_only'), 403);
        }
    }

    private function ensureActive(User $user): void
    {
        if ($user->status !== UserAccountStatus::Active) {
            throw new EventActionException(__('events.errors.account_inactive'), 403);
        }
    }
}
