<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\TanodTask;
use App\Models\TanodTaskResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TanodTaskAssignmentService
{
    /**
     * Create the initial response pool.
     *
     * Same-barangay active tanods receive the task first. When none are
     * available, active tanods from other barangays become the fallback pool.
     *
     * @return array{tanods: Collection<int, Employee>, fallback_used: bool}
     */
    public function assignInitialResponses(TanodTask $task): array
    {
        $barangayId = $task->barangay_id !== null
            ? (int) $task->barangay_id
            : null;

        $base = $this->availableTanodsQuery();

        if ($barangayId !== null) {
            $localTanods = (clone $base)
                ->where('barangay_id', $barangayId)
                ->get();

            if ($localTanods->isNotEmpty()) {
                $this->createPendingResponses(
                    $task,
                    $localTanods
                );

                return [
                    'tanods' => $localTanods,
                    'fallback_used' => false,
                ];
            }

            $fallbackTanods = (clone $base)
                ->where(function (Builder $query) use ($barangayId): void {
                    $query
                        ->whereNull('barangay_id')
                        ->orWhere(
                            'barangay_id',
                            '!=',
                            $barangayId
                        );
                })
                ->get();

            $this->createPendingResponses(
                $task,
                $fallbackTanods
            );

            if ($fallbackTanods->isNotEmpty()) {
                $this->markFallbackUsed($task);
            }

            return [
                'tanods' => $fallbackTanods,
                'fallback_used' => $fallbackTanods->isNotEmpty(),
            ];
        }

        /*
         * Legacy/unscoped manual task compatibility.
         * New website-created tasks will always supply a barangay.
         */
        $tanods = $base->get();

        $this->createPendingResponses(
            $task,
            $tanods
        );

        return [
            'tanods' => $tanods,
            'fallback_used' => false,
        ];
    }

    /**
     * Open cross-barangay fallback after every initially assigned local tanod
     * has declined and nobody has accepted.
     *
     * @return Collection<int, Employee>
     */
    public function expandFallbackIfNeeded(
        TanodTask $task
    ): Collection {
        return DB::transaction(function () use ($task): Collection {
            $lockedTask = TanodTask::query()
                ->whereKey($task->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedTask) {
                return collect();
            }

            if (
                strtolower(
                    trim((string) $lockedTask->status)
                ) !== 'open'
            ) {
                return collect();
            }

            if (
                Schema::hasColumn(
                    'tanod_tasks',
                    'fallback_used'
                )
                && (bool) $lockedTask->fallback_used
            ) {
                return collect();
            }

            $barangayId = $lockedTask->barangay_id !== null
                ? (int) $lockedTask->barangay_id
                : null;

            if ($barangayId === null) {
                return collect();
            }

            $responsesQuery = TanodTaskResponse::query()
                ->where(
                    'tanod_task_id',
                    $lockedTask->id
                );

            if (
                (clone $responsesQuery)
                    ->where(
                        'response_status',
                        'accepted'
                    )
                    ->exists()
            ) {
                return collect();
            }

            if (
                (clone $responsesQuery)
                    ->where(
                        'response_status',
                        'pending'
                    )
                    ->exists()
            ) {
                return collect();
            }

            $alreadyAssignedEmployeeIds =
                (clone $responsesQuery)
                    ->pluck('employee_id')
                    ->filter()
                    ->map(
                        fn ($id): int => (int) $id
                    )
                    ->all();

            $fallbackTanods =
                $this->availableTanodsQuery()
                    ->where(function (Builder $query) use ($barangayId): void {
                        $query
                            ->whereNull('barangay_id')
                            ->orWhere(
                                'barangay_id',
                                '!=',
                                $barangayId
                            );
                    })
                    ->when(
                        $alreadyAssignedEmployeeIds !== [],
                        fn (Builder $query): Builder =>
                            $query->whereNotIn(
                                'id',
                                $alreadyAssignedEmployeeIds
                            )
                    )
                    ->get();

            if ($fallbackTanods->isEmpty()) {
                return collect();
            }

            $this->createPendingResponses(
                $lockedTask,
                $fallbackTanods
            );

            $this->markFallbackUsed($lockedTask);

            return $fallbackTanods;
        });
    }

    private function availableTanodsQuery(): Builder
    {
        $query = Employee::query()
            ->with([
                'user',
                'barangay',
            ])
            ->tanods()
            ->active()
            ->whereNotNull('user_id');

        $query->whereHas(
            'user',
            function (Builder $userQuery): void {
                if (
                    Schema::hasColumn(
                        'users',
                        'is_active'
                    )
                ) {
                    $userQuery->where(
                        'is_active',
                        true
                    );

                    return;
                }

                if (
                    Schema::hasColumn(
                        'users',
                        'status'
                    )
                ) {
                    $userQuery->where(
                        'status',
                        true
                    );
                }
            }
        );

        return $query;
    }

    private function createPendingResponses(
        TanodTask $task,
        Collection $tanods
    ): void {
        foreach ($tanods as $tanod) {
            TanodTaskResponse::query()
                ->firstOrCreate(
                    [
                        'tanod_task_id' => $task->id,
                        'employee_id' => $tanod->id,
                    ],
                    [
                        'user_id' => $tanod->user_id,
                        'response_status' => 'pending',
                        'response_note' => null,
                        'responded_at' => null,
                    ]
                );
        }
    }

    private function markFallbackUsed(
        TanodTask $task
    ): void {
        if (
            ! Schema::hasColumn(
                'tanod_tasks',
                'fallback_used'
            )
        ) {
            return;
        }

        $task->forceFill([
            'fallback_used' => true,
        ])->save();
    }
}
