<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Services\PushNotifier;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Appareils qui reçoivent les notifications push (docs/fonctionnalites/notifications-push.md).
 * Routes : devices/token (application actuelle) et device-tokens (alias).
 */
class DeviceTokenController extends Controller
{
    use ApiResponse;

    // POST /devices/token ou /device-tokens  { token, platform?: 'android'|'ios'|'web' }
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => 'required|string|max:500',
            'platform' => 'nullable|in:android,ios,web',
        ]);

        // Jeton déjà connu (même téléphone, autre compte) : réattribué à l'utilisateur connecté, et rattaché à
        // la session de l'application qui l'envoie (supprimé à sa déconnexion : AuthController::logout).
        $attributes = ['user_id' => $request->user()->id, 'last_used_at' => now(), 'access_token_id' => self::accessTokenId($request)];
        if ($request->filled('platform')) {
            $attributes['platform'] = $request->platform;
        }

        DeviceToken::updateOrCreate(['token' => $request->token], $attributes);

        return $this->success(null, __('push.token_saved'));
    }

    // DELETE /devices/token ou /device-tokens  { token }
    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string']);

        DeviceToken::where('token', $request->token)
                   ->where('user_id', $request->user()->id)
                   ->delete();

        return $this->success(null, __('push.token_deleted'));
    }

    /**
     * POST /me/push-test : notification de test envoyée tout de suite à tous mes appareils, résultat par jeton.
     * Voir docs/fonctionnalites/notifications-push.md
     */
    public function test(Request $request, PushNotifier $notifier): JsonResponse
    {
        $result = $notifier->sendTest($request->user());

        return $this->success($result, match (true) {
            $result['disabled']       => __('notifications.test.disabled'),
            $result['total'] === 0    => __('notifications.test.no_device'),
            $result['sent'] === 0     => __('notifications.test.none_sent'),
            default                   => __('notifications.test.sent', ['sent' => $result['sent'], 'total' => $result['total']]),
        });
    }

    /** Jeton Sanctum de la requête (null pour une session du site). */
    public static function accessTokenId(Request $request): ?int
    {
        $token = $request->user()?->currentAccessToken();

        return $token instanceof \Laravel\Sanctum\PersonalAccessToken ? (int) $token->getKey() : null;
    }
}
