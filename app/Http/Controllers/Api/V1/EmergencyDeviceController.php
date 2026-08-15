<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MobileEmergencyDevice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmergencyDeviceController extends Controller
{
    public function enroll(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401, 'Authentication is required.');

        $validated = $request->validate([
            'installation_id' => ['required', 'string', 'max:120'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:50'],
        ]);

        $plainToken = Str::random(80);

        $device = MobileEmergencyDevice::query()->updateOrCreate(
            ['installation_id' => trim($validated['installation_id'])],
            [
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $plainToken),
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

        return response()->json([
            'message' => 'Emergency device enrollment completed.',
            'data' => [
                'installation_id' => $device->installation_id,
                'emergency_token' => $plainToken,
                'linked_user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role,
                ],
            ],
        ]);
    }
}
