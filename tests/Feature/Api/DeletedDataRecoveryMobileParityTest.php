<?php

namespace Tests\Feature\Api;

use App\Models\DeletedDataArchive;
use App\Models\EmergencyHotline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeletedDataRecoveryMobileParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_admin_can_view_archived_data(): void
    {
        $resident = User::factory()->create(['role' => 'resident', 'is_active' => true, 'status' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'status' => true]);

        $this->getJson('/api/v1/deleted-data-recovery')->assertUnauthorized();
        $this->actingAs($resident)->getJson('/api/v1/deleted-data-recovery')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/v1/deleted-data-recovery')
            ->assertOk()->assertJsonStructure(['data', 'summary', 'filters', 'types', 'pagination']);
    }

    public function test_mobile_admin_can_restore_a_deleted_hotline_through_existing_service(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'status' => true]);
        $hotline = EmergencyHotline::query()->create([
            'agency_name' => 'Mobile Recovery Check',
            'hotline_number' => '09170000000',
            'color' => 'blue',
            'is_active' => true,
            'sort_order' => 123,
        ]);
        $this->actingAs($admin)
            ->delete(route('admin.emergency-mode.destroy', $hotline))->assertRedirect();
        $archive = DeletedDataArchive::query()
            ->where('resource_type', 'emergency_hotline')
            ->where('resource_id', $hotline->id)
            ->whereNull('restored_at')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/api/v1/deleted-data-recovery?status=recoverable')
            ->assertOk()->assertJsonFragment(['resource_label' => 'Mobile Recovery Check']);
        $this->actingAs($admin)
            ->patchJson('/api/v1/deleted-data-recovery/'.$archive->id.'/restore')
            ->assertOk()->assertJsonFragment(['message' => 'Deleted data restored successfully.']);
        $this->assertDatabaseHas('emergency_hotlines', [
            'id' => $hotline->id,
            'agency_name' => 'Mobile Recovery Check',
        ]);
        $this->assertNotNull($archive->fresh()?->restored_at);
    }

    public function test_mobile_non_admin_cannot_restore_deleted_data(): void
    {
        $resident = User::factory()->create(['role' => 'resident', 'is_active' => true, 'status' => true]);
        $archive = DeletedDataArchive::query()->create([
            'resource_type' => 'emergency_hotline',
            'resource_id' => 9999,
            'resource_label' => 'Protected record',
            'payload' => ['data' => []],
            'deleted_at' => now(),
        ]);
        $this->actingAs($resident)
            ->patchJson('/api/v1/deleted-data-recovery/'.$archive->id.'/restore')
            ->assertForbidden();
    }
}
