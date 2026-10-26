<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AccountDelegation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Agir au nom d'un compte administré (docs/fonctionnalites/administrateurs-de-compte.md).
 * Application : en-tête X-Act-As ; site : compte choisi en session. La requête est alors
 * traitée comme si le compte administré était connecté ; la personne réelle reste dans
 * l'attribut de requête « acting_actor ». Les actions réservées au titulaire sont refusées
 * et chaque écriture est inscrite au journal du compte.
 */
class ActAsAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $isApi = $request->is('api/*');
        $targetId = $isApi
            ? $request->header(AccountDelegation::HEADER)
            : ($request->hasSession() ? $request->session()->get(AccountDelegation::SESSION_KEY) : null);
        if (!$targetId) return $next($request);

        $guard = $isApi ? 'sanctum' : 'web';
        /** @var User|null $actor */
        $actor = Auth::guard($guard)->user();
        if (!$actor || $actor->id === $targetId) return $next($request);

        $account = User::find($targetId);
        if (!$account || !AccountDelegation::canActFor($actor, $account)) {
            if (!$isApi) {
                $request->session()->forget(AccountDelegation::SESSION_KEY);
                return $next($request);
            }
            return response()->json([
                'success' => false,
                'code'    => 'acting_as_forbidden',
                'message' => __('api.acting_as_forbidden'),
            ], 403);
        }

        if (AccountDelegation::isOwnerOnly($request)) {
            // Site : les pages réservées au titulaire s'ouvrent sur son propre compte.
            if (!$isApi) {
                if ($request->is('logout')) $request->session()->forget(AccountDelegation::SESSION_KEY);
                return $next($request);
            }
            return response()->json([
                'success' => false,
                'code'    => 'owner_only',
                'message' => __('api.owner_only'),
            ], 403);
        }

        // Identifiants de connexion : jamais modifiés par un administrateur du compte.
        foreach (AccountDelegation::OWNER_FIELDS as $field) {
            $request->request->remove($field);
            if ($request->isJson()) $request->json()->remove($field);
        }

        $request->attributes->set(AccountDelegation::ACTOR_ATTRIBUTE, $actor);
        Auth::guard($guard)->setUser($account);
        Auth::setUser($account);
        $request->setUserResolver(fn () => $account);

        $response = $next($request);

        // Basculer de compte n'est pas une action faite au nom du compte ; une réponse rejouée
        // par IdempotentRequests a déjà été inscrite au journal lors du premier envoi.
        if (!$request->isMethodSafe() && $response->getStatusCode() < 400 && !$request->routeIs('accounts.switch')
            && !$response->headers->has('Idempotent-Replayed')) {
            AccountDelegation::record($request, $account, $actor, $response->getStatusCode());
        }
        if ($isApi) {
            $response->headers->set('X-Acting-As', $account->id);
        }

        return $response;
    }
}
