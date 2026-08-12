<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class IncidentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Incident::class);

        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $query = Incident::query()
            ->with([
                'barangay',
                'category',
                'currentStatus',
                'location',
                'reporter',
                'resident.user',
                'assignedTanod.user',
            ]);

        if ($user->isTanod()) {
            $employeeId = $user->employee?->id;

            if ($employeeId) {
                $query->where(
                    'assigned_to',
                    $employeeId
                );
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($user->isResident()) {
            $this->applyResidentIncidentOwnerFilter(
                $query,
                (int) $user->id
            );
        }

        $incidents = $query
            ->orderByDesc('incident_datetime')
            ->orderByDesc('created_at')
            ->paginate(15);

        $items = collect($incidents->items())
            ->map(
                fn (Incident $incident): array =>
                    $this->incidentData($incident)
            )
            ->values();

        return response()->json([
            'data' => $items,

            'pagination' => [
                'current_page' =>
                    $incidents->currentPage(),

                'last_page' =>
                    $incidents->lastPage(),

                'per_page' =>
                    $incidents->perPage(),

                'total' =>
                    $incidents->total(),

                'has_more' =>
                    $incidents->hasMorePages(),
            ],
        ]);
    }

    public function show(
        Request $request,
        Incident $incident
    ): JsonResponse {
        Gate::authorize('view', $incident);

        $incident->load([
            'barangay',
            'category',
            'currentStatus',
            'location',
            'reporter',
            'resident.user',
            'assignedTanod.user',
        ]);

        return response()->json([
            'data' => $this->incidentData(
                $incident,
                detailed: true
            ),
        ]);
    }

    private function incidentData(
        Incident $incident,
        bool $detailed = false
    ): array {
        $data = [
            'id' => (int) $incident->id,

            'incident_code' =>
                $incident->display_code,

            'title' =>
                $incident->display_title,

            'description' =>
                $incident->display_description,

            'priority' =>
                (string) $incident->priority,

            'severity_label' =>
                $incident->severity_label,

            'status' =>
                $incident
                    ->currentStatus
                    ?->status_name,

            'category' =>
                $incident
                    ->category
                    ?->category_name,

            'barangay' =>
                $incident
                    ->barangay
                    ?->barangay_name,

            'reporter' =>
                $incident->reporter
                    ? [
                        'id' =>
                            (int) $incident
                                ->reporter
                                ->id,

                        'name' =>
                            (string) $incident
                                ->reporter
                                ->name,
                    ]
                    : null,

            'assigned_tanod' =>
                $incident->assignedTanod
                    ? [
                        'id' =>
                            (int) $incident
                                ->assignedTanod
                                ->id,

                        'name' =>
                            (string) (
                                $incident
                                    ->assignedTanod
                                    ->user
                                    ?->name
                                ?? ''
                            ),
                    ]
                    : null,

            'incident_datetime' =>
                $incident
                    ->incident_datetime
                    ?->toIso8601String(),

            'reported_at' =>
                $incident
                    ->reported_at
                    ?->toIso8601String(),
        ];

        if ($detailed) {
            $data['persons_involved'] =
                $incident->persons_involved;

            $data['latitude'] =
                $incident->latitude;

            $data['longitude'] =
                $incident->longitude;

            $data['map_location_name'] =
                $incident->map_location_name;

            $data['location'] =
                $incident->location
                    ? [
                        'address' =>
                            $incident
                                ->location
                                ->location_address,

                        'purok' =>
                            $incident
                                ->location
                                ->purok,

                        'latitude' =>
                            $incident
                                ->location
                                ->latitude,

                        'longitude' =>
                            $incident
                                ->location
                                ->longitude,
                    ]
                    : null;
        }

        return $data;
    }

    private function applyResidentIncidentOwnerFilter(
    EloquentBuilder $query,
    int $userId
): void {
        $residentIds =
            $this->residentProfileIdsForUser(
                $userId
            );

        $query->where(
            function ($ownerQuery) use (
                $userId,
                $residentIds
            ): void {
                $hasCondition = false;

                foreach ([
                    'reporter_id',
                    'user_id',
                    'created_by',
                    'submitted_by',
                    'reported_by',
                    'resident_user_id',
                ] as $column) {
                    if (
                        ! Schema::hasColumn(
                            'incidents',
                            $column
                        )
                    ) {
                        continue;
                    }

                    if (! $hasCondition) {
                        $ownerQuery->where(
                            'incidents.' . $column,
                            $userId
                        );

                        $hasCondition = true;
                    } else {
                        $ownerQuery->orWhere(
                            'incidents.' . $column,
                            $userId
                        );
                    }
                }

                if (
                    Schema::hasColumn(
                        'incidents',
                        'resident_id'
                    )
                ) {
                    if (! $hasCondition) {
                        $ownerQuery->whereIn(
                            'incidents.resident_id',
                            $residentIds
                        );

                        $hasCondition = true;
                    } else {
                        $ownerQuery->orWhereIn(
                            'incidents.resident_id',
                            $residentIds
                        );
                    }
                }

                if (! $hasCondition) {
                    $ownerQuery->whereRaw(
                        '1 = 0'
                    );
                }
            }
        );
    }

    private function residentProfileIdsForUser(
        int $userId
    ): array {
        if (
            ! Schema::hasTable('residents')
            || ! Schema::hasColumn(
                'residents',
                'user_id'
            )
        ) {
            return [];
        }

        return DB::table('residents')
            ->where(
                'user_id',
                $userId
            )
            ->pluck('id')
            ->map(
                fn ($id): int => (int) $id
            )
            ->values()
            ->all();
    }
}