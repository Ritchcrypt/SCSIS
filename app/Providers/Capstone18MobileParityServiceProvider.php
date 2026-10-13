<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\Barangay;
use App\Models\Employee;
use App\Models\Incident;
use App\Models\TanodProfile;
use App\Models\TanodTask;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\DaoBarangays;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;

class Capstone18MobileParityServiceProvider extends ServiceProvider
{
    private const ANNOUNCEMENT_TARGETS_ATTRIBUTE =
        'capstone18_announcement_barangay_ids';

    private const TANOD_TASK_ROUTING_SCOPE =
        'capstone18_tanod_task_routing';

    private const TANOD_ROSTER_BARANGAY_SCOPE =
        'capstone18_tanod_roster_barangay';

    public function boot(): void
    {
        $this->enforceResidentRegistrationAge();
        $this->enforceIncidentSeverityAuthority();
        $this->enforceAnnouncementBarangayTargeting();
        $this->enforceManualTanodTaskRouting();
        $this->enforceTanodRosterBarangayAssignment();
    }

    private function enforceResidentRegistrationAge(): void
    {
        User::creating(function (User $user): void {
            if (! $this->routeIs('api.v1.auth.register')) {
                return;
            }

            $request = $this->request();

            if (! $request) {
                return;
            }

            $validated = Validator::make(
                $request->all(),
                [
                    'date_of_birth' => [
                        'required',
                        'date_format:Y-m-d',
                        'before_or_equal:'
                            . now()->subYears(18)->toDateString(),
                        'after_or_equal:'
                            . now()->subYears(120)->toDateString(),
                    ],
                    'age_confirmation' => [
                        'required',
                        'accepted',
                    ],
                ],
                [
                    'date_of_birth.required' =>
                        'Date of birth is required.',
                    'date_of_birth.before_or_equal' =>
                        'You must be at least 18 years old to create a resident account.',
                    'date_of_birth.after_or_equal' =>
                        'Please enter a valid date of birth.',
                    'age_confirmation.accepted' =>
                        'You must confirm that you are at least 18 years old.',
                ]
            )->validate();

            $user->forceFill([
                'date_of_birth' => $validated['date_of_birth'],
                'age_attested_at' => now(),
            ]);
        });
    }

    private function enforceIncidentSeverityAuthority(): void
    {
        Incident::creating(function (Incident $incident): void {
            if (! $this->routeIs('api.v1.incidents.store')) {
                return;
            }

            $request = $this->request();
            $user = $request?->user();

            if (! $user instanceof User) {
                return;
            }

            $canAssess = $user->isAdmin() || $user->isOfficial();

            if ($canAssess) {
                if (Schema::hasColumn('incidents', 'severity_needs_assessment')) {
                    $incident->severity_needs_assessment = false;
                }

                if (Schema::hasColumn('incidents', 'severity_assessed_by')) {
                    $incident->severity_assessed_by = $user->id;
                }

                if (Schema::hasColumn('incidents', 'severity_assessed_at')) {
                    $incident->severity_assessed_at = now();
                }

                if (Schema::hasColumn('incidents', 'severity')) {
                    $incident->severity = $incident->priority;
                }

                if (Schema::hasColumn('incidents', 'map_severity')) {
                    $incident->map_severity = $incident->priority;
                }

                return;
            }

            if (Schema::hasColumn('incidents', 'priority')) {
                $incident->priority = 'low';
            }

            if (Schema::hasColumn('incidents', 'severity')) {
                $incident->severity = 'low';
            }

            if (Schema::hasColumn('incidents', 'map_severity')) {
                $incident->map_severity = 'low';
            }

            if (Schema::hasColumn('incidents', 'severity_needs_assessment')) {
                $incident->severity_needs_assessment = true;
            }

            if (Schema::hasColumn('incidents', 'severity_assessed_by')) {
                $incident->severity_assessed_by = null;
            }

            if (Schema::hasColumn('incidents', 'severity_assessed_at')) {
                $incident->severity_assessed_at = null;
            }
        });
    }

    private function enforceAnnouncementBarangayTargeting(): void
    {
        Announcement::creating(function (): void {
            if (! $this->routeIs('api.v1.announcements.store')) {
                return;
            }

            $request = $this->request();

            if (! $request) {
                return;
            }

            $request->attributes->set(
                self::ANNOUNCEMENT_TARGETS_ATTRIBUTE,
                $this->validatedAnnouncementBarangayIds($request)
            );
        });

        Announcement::created(function (Announcement $announcement): void {
            if (! $this->routeIs('api.v1.announcements.store')) {
                return;
            }

            $targetIds = $this->announcementTargetIds();

            if ($targetIds === []) {
                return;
            }

            $announcement->targetBarangays()->sync($targetIds);
        });

        UserNotification::creating(function (UserNotification $notification): bool {
            if (
                ! $this->routeIs('api.v1.announcements.store')
                || ! in_array(
                    strtolower((string) $notification->type),
                    ['announcement', 'calamity'],
                    true
                )
            ) {
                return true;
            }

            $targetIds = $this->announcementTargetIds();

            if ($targetIds === []) {
                return false;
            }

            $recipientBarangayId = User::query()
                ->whereKey($notification->user_id)
                ->value('barangay_id');

            return $recipientBarangayId !== null
                && in_array((int) $recipientBarangayId, $targetIds, true);
        });
    }

    private function enforceManualTanodTaskRouting(): void
    {
        TanodTask::creating(function (TanodTask $task): void {
            if (! $this->routeIs('api.v1.tanod-tasks.store')) {
                return;
            }

            $request = $this->request();

            if (! $request) {
                return;
            }

            $barangayId = $this->validatedCanonicalBarangayId($request);
            $task->barangay_id = $barangayId;

            if (Schema::hasColumn('tanod_tasks', 'fallback_used')) {
                $task->fallback_used =
                    ! $this->localTanodExists($barangayId)
                    && $this->fallbackTanodExists($barangayId);
            }
        });

        Employee::addGlobalScope(
            self::TANOD_TASK_ROUTING_SCOPE,
            function (Builder $builder): void {
                if (! $this->routeIs('api.v1.tanod-tasks.store')) {
                    return;
                }

                $request = $this->request();

                if (! $request) {
                    return;
                }

                $barangayId = $this->validatedCanonicalBarangayId($request);

                $builder
                    ->whereNotNull('employees.user_id')
                    ->whereHas('user', function (Builder $userQuery): void {
                        if (Schema::hasColumn('users', 'is_active')) {
                            $userQuery->where('is_active', true);
                        } elseif (Schema::hasColumn('users', 'status')) {
                            $userQuery->where('status', true);
                        }
                    });

                if ($this->localTanodExists($barangayId)) {
                    $builder->where('employees.barangay_id', $barangayId);

                    return;
                }

                $builder->where(function (Builder $fallbackQuery) use ($barangayId): void {
                    $fallbackQuery
                        ->whereNull('employees.barangay_id')
                        ->orWhere('employees.barangay_id', '!=', $barangayId);
                });
            }
        );
    }

    private function enforceTanodRosterBarangayAssignment(): void
    {
        Barangay::addGlobalScope(
            self::TANOD_ROSTER_BARANGAY_SCOPE,
            function (Builder $builder): void {
                if (! $this->routeIs('api.v1.tanod-roster.store')) {
                    return;
                }

                $request = $this->request();
                $rawId = $request?->input('barangay_id');

                if (! is_numeric($rawId)) {
                    $builder->whereRaw('1 = 0');

                    return;
                }

                $builder
                    ->whereKey((int) $rawId)
                    ->whereIn('barangay_name', DaoBarangays::names());
            }
        );

        TanodProfile::updating(function (TanodProfile $profile): void {
            if (! $this->routeIs('api.v1.tanod-roster.update')) {
                return;
            }

            $request = $this->request();

            if (! $request) {
                return;
            }

            $barangayId = $this->validatedCanonicalBarangayId($request);

            if (
                $profile->user_id
                && Schema::hasColumn('users', 'barangay_id')
            ) {
                DB::table('users')
                    ->where('id', $profile->user_id)
                    ->update(['barangay_id' => $barangayId]);
            }

            if (
                $profile->employee_id
                && Schema::hasColumn('employees', 'barangay_id')
            ) {
                DB::table('employees')
                    ->where('id', $profile->employee_id)
                    ->update(['barangay_id' => $barangayId]);
            }
        });
    }

    /**
     * @return array<int, int>
     */
    private function validatedAnnouncementBarangayIds(Request $request): array
    {
        $validated = Validator::make(
            $request->all(),
            [
                'barangay_ids' => ['required', 'array', 'min:1'],
                'barangay_ids.*' => ['required', 'integer', 'distinct'],
            ]
        )->validate();

        $ids = collect($validated['barangay_ids'])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $validIds = Barangay::withoutGlobalScopes()
            ->whereIn('id', $ids)
            ->whereIn('barangay_name', DaoBarangays::names())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        sort($ids);
        sort($validIds);

        if ($ids !== $validIds) {
            throw ValidationException::withMessages([
                'barangay_ids' =>
                    'Select only official Dao barangays.',
            ]);
        }

        return $ids;
    }

    private function validatedCanonicalBarangayId(Request $request): int
    {
        $validated = Validator::make(
            $request->all(),
            ['barangay_id' => ['required', 'integer']]
        )->validate();

        $barangayId = (int) $validated['barangay_id'];

        $exists = Barangay::withoutGlobalScopes()
            ->whereKey($barangayId)
            ->whereIn('barangay_name', DaoBarangays::names())
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'barangay_id' =>
                    'Select an official Dao barangay.',
            ]);
        }

        return $barangayId;
    }

    /**
     * @return array<int, int>
     */
    private function announcementTargetIds(): array
    {
        $request = $this->request();

        if (! $request) {
            return [];
        }

        return array_values(
            array_map(
                'intval',
                (array) $request->attributes->get(
                    self::ANNOUNCEMENT_TARGETS_ATTRIBUTE,
                    []
                )
            )
        );
    }

    private function localTanodExists(int $barangayId): bool
    {
        return $this->availableTanodQuery()
            ->where('employees.barangay_id', $barangayId)
            ->exists();
    }

    private function fallbackTanodExists(int $barangayId): bool
    {
        return $this->availableTanodQuery()
            ->where(function ($query) use ($barangayId): void {
                $query
                    ->whereNull('employees.barangay_id')
                    ->orWhere('employees.barangay_id', '!=', $barangayId);
            })
            ->exists();
    }

    private function availableTanodQuery()
    {
        $query = DB::table('employees')
            ->join('users', 'users.id', '=', 'employees.user_id')
            ->where('employees.employee_type', 'tanod')
            ->where('employees.is_active', true)
            ->whereNotNull('employees.user_id');

        if (Schema::hasColumn('users', 'is_active')) {
            $query->where('users.is_active', true);
        } elseif (Schema::hasColumn('users', 'status')) {
            $query->where('users.status', true);
        }

        return $query;
    }

    private function routeIs(string $routeName): bool
    {
        $request = $this->request();

        return $request?->routeIs($routeName) ?? false;
    }

    private function request(): ?Request
    {
        if (! $this->app->bound('request')) {
            return null;
        }

        $request = $this->app->make('request');

        return $request instanceof Request
            ? $request
            : null;
    }
}
