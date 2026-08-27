<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MobileEmergencyAlert;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmergencyAlertManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeResponder($request);

        $alerts = MobileEmergencyAlert::query()
            ->with([
                'user:id,name,email,role,contact_number',
                'acknowledgedBy:id,name',
                'resolvedBy:id,name',
            ])
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

        $emergencyAlert->load([
            'user:id,name,email,role,contact_number,address',
            'acknowledgedBy:id,name',
            'resolvedBy:id,name',
        ]);

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
            'message' => 'Distress signal acknowledged.',
            'data' => $this->payload($this->freshAlert($emergencyAlert)),
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
            'message' => 'Distress signal resolved.',
            'data' => $this->payload($this->freshAlert($emergencyAlert)),
        ]);
    }


    public function destroy(
        Request $request,
        MobileEmergencyAlert $emergencyAlert
    ): JsonResponse {
        $this->authorizeResponder($request);

        $deleted = $this->deleteAlertIds([(int) $emergencyAlert->id]);

        return response()->json([
            'message' => $deleted > 0
                ? 'Distress signal deleted.'
                : 'Distress signal was already removed.',
            'data' => [
                'deleted_count' => $deleted,
            ],
        ]);
    }

    public function destroyAll(Request $request): JsonResponse
    {
        $this->authorizeResponder($request);

        $ids = MobileEmergencyAlert::query()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $deleted = $this->deleteAlertIds($ids);

        return response()->json([
            'message' => $deleted === 1
                ? '1 distress signal deleted.'
                : "{$deleted} distress signals deleted.",
            'data' => [
                'deleted_count' => $deleted,
            ],
        ]);
    }

    private function deleteAlertIds(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            fn (int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return 0;
        }

        return DB::transaction(function () use ($ids): int {
            UserNotification::query()
                ->where('type', 'mobile_emergency')
                ->whereIn('source_id', $ids)
                ->delete();

            return MobileEmergencyAlert::query()
                ->whereIn('id', $ids)
                ->delete();
        });
    }
    private function authorizeResponder(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401, 'Authentication is required.');
        abort_unless(
            $user->isActive() && ($user->isAdmin() || $user->isOfficial()),
            403,
            'Only active administrators and officials may manage distress signals.'
        );

        return $user;
    }

    private function freshAlert(MobileEmergencyAlert $alert): MobileEmergencyAlert
    {
        return $alert->fresh([
            'user:id,name,email,role,contact_number,address',
            'acknowledgedBy:id,name',
            'resolvedBy:id,name',
        ]);
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
            'acknowledged_by' => $alert->acknowledgedBy ? [
                'id' => $alert->acknowledgedBy->id,
                'name' => $alert->acknowledgedBy->name,
            ] : null,
            'resolved_at' => $alert->resolved_at?->toIso8601String(),
            'resolved_by' => $alert->resolvedBy ? [
                'id' => $alert->resolvedBy->id,
                'name' => $alert->resolvedBy->name,
            ] : null,
        ];
    }
}
