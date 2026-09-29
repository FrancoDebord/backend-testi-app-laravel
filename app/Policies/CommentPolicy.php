<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

/** Droits sur les commentaires des témoignages (page Vidéos). */
class CommentPolicy
{
    /** Seul l'auteur modifie son commentaire. */
    public function update(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id;
    }

    /** L'auteur supprime son commentaire ; la modération peut aussi le retirer (comme dans l'API). */
    public function delete(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id || $user->canModerate();
    }
}
