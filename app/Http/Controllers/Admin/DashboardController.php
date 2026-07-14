<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\Status;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function index(): View
    {
        /*
        |--------------------------------------------------------------------------
        | Status ID Resolution
        |--------------------------------------------------------------------------
        | Incidents use status_id, not a plain status string column.
        | So dashboard counts must read from the statuses table.
        */

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
            ->whereIn(DB::raw('LOWER(status_name)'), $activeStatusNames)
            ->pluck('id')
            ->all();

        $resolvedStatusIds = Status::query()
            ->whereIn(DB::raw('LOWER(status_name)'), $resolvedStatusNames)
            ->pluck('id')
            ->all();

        /*
        |--------------------------------------------------------------------------
        | Dashboard Counts
        |--------------------------------------------------------------------------
        */

        $totalIncidents = Incident::count();

        $activeCases = ! empty($activeStatusIds)
            ? Incident::whereIn('status_id', $activeStatusIds)->count()
            : 0;

        $resolvedCases = ! empty($resolvedStatusIds)
            ? Incident::whereIn('status_id', $resolvedStatusIds)->count()
            : 0;

        $criticalIncidents = Incident::query()
            ->where('priority', 'critical')
            ->when(! empty($resolvedStatusIds), function ($query) use ($resolvedStatusIds) {
                $query->whereNotIn('status_id', $resolvedStatusIds);
            })
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Tanod On Duty Count
        |--------------------------------------------------------------------------
        | Active account is NOT the same as on duty.
        | This counts only records where the real duty/status field says On Duty.
        */

        $tanodOnDuty = $this->countTanodOnDuty();

        /*
        |--------------------------------------------------------------------------
        | Recent Incident Activity
        |--------------------------------------------------------------------------
        */

        $recentIncidents = Incident::with([
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

        return view('admin.dashboard', [
            'totalIncidents' => $totalIncidents,
            'activeCases' => $activeCases,
            'resolvedCases' => $resolvedCases,
            'criticalIncidents' => $criticalIncidents,
            'tanodOnDuty' => $tanodOnDuty,
            'recentIncidents' => $recentIncidents,
        ]);
    }

    private function countTanodOnDuty(): int
    {
        /*
        |--------------------------------------------------------------------------
        | Accepted On Duty Values
        |--------------------------------------------------------------------------
        | These normalized values handle:
        | on_duty
        | On Duty
        | on duty
        | on-duty
        | onduty
        */

        $onDutyValues = [
            'on_duty',
            'onduty',
        ];

        /*
        |--------------------------------------------------------------------------
        | 1. Count from tanod_profiles
        |--------------------------------------------------------------------------
        | This is the most likely source for Tanod Roster duty status.
        */

        $tanodProfilesCount = $this->countOnDutyFromTable(
            table: 'tanod_profiles',
            statusColumns: [
                'duty_status',
                'status',
                'availability_status',
                'duty_state',
            ],
            tanodOnly: false,
            onDutyValues: $onDutyValues
        );

        if ($tanodProfilesCount > 0) {
            return $tanodProfilesCount;
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Count from employees
        |--------------------------------------------------------------------------
        | Used if your roster stores tanod duty status in employees.
        */

        $employeesCount = $this->countOnDutyFromTable(
            table: 'employees',
            statusColumns: [
                'duty_status',
                'status',
                'availability_status',
                'duty_state',
            ],
            tanodOnly: true,
            onDutyValues: $onDutyValues
        );

        if ($employeesCount > 0) {
            return $employeesCount;
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Count from tanods table
        |--------------------------------------------------------------------------
        | Safety fallback if your roster table is named tanods.
        */

        $tanodsCount = $this->countOnDutyFromTable(
            table: 'tanods',
            statusColumns: [
                'duty_status',
                'status',
                'availability_status',
                'duty_state',
            ],
            tanodOnly: false,
            onDutyValues: $onDutyValues
        );

        if ($tanodsCount > 0) {
            return $tanodsCount;
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Count from tanod_rosters table
        |--------------------------------------------------------------------------
        | Safety fallback if your roster table is named tanod_rosters.
        */

        $tanodRostersCount = $this->countOnDutyFromTable(
            table: 'tanod_rosters',
            statusColumns: [
                'duty_status',
                'status',
                'availability_status',
                'duty_state',
            ],
            tanodOnly: false,
            onDutyValues: $onDutyValues
        );

        if ($tanodRostersCount > 0) {
            return $tanodRostersCount;
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Count from users only if users has a real duty/status column
        |--------------------------------------------------------------------------
        | This does NOT count plain active users as on duty.
        */

        return $this->countOnDutyFromTable(
            table: 'users',
            statusColumns: [
                'duty_status',
                'status',
                'availability_status',
                'duty_state',
            ],
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
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $existingStatusColumns = array_values(array_filter(
            $statusColumns,
            fn ($column) => Schema::hasColumn($table, $column)
        ));

        if (empty($existingStatusColumns)) {
            return 0;
        }

        $query = DB::table($table);

        /*
        |--------------------------------------------------------------------------
        | Filter to Tanod records when possible
        |--------------------------------------------------------------------------
        */

        if ($tanodOnly) {
            if (Schema::hasColumn($table, 'role')) {
                $query->whereRaw("LOWER({$table}.role) = ?", ['tanod']);
            } elseif (Schema::hasColumn($table, 'employee_type')) {
                $query->whereRaw("LOWER({$table}.employee_type) = ?", ['tanod']);
            } elseif (Schema::hasColumn($table, 'position')) {
                $query->whereRaw("LOWER({$table}.position) LIKE ?", ['%tanod%']);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Do not count inactive accounts/records when the column exists
        |--------------------------------------------------------------------------
        */

        if (Schema::hasColumn($table, 'is_active')) {
            $query->where("{$table}.is_active", true);
        }

        /*
        |--------------------------------------------------------------------------
        | Count only On Duty status
        |--------------------------------------------------------------------------
        | This does NOT count:
        | active
        | available
        | off_duty
        | inactive
        */

        $query->where(function ($statusQuery) use ($table, $existingStatusColumns, $onDutyValues) {
            foreach ($existingStatusColumns as $column) {
                $statusQuery->orWhereIn(
                    DB::raw("LOWER(REPLACE(REPLACE(TRIM({$table}.{$column}), ' ', '_'), '-', '_'))"),
                    $onDutyValues
                );
            }
        });

        return $query->count();
    }
}