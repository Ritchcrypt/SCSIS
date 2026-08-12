<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\Status;
use App\Models\User;
use App\Support\SafeDatabaseIdentifier;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    private const TANOD_RESPONSE_STATUS_COLUMNS = [
        'response_status',
        'status',
    ];

    private const TANOD_SOURCE_TABLES = [
        'tanod_profiles',
        'employees',
        'tanods',
        'tanod_rosters',
        'users',
    ];

    private const TANOD_STATUS_COLUMNS = [
        'duty_status',
        'status',
        'availability_status',
        'duty_state',
    ];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $role = strtolower(
            trim((string) $user->role)
        );

        $dashboard = match ($role) {
            'admin' => $this->adminDashboard(),
            'official',
            'dao' => $this->officialDashboard(),
            'tanod' => $this->tanodDashboard($user),
            'resident' => $this->residentDashboard($user),

            default => null,
        };

        if ($dashboard === null) {
            return response()->json([
                'message' => 'This account role is not supported.',
            ], 403);
        }

        return response()->json([
            'data' => [
                'user' => [
                    'id' => (int) $user->id,
                    'name' => (string) $user->name,
                    'role' => $role,
                ],

                ...$dashboard,
            ],
        ]);
    }

    private function adminDashboard(): array
    {
        $activeStatusNames = [
            'pending',
            'verified',
            'responding',
            'dispatched',
            'active',
            'escalated',
        ];

        $resolvedStatusNames = [
            'resolved',
            'completed',
            'closed',
        ];

        $activeStatusIds = Status::query()
            ->whereIn(
                DB::raw('LOWER(status_name)'),
                $activeStatusNames
            )
            ->pluck('id')
            ->all();

        $resolvedStatusIds = Status::query()
            ->whereIn(
                DB::raw('LOWER(status_name)'),
                $resolvedStatusNames
            )
            ->pluck('id')
            ->all();

        $totalIncidents = Incident::count();

        $activeCases = ! empty($activeStatusIds)
            ? Incident::query()
                ->whereIn(
                    'status_id',
                    $activeStatusIds
                )
                ->count()
            : 0;

        $resolvedCases = ! empty($resolvedStatusIds)
            ? Incident::query()
                ->whereIn(
                    'status_id',
                    $resolvedStatusIds
                )
                ->count()
            : 0;

        $criticalIncidents = Incident::query()
            ->where(
                'priority',
                'critical'
            )
            ->when(
                ! empty($resolvedStatusIds),
                function ($query) use (
                    $resolvedStatusIds
                ): void {
                    $query->whereNotIn(
                        'status_id',
                        $resolvedStatusIds
                    );
                }
            )
            ->count();

        $recentIncidents = Incident::query()
            ->with([
                'reporter',
                'assignedTanod.user',
                'category',
                'currentStatus',
                'barangay',
            ])
            ->orderByDesc('reported_at')
            ->orderByDesc('incident_datetime')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return [
            'role' => 'admin',

            'summary' => [
                'total_incidents' => $totalIncidents,
                'active_cases' => $activeCases,
                'resolved_cases' => $resolvedCases,
                'critical_incidents' => $criticalIncidents,
                'tanod_on_duty' => $this->countTanodOnDuty(),
            ],

            'recent_incidents' =>
                $this->formatIncidents($recentIncidents),
        ];
    }

    private function officialDashboard(): array
    {
        return [
            'role' => 'official',

            'summary' => [
                'total_incidents' =>
                    $this->incidentCount(),

                'pending_incidents' =>
                    $this->incidentStatusCount([
                        'pending',
                        'reported',
                        'submitted',
                    ]),

                'active_incidents' =>
                    $this->incidentStatusCount([
                        'active',
                        'dispatched',
                        'responding',
                        'in progress',
                        'in_progress',
                        'escalated',
                        'under review',
                        'under_review',
                    ]),

                'resolved_incidents' =>
                    $this->incidentStatusCount([
                        'resolved',
                        'closed',
                        'completed',
                    ]),
            ],

            'recent_incidents' =>
                $this->formatIncidents(
                    $this->latestIncidents()
                ),
        ];
    }

    private function tanodDashboard(User $user): array
    {
        $employee = (
            Schema::hasTable('employees')
            && Schema::hasColumn(
                'employees',
                'user_id'
            )
        )
            ? DB::table('employees')
                ->where(
                    'user_id',
                    $user->id
                )
                ->first()
            : null;

        $employeeId = $employee->id ?? null;

        return [
            'role' => 'tanod',

            'summary' => [
                'assigned_incidents' =>
                    $this->tanodAssignedIncidentCount(
                        (int) $user->id,
                        $employeeId
                    ),

                'open_tasks' =>
                    $this->tanodTaskCount(
                        (int) $user->id,
                        $employeeId,
                        [
                            'pending',
                        ]
                    ),

                'accepted_tasks' =>
                    $this->tanodTaskCount(
                        (int) $user->id,
                        $employeeId,
                        [
                            'accepted',
                        ]
                    ),

                'declined_tasks' =>
                    $this->tanodTaskCount(
                        (int) $user->id,
                        $employeeId,
                        [
                            'declined',
                            'rejected',
                        ]
                    ),
            ],

            'recent_incidents' =>
                $this->formatIncidents(
                    $this->latestIncidents()
                ),
        ];
    }

    private function residentDashboard(User $user): array
    {
        $residentIds = $this->residentProfileIds(
            (int) $user->id
        );

        return [
            'role' => 'resident',

            'summary' => [
                'my_reports' =>
                    $this->residentIncidentCount(
                        (int) $user->id,
                        $residentIds
                    ),

                'pending_reports' =>
                    $this->residentIncidentStatusCount(
                        (int) $user->id,
                        $residentIds,
                        [
                            'pending',
                            'reported',
                            'submitted',
                        ]
                    ),

                'active_reports' =>
                    $this->residentIncidentStatusCount(
                        (int) $user->id,
                        $residentIds,
                        [
                            'active',
                            'dispatched',
                            'responding',
                            'in progress',
                            'in_progress',
                            'escalated',
                            'under review',
                            'under_review',
                        ]
                    ),

                'resolved_reports' =>
                    $this->residentIncidentStatusCount(
                        (int) $user->id,
                        $residentIds,
                        [
                            'resolved',
                            'closed',
                            'completed',
                        ]
                    ),

                'announcements' =>
                    $this->activeAnnouncementCount(),
            ],

            'recent_reports' =>
                $this->formatIncidents(
                    $this->latestResidentReports(
                        (int) $user->id,
                        $residentIds
                    )
                ),

            'latest_announcements' =>
                $this->latestAnnouncements()
                    ->map(
                        fn ($announcement): array => [
                            'id' =>
                                (int) $announcement->id,

                            'title' =>
                                (string) $announcement->title,

                            'content' =>
                                (string) $announcement->content,

                            'category' =>
                                (string) $announcement->category,

                            'priority' =>
                                (string) $announcement->priority,

                            'audience' =>
                                (string) $announcement->audience,

                            'published_at' =>
                                $this->dateValue(
                                    $announcement->published_at
                                        ?? null
                                ),
                        ]
                    )
                    ->values(),
        ];
    }

    private function incidentCount(): int
    {
        if (! Schema::hasTable('incidents')) {
            return 0;
        }

        return DB::table('incidents')->count();
    }

    private function incidentStatusCount(
        array $statuses
    ): int {
        if (! Schema::hasTable('incidents')) {
            return 0;
        }

        $query = DB::table('incidents');

        $this->applyIncidentStatusFilter(
            $query,
            $statuses
        );

        return $query->count();
    }

    private function tanodAssignedIncidentCount(
        int $userId,
        ?int $employeeId
    ): int {
        if (
            ! $employeeId
            || ! Schema::hasTable('incidents')
            || ! Schema::hasColumn(
                'incidents',
                'assigned_to'
            )
        ) {
            return 0;
        }

        $query = DB::table('incidents')
            ->where(
                'incidents.assigned_to',
                $employeeId
            );

        if (
            Schema::hasTable('tanod_tasks')
            && Schema::hasTable(
                'tanod_task_responses'
            )
            && Schema::hasColumn(
                'tanod_tasks',
                'incident_id'
            )
            && Schema::hasColumn(
                'tanod_task_responses',
                'tanod_task_id'
            )
        ) {
            $responseStatusColumn = null;

            if (
                Schema::hasColumn(
                    'tanod_task_responses',
                    'response_status'
                )
            ) {
                $responseStatusColumn =
                    'response_status';
            } elseif (
                Schema::hasColumn(
                    'tanod_task_responses',
                    'status'
                )
            ) {
                $responseStatusColumn = 'status';
            }

            if ($responseStatusColumn) {
                $responseStatusColumn =
                    SafeDatabaseIdentifier::approved(
                        $responseStatusColumn,
                        self::TANOD_RESPONSE_STATUS_COLUMNS
                    );

                $query->whereNotExists(
                    function ($subQuery) use (
                        $userId,
                        $employeeId,
                        $responseStatusColumn
                    ): void {
                        $subQuery
                            ->selectRaw('1')
                            ->from('tanod_tasks')
                            ->join(
                                'tanod_task_responses',
                                'tanod_task_responses.tanod_task_id',
                                '=',
                                'tanod_tasks.id'
                            )
                            ->whereColumn(
                                'tanod_tasks.incident_id',
                                'incidents.id'
                            )
                            ->whereIn(
                                'tanod_task_responses.'
                                    . $responseStatusColumn,
                                [
                                    'declined',
                                    'rejected',
                                ]
                            )
                            ->where(
                                function ($ownerQuery) use (
                                    $userId,
                                    $employeeId
                                ): void {
                                    if (
                                        $employeeId
                                        && Schema::hasColumn(
                                            'tanod_task_responses',
                                            'employee_id'
                                        )
                                    ) {
                                        $ownerQuery->orWhere(
                                            'tanod_task_responses.employee_id',
                                            $employeeId
                                        );
                                    }

                                    if (
                                        Schema::hasColumn(
                                            'tanod_task_responses',
                                            'user_id'
                                        )
                                    ) {
                                        $ownerQuery->orWhere(
                                            'tanod_task_responses.user_id',
                                            $userId
                                        );
                                    }
                                }
                            );
                    }
                );
            }
        }

        return $query->count();
    }

    private function tanodTaskCount(
        int $userId,
        ?int $employeeId,
        array $statuses
    ): int {
        if (
            ! Schema::hasTable(
                'tanod_task_responses'
            )
        ) {
            return 0;
        }

        $query = DB::table(
            'tanod_task_responses'
        );

        if (
            Schema::hasColumn(
                'tanod_task_responses',
                'response_status'
            )
        ) {
            $query->whereIn(
                'response_status',
                $statuses
            );
        } elseif (
            Schema::hasColumn(
                'tanod_task_responses',
                'status'
            )
        ) {
            $query->whereIn(
                'status',
                $statuses
            );
        } else {
            return 0;
        }

        if (
            $employeeId
            && Schema::hasColumn(
                'tanod_task_responses',
                'employee_id'
            )
        ) {
            $query->where(
                'employee_id',
                $employeeId
            );
        } elseif (
            Schema::hasColumn(
                'tanod_task_responses',
                'user_id'
            )
        ) {
            $query->where(
                'user_id',
                $userId
            );
        } else {
            return 0;
        }

        return $query->count();
    }

    private function residentIncidentCount(
        int $userId,
        array $residentIds = []
    ): int {
        if (! Schema::hasTable('incidents')) {
            return 0;
        }

        return $this->residentIncidentBaseQuery(
            $userId,
            $residentIds
        )->count();
    }

    private function residentIncidentStatusCount(
        int $userId,
        array $residentIds,
        array $statuses
    ): int {
        if (! Schema::hasTable('incidents')) {
            return 0;
        }

        $query = $this->residentIncidentBaseQuery(
            $userId,
            $residentIds
        );

        $this->applyIncidentStatusFilter(
            $query,
            $statuses
        );

        return $query->count();
    }

    private function activeAnnouncementCount(): int
    {
        if (
            ! Schema::hasTable('announcements')
        ) {
            return 0;
        }

        $query = DB::table('announcements');

        if (
            Schema::hasColumn(
                'announcements',
                'is_active'
            )
        ) {
            $query->where(
                'is_active',
                true
            );
        }

        return $query->count();
    }

    private function latestIncidents(
        int $limit = 6
    ): Collection {
        if (! Schema::hasTable('incidents')) {
            return collect();
        }

        $query = Incident::query()
            ->with([
                'currentStatus',
                'status',
                'category',
                'reporter',
                'resident',
                'assignedTanod.user',
            ]);

        $this->applyIncidentOrdering($query);

        return $query
            ->limit($limit)
            ->get();
    }

    private function latestResidentReports(
        int $userId,
        array $residentIds = [],
        int $limit = 6
    ): Collection {
        if (! Schema::hasTable('incidents')) {
            return collect();
        }

        $query = $this->residentIncidentBaseQuery(
            $userId,
            $residentIds
        )
            ->leftJoin(
                'statuses',
                'statuses.id',
                '=',
                'incidents.status_id'
            )
            ->leftJoin(
                'incident_categories',
                'incident_categories.id',
                '=',
                'incidents.category_id'
            )
            ->select([
                'incidents.id',
                'incidents.incident_code',
                'incidents.incident_title',
                'incidents.priority',
                'incidents.created_at',
                'incidents.reported_at',
                'incidents.incident_datetime',
                'statuses.status_name',
                'incident_categories.category_name',
            ]);

        $this->applyIncidentOrdering($query);

        return $query
            ->limit($limit)
            ->get();
    }

    private function latestAnnouncements(
        int $limit = 5
    ): Collection {
        if (
            ! Schema::hasTable('announcements')
        ) {
            return collect();
        }

        $query = DB::table('announcements');

        if (
            Schema::hasColumn(
                'announcements',
                'is_active'
            )
        ) {
            $query->where(
                'is_active',
                true
            );
        }

        return $query
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    private function residentIncidentBaseQuery(
        int $userId,
        array $residentIds = []
    ): QueryBuilder {
        $query = DB::table('incidents');

        $residentIds = collect($residentIds)
            ->filter()
            ->map(
                fn ($id): int => (int) $id
            )
            ->push($userId)
            ->unique()
            ->values()
            ->all();

        $ownershipColumns = [
            'reporter_id' => $userId,
            'user_id' => $userId,
            'created_by' => $userId,
            'submitted_by' => $userId,
            'reported_by' => $userId,
            'resident_user_id' => $userId,
        ];

        $query->where(
            function ($ownerQuery) use (
                $ownershipColumns,
                $residentIds
            ): void {
                $hasCondition = false;

                foreach (
                    $ownershipColumns
                    as $column => $value
                ) {
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
                            $value
                        );

                        $hasCondition = true;
                    } else {
                        $ownerQuery->orWhere(
                            'incidents.' . $column,
                            $value
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

        return $query;
    }

    private function residentProfileIds(
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

    private function applyIncidentStatusFilter(
        EloquentBuilder|QueryBuilder $query,
        array $statuses
    ): void {
        $normalizedStatuses = collect(
            $statuses
        )
            ->map(
                fn ($status): string =>
                    strtolower(
                        trim(
                            (string) $status
                        )
                    )
            )
            ->values()
            ->all();

        if (
            Schema::hasColumn(
                'incidents',
                'status_id'
            )
            && Schema::hasTable('statuses')
            && Schema::hasColumn(
                'statuses',
                'status_name'
            )
        ) {
            $query
                ->leftJoin(
                    'statuses as dashboard_statuses',
                    'dashboard_statuses.id',
                    '=',
                    'incidents.status_id'
                )
                ->whereIn(
                    DB::raw(
                        'LOWER(TRIM('
                            . SafeDatabaseIdentifier::wrap(
                                'dashboard_statuses.status_name'
                            )
                            . '))'
                    ),
                    $normalizedStatuses
                );

            return;
        }

        if (
            Schema::hasColumn(
                'incidents',
                'status'
            )
        ) {
            $query->whereIn(
                DB::raw(
                    'LOWER(TRIM('
                        . SafeDatabaseIdentifier::wrap(
                            'incidents.status'
                        )
                        . '))'
                ),
                $normalizedStatuses
            );

            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function applyIncidentOrdering(
        EloquentBuilder|QueryBuilder $query
    ): void {
        if (
            Schema::hasColumn(
                'incidents',
                'incident_datetime'
            )
        ) {
            $query->orderByDesc(
                'incidents.incident_datetime'
            );

            return;
        }

        if (
            Schema::hasColumn(
                'incidents',
                'reported_at'
            )
        ) {
            $query->orderByDesc(
                'incidents.reported_at'
            );

            return;
        }

        $query->orderByDesc(
            'incidents.created_at'
        );
    }

    private function countTanodOnDuty(): int
    {
        $onDutyValues = [
            'on_duty',
            'onduty',
        ];

        $tanodProfilesCount =
            $this->countOnDutyFromTable(
                table: 'tanod_profiles',
                statusColumns:
                    self::TANOD_STATUS_COLUMNS,
                tanodOnly: false,
                onDutyValues: $onDutyValues
            );

        if ($tanodProfilesCount > 0) {
            return $tanodProfilesCount;
        }

        $employeesCount =
            $this->countOnDutyFromTable(
                table: 'employees',
                statusColumns:
                    self::TANOD_STATUS_COLUMNS,
                tanodOnly: true,
                onDutyValues: $onDutyValues
            );

        if ($employeesCount > 0) {
            return $employeesCount;
        }

        $tanodsCount =
            $this->countOnDutyFromTable(
                table: 'tanods',
                statusColumns:
                    self::TANOD_STATUS_COLUMNS,
                tanodOnly: false,
                onDutyValues: $onDutyValues
            );

        if ($tanodsCount > 0) {
            return $tanodsCount;
        }

        $tanodRostersCount =
            $this->countOnDutyFromTable(
                table: 'tanod_rosters',
                statusColumns:
                    self::TANOD_STATUS_COLUMNS,
                tanodOnly: false,
                onDutyValues: $onDutyValues
            );

        if ($tanodRostersCount > 0) {
            return $tanodRostersCount;
        }

        return $this->countOnDutyFromTable(
            table: 'users',
            statusColumns:
                self::TANOD_STATUS_COLUMNS,
            tanodOnly: true,
            onDutyValues: $onDutyValues
        );
    }

    private function countOnDutyFromTable(
        string $table,
        array $statusColumns,
        bool $tanodOnly,
        array $onDutyValues
    ): int {
        $table = SafeDatabaseIdentifier::approved(
            $table,
            self::TANOD_SOURCE_TABLES
        );

        $statusColumns = array_map(
            static fn (string $column): string =>
                SafeDatabaseIdentifier::approved(
                    $column,
                    self::TANOD_STATUS_COLUMNS
                ),
            $statusColumns
        );

        if (! Schema::hasTable($table)) {
            return 0;
        }

        $existingStatusColumns =
            array_values(
                array_filter(
                    $statusColumns,
                    fn (string $column): bool =>
                        Schema::hasColumn(
                            $table,
                            $column
                        )
                )
            );

        if ($existingStatusColumns === []) {
            return 0;
        }

        $query = DB::table($table);

        if ($tanodOnly) {
            if (
                Schema::hasColumn(
                    $table,
                    'role'
                )
            ) {
                $query->whereRaw(
                    'LOWER('
                        . SafeDatabaseIdentifier::wrap(
                            $table . '.role'
                        )
                        . ') = ?',
                    [
                        'tanod',
                    ]
                );
            } elseif (
                Schema::hasColumn(
                    $table,
                    'employee_type'
                )
            ) {
                $query->whereRaw(
                    'LOWER('
                        . SafeDatabaseIdentifier::wrap(
                            $table
                                . '.employee_type'
                        )
                        . ') = ?',
                    [
                        'tanod',
                    ]
                );
            } elseif (
                Schema::hasColumn(
                    $table,
                    'position'
                )
            ) {
                $query->whereRaw(
                    'LOWER('
                        . SafeDatabaseIdentifier::wrap(
                            $table . '.position'
                        )
                        . ') LIKE ?',
                    [
                        '%tanod%',
                    ]
                );
            }
        }

        if (
            Schema::hasColumn(
                $table,
                'is_active'
            )
        ) {
            $query->where(
                $table . '.is_active',
                true
            );
        }

        $query->where(
            function ($statusQuery) use (
                $table,
                $existingStatusColumns,
                $onDutyValues
            ): void {
                foreach (
                    $existingStatusColumns
                    as $column
                ) {
                    $wrappedColumn =
                        SafeDatabaseIdentifier::wrap(
                            $table
                                . '.'
                                . $column
                        );

                    $statusQuery->orWhereIn(
                        DB::raw(
                            'LOWER(REPLACE(REPLACE(TRIM('
                                . $wrappedColumn
                                . "), ' ', '_'), '-', '_'))"
                        ),
                        $onDutyValues
                    );
                }
            }
        );

        return $query->count();
    }

    private function formatIncidents(
        Collection $incidents
    ): Collection {
        return $incidents
            ->map(
                fn ($incident): array => [
                    'id' =>
                        (int) data_get(
                            $incident,
                            'id'
                        ),

                    'incident_code' =>
                        data_get(
                            $incident,
                            'incident_code'
                        ),

                    'title' =>
                        data_get(
                            $incident,
                            'incident_title'
                        ),

                    'priority' =>
                        data_get(
                            $incident,
                            'priority'
                        ),

                    'status' =>
                        data_get(
                            $incident,
                            'currentStatus.status_name'
                        )
                        ?? data_get(
                            $incident,
                            'status.status_name'
                        )
                        ?? data_get(
                            $incident,
                            'status_name'
                        ),

                    'category' =>
                        data_get(
                            $incident,
                            'category.category_name'
                        )
                        ?? data_get(
                            $incident,
                            'category_name'
                        ),

                    'reporter_name' =>
                        data_get(
                            $incident,
                            'reporter.name'
                        ),

                    'assigned_tanod_name' =>
                        data_get(
                            $incident,
                            'assignedTanod.user.name'
                        )
                        ?? data_get(
                            $incident,
                            'assignedTanod.name'
                        ),

                    'incident_datetime' =>
                        $this->dateValue(
                            data_get(
                                $incident,
                                'incident_datetime'
                            )
                        ),

                    'reported_at' =>
                        $this->dateValue(
                            data_get(
                                $incident,
                                'reported_at'
                            )
                        ),

                    'created_at' =>
                        $this->dateValue(
                            data_get(
                                $incident,
                                'created_at'
                            )
                        ),
                ]
            )
            ->values();
    }

    private function dateValue(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (
            is_object($value)
            && method_exists(
                $value,
                'toIso8601String'
            )
        ) {
            return $value->toIso8601String();
        }

        return (string) $value;
    }
}