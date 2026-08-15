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
        $contactNumber = (string) preg_replace(
            '/[\s\-\(\)]+/',
            '',
            trim((string) $request->input('contact_number'))
        );

        if (Str::startsWith($contactNumber, '+63')) {
            $contactNumber = '0'.substr($contactNumber, 3);
        }

        $request->merge([
            'request_id' => trim((string) $request->input('request_id')),
            'installation_id' => trim((string) $request->input('installation_id')),
            'emergency_details' => trim((string) $request->input('emergency_details')),
            'contact_number' => $contactNumber,
            'location_source' => strtolower(trim((string) $request->input('location_source'))),
        ]);

        $validated = $request->validate([
            'request_id' => ['required', 'string', 'max:120'],
            'installation_id' => ['required', 'string', 'max:120'],
            'emergency_token' => ['nullable', 'string', 'max:255'],
            'emergency_details' => ['required', 'string', 'min:3', 'max:1000'],
            'contact_number' => [
                'required',
                'string',
                'regex:/^09\d{9}$/',
            ],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'location_source' => ['required', 'string', 'in:current,last_known'],
        ], [
            'emergency_details.required' => 'Describe the emergency before sending the SOS.',
            'emergency_details.min' => 'Emergency details must contain at least 3 characters.',
            'contact_number.required' => 'Mobile number is required.',
            'contact_number.regex' => 'Enter a valid Philippine mobile number, such as 09123456789.',
            'latitude.required' => 'A current or last-known device location is required.',
            'longitude.required' => 'A current or last-known device location is required.',
            'location_source.required' => 'Location source is required.',
            'location_source.in' => 'Location source must be current or last_known.',
        ]);

        $requestId = $validated['request_id'];
        $installationId = $validated['installation_id'];

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
            ->where('installation_id', $installationId)
            ->where('status', 'active')
            ->where('triggered_at', '>=', now()->subSeconds(30))
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
            $installationId,
            $device
        ): MobileEmergencyAlert {
            $alert = MobileEmergencyAlert::query()->create([
                'alert_code' => 'TMP-'.Str::random(20),
                'device_id' => $device?->id,
                'user_id' => $device?->user_id,
                'installation_id' => $installationId,
                'request_id' => $requestId,
                'status' => 'active',
                'emergency_details' => $validated['emergency_details'],
                'contact_number' => $validated['contact_number'],
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'accuracy_meters' => $validated['accuracy_meters'] ?? null,
                'location_source' => $validated['location_source'],
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

            $alert->load('user');

            $recipientIds = User::query()
                ->where('is_active', true)
                ->whereIn('role', ['admin', 'official', 'dao'])
                ->pluck('id');

            $identity = $alert->user?->name ?: 'a mobile user';
            $summary = Str::limit($alert->emergency_details, 120);

            foreach ($recipientIds as $recipientId) {
                UserNotification::query()->create([
                    'user_id' => $recipientId,
                    'type' => 'mobile_emergency',
                    'source_id' => $alert->id,
                    'title' => 'URGENT: Mobile SOS',
                    'message' => "{$alert->alert_code} from {$identity}: {$summary} Mobile: {$alert->contact_number}.",
                    'is_read' => false,
                ]);
            }

            return $alert;
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
            'emergency_details' => $alert->emergency_details,
            'contact_number' => $alert->contact_number,
            'latitude' => $alert->latitude,
            'longitude' => $alert->longitude,
            'accuracy_meters' => $alert->accuracy_meters,
            'location_source' => $alert->location_source,
            'triggered_at' => $alert->triggered_at?->toIso8601String(),
        ];
    }
}
