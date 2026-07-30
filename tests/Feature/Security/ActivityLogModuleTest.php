<?php

namespace Tests\Feature\Security;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ActivityLogModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_activity_log_pages(): void
    {
        $activityLog = $this->activityLog();

        $this->get(
            route('admin.activity-logs.index')
        )->assertRedirect(
            route('login')
        );

        $this->get(
            route(
                'admin.activity-logs.show',
                $activityLog
            )
        )->assertRedirect(
            route('login')
        );
    }

    public function test_non_admin_roles_cannot_access_activity_logs(): void
    {
        $activityLog = $this->activityLog();

        foreach ([
            'official',
            'dao',
            'tanod',
            'resident',
        ] as $role) {
            $user = $this->activeUser(
                $role
            );

            $this
                ->actingAs($user)
                ->get(
                    route(
                        'admin.activity-logs.index'
                    )
                )
                ->assertForbidden();

            $this
                ->actingAs($user)
                ->get(
                    route(
                        'admin.activity-logs.show',
                        $activityLog
                    )
                )
                ->assertForbidden();
        }
    }

    public function test_active_admin_can_view_index_and_log_details(): void
    {
        $admin = $this->activeUser(
            'admin',
            'Audit Administrator'
        );

        $activityLog = $this->activityLog([
            'actor_id' => $admin->id,
            'actor_name' => $admin->name,
            'actor_role' => 'admin',
            'event' => 'incident.status_updated',
            'category' => 'incident',
            'description' =>
                'An incident status was updated.',
            'ip_address' => '127.0.0.1',
        ]);

        $this
            ->actingAs($admin)
            ->get(
                route(
                    'admin.activity-logs.index'
                )
            )
            ->assertOk()
            ->assertSee(
                'Activity Logs'
            )
            ->assertSee(
                $activityLog->event
            )
            ->assertSee(
                $admin->name
            );

        $this
            ->actingAs($admin)
            ->get(
                route(
                    'admin.activity-logs.show',
                    $activityLog
                )
            )
            ->assertOk()
            ->assertSee(
                $activityLog->description
            )
            ->assertSee(
                '127.0.0.1'
            );
    }

    public function test_search_and_filters_return_only_matching_logs(): void
    {
        $admin = $this->activeUser(
            'admin'
        );

        $actor = $this->activeUser(
            'official',
            'Filter Actor'
        );

        $matching = $this->activityLog([
            'actor_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_role' => 'official',
            'event' => 'case.created',
            'category' => 'case',
            'description' =>
                'Special audit marker was recorded.',
            'created_at' => now()->subDay(),
        ]);

        $hidden = $this->activityLog([
            'event' => 'auth.login.succeeded',
            'category' => 'authentication',
            'description' =>
                'Unrelated authentication activity.',
            'created_at' => now()->subDays(10),
        ]);

        $this
            ->actingAs($admin)
            ->get(
                route(
                    'admin.activity-logs.index',
                    [
                        'search' =>
                            'Special audit marker',
                        'category' => 'case',
                        'event' => 'case.created',
                        'actor_id' => $actor->id,
                        'date_from' =>
                            now()
                                ->subDays(2)
                                ->format('Y-m-d'),
                        'date_to' =>
                            now()->format('Y-m-d'),
                        'per_page' => 10,
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                $matching->description
            )
            ->assertDontSee(
                $hidden->description
            );
    }

    public function test_search_payload_is_bound_instead_of_interpolated(): void
    {
        $admin = $this->activeUser(
            'admin'
        );

        $payload = "%' OR 1=1 --";

        $queries = [];

        DB::listen(
            function (
                QueryExecuted $query
            ) use (
                &$queries
            ): void {
                $queries[] = [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                ];
            }
        );

        $this
            ->actingAs($admin)
            ->get(
                route(
                    'admin.activity-logs.index',
                    [
                        'search' => $payload,
                    ]
                )
            )
            ->assertOk();

        $this->assertFalse(
            collect($queries)->contains(
                fn (array $query): bool =>
                    str_contains(
                        $query['sql'],
                        $payload
                    )
            )
        );
    }

    public function test_sensitive_metadata_is_redacted_on_details_page(): void
    {
        $admin = $this->activeUser(
            'admin'
        );

        $activityLog = $this->activityLog([
            'metadata' => [
                'password' =>
                    'never-display-this-password',
                'nested' => [
                    'authorization' =>
                        'Bearer never-display-this-token',
                    'safe_key' => 'safe-value',
                ],
            ],
        ]);

        $this
            ->actingAs($admin)
            ->get(
                route(
                    'admin.activity-logs.show',
                    $activityLog
                )
            )
            ->assertOk()
            ->assertSee(
                '[REDACTED]'
            )
            ->assertSee(
                'safe-value'
            )
            ->assertDontSee(
                'never-display-this-password'
            )
            ->assertDontSee(
                'never-display-this-token'
            );
    }

    public function test_activity_log_module_registers_read_only_routes(): void
    {
        $this->assertTrue(
            Route::has(
                'admin.activity-logs.index'
            )
        );

        $this->assertTrue(
            Route::has(
                'admin.activity-logs.show'
            )
        );

        $this->assertFalse(
            Route::has(
                'admin.activity-logs.store'
            )
        );

        $this->assertFalse(
            Route::has(
                'admin.activity-logs.update'
            )
        );

        $this->assertFalse(
            Route::has(
                'admin.activity-logs.destroy'
            )
        );
    }

    private function activeUser(
        string $role,
        string $name = 'Activity Log User'
    ): User {
        /** @var User $user */
        $user = User::factory()->create([
            'name' => $name,
            'role' => $role,
            'is_active' => true,
            'status' => true,
        ]);

        return $user;
    }

    private function activityLog(
        array $attributes = []
    ): ActivityLog {
        return ActivityLog::create(
            array_merge(
                [
                    'actor_id' => null,
                    'actor_name' => null,
                    'actor_role' => null,
                    'target_user_id' => null,
                    'target_name' => null,
                    'target_role' => null,
                    'event' => 'system.test_event',
                    'category' => 'system',
                    'description' =>
                        'Activity log module test record.',
                    'route_name' =>
                        'admin.activity-logs.index',
                    'request_method' => 'GET',
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'PHPUnit',
                    'metadata' => [
                        'test' => true,
                    ],
                    'created_at' => now(),
                ],
                $attributes
            )
        );
    }
}
