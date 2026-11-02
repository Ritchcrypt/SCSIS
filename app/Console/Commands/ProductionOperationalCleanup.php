<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Intentionally NOT a migration, seeder, scheduler task, or web endpoint.
 * An operator must manually approve a verified off-site backup before applying.
 */
final class ProductionOperationalCleanup extends Command
{
    protected $signature = 'tabangnow:operational-cleanup
        {--apply : Apply the reviewed operational record cleanup}
        {--confirm= : Required exact destructive-operation phrase}
        {--backup-id= : Reference of a separately VERIFIED restorable production backup}
        {--expect-snapshot= : Exact snapshot hash printed by the read-only preview}';

    protected $description = 'Preview operational data cleanup, preserving all users and security/reference data';

    private const CONFIRMATION = 'DELETE_OPERATIONS_KEEP_ALL_USERS';

    // Child tables FIRST. Never expand this allowlist through a wildcard.
    private const DELETE_ORDER = [
        'announcement_barangay',
        'resident_complaint_proofs',
        'tanod_task_responses',
        'incident_messages',
        'incident_status_history',
        'incident_escalations',
        'evidence',
        'notifications',
        'report_snapshots',
        'mobile_emergency_alerts',
        'tanod_tasks',
        'resident_complaints',
        'announcements',
        'incidents',
    ];

    private const REQUIRED_TABLES = [
        'users',
        'incidents',
        'notifications',
        'announcements',
        'tanod_tasks',
        'resident_complaints',
        'mobile_emergency_alerts',
    ];

    // Do not delete these or modify their schema, even to clear a demo.
    private const PROTECTED_TABLES = [
        'users',
        'residents',
        'employees',
        'tanod_profiles',
        'barangays',
        'statuses',
        'incident_categories',
        'emergency_hotlines',
        'gps_locations',
        'system_settings',
        'personal_access_tokens',
        'mobile_push_tokens',
        'mobile_emergency_devices',
        'sessions',
        'password_reset_tokens',
        'activity_logs',
        'deleted_data_archives',
        'migrations',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    public function handle(): int
    {
        try {
            $this->assertSchemaIsSafe();

            $snapshot = $this->snapshot();
            $this->info('READ-ONLY OPERATIONAL DATA PREVIEW');
            foreach ($snapshot['tables'] as $table => $count) {
                $this->line($table . ': ' . $count);
            }

            $this->info('PRESERVED user accounts: ' . $snapshot['user_count']);
            $this->info('PRESERVED admin accounts: ' . $snapshot['admin_count']);
            foreach ($snapshot['active_records'] as $name => $count) {
                $this->line('ACTIVE ' . $name . ': ' . $count);
            }
            $this->line('Activity logs, deleted-data archives, account credentials, MFA, role profiles and reference tables are deliberately excluded.');
            $digest = $this->digest($snapshot);
            $this->line('SNAPSHOT SHA256: ' . $digest);

            if (! $this->option('apply')) {
                $this->info('PREVIEW COMPLETE. ZERO ROWS MODIFIED.');
                return self::SUCCESS;
            }

            $this->assertApplyAuthorized($digest);

            DB::transaction(function () use ($digest, $snapshot): void {
                $current = $this->snapshot();
                if (! hash_equals($digest, $this->digest($current))) {
                    throw new RuntimeException('Dataset changed since preview. No records deleted.');
                }

                if (array_sum($current['active_records']) > 0) {
                    throw new RuntimeException(
                        'Active incidents, SOS signals, tasks or complaints exist. '
                        . 'Resolve and individually review them before any bulk cleanup.'
                    );
                }

                foreach ($current['tables'] as $table => $expectedCount) {
                    $deleted = DB::table($table)->delete();
                    if ($deleted !== $expectedCount) {
                        throw new RuntimeException('Unexpected delete count for ' . $table . '. All changes rolled back.');
                    }
                }

                if ($this->accountFingerprint() !== $snapshot['user_fingerprint']) {
                    throw new RuntimeException('User credentials or account data changed. All changes rolled back.');
                }

                if (DB::table('users')->count() !== $snapshot['user_count']
                    || DB::table('users')->where('role', 'admin')->count() !== $snapshot['admin_count']) {
                    throw new RuntimeException('Protected accounts changed. All changes rolled back.');
                }

                foreach ($current['tables'] as $table => $_) {
                    if (DB::table($table)->count() !== 0) {
                        throw new RuntimeException('Operational records remained in ' . $table . '. All changes rolled back.');
                    }
                }
            }, 1);

            $this->info('OPERATIONAL DATA CLEANUP COMPLETED. All user accounts and MFA data preserved.');
            $this->warn('No files on disk were deleted; inspect orphaned uploads separately.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('SAFETY STOP: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function assertSchemaIsSafe(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('MySQL only; no fallback engine or production database switch.');
        }

        foreach (self::REQUIRED_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException('Required table missing: ' . $table);
            }
        }

        if (array_intersect(self::DELETE_ORDER, self::PROTECTED_TABLES)) {
            throw new RuntimeException('Protected table found in delete allowlist.');
        }

        // Some historical installs may have additional legacy evidence tables
        // that are not covered by this reviewed schema. Never delete around them.
        foreach ([
            'incident_evidence',
            'incident_evidences',
            'incident_attachments',
            'incident_status_histories',
            'attachments',
            'emergency_agency_logs',
            'case_records',
        ] as $legacyTable) {
            if (Schema::hasTable($legacyTable) && DB::table($legacyTable)->exists()) {
                throw new RuntimeException(
                    'Legacy/unknown table contains rows: ' . $legacyTable
                    . '. Inspect and make a separate dependency plan first.'
                );
            }
        }

        // Detect undeclared referential dependencies. Never disable FK checks.
        $fks = DB::select(
            'SELECT TABLE_NAME AS child_table, REFERENCED_TABLE_NAME AS parent_table
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL'
        );
        foreach ($fks as $fk) {
            $child = (string) $fk->child_table;
            $parent = (string) $fk->parent_table;
            if (in_array($parent, self::DELETE_ORDER, true)
                && ! in_array($child, self::DELETE_ORDER, true)) {
                throw new RuntimeException(
                    'Unreviewed FK dependency: ' . $child . ' -> ' . $parent
                );
            }
            if (in_array($child, self::DELETE_ORDER, true)
                && in_array($parent, self::DELETE_ORDER, true)
                && array_search($child, self::DELETE_ORDER, true)
                    > array_search($parent, self::DELETE_ORDER, true)) {
                throw new RuntimeException('Incorrect child-first deletion order: ' . $child);
            }
        }

        if (DB::table('users')->where('role', 'admin')->count() < 1) {
            throw new RuntimeException('No admin account found. Refusing cleanup.');
        }
    }

    private function snapshot(): array
    {
        $counts = [];
        foreach (self::DELETE_ORDER as $table) {
            if (Schema::hasTable($table)) {
                $counts[$table] = DB::table($table)->count();
            }
        }

        return [
            'tables' => $counts,
            'active_records' => $this->activeRecordCounts(),
            'user_count' => DB::table('users')->count(),
            'admin_count' => DB::table('users')->where('role', 'admin')->count(),
            'user_fingerprint' => $this->accountFingerprint(),
        ];
    }

    private function activeRecordCounts(): array
    {
        $sources = [
            'incidents' => ['pending', 'dispatched', 'active', 'escalated'],
            'mobile_emergency_alerts' => ['pending', 'active', 'acknowledged', 'triggered'],
            'tanod_tasks' => ['open', 'pending', 'accepted', 'in_progress'],
            'resident_complaints' => ['pending', 'active', 'in_progress'],
        ];

        $counts = [];
        foreach ($sources as $table => $statuses) {
            $counts[$table] = Schema::hasTable($table)
                && Schema::hasColumn($table, 'status')
                    ? DB::table($table)->whereIn('status', $statuses)->count()
                    : 0;
        }

        return $counts;
    }

    private function accountFingerprint(): string
    {
        $userRows = DB::table('users')
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'role', 'password', 'mfa_secret',
                   'mfa_recovery_codes', 'is_active']);

        return hash('sha256', $userRows->toJson());
    }

    private function digest(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    private function assertApplyAuthorized(string $digest): void
    {
        if (! app()->environment('production', 'testing')) {
            throw new RuntimeException('Apply is allowed only in production or isolated automated tests.');
        }

        if (! app()->environment('testing') && ! app()->isDownForMaintenance()) {
            throw new RuntimeException('Put production into maintenance mode before cleanup.');
        }

        $confirmation = (string) $this->option('confirm');
        $backupId = trim((string) $this->option('backup-id'));
        $expected = (string) $this->option('expect-snapshot');

        if (! hash_equals(self::CONFIRMATION, $confirmation)) {
            throw new RuntimeException('Exact cleanup confirmation phrase missing.');
        }

        if (strlen($backupId) < 12 || strlen($backupId) > 200) {
            throw new RuntimeException('Verified backup reference missing or invalid.');
        }

        if (! preg_match('/^[a-f0-9]{64}$/D', $expected)
            || ! hash_equals($digest, $expected)) {
            throw new RuntimeException('Preview snapshot mismatch. Rerun preview before cleanup.');
        }
    }
}
