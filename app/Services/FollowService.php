<?php

namespace App\Services;

use App\Enums\UserAccountStatus;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * « Suivre » un compte (personne ou organisation), partagé par le site et l'API mobile.
 *
 * - Jamais soi-même (contrôle ici et contrainte en base).
 * - Sans doublon, même en cas de double clic : insertion « si absente » dans une transaction ;
 *   les compteurs follower_count / following_count ne bougent que si le lien est réellement créé ou retiré.
 * - La personne suivie est prévenue (notification « follow » + push).
 * Voir docs/fonctionnalites/abonnements.md
 */
class FollowService
{
    /** @return bool true si l'abonnement vient d'être créé (false : déjà abonné) */
    public function follow(User $follower, User $target): bool
    {
        \Illuminate\Support\Facades\Cache::forget("reco:follows:{$follower->id}"); \Illuminate\Support\Facades\Cache::forget("reco:personal:{$follower->id}"); // Mon fil
        if ($follower->id === $target->id) {
            throw new FollowException('Vous ne pouvez pas vous suivre vous-même.', 422);
        }
        if ($target->status !== UserAccountStatus::Active || $target->trashed()) {
            throw new FollowException('Ce compte n\'est pas disponible.', 404);
        }

        $created = DB::transaction(function () use ($follower, $target) {
            $inserted = DB::table('follows')->insertOrIgnore([
                'follower_id'  => $follower->id,
                'following_id' => $target->id,
                'created_at'   => now(),
            ]);
            if ($inserted) {
                User::whereKey($follower->id)->increment('following_count');
                User::whereKey($target->id)->increment('follower_count');
            }

            return $inserted > 0;
        });

        if ($created) {
            AppNotification::create([
                'recipient_id' => $target->id,
                'actor_id'     => $follower->id,
                'actor_name'   => $follower->display_name,
                'actor_avatar' => $follower->avatar_url,
                'type'         => 'follow',
                'message'      => $follower->display_name . ' vous suit maintenant',
                'created_at'   => now(),
            ]);
        }

        return $created;
    }

    /** @return bool true si l'abonnement existait et a été retiré */
    public function unfollow(User $follower, User $target): bool
    {
        \Illuminate\Support\Facades\Cache::forget("reco:follows:{$follower->id}"); \Illuminate\Support\Facades\Cache::forget("reco:personal:{$follower->id}"); // Mon fil
        return DB::transaction(function () use ($follower, $target) {
            $deleted = DB::table('follows')
                ->where('follower_id', $follower->id)
                ->where('following_id', $target->id)
                ->delete();
            if ($deleted) {
                // Jamais négatif, même si un compteur avait dérivé.
                User::whereKey($follower->id)->where('following_count', '>', 0)->decrement('following_count');
                User::whereKey($target->id)->where('follower_count', '>', 0)->decrement('follower_count');
            }

            return $deleted > 0;
        });
    }

    public function isFollowing(?User $viewer, User $target): bool
    {
        return $viewer !== null && $viewer->id !== $target->id
            && DB::table('follows')->where('follower_id', $viewer->id)->where('following_id', $target->id)->exists();
    }

    /** Recalcule les compteurs à partir de la table follows (commande follows:recount, migration). */
    public static function recountAll(): void
    {
        DB::table('users')->update([
            'follower_count'  => DB::raw('(SELECT COUNT(*) FROM follows WHERE follows.following_id = users.id)'),
            'following_count' => DB::raw('(SELECT COUNT(*) FROM follows WHERE follows.follower_id = users.id)'),
        ]);
    }
}

