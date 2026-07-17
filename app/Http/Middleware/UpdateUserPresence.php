<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class UpdateUserPresence
{
    private const HEARTBEAT_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! Schema::hasColumn('users', 'last_seen_at')) {
            return $next($request);
        }

        $userId = (int) $user->id;
        $isLogoutRequest = $request->routeIs('logout');

        if (! $isLogoutRequest) {
            $lastSeenAt = $user->last_seen_at;
            $needsHeartbeat = ! $lastSeenAt;

            if ($lastSeenAt) {
                try {
                    $needsHeartbeat = \Carbon\Carbon::parse($lastSeenAt)
                        ->lt(now()->subSeconds(self::HEARTBEAT_SECONDS));
                } catch (\Throwable $e) {
                    $needsHeartbeat = true;
                }
            }

            if ($needsHeartbeat) {
                DB::table('users')
                    ->where('id', $userId)
                    ->update(['last_seen_at' => now()]);

                $user->last_seen_at = now();
            }
        }

        $response = $next($request);

        if ($isLogoutRequest) {
            DB::table('users')
                ->where('id', $userId)
                ->update(['last_seen_at' => null]);
        }

        return $response;
    }
}
