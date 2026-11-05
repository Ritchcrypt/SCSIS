<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\EmergencyHotline;
use App\Models\Incident;
use App\Models\MobileEmergencyAlert;
use App\Models\ReportSnapshot;
use App\Models\ResidentComplaint;
use App\Models\TanodProfile;
use App\Models\TanodTask;
use App\Models\TanodTaskResponse;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Website sidebar-only review badges, independent of notification read state.
 *
 * Counters are per account and per module. A visit to an authorized module
 * acknowledges its visible updates; it never updates notifications.is_read,
 * acknowledges operational alerts, nor sends an FCM message.
 */
class SidebarModuleBadgeService
{
    private const ALERT_TYPES = [
        'assigned_incident', 'incident_assigned', 'new_assigned_incident',
        'incident', 'incident_reported', 'incident_update', 'incident_updated',
        'incident_status_update', 'status_update', 'dispatch', 'escalation',
        'emergency', 'resolved', 'tanod_task', 'tanod_task_assigned',
        'task_assigned', 'tanod_task_update', 'task_update', 'tanod_alert',
        'community_problem', 'community',
    ];

    /** @var list<string> */
    private const MODULES = [
        'dashboard', 'incidents', 'distress', 'tanod-alerts', 'tanods',
        'tanod-tasks', 'announcements', 'emergency-mode',
        'resident-complaints', 'map', 'reports', 'users', 'activity-logs',
    ];

    /**
     * Route allowlist mirrors the role-specific website sidebar. Used by
     * authenticated polling so the browser cannot request another role's data.
     * Missing/retired routes are ignored by the caller's menu renderer.
     *
     * @return array<int, array{route: string}>
     */
    public function menuRoutesForRole(User $user): array
    {
        $routes = match (strtolower(trim((string) $user->role))) {
            'admin' => [
                'admin.dashboard', 'admin.incidents.index',
                'admin.tanod-alerts.index', 'admin.tanods.index',
                'admin.tanod-tasks.index', 'admin.announcements.index',
                'admin.emergency-mode.index', 'admin.resident-complaints.index',
                'admin.map.index', 'admin.reports.index', 'admin.users.index',
                'admin.activity-logs.index',
            ],
            'official', 'dao' => [
                'official.dashboard', 'official.incidents.index',
                'official.tanods.index', 'official.announcements.index',
                'official.emergency-mode.index',
                'official.resident-complaints.index', 'official.map.index',
            ],
            'tanod' => [
                'tanod.dashboard', 'tanod.tanod-alerts.index',
                'tanod.tanod-tasks.index', 'tanod.tanods.index',
                'tanod.incidents.index', 'tanod.announcements.index',
                'tanod.emergency-mode.index',
            ],
            'resident' => [
                'resident.dashboard', 'resident.incidents.index',
                'resident.resident-complaints.create',
                'resident.announcements.index',
                'resident.emergency-mode.index',
            ],
            default => [],
        };

        // The website has carried alternative Distress Signal route names.
        // Include only an existing route; never create or widen access here.
        if ($user->isAdmin()) {
            foreach ([
                'admin.distress-signals.index',
                'admin.distress-signal.index',
                'admin.distress-signal.create',
                'distress-signal.create',
            ] as $candidate) {
                if (\Illuminate\Support\Facades\Route::has($candidate)) {
                    $routes[] = $candidate;
                }
            }
        }

        return array_map(static fn (string $route): array => ['route' => $route], $routes);
    }

    public function moduleForRoute(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }

        if (str_ends_with($routeName, '.dashboard')) {
            return 'dashboard';
        }

        foreach (self::MODULES as $module) {
            if (str_contains($routeName, '.'.$module.'.')) {
                return $module;
            }
        }

        if (str_contains($routeName, 'distress-signal') || str_contains($routeName, 'distress-signals')) {
            return 'distress';
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $navItems  The already-authorized role's menu.
     * @return array<string, int>  Keys are module names; no secret/user data in response.
     */
    public function counts(
        User $user,
        array $navItems,
        ?string $currentRoute = null,
        bool $markVisited = false,
    ): array {
        if (! $user->isActive() || ! Schema::hasTable('sidebar_module_reviews')) {
            return [];
        }

        $modules = array_values(array_unique(array_filter(array_map(
            fn (array $item): ?string => $this->moduleForRoute($item['route'] ?? null),
            $navItems,
        ))));

        if (! $modules) {
            return [];
        }

        $now = now()->toDateTimeString();
        $rows = DB::table('sidebar_module_reviews')
            ->where('user_id', $user->id)
            ->whereIn('module', $modules)
            ->pluck('reviewed_at', 'module')
            ->all();

        // Existing historical records have never been explicitly marked unread.
        // Start each user's counters from their first website visit after upgrade.
        foreach ($modules as $module) {
            if (array_key_exists($module, $rows)) {
                continue;
            }

            DB::table('sidebar_module_reviews')->insertOrIgnore([
                'user_id' => $user->id,
                'module' => $module,
                'reviewed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Only a real GET of an authorized module route acknowledges a visit.
        // The standalone AJAX counter endpoint never marks anything as reviewed.
        $visitedModule = $this->moduleForRoute($currentRoute);
        if ($markVisited && $visitedModule && in_array($visitedModule, $modules, true)
            && ! $this->isPrefetch()) {
            DB::table('sidebar_module_reviews')
                ->where('user_id', $user->id)
                ->where('module', $visitedModule)
                ->update(['reviewed_at' => $now, 'updated_at' => $now]);
        }

        $reviews = DB::table('sidebar_module_reviews')
            ->where('user_id', $user->id)
            ->whereIn('module', $modules)
            ->pluck('reviewed_at', 'module');

        $counts = [];
        foreach ($modules as $module) {
            $seenAt = $reviews[$module] ?? $now;
            $counts[$module] = $this->countVisibleUpdates($user, $module, $seenAt);
        }

        return $counts;
    }

    private function isPrefetch(): bool
    {
        foreach (['Purpose', 'Sec-Purpose', 'X-Livewire-Navigate-Prefetch'] as $header) {
            if (str_contains(strtolower((string) request()->header($header, '')), 'prefetch')) {
                return true;
            }
        }
        return false;
    }

    private function countVisibleUpdates(User $user, string $module, string $since): int
    {
        try {
            return min(99, match ($module) {
                'dashboard', 'incidents' => $this->incidentQuery($user)
                    ->where('updated_at', '>', $since)->count(),
                'distress' => Schema::hasTable('mobile_emergency_alerts')
                    ? MobileEmergencyAlert::query()
                        ->when($user->isResident(), fn (Builder $query) =>
                            $query->where('user_id', $user->id))
                        ->where('updated_at', '>', $since)->count()
                    : 0,
                'tanod-alerts' => $this->tanodAlertCount($user, $since),
                'tanods' => Schema::hasTable('tanod_profiles')
                    ? TanodProfile::query()->where('updated_at', '>', $since)->count()
                    : 0,
                'tanod-tasks' => $this->taskCount($user, $since),
                'announcements' => $this->announcementQuery($user)
                    ->where('updated_at', '>', $since)->count(),
                'emergency-mode' => Schema::hasTable('emergency_hotlines')
                    ? EmergencyHotline::query()->where('is_active', true)
                        ->where('updated_at', '>', $since)->count()
                    : 0,
                'resident-complaints' => $this->complaintQuery($user)
                    ->where('updated_at', '>', $since)->count(),
                'map' => $this->incidentQuery($user)->where(function (Builder $query): void {
                    $query->whereNotNull('latitude')->whereNotNull('longitude')
                        ->orWhereNotNull('location_id');
                })->where('updated_at', '>', $since)->count(),
                'reports' => $user->isAdmin() && Schema::hasTable('report_snapshots')
                    ? ReportSnapshot::query()->where('generated_at', '>', $since)->count()
                    : 0,
                'users' => $user->isAdmin()
                    ? User::query()->whereKeyNot($user->id)
                        ->where('updated_at', '>', $since)->count()
                    : 0,
                'activity-logs' => $user->isAdmin() && Schema::hasTable('activity_logs')
                    ? ActivityLog::query()->where('created_at', '>', $since)->count()
                    : 0,
                default => 0,
            });
        } catch (\Illuminate\Database\QueryException $exception) {
            // An environment with an incomplete optional legacy schema should
            // never break its entire authenticated website just for badges.
            report($exception);
            return 0;
        }
    }

    private function incidentQuery(User $user): Builder
    {
        $query = Incident::query();

        if ($user->isTanod()) {
            $employeeId = $user->employee?->id;
            $employeeId ? $query->where('assigned_to', $employeeId)
                : $query->whereRaw('1 = 0');
        }

        if ($user->isResident()) {
            $residentIds = $user->resident()->pluck('id');
            $query->where(function (Builder $owned) use ($user, $residentIds): void {
                $first = true;
                foreach ([
                    'reporter_id', 'user_id', 'created_by', 'submitted_by',
                    'reported_by', 'resident_user_id',
                ] as $column) {
                    if (! Schema::hasColumn('incidents', $column)) {
                        continue;
                    }
                    $first ? $owned->where('incidents.'.$column, $user->id)
                        : $owned->orWhere('incidents.'.$column, $user->id);
                    $first = false;
                }
                if (Schema::hasColumn('incidents', 'resident_id')) {
                    $first ? $owned->whereIn('incidents.resident_id', $residentIds)
                        : $owned->orWhereIn('incidents.resident_id', $residentIds);
                    $first = false;
                }
                if ($first) {
                    $owned->whereRaw('1 = 0');
                }
            });
        }

        return $query;
    }

    private function complaintQuery(User $user): Builder
    {
        return ResidentComplaint::query()
            ->when($user->isResident(), fn (Builder $query) =>
                $query->where('resident_id', $user->id));
    }

    private function taskCount(User $user, string $since): int
    {
        if (! $user->isTanod()) {
            return TanodTask::query()->where('updated_at', '>', $since)->count();
        }

        $employeeId = $user->employee?->id;
        return $employeeId
            ? TanodTaskResponse::query()->where('employee_id', $employeeId)
                ->whereHas('task', fn (Builder $query) => $query
                    ->whereIn('status', ['open', 'closed', 'cancelled']))
                ->where('updated_at', '>', $since)->count()
            : 0;
    }

    private function tanodAlertCount(User $user, string $since): int
    {
        // This is a SELECT only. The notification bell's read/acknowledge
        // fields and Firebase observers are never changed by sidebar badges.
        return UserNotification::query()
            ->where('user_id', $user->id)
            ->whereIn('type', self::ALERT_TYPES)
            ->where('created_at', '>', $since)
            ->count();
    }

    private function announcementQuery(User $user): Builder
    {
        $query = Announcement::query();

        if ($user->isAdmin() || $user->isOfficial()) {
            return $query;
        }

        $audiences = $user->isTanod()
            ? ['everyone', 'public', 'all', 'tanod']
            : ['everyone', 'public', 'all', 'resident', 'residents'];

        $query->where('is_active', true)->whereIn('audience', $audiences);

        if (! Schema::hasTable('announcement_barangay')) {
            return $query;
        }

        return $query->where(function (Builder $visible) use ($user): void {
            $visible->whereDoesntHave('targetBarangays');
            if ($user->barangay_id) {
                $visible->orWhereHas('targetBarangays', fn (Builder $barangay) =>
                    $barangay->where('barangays.id', $user->barangay_id));
            }
        });
    }
}
