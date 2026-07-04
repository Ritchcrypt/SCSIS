<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\Status;
use App\Models\TanodProfile;
use Illuminate\Support\Facades\DB;
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
        | These are now real database counts.
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

        $tanodOnDuty = TanodProfile::where('duty_status', 'on_duty')->count();

        /*
        |--------------------------------------------------------------------------
        | Recent Incident Activity
        |--------------------------------------------------------------------------
        | Load the actual relationships used by the incident module.
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
}