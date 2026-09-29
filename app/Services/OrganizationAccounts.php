<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\NotificationType;
use App\Enums\OrganizationType;
use App\Enums\VerificationStatus;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * Comptes organisation : règles de validation, mise à jour du profil
 * et vérification par un administrateur (API mobile et interface web).
 * Voir docs/fonctionnalites/comptes-organisation.md
 */
class OrganizationAccounts
{
    /** Règles des champs organisation à l'inscription. */
    public static function registerRules(): array
    {
        return [
            'account_type'         => ['nullable', Rule::enum(AccountType::class)],
            'organization_name'    => ['required_if:account_type,organization', 'nullable', 'string', 'max:150'],
            'organization_type'    => ['nullable', Rule::enum(OrganizationType::class)],
            'organization_city'    => ['nullable', 'string', 'max:100'],
            'organization_website' => ['nullable', 'url:http,https', 'max:255'],
        ];
    }

    /**
     * Règles de PUT /users/me. Pour une organisation, le nom ne peut pas être
     * vidé ; pour une personne, les champs sont ignorés (voir updateProfile).
     */
    public static function updateRules(User $user): array
    {
        return [
            'organization_name'    => $user->isOrganization()
                                        ? ['sometimes', 'required', 'string', 'max:150']
                                        : ['nullable', 'string', 'max:150'],
            'organization_type'    => ['nullable', Rule::enum(OrganizationType::class)],
            'organization_city'    => ['nullable', 'string', 'max:100'],
            'organization_website' => ['nullable', 'url:http,https', 'max:255'],
        ];
    }

    /** Attributs à enregistrer à l'inscription. */
    public static function registrationAttributes(array $input): array
    {
        if (($input['account_type'] ?? null) !== AccountType::Organization->value) {
            return ['account_type' => AccountType::Individual->value];
        }

        return [
            'account_type'         => AccountType::Organization->value,
            'organization_name'    => trim($input['organization_name']),
            'organization_type'    => $input['organization_type'] ?? null,
            'organization_city'    => $input['organization_city'] ?? null,
            'organization_website' => $input['organization_website'] ?? null,
            'verification_status'  => VerificationStatus::Pending->value,
        ];
    }

    /**
     * Applique les champs organisation d'une mise à jour de profil.
     * - Une personne ne peut pas se déclarer organisation ni se dire vérifiée.
     * - Renommer une organisation vérifiée la remet en attente de vérification.
     * - Modifier une organisation refusée renvoie la demande en vérification.
     */
    public static function updateProfile(User $user, array $input): void
    {
        if (!$user->isOrganization()) {
            return;
        }

        $fields  = ['organization_name', 'organization_type', 'organization_city', 'organization_website'];
        $changes = array_intersect_key($input, array_flip($fields));
        // Type non choisi (null) : on garde le type enregistré.
        if (array_key_exists('organization_type', $changes) && $changes['organization_type'] === null) {
            unset($changes['organization_type']);
        }
        if (isset($changes['organization_name'])) {
            $changes['organization_name'] = trim($changes['organization_name']);
        }
        if ($changes === []) {
            return;
        }

        $user->fill($changes);

        $status = $user->verification_status;
        if ($status === VerificationStatus::Verified && $user->isDirty('organization_name')) {
            self::resetToPending($user);
        } elseif ($status === VerificationStatus::Rejected && $user->isDirty()) {
            self::resetToPending($user);
        } elseif ($status === null) {
            $user->verification_status = VerificationStatus::Pending;
        }

        $user->save();
    }

    public static function verify(User $organization, User $admin): void
    {
        $organization->update([
            'verification_status' => VerificationStatus::Verified->value,
            'verified_at'         => now(),
            'verified_by'         => $admin->id,
            'verification_note'   => null,
        ]);

        self::notify($organization, $admin, NotificationType::OrganizationVerified,
            'Votre organisation « ' . $organization->organization_name . ' » est vérifiée. Le badge apparaît désormais sur votre profil et vos témoignages.');
    }

    public static function reject(User $organization, User $admin, ?string $reason): void
    {
        $reason = $reason !== null && trim($reason) !== '' ? trim($reason) : null;

        $organization->update([
            'verification_status' => VerificationStatus::Rejected->value,
            'verified_at'         => null,
            'verified_by'         => $admin->id,
            'verification_note'   => $reason,
        ]);

        self::notify($organization, $admin, NotificationType::OrganizationRejected,
            'La vérification de votre organisation « ' . $organization->organization_name . ' » a été refusée'
            . ($reason ? ' : ' . $reason : '.')
            . ' Vous pouvez corriger vos informations depuis votre profil.');
    }

    private static function resetToPending(User $user): void
    {
        $user->verification_status = VerificationStatus::Pending;
        $user->verified_at         = null;
        $user->verified_by         = null;
        $user->verification_note   = null;
    }

    /**
     * Notification dans l'application (même mécanisme que la modération des
     * témoignages). Le push FCM part automatiquement (AppNotificationObserver,
     * docs/fonctionnalites/notifications-push.md).
     */
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
