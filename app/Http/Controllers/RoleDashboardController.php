<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class RoleDashboardController extends Controller
{
    public function official(Request $request): View
    {
        return view('dashboards.official', [
            'summary' => [
                'total_incidents' => $this->incidentCount(),
                'pending_incidents' => $this->incidentStatusCount(['pending', 'reported']),
                'active_incidents' => $this->incidentStatusCount(['active', 'dispatched', 'responding', 'in progress', 'in_progress', 'escalated']),
                'resolved_incidents' => $this->incidentStatusCount(['resolved', 'closed', 'completed']),
            ],
            'latestIncidents' => $this->latestIncidents(),
        ]);
    }

    public function tanod(Request $request): View
    {
        $user = $request->user();

        $employee = Schema::hasTable('employees') && Schema::hasColumn('employees', 'user_id')
            ? DB::table('employees')->where('user_id', $user->id)->first()
            : null;

        $employeeId = $employee->id ?? null;

        return view('dashboards.tanod', [
            'summary' => [
                'assigned_incidents' => $this->tanodAssignedIncidentCount($employeeId),
                'open_tasks' => $this->tanodTaskCount($user->id, $employeeId, ['pending']),
                'accepted_tasks' => $this->tanodTaskCount($user->id, $employeeId, ['accepted']),
                'unread_alerts' => $this->tanodUnreadAlertCount($user->id),
            ],
            'assignedIncidents' => $this->latestTanodAssignedIncidents($employeeId),
            'latestTasks' => $this->latestTanodTasks($user->id, $employeeId),
            'latestAlerts' => $this->latestTanodAlerts($user->id),
        ]);
    }

    public function resident(Request $request): View
    {
        $user = $request->user();

        return view('dashboards.resident', [
            'summary' => [
                'my_reports' => $this->residentIncidentCount($user->id),
                'pending_reports' => $this->residentIncidentStatusCount($user->id, ['pending', 'reported']),
                'active_reports' => $this->residentIncidentStatusCount($user->id, ['active', 'dispatched', 'responding', 'in progress', 'in_progress', 'escalated']),
                'resolved_reports' => $this->residentIncidentStatusCount($user->id, ['resolved', 'closed', 'completed']),
                'announcements' => $this->activeAnnouncementCount(),
            ],
            'myLatestReports' => $this->latestResidentReports($user->id),
            'latestAnnouncements' => $this->latestAnnouncements(),
        ]);
    }

    private function incidentCount(): int
    {
        if (! Schema::hasTable('incidents')) {
            return 0;
        }

        return DB::table('incidents')->count();
    }

    private function incidentStatusCount(array $statuses): int
    {
        if (! Schema::hasTable('incidents')) {
            return 0;
        }

        $query = DB::table('incidents');

        $this->applyIncidentStatusFilter($query, $statuses);

        return $query->count();
    }

    private function tanodAssignedIncidentCount(?int $employeeId): int
    {
        if (! $employeeId || ! Schema::hasTable('incidents') || ! Schema::hasColumn('incidents', 'assigned_to')) {
            return 0;
        }

        return DB::table('incidents')
            ->where('assigned_to', $employeeId)
            ->count();
    }

    private function tanodTaskCount(int $userId, ?int $employeeId, array $statuses): int
    {
        if (! Schema::hasTable('tanod_task_responses')) {
            return 0;
        }

        $query = DB::table('tanod_task_responses');

        if (Schema::hasColumn('tanod_task_responses', 'response_status')) {
            $query->whereIn('response_status', $statuses);
        } elseif (Schema::hasColumn('tanod_task_responses', 'status')) {
            $query->whereIn('status', $statuses);
        } else {
            return 0;
        }

        if ($employeeId && Schema::hasColumn('tanod_task_responses', 'employee_id')) {
            $query->where('employee_id', $employeeId);
        } elseif (Schema::hasColumn('tanod_task_responses', 'user_id')) {
            $query->where('user_id', $userId);
        } else {
            return 0;
        }

        return $query->count();
    }

    private function tanodUnreadAlertCount(int $userId): int
    {
        if (! Schema::hasTable('notifications') || ! Schema::hasColumn('notifications', 'user_id')) {
            return 0;
        }

        $query = DB::table('notifications')->where('user_id', $userId);

        if (Schema::hasColumn('notifications', 'is_read')) {
            $query->where('is_read', false);
        }

        return $query->count();
    }

    private function residentIncidentCount(int $userId): int
    {
        if (! Schema::hasTable('incidents')) {
            return 0;
        }

        $query = DB::table('incidents');

        $this->applyResidentIncidentFilter($query, $userId);

        return $query->count();
    }

    private function residentIncidentStatusCount(int $userId, array $statuses): int
    {
        if (! Schema::hasTable('incidents')) {
            return 0;
        }

        $query = DB::table('incidents');

        $this->applyResidentIncidentFilter($query, $userId);
        $this->applyIncidentStatusFilter($query, $statuses);

        return $query->count();
    }

    private function activeAnnouncementCount(): int
    {
        if (! Schema::hasTable('announcements')) {
            return 0;
        }

        $query = DB::table('announcements');

        if (Schema::hasColumn('announcements', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query->count();
    }

    private function latestIncidents(int $limit = 6): Collection
    {
        if (! Schema::hasTable('incidents')) {
            return collect();
        }

        $query = DB::table('incidents')
            ->leftJoin('statuses', 'statuses.id', '=', 'incidents.status_id')
            ->leftJoin('incident_categories', 'incident_categories.id', '=', 'incidents.category_id')
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

        return $query->limit($limit)->get();
    }

    private function latestTanodAssignedIncidents(?int $employeeId, int $limit = 6): Collection
    {
        if (! $employeeId || ! Schema::hasTable('incidents') || ! Schema::hasColumn('incidents', 'assigned_to')) {
            return collect();
        }

        $query = DB::table('incidents')
            ->leftJoin('statuses', 'statuses.id', '=', 'incidents.status_id')
            ->leftJoin('incident_categories', 'incident_categories.id', '=', 'incidents.category_id')
            ->where('incidents.assigned_to', $employeeId)
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

        return $query->limit($limit)->get();
    }

    private function latestResidentReports(int $userId, int $limit = 6): Collection
    {
        if (! Schema::hasTable('incidents')) {
            return collect();
        }

        $query = DB::table('incidents')
            ->leftJoin('statuses', 'statuses.id', '=', 'incidents.status_id')
            ->leftJoin('incident_categories', 'incident_categories.id', '=', 'incidents.category_id')
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

        $this->applyResidentIncidentFilter($query, $userId);
        $this->applyIncidentOrdering($query);

        return $query->limit($limit)->get();
    }

    private function latestTanodTasks(int $userId, ?int $employeeId, int $limit = 5): Collection
    {
        if (! Schema::hasTable('tanod_task_responses')) {
            return collect();
        }

        $responseTable = 'tanod_task_responses';
        $taskTable = 'tanod_tasks';

        $query = DB::table($responseTable);

        $selects = [
            $responseTable . '.id',
            $responseTable . '.created_at',
        ];

        if (Schema::hasColumn($responseTable, 'response_status')) {
            $selects[] = $responseTable . '.response_status';
        } else {
            $selects[] = DB::raw('NULL as response_status');
        }

        if (Schema::hasColumn($responseTable, 'status')) {
            $selects[] = $responseTable . '.status';
        } else {
            $selects[] = DB::raw('NULL as status');
        }

        if (Schema::hasTable($taskTable) && Schema::hasColumn($responseTable, 'tanod_task_id')) {
            $query->leftJoin($taskTable, $taskTable . '.id', '=', $responseTable . '.tanod_task_id');

            if (Schema::hasColumn($taskTable, 'task_title')) {
                $selects[] = $taskTable . '.task_title';
            } elseif (Schema::hasColumn($taskTable, 'title')) {
                $selects[] = DB::raw($taskTable . '.title as task_title');
            } else {
                $selects[] = DB::raw("'Tanod Task' as task_title");
            }

            if (Schema::hasColumn($taskTable, 'task_description')) {
                $selects[] = $taskTable . '.task_description';
            } elseif (Schema::hasColumn($taskTable, 'description')) {
                $selects[] = DB::raw($taskTable . '.description as task_description');
            } else {
                $selects[] = DB::raw("'No task description provided.' as task_description");
            }

            if (Schema::hasColumn($taskTable, 'priority')) {
                $selects[] = $taskTable . '.priority';
            } else {
                $selects[] = DB::raw("'normal' as priority");
            }
        } else {
            $selects[] = DB::raw("'Tanod Task' as task_title");
            $selects[] = DB::raw("'No task description provided.' as task_description");
            $selects[] = DB::raw("'normal' as priority");
        }

        $query->select($selects);

        if ($employeeId && Schema::hasColumn($responseTable, 'employee_id')) {
            $query->where($responseTable . '.employee_id', $employeeId);
        } elseif (Schema::hasColumn($responseTable, 'user_id')) {
            $query->where($responseTable . '.user_id', $userId);
        } else {
            return collect();
        }

        return $query
            ->latest($responseTable . '.created_at')
            ->limit($limit)
            ->get();
    }

    private function latestTanodAlerts(int $userId, int $limit = 5): Collection
    {
        if (! Schema::hasTable('notifications') || ! Schema::hasColumn('notifications', 'user_id')) {
            return collect();
        }

        return DB::table('notifications')
            ->where('user_id', $userId)
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    private function latestAnnouncements(int $limit = 5): Collection
    {
        if (! Schema::hasTable('announcements')) {
            return collect();
        }

        $query = DB::table('announcements');

        if (Schema::hasColumn('announcements', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    private function applyResidentIncidentFilter($query, int $userId): void
    {
        $hasReporter = Schema::hasColumn('incidents', 'reporter_id');
        $hasResident = Schema::hasColumn('incidents', 'resident_id');

        if ($hasReporter && $hasResident) {
            $query->where(function ($residentQuery) use ($userId) {
                $residentQuery
                    ->where('incidents.reporter_id', $userId)
                    ->orWhere('incidents.resident_id', $userId);
            });

            return;
        }

        if ($hasReporter) {
            $query->where('incidents.reporter_id', $userId);
            return;
        }

        if ($hasResident) {
            $query->where('incidents.resident_id', $userId);
            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function applyIncidentStatusFilter($query, array $statuses): void
    {
        $normalizedStatuses = collect($statuses)
            ->map(fn ($status) => strtolower((string) $status))
            ->values()
            ->all();

        if (Schema::hasColumn('incidents', 'status')) {
            $query->whereIn(DB::raw('LOWER(incidents.status)'), $normalizedStatuses);
            return;
        }

        if (
            Schema::hasColumn('incidents', 'status_id')
            && Schema::hasTable('statuses')
            && Schema::hasColumn('statuses', 'status_name')
        ) {
            $query
                ->join('statuses', 'statuses.id', '=', 'incidents.status_id')
                ->whereIn(DB::raw('LOWER(statuses.status_name)'), $normalizedStatuses);

            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function applyIncidentOrdering($query): void
    {
        if (Schema::hasColumn('incidents', 'incident_datetime')) {
            $query->orderByDesc('incidents.incident_datetime');
            return;
        }

        if (Schema::hasColumn('incidents', 'reported_at')) {
            $query->orderByDesc('incidents.reported_at');
            return;
        }

        $query->orderByDesc('incidents.created_at');
    }
}