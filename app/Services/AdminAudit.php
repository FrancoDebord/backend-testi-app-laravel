<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Journal d'audit de l'administration : qui, quoi, quand, avant / après, motif.
 * Toutes les actions de modération des témoignages, de badge des comptes et de super-administrateur.
 * Voir docs/fonctionnalites/gestion-des-temoignages.md
 */
class AdminAudit
{
    public const LABELS = [
        'testimony.approved'        => 'Témoignage approuvé (publié)',
        'testimony.rejected'        => 'Témoignage refusé',
        'testimony.resubmitted'     => 'Témoignage modifié par l\'auteur, renvoyé en modération',
        'testimony.verified'        => 'Label « Témoignage approuvé » attribué',
        'testimony.unverified'      => 'Label « Témoignage approuvé » retiré',
        'testimony.featured'        => 'Mis à la une',
        'testimony.unfeatured'      => 'Retiré de la une',
        'testimony.deleted'         => 'Mis à la corbeille',
        'testimony.restored'        => 'Restauré depuis la corbeille',
        'testimony.force_deleted'   => 'Supprimé définitivement',
        'user.badge_granted'        => 'Badge « Approuvé » attribué',
        'user.badge_revoked'        => 'Badge « Approuvé » retiré',
        'user.super_admin_granted'  => 'Statut super-administrateur attribué',
        'user.super_admin_revoked'  => 'Statut super-administrateur retiré',
    ];

    /**
     * @param ?User $actor null : ligne de commande ou traitement automatique
     */
    public static function record(?User $actor, string $action, Model|string $subject, ?array $before = null, ?array $after = null, ?string $reason = null, ?string $subjectType = null): AdminAuditLog
    {
        $request = app()->runningInConsole() ? null : request();

        return AdminAuditLog::create([
            'actor_id'     => $actor?->id,
            'actor_name'   => $actor?->display_name ?? 'Système',
            'action'       => $action,
            'subject_type' => $subjectType ?? ($subject instanceof Model ? self::type($subject) : 'testimony'),
            'subject_id'   => $subject instanceof Model ? $subject->getKey() : $subject,
            'before'       => $before,
            'after'        => $after,
            'reason'       => $reason,
            'ip'           => $request?->ip(),
            'created_at'   => now(),
        ]);
    }

    /** Valeurs des champs $keys (dates en ISO 8601, enums en valeur brute) pour « avant / après ». */
    public static function snapshot(Model $model, array $keys): array
    {
        return collect($keys)->mapWithKeys(function ($k) use ($model) {
            $v = $model->getAttribute($k);

            return [$k => match (true) {
                $v instanceof \BackedEnum        => $v->value,
                $v instanceof \DateTimeInterface => $v->format(DATE_ATOM),
                default                          => $v,
            }];
        })->all();
    }

    private static function type(Model $model): string
    {
        return match (true) {
            $model instanceof \App\Models\Testimony => 'testimony',
            $model instanceof User                  => 'user',
            default                                 => class_basename($model),
        };
    }
}
