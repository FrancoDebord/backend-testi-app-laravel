<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\AccountDelegateAction;
use App\Models\AppNotification;
use App\Models\User;
use App\Support\NotificationText;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Administrateurs de compte : chaque titulaire administre son compte et peut nommer
 * 2 personnes qui agissent en son nom (publier, commenter, prier, événements…).
 * Voir docs/fonctionnalites/administrateurs-de-compte.md
 */
class AccountDelegation
{
    public const MAX_MANAGERS = 2;

    /** En-tête de l'application : compte au nom duquel la requête est faite. */
    public const HEADER = 'X-Act-As';

    /** Session du site : compte administré choisi. */
    public const SESSION_KEY = 'acting_as';

    /** Attribut de requête : la personne réellement connectée (si elle agit pour un autre compte). */
    public const ACTOR_ATTRIBUTE = 'acting_actor';

    /**
     * Réservé au titulaire (refusé quand on agit au nom d'un compte) : identifiants,
     * suppression, administrateurs, vérification, position, appareils, modération.
     * Motifs sur le chemin sans le préfixe api/v1/.
     */
    private const OWNER_ONLY = [
        'auth/*',
        'users/me/managers*',
        'users/me/managed-organizations*',
        'users/me/verification*',
        'users/me/location*',
        'users/me/settings*',
        'devices/*',
        'device-tokens*',
        'me/push-test',
        'admin/*',
        'moderation/*',
        // Carnet privé et paroles prophétiques : intimes, jamais partagés.
        'journal*',
        'prophecies*',
        'carnet*',
        // Site
        'logout',
        'profile/settings*',
        'profile/localisation*',
        'profile/verification*',
        'profile/gestionnaires*',
        'profile/organisations-gerees*',
        'profile/password*',
        'profile/delete*',
    ];

    /** Comptes de l'équipe : leurs droits ne se délèguent pas. */
    public static function isStaff(User $user): bool
    {
        return in_array($user->role, [UserRole::Moderateur, UserRole::Administrateur], true);
    }

    /** $actor peut agir au nom de $account (titulaire ou administrateur nommé, comptes actifs). */
    public static function canActFor(User $actor, User $account): bool
    {
        if ($actor->id === $account->id) return true;
        if ($account->status !== UserAccountStatus::Active || $actor->status !== UserAccountStatus::Active) return false;
        if (self::isStaff($account)) return false;

        return $actor->managedOrganizations()->whereKey($account->id)->exists();
    }

    /** Comptes que $actor administre : le sien d'abord, puis ceux qu'on lui a confiés. */
    public static function accounts(User $actor): Collection
    {
        $managed = $actor->managedOrganizations()
            ->where('status', UserAccountStatus::Active->value)
            ->whereNotIn('role', [UserRole::Moderateur->value, UserRole::Administrateur->value])
            ->orderBy('display_name')
            ->get();

        return collect([self::summary($actor, 'owner')])
            ->concat($managed->map(fn (User $u) => self::summary($u, 'manager', $u->pivot?->created_at)));
    }

    public static function summary(User $u, string $role, $since = null): array
    {
        return [
            'id'             => $u->id,
            'displayName'    => $u->isOrganization() ? ($u->organization_name ?: $u->display_name) : $u->display_name,
            'username'       => $u->username ?? null,
            'initials'       => $u->initials,
            'avatarUrl'      => $u->avatar_url,
            'isOrganization' => $u->isOrganization(),
            'isVerified'     => $u->isVerified(),
            ...\App\Models\User::approvalFieldsFor($u), // badge-approuve.md
            'role'           => $role, // owner | manager
            'since'          => $since?->toIso8601String(),
        ];
    }

    /** Champs de connexion ignorés dans une modification de profil faite au nom du compte. */
    // locale : langue des push et e-mails de la personne (docs/fonctionnalites/langues.md).
    public const OWNER_FIELDS = ['email', 'phone', 'phone_country', 'password', 'password_confirmation', 'current_password', 'locale'];

    public static function isOwnerOnly(Request $request): bool
    {
        $path = Str::after($request->path(), 'api/v1/');
        // Supprimer le compte.
        if ($request->isMethod('DELETE') && $path === 'users/me') return true;
        foreach (self::OWNER_ONLY as $pattern) {
            if (Str::is($pattern, $path)) return true;
        }

        return false;
    }

    /** Personne réellement connectée, même quand la requête est faite au nom d'un autre compte. */
    public static function actor(Request $request): ?User
    {
        return $request->attributes->get(self::ACTOR_ATTRIBUTE) ?? $request->user();
    }

    public static function record(Request $request, User $account, User $actor, int $status): void
    {
        AccountDelegateAction::create([
            'account_id' => $account->id,
            'actor_id'   => $actor->id,
            'actor_name' => mb_substr((string) $actor->display_name, 0, 120),
            'method'     => $request->method(),
            'path'       => mb_substr('/'.$request->path(), 0, 255),
            'route_name' => $request->route()?->getName(),
            'status'     => $status,
            'created_at' => now(),
        ]);
    }

    // ─── Nommer / retirer ────────────────────────────────────────────────────

    /** Par le titulaire (ou un administrateur de la plateforme). */
    public static function add(User $account, User $by, string $userId): User
    {
        self::ensureCanDelegate($account, $by);
        $target = User::find($userId);
        if (!$target || $target->status !== UserAccountStatus::Active) {
            throw new EventActionException(__('account.managers.target_not_found'), 404);
        }
        if ($target->id === $account->id) {
            throw new EventActionException(__('account.managers.self'), 422);
        }
        if ($target->isOrganization()) {
            throw new EventActionException(__('account.managers.not_organization'), 422);
        }
        if ($account->organizationManagers()->whereKey($target->id)->exists()) {
            throw new EventActionException(__('account.managers.already'), 409);
        }
        if ($account->organizationManagers()->count() >= self::MAX_MANAGERS) {
            throw new EventActionException(__('account.managers.limit'), 422);
        }
        $account->organizationManagers()->attach($target->id);
        self::notify($target, $account, NotificationType::AccountManagerAdded,
            'account.notify_manager_added', ['name' => $account->display_name]);

        return $target;
    }

    /** Par le titulaire (ou un administrateur de la plateforme), ou par l'administrateur lui-même. */
    public static function remove(User $account, User $by, string $userId): void
    {
        if ($by->id !== $userId) {
            self::ensureCanDelegate($account, $by);
        }
        if (!$account->organizationManagers()->whereKey($userId)->exists()) return;
        $account->organizationManagers()->detach($userId);
        if ($by->id !== $userId && ($target = User::find($userId))) {
            self::notify($target, $account, NotificationType::AccountManagerRemoved,
                'account.notify_manager_removed', ['name' => $account->display_name]);
        }
    }

    private static function ensureCanDelegate(User $account, User $by): void
    {
        if ($by->status !== UserAccountStatus::Active) {
            throw new EventActionException(__('account.managers.not_allowed'), 403);
        }
        if ($by->id !== $account->id && !$by->isAdmin()) {
            throw new EventActionException(__('account.managers.owner_only'), 403);
        }
        if (self::isStaff($account)) {
            throw new EventActionException(__('account.managers.staff'), 422);
        }
    }

    private static function notify(User $recipient, User $account, NotificationType $type, string $key, array $params): void
    {
        AppNotification::create([
            'recipient_id' => $recipient->id,
            'actor_id'     => $account->id,
            'actor_name'   => $account->display_name,
            'type'         => $type->value,
            ...NotificationText::fields($key, $params),
            'created_at'   => now(),
        ]);
    }
}
