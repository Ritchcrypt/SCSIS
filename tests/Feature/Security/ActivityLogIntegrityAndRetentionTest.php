<?php

namespace Tests\Feature\Security;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Tests\TestCase;

class ActivityLogIntegrityAndRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_administrators_can_view_activity_logs(): void
    {
        /** @var User $admin */
        $admin = $this->activeUser('admin');

        /** @var User $resident */
        $resident = $this->activeUser('resident');

        /** @var User $inactiveAdmin */
        $inactiveAdmin = User::factory()->create([
            'role' => 'admin',
            'is_active' => false,
            'status' => false,
        ]);

        $log = $this->activityLog();

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'viewAny',
                ActivityLog::class
            )
        );

        $this->assertTrue(
            Gate::forUser($admin)->allows(
                'view',
                $log
            )
        );

        $this->assertFalse(
            Gate::forUser($resident)->allows(
                'viewAny',
                ActivityLog::class
            )
        );

        $this->assertFalse(
            Gate::forUser($inactiveAdmin)->allows(
                'viewAny',
                ActivityLog::class
            )
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'update',
                $log
            )
        );

        $this->assertFalse(
            Gate::forUser($admin)->allows(
                'delete',
                $log
            )
        );
    }

    public function test_existing_activity_logs_cannot_be_updated_through_eloquent(): void
    {
        $log = $this->activityLog([
            'description' => 'Original immutable description.',
        ]);

        $this->expectException(LogicException::class);

        $log->update([
            'description' => 'Attempted modification.',
        ]);
    }

    public function test_existing_activity_logs_cannot_be_deleted_through_eloquent(): void
    {
        $log = $this->activityLog();

        $this->expectException(LogicException::class);

        $log->delete();
    }

    public function test_prune_dry_run_does_not_delete_records(): void
    {
        $old = $this->activityLog([
            'event' => 'test.old',
            'created_at' => now()->subDays(400),
        ]);

        $recent = $this->activityLog([
            'event' => 'test.recent',
            'created_at' => now()->subDays(10),
        ]);

        $this->artisan('activity-logs:prune', [
            '--days' => 365,
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('activity_logs', [
            'id' => $old->id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'id' => $recent->id,
        ]);
    }

    public function test_prune_command_requires_force_before_deletion(): void
    {
        $old = $this->activityLog([
            'created_at' => now()->subDays(400),
        ]);

        $this->artisan('activity-logs:prune', [
            '--days' => 365,
        ])->assertExitCode(1);

        $this->assertDatabaseHas('activity_logs', [
            'id' => $old->id,
        ]);
    }

    public function test_prune_command_deletes_only_expired_records_and_audits_the_action(): void
    {
        $old = $this->activityLog([
            'event' => 'test.expired',
            'created_at' => now()->subDays(400),
        ]);

        $recent = $this->activityLog([
            'event' => 'test.retained',
            'created_at' => now()->subDays(10),
        ]);

        $this->artisan('activity-logs:prune', [
            '--days' => 365,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseMissing('activity_logs', [
            'id' => $old->id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'id' => $recent->id,
        ]);

        $pruneLog = ActivityLog::query()
            ->where('event', 'activity_log.pruned')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            365,
            $pruneLog->metadata['retention_days']
        );

        $this->assertSame(
            1,
            $pruneLog->metadata['deleted_count']
        );
    }

    public function test_prune_command_rejects_a_retention_period_below_the_guardrail(): void
    {
        $old = $this->activityLog([
            'created_at' => now()->subDays(400),
        ]);

        $this->artisan('activity-logs:prune', [
            '--days' => 30,
            '--force' => true,
        ])->assertExitCode(1);

        $this->assertDatabaseHas('activity_logs', [
            'id' => $old->id,
        ]);
    }

    public function test_user_deletion_does_not_rewrite_historical_audit_identifiers(): void
    {
        /** @var User $admin */
        $admin = $this->activeUser('admin');

        /** @var User $target */
        $target = $this->activeUser('resident');

        $historicalLog = $this->activityLog([
            'actor_id' => $target->id,
            'actor_name' => $target->name,
            'actor_role' => $target->role,
            'target_user_id' => $target->id,
            'target_name' => $target->name,
            'target_role' => $target->role,
            'event' => 'test.historical_identity',
        ]);

        $response = $this
            ->actingAs($admin)
            ->delete(
                route(
                    'admin.users.destroy',
                    $target
                )
            );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $historicalLog->refresh();

        $this->assertSame(
            $target->id,
            $historicalLog->actor_id
        );

        $this->assertSame(
            $target->id,
            $historicalLog->target_user_id
        );

        $this->assertSame(
            $target->name,
            $historicalLog->actor_name
        );

        $this->assertSame(
            $target->name,
            $historicalLog->target_name
        );
    }

    private function activityLog(
        array $attributes = []
    ): ActivityLog {
        return ActivityLog::query()->create(
            array_merge([
                'actor_id' => null,
                'actor_name' => null,
                'actor_role' => null,
                'target_user_id' => null,
                'target_name' => null,
                'target_role' => null,
                'event' => 'test.activity',
                'category' => 'security',
                'description' => 'Test activity record.',
                'route_name' => null,
                'request_method' => null,
                'ip_address' => null,
                'user_agent' => null,
                'metadata' => [],
                'created_at' => now(),
            ], $attributes)
        );
    }

    private function activeUser(
        string $role
    ): User {
        /** @var User $user */
        $user = User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'status' => true,
        ]);

        return $user;
    }
}
