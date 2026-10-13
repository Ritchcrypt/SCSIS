<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Announcement;
use App\Models\Employee;
use App\Models\Incident;
use App\Models\User;
use App\Services\WeatherDisasterFeedService;
use App\Support\SafeDatabaseIdentifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DashboardParityController extends DashboardController
{
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
        $response = parent::index($request);

        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        $payload = $response->getData(true);
        $data = is_array($payload['data'] ?? null)
            ? $payload['data']
            : [];

        $user = $request->user();
        $role = strtolower(
            trim((string) ($data['user']['role'] ?? $user?->role ?? ''))
        );

        if (in_array($role, ['official', 'dao'], true)) {
            $summary = is_array($data['summary'] ?? null)
                ? $data['summary']
                : [];

            $pendingIncidents = (int) ($summary['pending_incidents'] ?? 0);
            $activeIncidents = (int) ($summary['active_incidents'] ?? 0);

            $summary['active_cases'] = $pendingIncidents + $activeIncidents;
            $summary['resolved_cases'] = (int) ($summary['resolved_incidents'] ?? 0);
            $summary['critical_incidents'] = $this->criticalIncidentCount();
            $summary['tanod_on_duty'] = $this->countTanodOnDuty();

            $data['summary'] = $summary;
        }

        if ($role === 'tanod' && $user instanceof User) {
            $data['recent_incidents'] = $this->tanodRecentIncidents($user);
        }

        if ($role === 'resident' && $user instanceof User) {
            $this->applyResidentAnnouncementVisibility($data, $user);
        }

        $data['weather_feed'] = $this->weatherFeed();
        $payload['data'] = $data;

        return response()->json(
            $payload,
            $response->getStatusCode()
        );
    }

    private function applyResidentAnnouncementVisibility(
        array &$data,
        User $user
    ): void {
        $query = $this->visibleAnnouncementQuery($user);

        $summary = is_array($data['summary'] ?? null)
            ? $data['summary']
            : [];

        $summary['announcements'] = (clone $query)->count();
        $data['summary'] = $summary;

        $latestQuery = clone $query;

        if (Schema::hasColumn('announcements', 'published_at')) {
            $latestQuery->orderByDesc('published_at');
        }

        $latestQuery->orderByDesc('created_at');

        $data['latest_announcements'] = $latestQuery
            ->limit(5)
            ->get()
            ->map(
                fn (Announcement $announcement): array => [
                    'id' => (int) $announcement->id,
                    'title' => (string) $announcement->title,
                    'content' => (string) $announcement->content,
                    'category' => (string) $announcement->category,
                    'priority' => (string) $announcement->priority,
                    'audience' => (string) $announcement->audience,
                    'published_at' => $this->dateValue(
                        $announcement->published_at
                    ),
                ]
            )
            ->values()
            ->all();
    }

    private function visibleAnnouncementQuery(User $user): Builder
    {
        $query = Announcement::query();

        if (Schema::hasColumn('announcements', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('announcements', 'audience')) {
            $query->whereIn('audience', [
                'everyone',
                'public',
                'all',
                'residents',
                'resident',
            ]);
        }

        $barangayTargetingReady =
            Schema::hasTable('announcement_barangay')
            && Schema::hasColumn('users', 'barangay_id');

        if (! $barangayTargetingReady) {
            return $query;
        }

        if (empty($user->barangay_id)) {
            return $query->whereDoesntHave('targetBarangays');
        }

        return $query->where(
            function (Builder $visibilityQuery) use ($user): void {
                $visibilityQuery
                    ->whereDoesntHave('targetBarangays')
                    ->orWhereHas(
                        'targetBarangays',
                        function (Builder $barangayQuery) use ($user): void {
                            $barangayQuery->where(
                                'barangays.id',
                                (int) $user->barangay_id
                            );
                        }
                    );
            }
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tanodRecentIncidents(User $user): array
    {
        if (! Schema::hasTable('incidents')) {
            return [];
        }

        $employeeId = Employee::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('employee_type', 'tanod')
            ->value('id');

        if (! $employeeId) {
            return [];
        }

        $query = Incident::query()
            ->with([
                'currentStatus',
                'status',
                'category',
                'reporter',
                'assignedTanod.user',
            ])
            ->where('assigned_to', (int) $employeeId);

        if (Schema::hasColumn('incidents', 'incident_datetime')) {
            $query->orderByDesc('incident_datetime');
        } elseif (Schema::hasColumn('incidents', 'reported_at')) {
            $query->orderByDesc('reported_at');
        } else {
            $query->orderByDesc('created_at');
        }

        return $query
            ->limit(6)
            ->get()
            ->map(
                fn (Incident $incident): array => [
                    'id' => (int) $incident->id,
                    'incident_code' => $incident->incident_code,
                    'title' => $incident->incident_title,
                    'priority' => $incident->priority,
                    'status' => $incident->currentStatus?->status_name
                        ?? $incident->status?->status_name,
                    'category' => $incident->category?->category_name,
                    'reporter_name' => $incident->reporter?->name,
                    'assigned_tanod_name' =>
                        $incident->assignedTanod?->user?->name,
                    'incident_datetime' =>
                        $this->dateValue($incident->incident_datetime),
                    'reported_at' =>
                        $this->dateValue($incident->reported_at),
                    'created_at' =>
                        $this->dateValue($incident->created_at),
                ]
            )
            ->values()
            ->all();
    }

    private function weatherFeed(): array
    {
        try {
            return app(WeatherDisasterFeedService::class)->feed();
        } catch (Throwable) {
            return [
                'weather' => [
                    'location' => 'Dao, Capiz',
                    'temperature' => null,
                    'feels_like' => null,
                    'humidity' => null,
                    'wind_speed' => null,
                    'rain_chance' => null,
                    'condition' => 'Weather feed temporarily unavailable',
                    'icon' => '🌤️',
                    'risk_level' => 'normal',
                    'advisory' => 'Weather service is temporarily unavailable. The dashboard can still be used safely.',
                    'source' => 'Local fallback',
                    'updated_at' => now()->format('M d, Y h:i A'),
                    'status_message' => 'Local fallback mode.',
                ],
                'advisories' => [],
                'announcements_url' => null,
            ];
        }
    }

    private function criticalIncidentCount(): int
    {
        if (! Schema::hasTable('incidents')) {
            return 0;
        }

        if (Schema::hasColumn('incidents', 'priority')) {
            return Incident::query()
                ->whereRaw('LOWER(TRIM(priority)) = ?', ['critical'])
                ->count();
        }

        if (Schema::hasColumn('incidents', 'severity')) {
            return Incident::query()
                ->whereRaw('LOWER(TRIM(severity)) = ?', ['critical'])
                ->count();
        }

        return 0;
    }

    private function countTanodOnDuty(): int
    {
        $onDutyValues = [
            'on_duty',
            'onduty',
        ];

        $sources = [
            ['tanod_profiles', false],
            ['employees', true],
            ['tanods', false],
            ['tanod_rosters', false],
            ['users', true],
        ];

        foreach ($sources as [$table, $tanodOnly]) {
            $count = $this->countOnDutyFromTable(
                table: $table,
                statusColumns: self::TANOD_STATUS_COLUMNS,
                tanodOnly: $tanodOnly,
                onDutyValues: $onDutyValues
            );

            if ($count > 0) {
                return $count;
            }
        }

        return 0;
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

        $existingStatusColumns = array_values(
            array_filter(
                $statusColumns,
                fn (string $column): bool =>
                    Schema::hasColumn($table, $column)
            )
        );

        if ($existingStatusColumns === []) {
            return 0;
        }

        $query = DB::table($table);

        if ($tanodOnly) {
            if (Schema::hasColumn($table, 'role')) {
                $query->whereRaw(
                    'LOWER('
                        . SafeDatabaseIdentifier::wrap($table . '.role')
                        . ') = ?',
                    ['tanod']
                );
            } elseif (Schema::hasColumn($table, 'employee_type')) {
                $query->whereRaw(
                    'LOWER('
                        . SafeDatabaseIdentifier::wrap($table . '.employee_type')
                        . ') = ?',
                    ['tanod']
                );
            } elseif (Schema::hasColumn($table, 'position')) {
                $query->whereRaw(
                    'LOWER('
                        . SafeDatabaseIdentifier::wrap($table . '.position')
                        . ') LIKE ?',
                    ['%tanod%']
                );
            }
        }

        if (Schema::hasColumn($table, 'is_active')) {
            $query->where($table . '.is_active', true);
        }

        $query->where(
            function ($statusQuery) use (
                $table,
                $existingStatusColumns,
                $onDutyValues
            ): void {
                foreach ($existingStatusColumns as $column) {
                    $wrappedColumn = SafeDatabaseIdentifier::wrap(
                        $table . '.' . $column
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

    private function dateValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (
            is_object($value)
            && method_exists($value, 'toIso8601String')
        ) {
            return $value->toIso8601String();
        }

        return (string) $value;
    }
}
