<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AdminAudit;
use Illuminate\Console\Command;

/**
 * Seul moyen d'attribuer ou de retirer le statut super-administrateur (users.is_super_admin) :
 * jamais par l'API ni le back-office. Réservé aux comptes administrateurs. Consigné dans admin_audit_logs.
 *   php artisan users:super-admin admin@example.com
 *   php artisan users:super-admin admin@example.com --revoke
 * Voir docs/fonctionnalites/gestion-des-temoignages.md
 */
class SetSuperAdmin extends Command
{
    protected $signature = 'users:super-admin {email : Adresse e-mail du compte} {--revoke : Retirer le statut}';

    protected $description = 'Attribue (ou retire) le statut super-administrateur à un administrateur';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (!$user) {
            $this->error('Aucun compte avec cette adresse.');
            return self::FAILURE;
        }

        $revoke = (bool) $this->option('revoke');
        if (!$revoke && !$user->isAdmin()) {
            $this->error("{$user->display_name} n'est pas administrateur : le statut super-administrateur est réservé aux administrateurs.");
            return self::FAILURE;
        }
        if ((bool) $user->is_super_admin === !$revoke) {
            $this->info($revoke ? "{$user->display_name} n'est pas super-administrateur." : "{$user->display_name} est déjà super-administrateur.");
            return self::SUCCESS;
        }

        $before = ['is_super_admin' => (bool) $user->is_super_admin];
        $user->forceFill(['is_super_admin' => !$revoke])->save();
        AdminAudit::record(null, $revoke ? 'user.super_admin_revoked' : 'user.super_admin_granted', $user, $before,
            ['is_super_admin' => !$revoke], 'Ligne de commande (users:super-admin)');

        $this->info($revoke ? "Statut super-administrateur retiré à {$user->display_name}." : "{$user->display_name} est maintenant super-administrateur.");

        return self::SUCCESS;
    }
}
