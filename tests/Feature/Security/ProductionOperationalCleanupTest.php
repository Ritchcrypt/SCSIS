<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionOperationalCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_preview_changes_no_records_and_preserves_all_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $resident = User::factory()->create(['role' => 'resident', 'is_active' => true]);
        $incidentId = $this->seedOperationalData($resident);

        $result = Artisan::call('tabangnow:operational-cleanup');

        $this->assertSame(0, $result, Artisan::output());
        // Console output capture varies by runner; verify database immutability directly.
        $this->assertDatabaseHas('incidents', ['id' => $incidentId]);
        $this->assertSame(2, DB::table('users')->count());
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);
        $this->assertDatabaseHas('users', ['id' => $resident->id, 'role' => 'resident']);
    }

    public function test_apply_requires_exact_snapshot_confirmation_and_verified_backup_reference(): void
    {
        User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->seedOperationalData();

        Artisan::call('tabangnow:operational-cleanup');
        $snapshot = $this->readSnapshot(Artisan::output());

        $result = Artisan::call('tabangnow:operational-cleanup', [
            '--apply' => true,
            '--confirm' => 'WRONG',
            '--backup-id' => 'backup-reference-verified-001',
            '--expect-snapshot' => $snapshot,
        ]);

        $this->assertSame(1, $result);
        $this->assertSame(1, DB::table('incidents')->count());

        $result = Artisan::call('tabangnow:operational-cleanup', [
            '--apply' => true,
            '--confirm' => 'DELETE_OPERATIONS_KEEP_ALL_USERS',
            '--backup-id' => 'bad',
            '--expect-snapshot' => $snapshot,
        ]);

        $this->assertSame(1, $result);
        $this->assertSame(1, DB::table('incidents')->count());

        $result = Artisan::call('tabangnow:operational-cleanup', [
            '--apply' => true,
            '--confirm' => 'DELETE_OPERATIONS_KEEP_ALL_USERS',
            '--backup-id' => 'backup-reference-verified-001',
            '--expect-snapshot' => str_repeat('0', 64),
        ]);

        $this->assertSame(1, $result);
        $this->assertSame(1, DB::table('incidents')->count());
    }

    public function test_reviewed_cleanup_removes_operational_rows_but_preserves_users_mfa_and_audit_history(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $resident = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
        ]);

        $admin->forceFill([
            'mfa_secret' => 'encrypted-preserve-test',
            'mfa_recovery_codes' => '["hashed-preserve-test"]',
        ])->save();

        $this->seedOperationalData($resident);

        $userIdsBefore = DB::table('users')->orderBy('id')->pluck('id')->all();
        $userCountBefore = count($userIdsBefore);

        DB::table('activity_logs')->insert([
            'event' => 'cleanup.preflight',
            'category' => 'security',
            'description' => 'Preserve audit evidence',
            'created_at' => now(),
        ]);

        Artisan::call('tabangnow:operational-cleanup');
        $snapshot = $this->readSnapshot(Artisan::output());

        $result = Artisan::call('tabangnow:operational-cleanup', [
            '--apply' => true,
            '--confirm' => 'DELETE_OPERATIONS_KEEP_ALL_USERS',
            '--backup-id' => 'verified-backup-reference-001',
            '--expect-snapshot' => $snapshot,
        ]);

        $this->assertSame(0, $result, Artisan::output());
        foreach ([
            'incidents',
            'incident_messages',
            'notifications',
            'announcements',
            'tanod_tasks',
            'resident_complaints',
            'mobile_emergency_alerts',
        ] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }

        $this->assertSame($userCountBefore, DB::table('users')->count());
        $this->assertSame($userIdsBefore, DB::table('users')->orderBy('id')->pluck('id')->all());
        $this->assertDatabaseHas('users', ['id' => $resident->id]);
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => 'admin',
            'mfa_secret' => 'encrypted-preserve-test',
            'mfa_recovery_codes' => '["hashed-preserve-test"]',
        ]);
        $this->assertSame(1, DB::table('activity_logs')->count());
    }

    public function test_snapshot_changes_block_cleanup(): void
    {
        User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->seedOperationalData();

        Artisan::call('tabangnow:operational-cleanup');
        $snapshot = $this->readSnapshot(Artisan::output());

        DB::table('announcements')->insert([
            'title' => 'Another new record',
            'content' => 'Cannot delete behind stale preview',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = Artisan::call('tabangnow:operational-cleanup', [
            '--apply' => true,
            '--confirm' => 'DELETE_OPERATIONS_KEEP_ALL_USERS',
            '--backup-id' => 'verified-backup-reference-001',
            '--expect-snapshot' => $snapshot,
        ]);

        $this->assertSame(1, $result);
        $this->assertSame(1, DB::table('incidents')->count());
        $this->assertSame(1, DB::table('announcements')->count());
    }

    private function readSnapshot(string $output): string
    {
        $this->assertSame(
            1,
            preg_match('/SNAPSHOT SHA256: ([a-f0-9]{64})/', $output, $matches),
            $output
        );

        return $matches[1];
    }

    private function seedOperationalData(?User $resident = null): int
    {
        $id = DB::table('incidents')->insertGetId([
            'title' => 'Cleanup test incident',
            'reporter_id' => $resident?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('incident_messages')->insert([
            'incident_id' => $id,
            'user_id' => $resident?->id,
            'message' => 'Test incident message',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($resident) {
            DB::table('notifications')->insert([
                'user_id' => $resident->id,
                'type' => 'incident',
                'source_id' => $id,
                'title' => 'New incident',
                'message' => 'Test notification',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $id;
    }
}
