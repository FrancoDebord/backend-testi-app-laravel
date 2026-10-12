<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\GoogleSignIn;
use App\Services\GoogleSignInException;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /auth/google { id_token } — et POST /auth/social { provider: google, token | id_token } (anciennes versions).
 * Le jeton vient de google_sign_in (serverClientId = identifiant client Web). Même réponse que POST /auth/login.
 * Voir docs/fonctionnalites/connexion-google.md
 */
class GoogleAuthController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, GoogleSignIn $google): JsonResponse
    {
        // `token` : nom envoyé par les versions actuelles de l'application sur /auth/social.
        if (!$request->filled('id_token') && $request->filled('token')) {
            $request->merge(['id_token' => $request->input('token')]);
        }
        $request->validate([
            'id_token' => ['required', 'string', 'max:4096'],
            'provider' => ['sometimes', 'string', 'in:google'],
        ], ['id_token.required' => 'Jeton Google manquant.']);

        try {
            [$user, $isNewUser] = $google->authenticate((string) $request->input('id_token'));
        } catch (GoogleSignInException $e) {
            return $this->error($e->getMessage(), $e->status);
        }

        if ($user->status->value === 'banned') {
            return $this->error('Votre compte a été banni', 403);
        }

        $token = $user->createToken('mobile-google')->plainTextToken;

        return $this->success([
            'user'         => UserResource::owner($user),
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'is_new_user'  => $isNewUser,
        ], 'Connexion réussie');
    }
}
