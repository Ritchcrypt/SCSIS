<?php

namespace Tests\Feature\Security;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityLogDeleteAllTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_permanently_clear_all_logs_from_website(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'status' => true,
        ]);

        $this->seedLogs(3);

        $this->actingAs($admin)
            ->delete('/admin/activity-logs')
            ->assertRedirect('/admin/activity-logs')
            ->assertSessionHas('success');

        $this->assertSame(
            0,
            DB::table((new ActivityLog())->getTable())->count()
        );
    }

    public function test_admin_can_permanently_clear_all_logs_from_mobile_api(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'status' => true,
        ]);

        $this->seedLogs(4);

        Sanctum::actingAs($admin, ['mobile']);

        $this->deleteJson('/api/v1/activity-logs')
            ->assertOk()
            ->assertJsonPath('deleted_count', 4);

        $this->assertSame(
            0,
            DB::table((new ActivityLog())->getTable())->count()
        );
    }

    public function test_non_admin_cannot_clear_activity_logs(): void
    {
        $resident = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
        ]);

        $this->seedLogs(2);

        Sanctum::actingAs($resident, ['mobile']);

        $this->deleteJson('/api/v1/activity-logs')
            ->assertForbidden();

        $this->assertSame(
            2,
            DB::table((new ActivityLog())->getTable())->count()
        );
    }

    public function test_individual_activity_logs_remain_non_deletable_by_policy(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'status' => true,
        ]);

        $log = ActivityLog::query()->create([
            'actor_id' => $admin->id,
            'actor_name' => $admin->name,
            'actor_role' => 'admin',
            'event' => 'test.activity',
            'category' => 'system',
            'description' => 'Activity Log deletion policy test.',
            'created_at' => now(),
        ]);

        $this->assertFalse(
            $admin->can('delete', $log)
        );

        $this->assertTrue(
            $admin->can('deleteAny', ActivityLog::class)
        );
    }

    public function test_website_index_contains_destructive_confirmation_action(): void
    {
        $view = file_get_contents(
            resource_path('views/admin/activity-logs/index.blade.php')
        );

        $this->assertStringContainsString(
            "route('admin.activity-logs.destroy-all')",
            $view
        );
        $this->assertStringContainsString(
            'Delete All Permanently',
            $view
        );
        $this->assertStringContainsString(
            'This cannot be undone',
            $view
        );
    }

    private function seedLogs(int $count): void
    {
        for ($index = 1; $index <= $count; $index++) {
            ActivityLog::query()->create([
                'event' => 'test.activity.' . $index,
                'category' => 'system',
                'description' => 'Test activity log #' . $index,
                'created_at' => now()->subSeconds($index),
            ]);
        }
    }
}