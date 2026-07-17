<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\CaseRecord;
use App\Models\Employee;
use App\Models\Incident;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $data = $this->reportData($request);

        return view('reports.index', $data);
    }

    public function downloadPdf(Request $request)
{
    $data = $this->reportData($request);

    $fileName = 'barangay-report-' . strtolower(str_replace(' ', '-', $data['periodLabel'])) . '-' . now()->format('Ymd-His') . '.pdf';

    $pdf = Pdf::loadView('reports.pdf', $data)
        ->setPaper('a4', 'portrait');

    return $pdf->download($fileName);
}

    private function reportData(Request $request): array
    {
        $period = $this->validPeriod($request->query('period', 'week'));
        [$startDate, $endDate, $periodLabel] = $this->periodRange($period);

        $incidentBase = $this->incidentBaseQuery($startDate, $endDate);

        $totalIncidents = (clone $incidentBase)->count();

        $activeIncidents = (clone $incidentBase)
            ->where(function ($query) {
                $query->whereDoesntHave('currentStatus')
                    ->orWhereHas('currentStatus', function ($statusQuery) {
                        $statusQuery->whereNotIn(DB::raw('LOWER(status_name)'), [
                            'resolved',
                            'closed',
                            'completed',
                            'cancelled',
                            'canceled',
                            'rejected',
                        ]);
                    });
            })
            ->count();

        $resolvedIncidents = (clone $incidentBase)
            ->whereHas('currentStatus', function ($query) {
                $query->whereIn(DB::raw('LOWER(status_name)'), [
                    'resolved',
                    'closed',
                    'completed',
                ]);
            })
            ->count();

        $casesFiled = $this->countTableByDate('case_records', $startDate, $endDate);
        $announcements = $this->countTableByDate('announcements', $startDate, $endDate);
$currentUser = Auth::user();

        return [
            'period' => $period,
            'periodLabel' => $periodLabel,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'generatedAt' => now(),
            'generatedBy' => $currentUser ? $currentUser->name : 'System',

            'totalIncidents' => $totalIncidents,
            'activeIncidents' => $activeIncidents,
            'resolvedIncidents' => $resolvedIncidents,
            'casesFiled' => $casesFiled,
            'announcements' => $announcements,

            'records' => $this->recordsBreakdown($startDate, $endDate),
            'statusSummary' => $this->statusSummary($startDate, $endDate),
            'severitySummary' => $this->severitySummary($startDate, $endDate),
            'barangaySummary' => $this->barangaySummary($startDate, $endDate),
            'tanodSummary' => $this->tanodResponseSummary($startDate, $endDate),
        ];
    }

    private function incidentBaseQuery(Carbon $startDate, Carbon $endDate)
    {
        return Incident::query()
            ->with(['barangay', 'category', 'currentStatus'])
            ->whereBetween('created_at', [$startDate, $endDate]);
    }

    private function recordsBreakdown(Carbon $startDate, Carbon $endDate): array
    {
        $records = [];

        $incidents = Incident::query()
            ->with(['category', 'barangay', 'currentStatus'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->latest()
            ->limit(50)
            ->get();

        foreach ($incidents as $incident) {
            $records[] = [
                'category' => 'Incident',
                'title' => ($incident->incident_code ?? 'INC-' . $incident->id) . ' - ' . ($incident->incident_title ?? $incident->title ?? 'Untitled Incident'),
                'type' => $incident->category?->category_name ?? $incident->category?->name ?? 'Uncategorized',
                'status' => $incident->currentStatus?->status_name
                    ?? $incident->currentStatus?->name
                    ?? 'Pending',
                'severity' => ucfirst((string) (
                    $incident->priority
                    ?? $incident->severity
                    ?? 'Low'
                )),
                'barangay' => $incident->barangay?->barangay_name
                    ?? $incident->barangay?->name
                    ?? 'Unknown barangay',
                'datetime' => optional($incident->created_at)->format('M d, Y h:i A') ?? '-',
                'sort_date' => optional($incident->created_at)->timestamp ?? 0,
            ];
        }

        if (class_exists(CaseRecord::class) && Schema::hasTable('case_records')) {
            $cases = CaseRecord::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->latest()
                ->limit(50)
                ->get();

            foreach ($cases as $case) {
                $records[] = [
                    'category' => 'Case',
                    'title' => 'Case No. ' . ($case->case_number ?? $case->id) . ' - ' . ($case->title ?? $case->case_title ?? 'Barangay case record'),
                    'type' => ucfirst(str_replace('_', ' ', (string) ($case->case_type ?? 'case'))),
                    'status' => '—',
                    'severity' => '—',
                    'barangay' => '—',
                    'datetime' => optional($case->created_at)->format('M d, Y h:i A') ?? '-',
                    'sort_date' => optional($case->created_at)->timestamp ?? 0,
                ];
            }
        }

        if (class_exists(Announcement::class) && Schema::hasTable('announcements')) {
            $announcementRecords = Announcement::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->latest()
                ->limit(50)
                ->get();

            foreach ($announcementRecords as $announcement) {
                $records[] = [
                    'category' => 'Announcement',
                    'title' => $announcement->title ?? 'Announcement',
                    'type' => ucfirst(str_replace('_', ' ', (string) ($announcement->category ?? 'announcement'))),
                    'status' => '—',
                    'severity' => '—',
                    'barangay' => '—',
                    'datetime' => optional($announcement->created_at)->format('M d, Y h:i A') ?? '-',
                    'sort_date' => optional($announcement->created_at)->timestamp ?? 0,
                ];
            }
        }

        usort($records, function ($a, $b) {
            return $b['sort_date'] <=> $a['sort_date'];
        });

        return array_slice($records, 0, 50);
    }

    private function statusSummary(Carbon $startDate, Carbon $endDate): array
    {
        return Incident::query()
            ->select('status_id', DB::raw('COUNT(*) as total'))
            ->with('currentStatus')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('status_id')
            ->get()
            ->map(function ($row) {
                return [
                    'label' => $row->currentStatus?->status_name ?? 'Unknown',
                    'total' => $row->total,
                ];
            })
            ->values()
            ->all();
    }

    private function severitySummary(Carbon $startDate, Carbon $endDate): array
    {
        if (! Schema::hasColumn('incidents', 'priority')) {
            return [];
        }

        return Incident::query()
            ->select('priority', DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('priority')
            ->orderByRaw("FIELD(priority, 'critical', 'high', 'moderate', 'low')")
            ->get()
            ->map(function ($row) {
                return [
                    'label' => ucfirst((string) $row->priority),
                    'total' => $row->total,
                ];
            })
            ->values()
            ->all();
    }

    private function barangaySummary(Carbon $startDate, Carbon $endDate): array
    {
        return Incident::query()
            ->select('barangay_id', DB::raw('COUNT(*) as total'))
            ->with('barangay')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('barangay_id')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                return [
                    'label' => $row->barangay?->barangay_name ?? $row->barangay?->name ?? 'Unknown barangay',
                    'total' => $row->total,
                ];
            })
            ->values()
            ->all();
    }

    private function tanodResponseSummary(Carbon $startDate, Carbon $endDate)
    {
        if (
            ! Schema::hasTable('employees')
            || ! Schema::hasTable('tanod_tasks')
            || ! Schema::hasTable('tanod_task_responses')
            || ! Schema::hasColumn('tanod_task_responses', 'employee_id')
            || ! Schema::hasColumn('tanod_task_responses', 'tanod_task_id')
            || ! Schema::hasColumn('tanod_task_responses', 'response_status')
        ) {
            return collect();
        }

        $taskDateColumn = Schema::hasColumn('tanod_tasks', 'created_at')
            ? 'tasks.created_at'
            : null;

        $responseDateColumn = Schema::hasColumn('tanod_task_responses', 'created_at')
            ? 'responses.created_at'
            : null;

        $hasRespondedAt = Schema::hasColumn('tanod_task_responses', 'responded_at');
        $hasUpdatedAt = Schema::hasColumn('tanod_task_responses', 'updated_at');

        return Employee::query()
            ->with('user')
            ->when(Schema::hasColumn('employees', 'employee_type'), function ($query) {
                $query->where('employee_type', 'tanod');
            })
            ->orderBy('id')
            ->get()
            ->map(function ($employee) use (
                $startDate,
                $endDate,
                $taskDateColumn,
                $responseDateColumn,
                $hasRespondedAt,
                $hasUpdatedAt
            ) {
                $responsesQuery = DB::table('tanod_task_responses as responses')
                    ->join(
                        'tanod_tasks as tasks',
                        'tasks.id',
                        '=',
                        'responses.tanod_task_id'
                    )
                    ->where('responses.employee_id', $employee->id);

                if ($taskDateColumn) {
                    $responsesQuery->whereBetween(
                        $taskDateColumn,
                        [$startDate, $endDate]
                    );
                } elseif ($responseDateColumn) {
                    $responsesQuery->whereBetween(
                        $responseDateColumn,
                        [$startDate, $endDate]
                    );
                }

                $totalTasks = (clone $responsesQuery)->count();

                $accepted = (clone $responsesQuery)
                    ->whereRaw(
                        "LOWER(COALESCE(responses.response_status, 'pending')) = ?",
                        ['accepted']
                    )
                    ->count();

                $declined = (clone $responsesQuery)
                    ->whereRaw(
                        "LOWER(COALESCE(responses.response_status, 'pending')) = ?",
                        ['declined']
                    )
                    ->count();

                $pending = (clone $responsesQuery)
                    ->where(function ($query) {
                        $query->whereNull('responses.response_status')
                            ->orWhereRaw(
                                "LOWER(responses.response_status) = ?",
                                ['pending']
                            );
                    })
                    ->count();

                $responded = $accepted + $declined;

                $responseRate = $totalTasks > 0
                    ? round(($responded / $totalTasks) * 100)
                    : 0;

                $lastResponseValue = null;

                if ($hasRespondedAt) {
                    $lastResponseValue = (clone $responsesQuery)
                        ->whereNotNull('responses.responded_at')
                        ->max('responses.responded_at');
                }

                if (! $lastResponseValue && $hasUpdatedAt) {
                    $lastResponseValue = (clone $responsesQuery)
                        ->max('responses.updated_at');
                }

                try {
                    $lastResponse = $lastResponseValue
                        ? Carbon::parse($lastResponseValue)->format('M d, Y h:i A')
                        : 'No response yet';
                } catch (\Throwable $e) {
                    $lastResponse = 'No response yet';
                }

                return [
                    'name' => $employee->user?->name
                        ?? 'Tanod #' . $employee->id,
                    'total_tasks' => $totalTasks,
                    'accepted' => $accepted,
                    'declined' => $declined,
                    'pending' => $pending,
                    'response_rate' => $responseRate,
                    'last_response' => $lastResponse,
                ];
            })
            ->filter(function ($row) {
                return $row['total_tasks'] > 0;
            })
            ->values();
    }

    private function countTableByDate(string $table, Carbon $startDate, Carbon $endDate): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        if (! Schema::hasColumn($table, 'created_at')) {
            return DB::table($table)->count();
        }

        return DB::table($table)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
    }

    private function validPeriod(?string $period): string
    {
        return in_array($period, ['today', 'week', 'month', 'year'], true)
            ? $period
            : 'week';
    }

    private function periodRange(string $period): array
    {
        return match ($period) {
            'today' => [
                now()->startOfDay(),
                now()->endOfDay(),
                'Today',
            ],
            'month' => [
                now()->startOfMonth(),
                now()->endOfMonth(),
                'This Month',
            ],
            'year' => [
                now()->startOfYear(),
                now()->endOfYear(),
                'This Year',
            ],
            default => [
                now()->startOfWeek(),
                now()->endOfWeek(),
                'This Week',
            ],
        };
    }
}