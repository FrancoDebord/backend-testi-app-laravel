<?php

namespace App\Policies;

use App\Models\Testimony;
use App\Models\User;

/**
 * Gestion des témoignages (back-office et API). Voir docs/fonctionnalites/gestion-des-temoignages.md
 *
 * | Action                                   | Modérateur | Administrateur | Super-admin |
 * |------------------------------------------|:----------:|:--------------:|:-----------:|
 * | moderate (approuver, refuser), feature   |     oui    |       oui      |     oui     |
 * | trash, restore (corbeille 30 jours)      |     oui    |       oui      |     oui     |
 * | viewStats, viewAny (back-office)         |     oui    |       oui      |     oui     |
 * | verify (label « Témoignage approuvé »)   |     non    |       oui      |     oui     |
 * | forceDelete (suppression définitive)     |     non    |       non      |     oui     |
 *
 * Le carnet privé n'est jamais géré par l'équipe : seul son auteur y a accès.
 */
class TestimonyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canModerate();
    }

    public function viewStats(User $user, Testimony $testimony): bool
    {
        return $user->canModerate() && !$testimony->isInJournal();
    }

    public function moderate(User $user, Testimony $testimony): bool
    {
        return $user->canModerate() && !$testimony->isInJournal() && !$testimony->trashed();
    }

    public function feature(User $user, Testimony $testimony): bool
    {
        return $this->moderate($user, $testimony);
    }

    public function verify(User $user, Testimony $testimony): bool
    {
        return $user->isAdmin() && !$testimony->isInJournal() && !$testimony->trashed();
    }

    public function trash(User $user, Testimony $testimony): bool
    {
        return $user->canModerate() && !$testimony->isInJournal() && !$testimony->trashed();
    }

    public function restore(User $user, Testimony $testimony): bool
    {
        return $user->canModerate() && $testimony->trashed();
    }

    public function forceDelete(User $user, Testimony $testimony): bool
    {
        return $user->isSuperAdmin();
    }
}
