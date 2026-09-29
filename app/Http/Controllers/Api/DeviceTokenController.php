<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    use ApiResponse;

    // POST /devices/token  { token, platform: 'android'|'ios'|'web' }
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => 'required|string|max:500',
            'platform' => 'required|in:android,ios,web',
        ]);

        DeviceToken::updateOrCreate(
            ['token' => $request->token],
            [
                'user_id'  => $request->user()->id,
                'platform' => $request->platform,
            ]
        );

        return $this->success(null, 'Token enregistré');
    }

    // DELETE /devices/token  { token }
    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string']);

        DeviceToken::where('token', $request->token)
                   ->where('user_id', $request->user()->id)
                   ->delete();

        return $this->success(null, 'Token supprimé');
    }
}
