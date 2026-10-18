<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\AppNotification;
use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\DailyVerse;
use App\Models\DailyVerseComment;
use App\Models\Event;
use App\Models\EventComment;
use App\Models\User;
use App\Support\NotificationText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * « J'aime » sur les commentaires : témoignages (comments), événements (event_comments) et verset du jour
 * (daily_verse_comments). Une ligne comment_likes par personne et par commentaire ; le compteur likes_count
 * de chaque table suit les ajouts et retraits (jamais négatif). Aimer ou retirer deux fois ne change rien.
 * L'auteur est prévenu la première fois seulement (notification comment_liked, préférence push_likes).
 * Voir docs/fonctionnalites/jaime-commentaires.md
 *
 * Instance par requête (AppServiceProvider::scoped) : preload() lit en une requête les commentaires
 * d'une liste aimés par la personne, isLikedBy() s'en sert ensuite sans nouvelle requête.
 */
class CommentLikes
{
    public const TESTIMONY = 'testimony_comment';
    public const EVENT     = 'event_comment';
    public const VERSE     = 'verse_comment';

    /** Type => modèle du commentaire. */
    public const MODELS = [
        self::TESTIMONY => Comment::class,
        self::EVENT     => EventComment::class,
        self::VERSE     => DailyVerseComment::class,
    ];

    /** @var array<string, array<string, bool>> « type|personne » => [commentaire => aimé ?] déjà lus */
    private array $known = [];

    // ─── Recherche (404 si invisible) ─────────────────────────────────────

    /**
     * Commentaire `$commentId` visible de `$viewer`, ou null (inexistant, contenu parent invisible,
     * commentaire retiré). `$parentId` : événement (EVENT) ou verset (VERSE) ; ignoré pour TESTIMONY.
     */
    public function find(string $type, string $commentId, ?string $parentId, ?User $viewer): ?Model
    {
        if (!Str::isUuid($commentId)) {
            return null;
        }

        return match ($type) {
            self::TESTIMONY => $this->findTestimonyComment($commentId, $viewer),
            self::EVENT     => $this->findEventComment($commentId, $parentId, $viewer),
            self::VERSE     => $this->findVerseComment($commentId, $parentId, $viewer),
            default         => null,
        };
    }

    private function findTestimonyComment(string $id, ?User $viewer): ?Comment
    {
        $comment = Comment::with('testimony')->find($id);

        return $comment && $comment->testimony && $comment->testimony->isVisibleTo($viewer) ? $comment : null;
    }

    private function findEventComment(string $id, ?string $eventId, ?User $viewer): ?EventComment
    {
        $event = $eventId && Str::isUuid($eventId) ? Event::find($eventId) : null;
        if (!$event || !$event->isVisibleTo($viewer)) {
            return null;
        }

        return EventComment::where('event_id', $event->id)->find($id)?->setRelation('event', $event);
    }

    private function findVerseComment(string $id, ?string $verseId, ?User $viewer): ?DailyVerseComment
    {
        $verse = $verseId && ctype_digit((string) $verseId) ? DailyVerse::where('is_active', true)->find((int) $verseId) : null;
        if (!$verse) {
            return null;
        }
        $comment = DailyVerseComment::where('daily_verse_id', $verse->id)->find($id);

        return $comment && $comment->isVisibleTo($viewer) ? $comment->setRelation('verse', $verse) : null;
    }

    // ─── Aimer / retirer ──────────────────────────────────────────────────

    /**
     * Aime le commentaire (idempotent). Retourne l'état à jour { likesCount, isLikedByMe }.
     *
     * @return array{likesCount: int, isLikedByMe: bool}
     */
    public function like(string $type, Model $comment, User $user): array
    {
        $created = DB::transaction(function () use ($type, $comment, $user) {
            $comment->newQuery()->whereKey($comment->getKey())->lockForUpdate()->first();

            $exists = CommentLike::where('likeable_type', $type)->where('likeable_id', (string) $comment->getKey())
                ->where('user_id', $user->id)->exists();
            if ($exists) {
                return false;
            }

            CommentLike::create(['likeable_type' => $type, 'likeable_id' => (string) $comment->getKey(), 'user_id' => $user->id]);
            $comment->newQuery()->whereKey($comment->getKey())->increment('likes_count');

            return true;
        });

        $this->remember($type, $user, (string) $comment->getKey(), true);
        if ($created) {
            $this->notifyAuthor($type, $comment, $user);
        }

        return $this->state($type, $comment, $user);
    }

    /**
     * Retire le « j'aime » (idempotent ; compteur jamais négatif). Retourne l'état à jour.
     *
     * @return array{likesCount: int, isLikedByMe: bool}
     */
    public function unlike(string $type, Model $comment, User $user): array
    {
        DB::transaction(function () use ($type, $comment, $user) {
            $comment->newQuery()->whereKey($comment->getKey())->lockForUpdate()->first();

            $deleted = CommentLike::where('likeable_type', $type)->where('likeable_id', (string) $comment->getKey())
                ->where('user_id', $user->id)->delete();
            if ($deleted > 0) {
                $comment->newQuery()->whereKey($comment->getKey())
                    ->update(['likes_count' => DB::raw("CASE WHEN likes_count > {$deleted} THEN likes_count - {$deleted} ELSE 0 END")]);
            }
        });

        $this->remember($type, $user, (string) $comment->getKey(), false);

        return $this->state($type, $comment, $user);
    }

    /** @return array{likesCount: int, isLikedByMe: bool} */
    public function state(string $type, Model $comment, ?User $viewer): array
    {
        $count = (int) $comment->newQuery()->whereKey($comment->getKey())->value('likes_count');
        $comment->setAttribute('likes_count', $count);
        $comment->syncOriginalAttribute('likes_count');

        return ['likesCount' => $count, 'isLikedByMe' => $this->isLikedBy($type, (string) $comment->getKey(), $viewer)];
    }

    // ─── Commentaires aimés par la personne (listes) ──────────────────────

    /**
     * Lit en une requête lesquels de ces commentaires (et de leurs réponses déjà chargées) la personne aime.
     *
     * @param iterable<Model> $comments
     */
    public function preload(string $type, iterable $comments, ?User $viewer): void
    {
        if (!$viewer) {
            return;
        }

        $ids = [];
        foreach ($comments as $comment) {
            $ids[] = (string) $comment->getKey();
            if ($comment->relationLoaded('replies')) {
                foreach ($comment->getRelation('replies') as $reply) {
                    $ids[] = (string) $reply->getKey();
                }
            }
        }

        $key = $type . '|' . $viewer->id;
        $ids = array_values(array_diff(array_unique($ids), array_keys($this->known[$key] ?? [])));
        if ($ids === []) {
            return;
        }

        $liked = array_flip($this->likedIds($type, $ids, $viewer));
        foreach ($ids as $id) {
            $this->known[$key][$id] = isset($liked[$id]);
        }
    }

    /**
     * Identifiants, parmi `$ids`, des commentaires aimés par la personne.
     *
     * @param array<int, string> $ids
     * @return array<int, string>
     */
    public function likedIds(string $type, array $ids, ?User $viewer): array
    {
        if (!$viewer || $ids === []) {
            return [];
        }

        return CommentLike::where('likeable_type', $type)->where('user_id', $viewer->id)
            ->whereIn('likeable_id', array_map('strval', $ids))
            ->pluck('likeable_id')->map(fn ($id) => (string) $id)->all();
    }

    /** La personne aime-t-elle ce commentaire ? (faux pour un visiteur ; lu dans preload() si possible) */
    public function isLikedBy(string $type, string $commentId, ?User $viewer): bool
    {
        if (!$viewer) {
            return false;
        }

        $key = $type . '|' . $viewer->id;
        if (!isset($this->known[$key][$commentId])) {
            $this->known[$key][$commentId] = CommentLike::where('likeable_type', $type)->where('likeable_id', $commentId)
                ->where('user_id', $viewer->id)->exists();
        }

        return $this->known[$key][$commentId];
    }

    private function remember(string $type, User $user, string $commentId, bool $liked): void
    {
        $this->known[$type . '|' . $user->id][$commentId] = $liked;
    }

    // ─── Notification ─────────────────────────────────────────────────────

    /**
     * Prévient l'auteur (jamais soi-même) la première fois que cette personne aime ce commentaire :
     * retirer puis remettre « j'aime » ne renvoie rien (même règle que prayer_request_prayed).
     */
    private function notifyAuthor(string $type, Model $comment, User $actor): void
    {
        $authorId = $comment->getAttribute('user_id');
        if (!$authorId || (string) $authorId === (string) $actor->id) {
            return;
        }

        try {
            $already = AppNotification::where('recipient_id', $authorId)
                ->where('actor_id', $actor->id)
                ->where('type', NotificationType::CommentLiked->value)
                ->where('comment_id', (string) $comment->getKey())
                ->exists();
            if ($already) {
                return;
            }

            $payload = ['comment_type' => $type, 'comment_id' => (string) $comment->getKey()];
            $testimonyId = null;
            $testimonyTitle = null;
            if ($type === self::TESTIMONY) {
                $testimonyId    = $comment->testimony_id;
                $testimonyTitle = $comment->testimony?->title;
                $payload['testimony_id'] = $testimonyId;
            } elseif ($type === self::EVENT) {
                $payload['event_id'] = $comment->event_id;
            } elseif ($type === self::VERSE) {
                $payload['daily_verse_id'] = $comment->daily_verse_id;
            }

            AppNotification::create([
                'recipient_id'    => $authorId,
                'actor_id'        => $actor->id,
                'actor_name'      => $actor->display_name,
                'actor_avatar'    => $actor->avatar_url,
                'type'            => NotificationType::CommentLiked->value,
                'testimony_id'    => $testimonyId,
                'testimony_title' => $testimonyTitle,
                'comment_id'      => (string) $comment->getKey(),
                ...NotificationText::fields('comment_likes.notify', ['actor' => $actor->display_name], $payload),
                'created_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
