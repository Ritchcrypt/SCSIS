<?php

namespace App\Console\Commands;

use App\Services\ActivityLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PruneActivityLogs extends Command
{
    protected $signature = 'activity-logs:prune
        {--days= : Override the configured retention period}
        {--dry-run : Count eligible records without deleting them}
        {--force : Authorise permanent deletion of eligible records}';

    protected $description =
        'Safely prune activity logs older than the approved retention period';

    public function handle(
        ActivityLogger $activityLogger
    ): int {
        if (! Schema::hasTable('activity_logs')) {
            $this->warn(
                'The activity_logs table does not exist. Nothing was changed.'
            );

            return self::SUCCESS;
        }

        $retentionDays = $this->retentionDays();

        if ($retentionDays === null) {
            return self::FAILURE;
        }

        $minimumRetentionDays = max(
            1,
            (int) config(
                'activity.minimum_retention_days',
                90
            )
        );

        if ($retentionDays < $minimumRetentionDays) {
            $this->error(
                "Retention must be at least {$minimumRetentionDays} days."
            );

            return self::FAILURE;
        }

        $cutoff = now()->subDays($retentionDays);

        $eligibleCount = DB::table('activity_logs')
            ->where('created_at', '<', $cutoff)
            ->count();

        if ((bool) $this->option('dry-run')) {
            $this->info(
                "{$eligibleCount} activity log record(s) are older than "
                . "{$retentionDays} days. No records were deleted."
            );

            return self::SUCCESS;
        }

        if (! (bool) $this->option('force')) {
            $this->error(
                'No records were deleted. Run with --dry-run first, then add '
                . '--force only after confirming the retention policy.'
            );

            return self::FAILURE;
        }

        if ($eligibleCount === 0) {
            $this->info(
                'No activity log records are eligible for pruning.'
            );

            return self::SUCCESS;
        }

        $batchSize = max(
            100,
            min(
                10000,
                (int) config(
                    'activity.prune_batch_size',
                    1000
                )
            )
        );

        $deletedCount = 0;

        while (true) {
            $ids = DB::table('activity_logs')
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($batchSize)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deletedCount += DB::table('activity_logs')
                ->whereIn('id', $ids->all())
                ->delete();
        }

        /*
        |--------------------------------------------------------------------------
        | Record the controlled retention action
        |--------------------------------------------------------------------------
        |
        | The new audit event contains only the policy parameters and deletion
        | count. It does not reproduce any deleted record content.
        |
        */

        $activityLogger->record(
            event: 'activity_log.pruned',
            category: 'security',
            description: 'Expired activity logs were pruned by the retention command.',
            metadata: [
                'retention_days' => $retentionDays,
                'deleted_count' => $deletedCount,
                'cutoff' => $cutoff->toIso8601String(),
            ],
        );

        $this->info(
            "{$deletedCount} activity log record(s) were permanently pruned."
        );

        return self::SUCCESS;
    }

    private function retentionDays(): ?int
    {
        $option = $this->option('days');

        if ($option === null || $option === '') {
            $configuredDays = (int) config(
                'activity.retention_days',
                365
            );

            if ($configuredDays < 1) {
                $this->error(
                    'ACTIVITY_LOG_RETENTION_DAYS must be a positive integer.'
                );

                return null;
            }

            return $configuredDays;
        }

        if (
            ! is_scalar($option)
            || ! ctype_digit((string) $option)
            || (int) $option < 1
        ) {
            $this->error(
                'The --days option must be a positive integer.'
            );

            return null;
        }

        return (int) $option;
    }
}
