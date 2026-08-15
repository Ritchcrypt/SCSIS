<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MobileEmergencyAlert;
use App\Models\MobileEmergencyDevice;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class EmergencySosController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'request_id' => ['required', 'string', 'max:120'],
            'installation_id' => ['required', 'string', 'max:120'],
            'emergency_token' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $requestId = trim($validated['request_id']);
        $installationId = trim($validated['installation_id']);

        $existing = MobileEmergencyAlert::query()
            ->where('request_id', $requestId)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Emergency alert already received.',
                'data' => $this->alertPayload($existing),
            ]);
        }

        $rateKey = 'mobile-sos|'.hash(
            'sha256',
            $installationId.'|'.($request->ip() ?? 'unknown')
        );

        if (RateLimiter::tooManyAttempts($rateKey, 6)) {
            $seconds = RateLimiter::availableIn($rateKey);

            return response()->json([
                'message' => 'Too many emergency requests from this device. If this is a real emergency, contact the listed emergency hotline directly.',
                'retry_after' => $seconds,
            ], 429, [
                'Retry-After' => (string) $seconds,
            ]);
        }

        RateLimiter::hit($rateKey, 60);

        $device = $this->resolveDevice(
            $installationId,
            $validated['emergency_token'] ?? null
        );

        $recentDuplicate = MobileEmergencyAlert::query()
            ->where('status', 'active')
            ->where('created_at', '>=', now()->subSeconds(30))
            ->where(function ($query) use ($device, $installationId): void {
                if ($device) {
                    $query->where('device_id', $device->id);

                    return;
                }

                $query->whereNull('device_id')
                    ->where('request_id', 'like', $installationId.'%');
            })
            ->latest('id')
            ->first();

        if ($recentDuplicate) {
            return response()->json([
                'message' => 'An active emergency alert from this device was already received moments ago.',
                'data' => $this->alertPayload($recentDuplicate),
            ]);
        }

        $alert = DB::transaction(function () use (
            $request,
            $validated,
            $requestId,
            $device
        ): MobileEmergencyAlert {
            $alert = MobileEmergencyAlert::query()->create([
                'alert_code' => 'PENDING-'.Str::uuid(),
                'device_id' => $device?->id,
                'user_id' => $device?->user_id,
                'request_id' => $requestId,
                'status' => 'active',
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'accuracy_meters' => $validated['accuracy_meters'] ?? null,
                'source' => 'mobile',
                'ip_hash' => $request->ip()
                    ? hash('sha256', (string) $request->ip())
                    : null,
                'user_agent_hash' => $request->userAgent()
                    ? hash('sha256', (string) $request->userAgent())
                    : null,
                'triggered_at' => now(),
            ]);

            $alert->forceFill([
                'alert_code' => 'SOS-'.now()->format('Ymd').'-'.str_pad((string) $alert->id, 6, '0', STR_PAD_LEFT),
            ])->save();

            $recipientIds = User::query()
                ->where('is_active', true)
                ->whereIn('role', ['admin', 'official', 'dao'])
                ->pluck('id');

            $identity = $alert->user?->name ?: 'an unidentified mobile device';

            foreach ($recipientIds as $recipientId) {
                UserNotification::query()->create([
                    'user_id' => $recipientId,
                    'type' => 'mobile_emergency',
                    'source_id' => $alert->id,
                    'title' => 'URGENT: Mobile emergency alert',
                    'message' => "{$alert->alert_code} was triggered by {$identity}. Immediate review is required.",
                    'is_read' => false,
                ]);
            }

            return $alert->fresh(['user']);
        });

        if ($device) {
            $device->forceFill(['last_seen_at' => now()])->save();
        }

        return response()->json([
            'message' => 'Emergency alert sent successfully.',
            'data' => $this->alertPayload($alert),
        ], 201);
    }

    private function resolveDevice(
        string $installationId,
        ?string $plainToken
    ): ?MobileEmergencyDevice {
        if (! $plainToken) {
            return null;
        }

        return MobileEmergencyDevice::query()
            ->where('installation_id', $installationId)
            ->where('token_hash', hash('sha256', $plainToken))
            ->whereNull('revoked_at')
            ->first();
    }

    private function alertPayload(MobileEmergencyAlert $alert): array
    {
        $alert->loadMissing('user');

        return [
            'id' => $alert->id,
            'alert_code' => $alert->alert_code,
            'status' => $alert->status,
            'identified' => $alert->user_id !== null,
            'display_name' => $alert->display_name,
            'triggered_at' => $alert->triggered_at?->toIso8601String(),
        ];
    }
}
