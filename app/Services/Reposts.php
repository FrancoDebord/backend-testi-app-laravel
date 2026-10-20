<?php

namespace App\Services;

use App\Enums\UserAccountStatus;
use App\Models\AppNotification;
use App\Models\Testimony;
use App\Models\TestimonyRepost;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Partage interne (« republier ») d'un témoignage sur My Testimony, commun à l'API mobile et au site.
 *
 * - un partage par personne et par témoignage (un nouvel envoi ne fait que changer le commentaire) ;
 * - seulement un témoignage publié et public, jamais le sien ;
 * - l'auteur d'origine est prévenu (notification « share ») ;
 * - le partage apparaît dans « Mon fil » des abonnés du partageur (Recommendations::personalFeed)
 *   et dans son profil ; il disparaît si le témoignage n'est plus public ou est supprimé.
 *
 * Voir docs/fonctionnalites/partage-interne.md
 */
class Reposts
{
    /** Messages de validation du commentaire de partage. */
    public static function messages(): array
    {
        return ['comment.max' => __('reactions.content.comment_max')];
    }

    public static function rules(): array
    {
        return ['comment' => ['nullable', 'string', 'max:' . TestimonyRepost::COMMENT_MAX]];
    }

    /**
     * Partager (ou changer le commentaire d'un partage existant).
     *
     * @return array{0: TestimonyRepost, 1: bool} le partage et vrai s'il vient d'être créé
     * @throws RepostException
     */
    public function repost(User $user, string $testimonyId, ?string $comment = null): array
    {
        $testimony = $this->shareable($testimonyId);
        if ($testimony->user_id === $user->id) {
            throw new RepostException(__('testimonies.repost.own'), 422);
        }
        $comment = filled($comment) ? Str::limit(trim($comment), TestimonyRepost::COMMENT_MAX, '') : null;

        $existing = TestimonyRepost::where('user_id', $user->id)->where('testimony_id', $testimony->id)->first();
        if ($existing) {
            $existing->update(['comment' => $comment]);

            return [$existing->load('user'), false];
        }

        $repost = DB::transaction(function () use ($user, $testimony, $comment) {
            $repost = TestimonyRepost::create(['user_id' => $user->id, 'testimony_id' => $testimony->id, 'comment' => $comment]);
            $testimony->increment('repost_count');

            return $repost;
        });

        $this->notifyAuthor($user, $testimony);
        $this->forgetFollowerFeeds($user);

        return [$repost->load('user'), true];
    }

    /** Annuler son partage. Renvoie vrai si un partage a été retiré. */
    public function remove(User $user, string $testimonyId): bool
    {
        $repost = Str::isUuid($testimonyId)
            ? TestimonyRepost::where('user_id', $user->id)->where('testimony_id', $testimonyId)->first()
            : null;
        if (!$repost) {
            return false;
        }

        DB::transaction(function () use ($repost) {
            $repost->delete();
            Testimony::withTrashed()->whereKey($repost->testimony_id)->where('repost_count', '>', 0)->decrement('repost_count');
        });
        $this->forgetFollowerFeeds($user);

        return true;
    }

    public function isRepostedBy(?User $user, string $testimonyId): bool
    {
        return $user !== null
            && TestimonyRepost::where('user_id', $user->id)->where('testimony_id', $testimonyId)->exists();
    }

    /** Partages visibles d'un compte (profil), du plus récent au plus ancien : témoignages encore publics. */
    public function ofUser(User $owner): Builder
    {
        return TestimonyRepost::query()
            ->where('user_id', $owner->id)
            ->whereHas('testimony', fn ($t) => $t->published())
            ->with(['user', 'testimony.user', 'testimony.category'])
            ->latest();
    }

    /** Témoignage publié et public, sinon 404 (un témoignage réservé aux abonnés ne se partage pas). */
    private function shareable(string $testimonyId): Testimony
    {
        $testimony = Str::isUuid($testimonyId) ? Testimony::published()->find($testimonyId) : null;
        if (!$testimony) {
            throw new RepostException(__('testimonies.repost.not_shareable'), 404);
        }

        return $testimony;
    }

    private function notifyAuthor(User $user, Testimony $testimony): void
    {
        AppNotification::create([
            'recipient_id'    => $testimony->user_id,
            'actor_id'        => $user->id,
            'actor_name'      => $user->display_name,
            'actor_avatar'    => $user->avatar_url,
            'type'            => 'share',
            'testimony_id'    => $testimony->id,
            'testimony_title' => $testimony->title,
            ...\App\Support\NotificationText::fields('testimonies.repost.notify', ['actor' => $user->display_name, 'title' => $testimony->title]),
            'created_at'      => now(),
        ]);
    }

    /** « Mon fil » de ses abonnés (cache de 10 minutes) : le partage y apparaît tout de suite. */
    private function forgetFollowerFeeds(User $user): void
    {
        $user->followers()->select('users.id')->limit(2000)->pluck('users.id')
            ->each(fn ($id) => Cache::forget(Recommendations::personalCacheKey($id)));
    }

    /**
     * Partage à montrer avec le témoignage dans les listes (« X a partagé ») : un compte suivi par
     * $viewer d'abord, puis le sien, puis le plus récent. Null si personne ne l'a partagé.
     * Aucune requête pour un témoignage jamais partagé (`repost_count` = 0).
     */
    public function highlightFor(Testimony $testimony, ?User $viewer): ?TestimonyRepost
    {
        if ((int) ($testimony->repost_count ?? 0) <= 0) return null;

        $query = TestimonyRepost::query()
            ->where('testimony_id', $testimony->id)
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
}
