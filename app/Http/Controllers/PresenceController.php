<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class PresenceController extends Controller
{
    private const ONLINE_WINDOW_SECONDS = 120;
    private const HEARTBEAT_WRITE_SECONDS = 30;
    private const DELETED_USER_EMAIL = 'deleted-user@tabangnow.local';

    public function heartbeat(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if (Schema::hasColumn('users', 'last_seen_at')) {
            $shouldWrite = ! $user->last_seen_at;

            if ($user->last_seen_at) {
                try {
                    $shouldWrite = Carbon::parse(
                        $user->last_seen_at
                    )->lt(
                        now()->subSeconds(
                            self::HEARTBEAT_WRITE_SECONDS
                        )
                    );
                } catch (\Throwable $exception) {
                    $shouldWrite = true;
                }
            }

            if ($shouldWrite) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'last_seen_at' => now(),
                    ]);
            }
        }

        return response()
            ->json([
                'ok' => true,
                'server_time' => now()->toIso8601String(),
            ])
            ->header('Cache-Control', 'no-store');
    }

    public function users(Request $request): JsonResponse
    {
        Gate::authorize('viewUserPresence');

        $query = User::query();

        if (Schema::hasColumn('users', 'email')) {
            $query->where(
                'email',
                '!=',
                self::DELETED_USER_EMAIL
            );
        }

        if (! Schema::hasColumn('users', 'last_seen_at')) {
            $total = $query->count();

            return response()
                ->json([
                    'users' => [],
                    'summary' => [
                        'online' => 0,
                        'offline' => $total,
                    ],
                    'server_time' => now()->toIso8601String(),
                ])
                ->header('Cache-Control', 'no-store');
        }

        $onlineThreshold = now()->subSeconds(
            self::ONLINE_WINDOW_SECONDS
        );

        $records = $query->get([
            'id',
            'last_seen_at',
        ]);

        $users = [];
        $onlineCount = 0;

        foreach ($records as $record) {
            $isOnline = false;
            $lastSeenAt = null;

            if ($record->last_seen_at) {
                try {
                    $parsedLastSeenAt = Carbon::parse(
                        $record->last_seen_at
                    );

                    $isOnline = $parsedLastSeenAt
                        ->greaterThanOrEqualTo(
                            $onlineThreshold
                        );

                    $lastSeenAt = $parsedLastSeenAt
                        ->toIso8601String();
                } catch (\Throwable $exception) {
                    $isOnline = false;
                    $lastSeenAt = null;
                }
            }

            if ($isOnline) {
                $onlineCount++;
            }

            $users[(string) $record->id] = [
                'online' => $isOnline,
                'last_seen_at' => $lastSeenAt,
            ];
        }

        return response()
            ->json([
                'users' => $users,
                'summary' => [
                    'online' => $onlineCount,
                    'offline' => $records->count() - $onlineCount,
                ],
                'server_time' => now()->toIso8601String(),
            ])
            ->header('Cache-Control', 'no-store');
    }
}
