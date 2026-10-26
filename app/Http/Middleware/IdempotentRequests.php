<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Écritures rejouées par l'application après une coupure (docs/fonctionnalites/hors-connexion.md).
 * Une écriture envoyée avec l'en-tête Idempotency-Key par une personne connectée n'est exécutée
 * qu'une fois : si la même clé revient (réponse perdue, file hors ligne renvoyée), la réponse
 * enregistrée la première fois est renvoyée telle quelle, avec l'en-tête Idempotent-Replayed.
 */
class IdempotentRequests
{
    public const HEADER = 'Idempotency-Key';

    /** Durée de conservation d'une réponse (la file de l'application rejoue dans ce délai). */
    private const TTL_SECONDS = 7 * 24 * 3600;

    public function handle(Request $request, Closure $next): Response
    {
        $key = trim((string) $request->header(self::HEADER));
        if ($key === '' || $request->isMethodSafe() || !preg_match('/^[A-Za-z0-9_.:-]{8,100}$/', $key)) {
            return $next($request);
        }

        $user = Auth::guard('sanctum')->user();
        if (!$user) return $next($request);

        // Même clé sur une autre route : autre écriture (l'application dérive les clés d'une même opération).
        $cacheKey = 'idempotency:'.$user->getAuthIdentifier().':'.sha1($request->method().' '.$request->path().' '.$key);

        if ($replay = $this->replay($cacheKey)) return $replay;

        $lock = Cache::lock($cacheKey.':lock', 120);
        if (!$lock->block(30)) {
            return response()->json(['success' => false, 'message' => __('api.request_in_progress')], 409);
        }

        try {
            // Une requête identique a pu finir pendant l'attente du verrou.
            if ($replay = $this->replay($cacheKey)) return $replay;

            $response = $next($request);

            $status = $response->getStatusCode();
            if ($status >= 200 && $status < 300) {
                Cache::put($cacheKey, [
                    'status'       => $status,
                    'content'      => $response->getContent(),
                    'content_type' => $response->headers->get('Content-Type', 'application/json'),
                ], self::TTL_SECONDS);
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    private function replay(string $cacheKey): ?Response
    {
        $saved = Cache::get($cacheKey);
        if (!is_array($saved)) return null;

        return response($saved['content'], $saved['status'], [
            'Content-Type'        => $saved['content_type'],
            'Idempotent-Replayed' => 'true',
        ]);
    }
}
