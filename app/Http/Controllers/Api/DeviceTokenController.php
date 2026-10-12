<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
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

        // Jeton déjà connu (même téléphone, autre compte) : réattribué à l'utilisateur connecté.
        $attributes = ['user_id' => $request->user()->id, 'last_used_at' => now()];
        if ($request->filled('platform')) {
            $attributes['platform'] = $request->platform;
        }

        DeviceToken::updateOrCreate(['token' => $request->token], $attributes);

        return $this->success(null, 'Token enregistré');
    }

    // DELETE /devices/token ou /device-tokens  { token }
    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string']);

        DeviceToken::where('token', $request->token)
                   ->where('user_id', $request->user()->id)
                   ->delete();

        return $this->success(null, 'Token supprimé');
    }
}
