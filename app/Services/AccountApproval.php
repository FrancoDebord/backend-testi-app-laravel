<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\VerificationStatus;
use App\Models\AppNotification;
use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Support\Facades\DB;

/**
 * Badge « Approuvé » des comptes (docs/fonctionnalites/badge-approuve.md). Le badge est l'état
 * verification_status = verified ; approval_source dit d'où il vient :
 * - verification_form   : demande de vérification d'une personne approuvée (PersonalVerification) ;
 * - organization_review : organisation vérifiée par un administrateur (OrganizationAccounts) ;
 * - admin_manual        : attribution manuelle depuis la fiche utilisateur (ce service).
 * Attribution et retrait manuels : administrateurs seulement, motif obligatoire, journal d'audit,
 * notification de la personne (in-app + push).
 */
class AccountApproval
{
    private const AUDITED = ['verification_status', 'verified_at', 'verified_by', 'verification_note', 'approval_source'];

    public function grant(User $user, User $admin, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new VerificationActionException('Indiquez le motif de l’attribution.', 422);
        }
        if ($user->trashed()) {
            throw new VerificationActionException('Ce compte a été supprimé.', 422);
        }
        if ($user->isApproved()) {
            throw new VerificationActionException('Ce compte a déjà le badge « Approuvé ».', 422);
        }

        $before = AdminAudit::snapshot($user, self::AUDITED);
        DB::transaction(function () use ($user, $admin, $reason) {
            // Demande en cours (personne) : considérée comme traitée.
            VerificationRequest::where('user_id', $user->id)->where('status', VerificationRequest::PENDING)->update([
                'status' => VerificationRequest::APPROVED, 'review_note' => null,
                'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'updated_at' => now(),
            ]);
            $user->forceFill([
                'verification_status' => VerificationStatus::Verified->value,
                'verified_at'         => now(),
                'verified_by'         => $admin->id,
                'verification_note'   => null,
                'approval_source'     => 'admin_manual',
            ])->save();
        });
        AdminAudit::record($admin, 'user.badge_granted', $user, $before, AdminAudit::snapshot($user, self::AUDITED), $reason);

        self::notify($user, $admin, $user->isOrganization() ? NotificationType::OrganizationVerified : NotificationType::AccountVerified,
            $user->isOrganization()
                ? 'Votre organisation a reçu le badge « Approuvé ». Il apparaît désormais sur votre profil et vos publications.'
                : 'Votre compte a reçu le badge « Approuvé ». Il apparaît désormais sur votre profil et vos publications.');
    }

    public function revoke(User $user, User $admin, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new VerificationActionException('Indiquez le motif du retrait.', 422);
        }
        if (!$user->isApproved()) {
            throw new VerificationActionException('Ce compte n’a pas le badge « Approuvé ».', 422);
        }

        $before = AdminAudit::snapshot($user, self::AUDITED);
        DB::transaction(function () use ($user, $admin, $reason) {
            VerificationRequest::where('user_id', $user->id)->where('status', VerificationRequest::APPROVED)->update([
                'status' => VerificationRequest::REVOKED, 'review_note' => $reason,
                'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'updated_at' => now(),
            ]);
            $user->forceFill([
                'verification_status' => VerificationStatus::Rejected->value,
                'verified_at'         => null,
                'verified_by'         => $admin->id,
                'verification_note'   => $reason,
                'approval_source'     => null,
            ])->save();
        });
        AdminAudit::record($admin, 'user.badge_revoked', $user, $before, AdminAudit::snapshot($user, self::AUDITED), $reason);

        self::notify($user, $admin, NotificationType::AccountApprovalRevoked,
            'Le badge « Approuvé » a été retiré de votre compte : ' . rtrim($reason, ' .') . '.');
    }

    /** Badge attribué ou retiré par un autre chemin (formulaire, organisation) : audit commun. */
    public static function audit(User $user, ?User $admin, string $action, array $before, ?string $reason = null): void
    {
        AdminAudit::record($admin, $action, $user, $before, AdminAudit::snapshot($user, self::AUDITED), $reason);
    }

    public static function snapshot(User $user): array
    {
        return AdminAudit::snapshot($user, self::AUDITED);
    }

    private static function notify(User $recipient, User $admin, NotificationType $type, string $message): void
    {
        AppNotification::create([
            'recipient_id' => $recipient->id,
            'actor_id'     => $admin->id,
            'actor_name'   => $admin->display_name,
            'type'         => $type->value,
            'message'      => mb_substr($message, 0, 255),
            'created_at'   => now(),
        ]);
    }
}
