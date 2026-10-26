<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\NotificationType;
use App\Enums\ReactionType;
use App\Enums\UserAccountStatus;
use App\Models\AppNotification;
use App\Models\ContentReaction;
use App\Models\ContentRepost;
use App\Models\Event;
use App\Models\PrayerRequest;
use App\Models\PrayerSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Réactions et partages internes des événements, requêtes et sessions de prière, communs à l'API
 * et au site (les témoignages ont leurs propres tables : TestimonyReactions, Reposts).
 *
 * - Une réaction « j'aime » (like, love, fire → like_count) et une « je prie » (pray, amen, worship
 *   → prayer_count) au plus par personne : réagir de nouveau ne compte jamais deux fois, changer de
 *   réaction remplace l'ancienne. Requêtes de prière : « Je prie » reste le bouton dédié
 *   (PrayerRequests::setPrayed), seules les réactions « j'aime » passent ici.
 * - Un partage par personne (un nouvel envoi ne change que le commentaire) ; seulement une
 *   publication publique, jamais la sienne ; l'auteur est prévenu ; le partage apparaît dans
 *   « Mon fil » des abonnés du partageur (FeedInserts).
 *
 * Voir docs/fonctionnalites/reactions.md et partage-interne.md
 */
class ContentInteractions
{
    /** Types de publication → modèle, colonne de l'auteur (textes des notifications : lang/<langue>/reactions.php, notify.*). */
    public const KINDS = [
        'event'          => [Event::class, 'organizer_id'],
        'prayer_request' => [PrayerRequest::class, 'user_id'],
        'prayer_session' => [PrayerSession::class, 'host_id'],
    ];

    /** Fenêtre des partages repris dans « Mon fil » des abonnés. */
    public const FEED_DAYS = 30;

    public static function kindOf(Model $subject): string
    {
        foreach (self::KINDS as $kind => [$class]) {
            if ($subject instanceof $class) return $kind;
        }
        throw new \InvalidArgumentException('Publication non prise en charge.');
    }

    public static function ownerId(Model $subject): ?string
    {
        return $subject->{self::KINDS[self::kindOf($subject)][1]};
    }

    // ─── Lecture ─────────────────────────────────────────────────────────────

    /** Publication visible de $viewer, sinon ContentActionException 404. */
    public function find(string $kind, string $id, ?User $viewer): Model
    {
        $class = self::KINDS[$kind][0] ?? null;
        $subject = $class && Str::isUuid($id) ? $class::find($id) : null;
        if (!$subject || !$subject->isVisibleTo($viewer)) {
            throw new ContentActionException(__('reactions.content.not_found'), 404);
        }

        return $subject;
    }

    /** Peut être partagée sur My Testimony : publique, ni retirée ni annulée (ni brouillon). */
    public function isShareable(Model $subject): bool
    {
        return match (self::kindOf($subject)) {
            'event'          => in_array($subject->status, [EventStatus::Published, EventStatus::Postponed], true) && !$subject->isHidden(),
            'prayer_request' => $subject->visibility === PrayerRequest::PUBLIC && !$subject->isHidden(),
            'prayer_session' => $subject->visibility === PrayerSession::PUBLIC && $subject->status !== PrayerSession::CANCELLED,
        };
    }

    /**
     * État de la personne et compteurs (champs ajoutés aux ressources EventResource,
     * PrayerRequestResource, PrayerSessionResource).
     */
    public function payload(Model $subject, ?User $viewer, \Illuminate\Http\Request $request): array
    {
        $kind = self::kindOf($subject);
        $mine = $viewer
            ? ContentReaction::where('user_id', $viewer->id)->where('subject_type', $kind)->where('subject_id', $subject->id)->get()
            : collect();
        $appreciation = $mine->first(fn (ContentReaction $r) => $r->type->group() === 'appreciation')?->type;
        $prayer = $mine->first(fn (ContentReaction $r) => $r->type->group() === 'prayer');
        $repost = $this->highlightFor($subject, $viewer);

        return [
            'reactions' => [
                'likesCount'       => (int) ($subject->like_count ?? 0),
                // Requête de prière : le « Je prie » dédié (prayerCount, hasPrayed) reste la référence.
                'prayersCount'     => (int) ($subject->prayer_count ?? 0),
                'repostsCount'     => (int) ($subject->repost_count ?? 0),
                'myReaction'       => $appreciation?->value,
                'myPrayerReaction' => $prayer?->type->value,
                // Nombre de fois que la personne a prié (docs/fonctionnalites/prier-plusieurs-fois.md).
                'myPrayerTimes'    => (int) ($prayer?->prayer_times ?? 0),
                'isRepostedByMe'   => $viewer !== null && (int) ($subject->repost_count ?? 0) > 0
                    && ContentRepost::where('user_id', $viewer->id)->where('subject_type', $kind)->where('subject_id', $subject->id)->exists(),
                'canRepost'        => $viewer !== null && $viewer->id !== self::ownerId($subject) && $this->isShareable($subject),
            ],
            // « X a partagé » : un compte suivi d'abord (comme les témoignages).
            'repost' => $repost ? [
                'id'        => $repost->id,
                'comment'   => $repost->comment,
                'createdAt' => $repost->created_at?->toIso8601String(),
                'user'      => [
                    'id'          => $repost->user->id,
                    'displayName' => $repost->user->display_name,
                    'initials'    => $repost->user->initials,
                    'avatarUrl'   => $repost->user->avatar_url,
                    'isVerified'  => (bool) $repost->user->isVerified(),
                    ...\App\Models\User::approvalFieldsFor($repost->user), // badge-approuve.md
                ],
            ] : null,
        ];
    }

    /** Partage à montrer avec la publication : compte suivi d'abord, puis le sien, puis le plus récent. */
    public function highlightFor(Model $subject, ?User $viewer): ?ContentRepost
    {
        if ((int) ($subject->repost_count ?? 0) <= 0) return null;

        $query = ContentRepost::query()
            ->where('subject_type', self::kindOf($subject))->where('subject_id', $subject->id)
            ->whereHas('user', fn ($u) => $u->where('status', UserAccountStatus::Active->value))
            ->with('user');
        if ($viewer) {
            $query->orderByRaw(
                'CASE WHEN user_id IN (SELECT following_id FROM follows WHERE follower_id = ?) THEN 0 WHEN user_id = ? THEN 1 ELSE 2 END',
                [$viewer->id, $viewer->id],
            );
        }

        return $query->latest()->first();
    }

    /** Identifiants des publications de ce type partagées récemment par des comptes suivis (« Mon fil »). */
    public function repostedByFollowed(string $kind, array $followedIds): Collection
    {
        if (!$followedIds) return collect();

        return ContentRepost::query()
            ->where('subject_type', $kind)->whereIn('user_id', $followedIds)
            ->where('created_at', '>=', now()->subDays(self::FEED_DAYS))
            ->latest()->limit(50)->pluck('subject_id')->unique()->values();
    }

    // ─── Réactions ───────────────────────────────────────────────────────────

    /**
     * Pose la réaction (idempotent). Retourne vrai si c'est une nouvelle réaction du groupe.
     * `$again` (prier encore) : la personne prie déjà → une prière de plus (prayer_times), après un nouveau temps
     * de prière ; prayer_count (personnes) ne change pas. Voir docs/fonctionnalites/prier-plusieurs-fois.md
     */
    public function react(User $user, Model $subject, ReactionType $type, bool $again = false): bool
    {
        $kind = self::kindOf($subject);
        if ($kind === 'prayer_request' && $type->group() === 'prayer') {
            throw new ContentActionException(__('reactions.content.use_pray'), 422);
        }

        $created = DB::transaction(function () use ($user, $subject, $kind, $type, $again) {
            $subject::whereKey($subject->id)->lockForUpdate()->first();
            $mine = ContentReaction::where('user_id', $user->id)->where('subject_type', $kind)->where('subject_id', $subject->id)
                ->whereIn('type', ReactionType::valuesOfGroup($type->group()))->get();
            $same = $mine->first(fn (ContentReaction $r) => $r->type === $type);
            $others = $mine->reject(fn (ContentReaction $r) => $r->type === $type);

            // Nouvelle prière (aucune réaction du groupe « je prie ») : le temps de prière doit être écoulé.
            // Passer de pray à amen, ou prier de nouveau, n'est jamais bloqué (docs/fonctionnalites/temps-de-priere.md).
            if ($type->group() === 'prayer' && $mine->isEmpty()) {
                app(PrayerFocus::class)->ensureCompleted($user, $kind, (string) $subject->id);
            }
            // Prier encore : même réaction gardée, une prière de plus.
            if ($again && $type->group() === 'prayer' && $mine->isNotEmpty()) {
                app(PrayerFocus::class)->ensureCompleted($user, $kind, (string) $subject->id);
                $mine->sortByDesc('prayer_times')->first()->increment('prayer_times');

                return false;
            }

            if ($others->isNotEmpty()) {
                ContentReaction::whereKey($others->modelKeys())->delete();
                $this->decrement($subject, $type->counterField(), $others->count());
            }
            if ($same) return false;

            ContentReaction::create(['user_id' => $user->id, 'subject_type' => $kind, 'subject_id' => $subject->id, 'type' => $type->value]);
            $subject::whereKey($subject->id)->increment($type->counterField());

            return $others->isEmpty();
        });

        if ($created) $this->notifyOwner($user, $subject, $type->group() === 'prayer' ? 'prayer' : 'like');
        $subject->refresh();

        return $created;
    }

    /**
     * Commence (ou recommence) le temps de prière avant une réaction du groupe « je prie » sur une
     * publication visible (find() : 404 sinon). Voir docs/fonctionnalites/temps-de-priere.md
     *
     * @return array{startedAt: string, requiredSeconds: int, confirmableAt: string}
     */
    public function startPrayer(User $user, Model $subject): array
    {
        return app(PrayerFocus::class)->start($user, self::kindOf($subject), (string) $subject->id);
    }

    /** Retire les réactions de la personne du groupe donné ('appreciation' | 'prayer'), toutes si null. */
    public function unreact(User $user, Model $subject, ?string $group = null): void
    {
        $kind = self::kindOf($subject);
        DB::transaction(function () use ($user, $subject, $kind, $group) {
            $subject::whereKey($subject->id)->lockForUpdate()->first();
            $mine = ContentReaction::where('user_id', $user->id)->where('subject_type', $kind)->where('subject_id', $subject->id)
                ->when($group, fn ($q) => $q->whereIn('type', ReactionType::valuesOfGroup($group)))->get();
            if ($mine->isEmpty()) return;

            ContentReaction::whereKey($mine->modelKeys())->delete();
            foreach ($mine->groupBy(fn (ContentReaction $r) => $r->type->counterField()) as $field => $rows) {
                $this->decrement($subject, $field, $rows->count());
            }
        });
        $subject->refresh();
    }

    /** Retire la dernière prière de la personne (une de moins, ou plus de réaction « je prie »). Renvoie les prières restantes. */
    public function removeLastPrayer(User $user, Model $subject): int
    {
        $kind = self::kindOf($subject);
        $current = ContentReaction::where('user_id', $user->id)->where('subject_type', $kind)->where('subject_id', $subject->id)
            ->whereIn('type', ReactionType::valuesOfGroup('prayer'))->orderByDesc('prayer_times')->first();
        if (!$current) return 0;
        if ($current->prayer_times <= 1) {
            $this->unreact($user, $subject, 'prayer');
            return 0;
        }
        $current->decrement('prayer_times');

        return $current->prayer_times;
    }

    /** Bascule (site) : retire la réaction si c'est déjà celle-là, sinon la pose. Vrai si posée. */
    public function toggle(User $user, Model $subject, ReactionType $type): bool
    {
        $exists = ContentReaction::where('user_id', $user->id)->where('subject_type', self::kindOf($subject))
            ->where('subject_id', $subject->id)->where('type', $type->value)->exists();
        if ($exists) {
            $this->unreact($user, $subject, $type->group());
            return false;
        }
        $this->react($user, $subject, $type);

        return true;
    }

    // ─── Partages ────────────────────────────────────────────────────────────

    /**
     * Partager (ou changer le commentaire d'un partage existant).
     * @return array{0: ContentRepost, 1: bool} le partage et vrai s'il vient d'être créé
     */
    public function repost(User $user, Model $subject, ?string $comment = null): array
    {
        if (self::ownerId($subject) === $user->id) {
            throw new ContentActionException(__('reactions.content.own'), 422);
        }
        if (!$this->isShareable($subject)) {
            throw new ContentActionException(__('reactions.content.not_shareable'), 422);
        }
        $kind = self::kindOf($subject);
        $comment = filled($comment) ? Str::limit(trim($comment), ContentRepost::COMMENT_MAX, '') : null;

        $existing = ContentRepost::where('user_id', $user->id)->where('subject_type', $kind)->where('subject_id', $subject->id)->first();
        if ($existing) {
            $existing->update(['comment' => $comment]);
            return [$existing->load('user'), false];
        }

        $repost = DB::transaction(function () use ($user, $subject, $kind, $comment) {
            $repost = ContentRepost::create(['user_id' => $user->id, 'subject_type' => $kind, 'subject_id' => $subject->id, 'comment' => $comment]);
            $subject::whereKey($subject->id)->increment('repost_count');

            return $repost;
        });
        $this->notifyOwner($user, $subject, 'share');
        $subject->refresh();

        return [$repost->load('user'), true];
    }

    /** Annuler son partage. Faux s'il n'y en avait pas. */
    public function unrepost(User $user, Model $subject): bool
    {
        $deleted = ContentRepost::where('user_id', $user->id)->where('subject_type', self::kindOf($subject))
            ->where('subject_id', $subject->id)->delete();
        if ($deleted) $this->decrement($subject, 'repost_count', $deleted);
        $subject->refresh();

        return $deleted > 0;
    }

    // ─── Outils ──────────────────────────────────────────────────────────────

    private function decrement(Model $subject, string $field, int $by): void
    {
        $subject::whereKey($subject->id)->update([$field => DB::raw("CASE WHEN {$field} > {$by} THEN {$field} - {$by} ELSE 0 END")]);
    }

    /** Prévient l'auteur (notification + push). Jamais l'auteur lui-même ; jamais bloquant. */
    private function notifyOwner(User $actor, Model $subject, string $action): void
    {
        $ownerId = self::ownerId($subject);
        if (!$ownerId || $ownerId === $actor->id) return;

        $kind = self::kindOf($subject);
        // Type « prayer » : l'application préfixe le nom (comme les encouragements) ; sinon le nom est inclus.
        // Requête de prière : sans titre.
        [$type, $verb] = match ($action) {
            'prayer' => [NotificationType::Prayer, 'prayer'],
            'share'  => [NotificationType::Share, 'share'],
            default  => [NotificationType::Like, 'like'],
        };
        $params = ['actor' => $actor->display_name];
        if ($kind !== 'prayer_request') {
            $params['title'] = Str::limit((string) $subject->title, 80);
        }

        try {
            AppNotification::create([
                'recipient_id' => $ownerId,
                'actor_id'     => $actor->id,
                'actor_name'   => $actor->display_name,
                'actor_avatar' => $actor->avatar_url,
                'type'         => $type->value,
                ...\App\Support\NotificationText::fields("reactions.notify.{$verb}_{$kind}", $params, [$kind . '_id' => $subject->id]),
                'created_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
