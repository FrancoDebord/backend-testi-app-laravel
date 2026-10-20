<?php

namespace App\Services;

use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\AccountDeletionRequest;
use App\Models\AppNotification;
use App\Models\User;
use App\Support\NotificationText;

/**
 * Demandes de suppression faites sans connexion (page /suppression-compte) : enregistrées, puis les
 * super-administrateurs sont prévenus (notification et push). Voir docs/fonctionnalites/confidentialite-et-suppression.md
 */
final class AccountDeletionRequests
{
    /** @param string $type AccountDeletionRequest::TYPE_ACCOUNT | TYPE_PARTIAL (certaines données, précisées dans $message) */
    public function submit(string $contact, ?string $message, ?string $ip, string $type = AccountDeletionRequest::TYPE_ACCOUNT): AccountDeletionRequest
    {
        $contact = trim($contact);
        $isEmail = str_contains($contact, '@');
        $value   = $isEmail ? mb_strtolower($contact) : self::normalizePhone($contact);

        $user = $isEmail
            ? User::whereRaw('LOWER(email) = ?', [$value])->first()
            : User::where('phone', $value)->first();

        $request = AccountDeletionRequest::create([
            'type'         => in_array($type, AccountDeletionRequest::TYPES, true) ? $type : AccountDeletionRequest::TYPE_ACCOUNT,
            'contact'      => mb_substr($value, 0, 190),
            'contact_type' => $isEmail ? 'email' : 'phone',
            'user_id'      => $user?->id,
            'message'      => $message !== null && trim($message) !== '' ? mb_substr(trim($message), 0, 1000) : null,
            'ip_address'   => $ip,
        ]);

        $this->notifySuperAdmins($request);

        return $request;
    }

    /** « 00229 90 00 00 01 », « +229-90… » → « +2299000… » (format enregistré dans users.phone). */
    public static function normalizePhone(string $input): string
    {
        $digits = preg_replace('/\D+/', '', $input);
        if (str_starts_with(trim($input), '00')) {
            $digits = substr($digits, 2);
        }

        return '+' . $digits;
    }

    /** Super-administrateurs actifs ; à défaut, tous les administrateurs actifs (comme les alertes Bunny). */
    public static function recipients()
    {
        $admins = User::where('role', UserRole::Administrateur->value)->where('status', UserAccountStatus::Active->value)->get();
        $super  = $admins->filter(fn (User $u) => $u->isSuperAdmin());

        return $super->isNotEmpty() ? $super : $admins;
    }

    private function notifySuperAdmins(AccountDeletionRequest $request): void
    {
        $text = NotificationText::fields(
            'privacy.deletion.notify_admin_' . ($request->isPartial() ? 'partial_' : '') . ($request->user_id ? 'known' : 'unknown'),
            ['contact' => $request->contact],
            ['account_deletion_request_id' => $request->id, 'user_id' => $request->user_id, 'request_type' => $request->type],
        );

        foreach (self::recipients() as $admin) {
            AppNotification::create([
                'recipient_id' => $admin->id,
                'actor_id'     => null,
                'actor_name'   => 'My Testimony',
                'type'         => 'account_deletion_requested',
                ...$text,
                'created_at'   => now(),
            ]);
        }
    }
}
