<?php

namespace Tests\Feature\Security;

use App\Models\DeletedDataArchive;
use App\Models\EmergencyHotline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeletedDataRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_can_recover_a_deleted_hotline(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'status' => true,
        ]);

        $hotline = EmergencyHotline::query()->create([
            'agency_name' => 'Capstone 18 Recovery Test',
            'hotline_number' => '09170000000',
            'color' => 'red',
            'is_active' => true,
            'sort_order' => 99,
        ]);

        $this
            ->actingAs($admin)
            ->delete(route('admin.emergency-mode.destroy', $hotline))
            ->assertRedirect();

        $this->assertDatabaseMissing('emergency_hotlines', [
            'id' => $hotline->id,
        ]);

        $archive = DeletedDataArchive::query()
            ->where('resource_type', 'emergency_hotline')
            ->where('resource_id', $hotline->id)
            ->whereNull('restored_at')
            ->firstOrFail();

        $this
            ->actingAs($admin)
            ->get(route('admin.activity-logs.deleted-data.index'))
            ->assertOk()
            ->assertSee('Deleted Data Recovery')
            ->assertSee('Capstone 18 Recovery Test');

        $this
            ->actingAs($admin)
            ->patch(route('admin.activity-logs.deleted-data.restore', $archive))
            ->assertRedirect(route('admin.activity-logs.deleted-data.index'));

        $this->assertDatabaseHas('emergency_hotlines', [
            'id' => $hotline->id,
            'agency_name' => 'Capstone 18 Recovery Test',
            'hotline_number' => '09170000000',
        ]);

        $this->assertNotNull($archive->fresh()?->restored_at);
    }

    public function test_non_admin_cannot_open_deleted_data_recovery(): void
    {
        $resident = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
        ]);

        $this
            ->actingAs($resident)
            ->get(route('admin.activity-logs.deleted-data.index'))
            ->assertForbidden();
    }

    public function test_failed_or_forbidden_delete_does_not_leave_a_recovery_archive(): void
    {
        $resident = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
        ]);

        $hotline = EmergencyHotline::query()->create([
            'agency_name' => 'Forbidden Recovery Test',
            'hotline_number' => '09171111111',
            'color' => 'blue',
            'is_active' => true,
            'sort_order' => 100,
        ]);

        $this
            ->actingAs($resident)
            ->delete(route('admin.emergency-mode.destroy', $hotline))
            ->assertForbidden();

        $this->assertDatabaseHas('emergency_hotlines', [
            'id' => $hotline->id,
        ]);

        $this->assertDatabaseMissing('deleted_data_archives', [
            'resource_type' => 'emergency_hotline',
            'resource_id' => $hotline->id,
        ]);
    }
}
