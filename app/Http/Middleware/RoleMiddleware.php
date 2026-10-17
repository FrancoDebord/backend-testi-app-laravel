<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => __('api.unauthenticated')], 401);
            }
            return redirect()->route('login');
        }

        $userRole = $request->user()->role->value;

        if (!in_array($userRole, $roles)) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => __('api.forbidden')], 403);
            }
            abort(403, __('api.forbidden'));
        }

        return $next($request);
    }
}
