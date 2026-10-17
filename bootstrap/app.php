<?php

use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);

        $middleware->statefulApi();

        // Langue : API selon Accept-Language, site selon le sélecteur puis le compte (docs/fonctionnalites/langues.md)
        $middleware->prependToGroup('api', \App\Http\Middleware\SetLocale::class);

        // Agir au nom d'un compte administré (docs/fonctionnalites/administrateurs-de-compte.md)
        $middleware->appendToGroup('api', \App\Http\Middleware\ActAsAccount::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\ActAsAccount::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\SetLocale::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Adresse de caméra IP (peut contenir un mot de passe) : jamais remise en session (docs/fonctionnalites/lives-camera-ip.md)
        $exceptions->dontFlash(['camera_url', 'camera_password', 'camera_passphrase']);

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson()) {
                \App\Support\Locales::apply(\App\Support\Locales::fromRequest($request));

                return response()->json(['success' => false, 'message' => __('api.unauthenticated')], 401);
            }
        });

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('api.invalid_data'),
                    'errors'  => $e->errors(),
                ], 422);
            }
        });
    })->create();
