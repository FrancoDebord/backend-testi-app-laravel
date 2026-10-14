<?php

namespace App\Policies;

use App\Models\User;

/**
 * Comptes : badge « Approuvé » (docs/fonctionnalites/badge-approuve.md). Administrateurs seulement ;
 * jamais un modérateur. Le statut super-administrateur ne passe par aucune policy : seule la
 * commande users:super-admin le modifie.
 */
class UserPolicy
{
    public function manageApproval(User $actor, User $target): bool
    {
        return $actor->isAdmin() && !$target->trashed();
    }
}
