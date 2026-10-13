<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\TanodProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class TanodDutyCounter
{
    public function currentCount(): int
    {
        return $this->countAt(now())['count'];
    }

    /**
     * @return array{count:int,basis:string}
     */
    public function countAt(Carbon $asOf): array
    {
        $result = $this->recordsAt($asOf);

        return [
            'count' => count($result['records']),
            'basis' => $result['basis'],
        ];
    }

    /**
     * Return the Tanod roster members who were on duty at the requested
     * point in time.
     *
     * Historical status reconstruction uses immutable
     * tanod_roster.updated ActivityLog metadata when available.
     *
     * @return array{
     *     records:array<int,array{
     *         id:int,
     *         name:string,
     *         badge_number:string,
     *         shift:string,
     *         barangay:string,
     *         status:string
     *     }>,
     *     basis:string
     * }
     */
    public function recordsAt(Carbon $asOf): array
    {
        if (
            ! Schema::hasTable('tanod_profiles')
            || ! Schema::hasColumn(
                'tanod_profiles',
                'status'
            )
        ) {
            return [
                'records' => [],
                'basis' => 'unavailable',
            ];
        }

        $profiles = TanodProfile::query()
            ->with([
                'user:id,name',
                'employee.barangay',
            ])
            ->when(
                Schema::hasColumn(
                    'tanod_profiles',
                    'created_at'
                ),
                fn ($query) => $query->where(
                    'created_at',
                    '<=',
                    $asOf
                )
            )
            ->get();

        if ($profiles->isEmpty()) {
            return [
                'records' => [],
                'basis' => $asOf->isPast()
                    ? 'historical_roster'
                    : 'current_roster',
            ];
        }

        $statusByProfile = $profiles
            ->mapWithKeys(
                fn (TanodProfile $profile): array => [
                    (int) $profile->id =>
                        (string) ($profile->status ?? ''),
                ]
            )
            ->all();

        $historical =
            $asOf->lt(now())
            && Schema::hasTable('activity_logs');

        $usedAuditHistory = false;

        if ($historical) {
            $updates = ActivityLog::query()
                ->where(
                    'event',
                    'tanod_roster.updated'
                )
                ->where(
                    'created_at',
                    '>',
                    $asOf
                )
                ->orderBy('created_at')
                ->orderBy('id')
                ->get([
                    'metadata',
                    'created_at',
                ]);

            $firstPreviousStatus = [];

            foreach ($updates as $update) {
                $metadata = is_array(
                    $update->metadata
                )
                    ? $update->metadata
                    : [];

                $profileId = (int) (
                    $metadata['tanod_profile_id']
                    ?? 0
                );

                if (
                    $profileId < 1
                    || ! array_key_exists(
                        $profileId,
                        $statusByProfile
                    )
                    || array_key_exists(
                        $profileId,
                        $firstPreviousStatus
                    )
                ) {
                    continue;
                }

                if (
                    array_key_exists(
                        'previous_status',
                        $metadata
                    )
                ) {
                    $firstPreviousStatus[
                        $profileId
                    ] = (string) (
                        $metadata['previous_status']
                        ?? ''
                    );

                    $usedAuditHistory = true;
                }
            }

            foreach (
                $firstPreviousStatus
                as $profileId => $status
            ) {
                $statusByProfile[
                    $profileId
                ] = $status;
            }
        }

        $records = [];

        foreach ($profiles as $profile) {
            $status = $statusByProfile[
                (int) $profile->id
            ] ?? (string) $profile->status;

            if (
                $this->normalizeStatus(
                    $status
                ) !== 'on_duty'
            ) {
                continue;
            }

            $records[] = [
                'id' => (int) $profile->id,
                'name' =>
                    $profile->user?->name
                    ?: 'Tanod #'
                        . $profile->id,

                'shift' =>
                    trim(
                        (string) (
                            $profile->display_shift
                            ?? $profile->shift
                            ?? ''
                        )
                    ),
                'barangay' =>
                    $profile
                        ->employee
                        ?->barangay
                        ?->barangay_name
                    ?: '—',
                'status' => 'On Duty',
            ];
        }

        usort(
            $records,
            static fn (
                array $left,
                array $right
            ): int =>
                strcasecmp(
                    $left['name'],
                    $right['name']
                )
        );

        return [
            'records' => $records,
            'basis' => $historical
                ? (
                    $usedAuditHistory
                        ? 'historical_audit'
                        : 'historical_roster_fallback'
                )
                : 'current_roster',
        ];
    }

    private function normalizeStatus(
        string $status
    ): string {
        $normalized = strtolower(
            trim($status)
        );

        $normalized = str_replace(
            [
                '-',
                ' ',
            ],
            '_',
            $normalized
        );

        return $normalized === 'onduty'
            ? 'on_duty'
            : $normalized;
    }
}
