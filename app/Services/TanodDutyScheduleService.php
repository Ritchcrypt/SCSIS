<?php

namespace App\Services;

use App\Models\TanodProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TanodDutyScheduleService
{
    public function schemaReady(): bool
    {
        return Schema::hasColumn('tanod_profiles', 'auto_duty_enabled');
    }

    public function syncDue(): int
    {
        if (! $this->schemaReady()) {
            return 0;
        }

        $ids = TanodProfile::query()
            ->where('auto_duty_enabled', true)
            ->whereNotNull('date_appointed')
            ->orderBy('id')
            ->pluck('id');

        $activated = 0;

        foreach ($ids as $id) {
            if ($this->syncProfileId((int) $id)) {
                $activated++;
            }
        }

        return $activated;
    }

    public function syncProfile(TanodProfile $tanod): bool
    {
        if (! $this->schemaReady()) {
            return false;
        }

        return $this->syncProfileId((int) $tanod->getKey());
    }

    public function scheduledAt(TanodProfile $tanod): ?CarbonImmutable
    {
        if (! $tanod->date_appointed) {
            return null;
        }

        [$hour, $minute] = match ((string) $tanod->shift) {
            'day' => [6, 0],
            'afternoon' => [14, 0],
            'night' => [22, 0],
            'floating' => [0, 0],
            default => [0, 0],
        };

        return CarbonImmutable::parse(
            $tanod->date_appointed->format('Y-m-d'),
            $this->timezone()
        )->setTime($hour, $minute);
    }

    public function scheduledLabel(TanodProfile $tanod): ?string
    {
        $scheduledAt = $this->scheduledAt($tanod);

        return $scheduledAt?->format('M j, Y g:i A');
    }

    private function syncProfileId(int $tanodId): bool
    {
        return (bool) DB::transaction(function () use ($tanodId): bool {
            $tanod = TanodProfile::query()
                ->with('employee')
                ->lockForUpdate()
                ->find($tanodId);

            if (! $tanod || ! (bool) $tanod->auto_duty_enabled) {
                return false;
            }

            $scheduledAt = $this->scheduledAt($tanod);

            if (! $scheduledAt || $scheduledAt->isFuture()) {
                return false;
            }

            $previousStatus = (string) $tanod->status;

            $tanod->forceFill([
                'status' => 'on_duty',
                'auto_duty_enabled' => false,
            ])->save();

            if ($tanod->employee) {
                $tanod->employee->forceFill([
                    'is_active' => true,
                ])->save();
            }

            Log::info('Scheduled tanod duty activation completed.', [
                'tanod_profile_id' => (int) $tanod->id,
                'employee_id' => $tanod->employee_id,
                'previous_status' => $previousStatus,
                'new_status' => 'on_duty',
                'scheduled_at' => $scheduledAt->toIso8601String(),
            ]);

            return true;
        });
    }

    private function timezone(): string
    {
        return (string) config('app.timezone', 'Asia/Manila');
    }
}
