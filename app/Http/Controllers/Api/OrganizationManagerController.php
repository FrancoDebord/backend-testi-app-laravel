<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountDelegateAction;
use App\Models\User;
use App\Services\AccountDelegation;
use App\Services\EventActionException;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Administrateurs de compte : le titulaire nomme 2 personnes au plus qui agissent en son
 * nom (publier, commenter, prier, événements…). Tous les comptes, personnels ou organisations.
 * Voir docs/fonctionnalites/administrateurs-de-compte.md
 */
class OrganizationManagerController extends Controller
{
    use ApiResponse;

    /** Titulaire connecté : ses administrateurs. */
    public function index(Request $request): JsonResponse
    {
        return $this->success($this->payload($request->user()));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['user_id' => 'required|uuid'], ['user_id.required' => 'Choisissez une personne.']);
        $account = $request->user();

        try {
            $target = AccountDelegation::add($account, $account, $request->input('user_id'));
        } catch (EventActionException $e) {
            return $this->error($e->getMessage(), $e->status());
        }

        return $this->success($this->payload($account), "{$target->display_name} peut maintenant administrer votre compte.");
    }

    public function destroy(Request $request, string $userId): JsonResponse
    {
        $account = $request->user();
        try {
            AccountDelegation::remove($account, $account, $userId);
        } catch (EventActionException $e) {
            return $this->error($e->getMessage(), $e->status());
        }

        return $this->success($this->payload($account), 'Administrateur retiré.');
    }

    /** Personne connectée : comptes qu'on lui a confiés (organisations et personnes). */
    public function managed(Request $request): JsonResponse
    {
        return $this->success(AccountDelegation::accounts($request->user())
            ->where('role', 'manager')
            ->values());
    }

    /**
     * Sélecteur de comptes : le sien d'abord, puis ceux qu'elle administre.
     * Répond pour la personne réellement connectée, même avec l'en-tête X-Act-As.
     */
    public function accounts(Request $request): JsonResponse
    {
        $actor = AccountDelegation::actor($request);
        $acting = $request->attributes->get(AccountDelegation::ACTOR_ATTRIBUTE) ? $request->user()->id : null;

        return $this->success([
            'accounts' => AccountDelegation::accounts($actor)->values(),
            'actingAs' => $acting,
        ]);
    }

    /** Titulaire : actions faites en son nom par ses administrateurs (50 dernières). */
    public function activity(Request $request): JsonResponse
    {
        $items = AccountDelegateAction::where('account_id', $request->user()->id)
            ->latest('created_at')->latest('id')->limit(50)->get()
            ->map(fn (AccountDelegateAction $a) => [
                'id'        => $a->id,
                'actorId'   => $a->actor_id,
                'actorName' => $a->actor_name,
                'action'    => self::label($a),
                'createdAt' => $a->created_at?->toIso8601String(),
            ]);

        return $this->success($items->values());
    }

    /** Se retirer de l'administration d'un compte. */
    public function leave(Request $request, string $organizationId): JsonResponse
    {
        $account = User::find($organizationId);
        abort_if(!$account, 404, 'Compte introuvable.');
        try {
            AccountDelegation::remove($account, $request->user(), $request->user()->id);
        } catch (EventActionException $e) {
            return $this->error($e->getMessage(), $e->status());
        }

        return $this->success(null, "Vous n'administrez plus ce compte.");
    }

    /** Libellé lisible d'une action du journal (API et site). */
    public static function label(AccountDelegateAction $a): string
    {
        $p = $a->path;
        $m = $a->method;

        return match (true) {
            str_contains($p, 'comment')                                   => $m === 'DELETE' ? 'A supprimé un commentaire' : 'A commenté',
            (bool) preg_match('#/(testimonies|temoignages)/?$#', $p) && $m === 'POST' => 'A publié un témoignage',
            str_contains($p, 'testimon') || str_contains($p, 'temoignage') => $m === 'DELETE' ? 'A supprimé un témoignage' : 'Témoignage',
            str_contains($p, 'react') || str_contains($p, 'like') || str_contains($p, 'pray') && !str_contains($p, 'prayer') => 'A réagi',
            str_contains($p, 'prayer/sessions') || str_contains($p, 'sessions')  => 'Session de prière',
            str_contains($p, 'prayer') || str_contains($p, 'priere')       => 'Requête de prière',
            str_contains($p, 'events') || str_contains($p, 'evenements')   => 'Événement',
            str_contains($p, 'follow')                                    => $m === 'DELETE' ? 'Ne suit plus un compte' : 'A suivi un compte',
            str_contains($p, 'lives') || str_contains($p, 'directs')       => 'Direct',
            str_contains($p, 'users/me') || str_contains($p, 'profile')    => 'A modifié le profil',
            default                                                       => 'Action',
        };
    }

    private function payload(User $account): array
    {
        return [
            'max'      => AccountDelegation::MAX_MANAGERS,
            'managers' => $account->organizationManagers()->get()->map(fn (User $u) => [
                'id'          => $u->id,
                'displayName' => $u->display_name,
                'initials'    => $u->initials,
                'avatarUrl'   => $u->avatar_url,
                'since'       => $u->pivot?->created_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
