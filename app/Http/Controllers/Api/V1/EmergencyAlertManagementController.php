<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MobileEmergencyAlert;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmergencyAlertManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeResponder($request);

        $alerts = MobileEmergencyAlert::query()
            ->with('user:id,name,email,role,contact_number')
            ->latest('triggered_at')
            ->limit(100)
            ->get()
            ->map(fn (MobileEmergencyAlert $alert) => $this->payload($alert))
            ->values();

        return response()->json(['data' => $alerts]);
    }

    public function show(Request $request, MobileEmergencyAlert $emergencyAlert): JsonResponse
    {
        $this->authorizeResponder($request);

        $emergencyAlert->load('user:id,name,email,role,contact_number,address');

        return response()->json(['data' => $this->payload($emergencyAlert)]);
    }

    public function acknowledge(Request $request, MobileEmergencyAlert $emergencyAlert): JsonResponse
    {
        $user = $this->authorizeResponder($request);

        DB::transaction(function () use ($emergencyAlert, $user): void {
            $locked = MobileEmergencyAlert::query()
                ->lockForUpdate()
                ->findOrFail($emergencyAlert->id);

            if ($locked->status === 'resolved') {
                return;
            }

            $locked->forceFill([
                'status' => 'acknowledged',
                'acknowledged_by' => $locked->acknowledged_by ?: $user->id,
                'acknowledged_at' => $locked->acknowledged_at ?: now(),
            ])->save();
        });

        return response()->json([
            'message' => 'Emergency alert acknowledged.',
            'data' => $this->payload($emergencyAlert->fresh(['user'])),
        ]);
    }

    public function resolve(Request $request, MobileEmergencyAlert $emergencyAlert): JsonResponse
    {
        $user = $this->authorizeResponder($request);

        DB::transaction(function () use ($emergencyAlert, $user): void {
            $locked = MobileEmergencyAlert::query()
                ->lockForUpdate()
                ->findOrFail($emergencyAlert->id);

            $locked->forceFill([
                'status' => 'resolved',
                'acknowledged_by' => $locked->acknowledged_by ?: $user->id,
                'acknowledged_at' => $locked->acknowledged_at ?: now(),
                'resolved_by' => $user->id,
                'resolved_at' => now(),
            ])->save();
        });

        return response()->json([
            'message' => 'Emergency alert resolved.',
            'data' => $this->payload($emergencyAlert->fresh(['user'])),
        ]);
    }

    private function authorizeResponder(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401, 'Authentication is required.');
        abort_unless(
            $user->isActive() && ($user->isAdmin() || $user->isOfficial()),
            403,
            'Only active administrators and officials may manage emergency alerts.'
        );

        return $user;
    }

    private function payload(MobileEmergencyAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'alert_code' => $alert->alert_code,
            'status' => $alert->status,
            'identified' => $alert->user_id !== null,
            'display_name' => $alert->display_name,
            'user' => $alert->user ? [
                'id' => $alert->user->id,
                'name' => $alert->user->name,
                'email' => $alert->user->email,
                'role' => $alert->user->role,
                'contact_number' => $alert->user->contact_number,
                'address' => $alert->user->address,
            ] : null,
            'emergency_details' => $alert->emergency_details,
            'contact_number' => $alert->contact_number,
            'latitude' => $alert->latitude,
            'longitude' => $alert->longitude,
            'accuracy_meters' => $alert->accuracy_meters,
            'location_source' => $alert->location_source,
            'triggered_at' => $alert->triggered_at?->toIso8601String(),
            'acknowledged_at' => $alert->acknowledged_at?->toIso8601String(),
            'resolved_at' => $alert->resolved_at?->toIso8601String(),
        ];
    }
}
