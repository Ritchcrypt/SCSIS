<?php

namespace Tests\Feature\Security;

use App\Models\ActivityLog;
use App\Models\DeletedDataArchive;
use App\Models\EmergencyHotline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeletedDataRecoveryPurgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_can_purge_recoverable_and_restored_snapshots_without_touching_live_data(): void
    {
        $admin = $this->activeUser('admin');
        $resident = $this->activeUser('resident');

        $liveHotline = EmergencyHotline::query()->create([
            'agency_name' => 'Live Response Hotline',
            'hotline_number' => '09170001234',
            'color' => 'blue',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->archive('tanod_task', 11, 'Task A');
        $this->archive('announcement', 12, 'Announcement B', now());
        $this->archive('emergency_hotline', 13, 'Hotline C');

        // A future or unsupported archive type is outside this page's scope.
        $unsupported = $this->archive('future_resource', 14, 'Not shown on recovery page');

        $beforeNotifications = DB::table('notifications')->count();

        $this->actingAs($admin)
            ->post(route('admin.activity-logs.deleted-data.destroy-all'))
            ->assertRedirect(route('admin.activity-logs.deleted-data.index'))
            ->assertSessionHas('success');

        $this->assertSame(1, DeletedDataArchive::query()->count());
        $this->assertDatabaseHas('deleted_data_archives', [
            'id' => $unsupported->id,
            'resource_type' => 'future_resource',
        ]);
        $this->assertDatabaseMissing('deleted_data_archives', ['resource_type' => 'tanod_task']);
        $this->assertDatabaseMissing('deleted_data_archives', ['resource_type' => 'announcement']);
        $this->assertDatabaseMissing('deleted_data_archives', ['resource_type' => 'emergency_hotline']);

        $this->assertDatabaseHas('emergency_hotlines', ['id' => $liveHotline->id]);
        $this->assertDatabaseHas('users', ['id' => $resident->id]);
        $this->assertSame($beforeNotifications, DB::table('notifications')->count());

        $this->assertTrue(
            ActivityLog::query()
                ->where('event', 'data_recovery.archives_purged')
                ->where('actor_id', $admin->id)
                ->exists()
        );
    }

    public function test_opening_recovery_page_does_not_delete_records(): void
    {
        $admin = $this->activeUser('admin');
        $archive = $this->archive('incident', 50, 'Keep until confirmed');

        $this->actingAs($admin)
            ->get(route('admin.activity-logs.deleted-data.index'))
            ->assertOk()
            ->assertSee('Delete Records')
            ->assertSee('data-confirm-title="Delete all recovery records?"', false)
            ->assertSee('data-confirm-button="Delete All Records"', false)
            ->assertSee('data-confirm-tone="danger"', false)
            ->assertSee('Back to Activity Logs');

        $this->assertDatabaseHas('deleted_data_archives', ['id' => $archive->id]);
    }

    public function test_non_admin_roles_cannot_purge_recovery_snapshots(): void
    {
        $archive = $this->archive('incident', 51, 'Protected archive');

        foreach (['official', 'dao', 'tanod', 'resident'] as $role) {
            $this->actingAs($this->activeUser($role))
                ->post(route('admin.activity-logs.deleted-data.destroy-all'))
                ->assertForbidden();

            $this->assertDatabaseHas('deleted_data_archives', ['id' => $archive->id]);
        }
    }

    public function test_guest_is_redirected_and_cannot_delete_archives(): void
    {
        $archive = $this->archive('incident', 52, 'Guest protected');

        $this->post(route('admin.activity-logs.deleted-data.destroy-all'))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('deleted_data_archives', ['id' => $archive->id]);
    }

    public function test_individual_archive_deletion_remains_disallowed_by_policy(): void
    {
        $admin = $this->activeUser('admin');
        $archive = $this->archive('incident', 53, 'Policy protected');

        $this->assertTrue($admin->can('deleteAny', DeletedDataArchive::class));
        $this->assertFalse($admin->can('delete', $archive));
        $this->assertFalse($admin->can('forceDelete', $archive));
    }

    private function activeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => true,
            'is_active' => true,
        ]);
    }

    private function archive(
        string $type,
        int $id,
        string $label,
        mixed $restoredAt = null
    ): DeletedDataArchive {
        return DeletedDataArchive::query()->create([
            'resource_type' => $type,
            'resource_id' => $id,
            'resource_label' => $label,
            'deleted_by_name' => 'Test administrator',
            'deleted_by_role' => 'admin',
            'payload' => ['snapshot' => $label],
            'deleted_at' => now()->subMinute(),
            'restored_at' => $restoredAt,
        ]);
    }
}
