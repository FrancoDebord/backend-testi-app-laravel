<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Langue de la requête (docs/fonctionnalites/langues.md) : API selon Accept-Language (français par
 * défaut), site selon le sélecteur de langue (session) puis la langue du compte. La réponse de l'API
 * indique la langue utilisée (Content-Language).
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = Locales::fromRequest($request);
        Locales::apply($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);
        if ($request->is('api/*')) {
            $response->setVary('Accept-Language', false);
        }

        return $response;
    }
}
