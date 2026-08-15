<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiUserIsActive
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Reject deactivated accounts
        |--------------------------------------------------------------------------
        |
        | This is intentionally separate from the browser active.user
        | middleware because API requests do not use browser redirects,
        | sessions, or CSRF regeneration.
        |
        */
        if (! $user->isActive()) {
            $accessToken = $user->currentAccessToken();

            if ($accessToken instanceof PersonalAccessToken) {
                $accessToken->delete();
            }

            return response()->json([
                'message' => 'Your account is inactive. Please contact the administrator.',
            ], 403);
        }

        return $next($request);
    }
}