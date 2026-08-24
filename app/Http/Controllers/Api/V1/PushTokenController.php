<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MobilePushToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PushTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401, 'Authentication is required.');

        $validated = $request->validate([
            'installation_id' => ['required', 'string', 'max:120'],
            'fcm_token' => ['required', 'string', 'max:4096'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:50'],
        ]);

        $installationId = trim((string) $validated['installation_id']);
        $fcmToken = trim((string) $validated['fcm_token']);
        $tokenHash = hash('sha256', $fcmToken);

        DB::transaction(function () use (
            $user,
            $validated,
            $installationId,
            $fcmToken,
            $tokenHash
        ): void {
            MobilePushToken::query()
                ->where('token_hash', $tokenHash)
                ->where('installation_id', '!=', $installationId)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            MobilePushToken::query()->updateOrCreate(
                ['installation_id' => $installationId],
                [
                    'user_id' => $user->id,
                    'fcm_token' => $fcmToken,
                    'token_hash' => $tokenHash,
                    'device_name' => isset($validated['device_name'])
                        ? trim((string) $validated['device_name'])
                        : null,
                    'platform' => isset($validated['platform'])
                        ? strtolower(trim((string) $validated['platform']))
                        : 'android',
                    'last_seen_at' => now(),
                    'revoked_at' => null,
                ]
            );
        });

        return response()->json([
            'message' => 'Push notification device registered.',
            'data' => [
                'installation_id' => $installationId,
                'platform' => isset($validated['platform'])
                    ? strtolower(trim((string) $validated['platform']))
                    : 'android',
            ],
        ]);
    }

    public function destroyCurrent(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401, 'Authentication is required.');

        $validated = $request->validate([
            'installation_id' => ['required', 'string', 'max:120'],
        ]);

        MobilePushToken::query()
            ->where('user_id', (int) $user->id)
            ->where('installation_id', trim((string) $validated['installation_id']))
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'last_seen_at' => now(),
            ]);

        return response()->json([
            'message' => 'Push notification device revoked.',
        ]);
    }
}
