<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnforceSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $lifetimeMinutes = max(
            1,
            (int) config('session.lifetime', 120)
        );

        $now = time();

        $lastActivityAt = (int) $request->session()->get(
            'security.last_activity_at',
            0
        );

        $isHeartbeatRequest = $request->routeIs('presence.heartbeat');
        $isLogoutRequest = $request->routeIs('logout');

        if (
            $lastActivityAt > 0
            && ($now - $lastActivityAt) >= ($lifetimeMinutes * 60)
        ) {
            $userId = (int) $user->id;

            /*
            |--------------------------------------------------------------------------
            | Preserve the security reason for the logout audit event
            |--------------------------------------------------------------------------
            */

            $request->session()->put(
                'security.logout_reason',
                'inactivity_timeout'
            );

            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if (
                Schema::hasTable('users')
                && Schema::hasColumn('users', 'last_seen_at')
            ) {
                DB::table('users')
                    ->where('id', $userId)
                    ->update([
                        'last_seen_at' => null,
                    ]);
            }

            if ($isHeartbeatRequest || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session expired due to inactivity.',
                ], 401);
            }

            return redirect()
                ->route('login')
                ->with(
                    'status',
                    'Your session expired due to inactivity. Please log in again.'
                );
        }

        if (! $isHeartbeatRequest && ! $isLogoutRequest) {
            $request->session()->put(
                'security.last_activity_at',
                $now
            );
        }

        return $next($request);
    }
}
