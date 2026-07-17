<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PresenceController extends Controller
{
    public function heartbeat(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! Schema::hasColumn('users', 'last_seen_at')) {
            return response()->json([
                'message' => 'The users.last_seen_at column does not exist.',
            ], 500);
        }

        DB::table('users')
            ->where('id', $user->id)
            ->update([
                'last_seen_at' => now(),
            ]);

        return response()->json([
            'ok' => true,
            'user_id' => $user->id,
            'last_seen_at' => now()->toDateTimeString(),
        ]);
    }
}