<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserPresenceParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_heartbeat_marks_any_active_authenticated_role_online(): void
    {
        foreach (['admin', 'official', 'tanod', 'resident'] as $role) {
            $user = User::factory()->create([
                'role' => $role,
                'is_active' => true,
                'status' => true,
                'last_seen_at' => now()->subMinutes(10),
            ]);

            Sanctum::actingAs($user, ['mobile']);

            $this->postJson('/api/v1/presence/heartbeat')
                ->assertOk()
                ->assertJsonPath('ok', true);

            $user->refresh();

            $this->assertNotNull($user->last_seen_at);
            $this->assertTrue(
                Carbon::parse($user->last_seen_at)
                    ->greaterThanOrEqualTo(now()->subSeconds(5)),
                "Expected {$role} heartbeat to refresh last_seen_at."
            );
        }
    }

    public function test_admin_presence_snapshot_uses_same_two_minute_window(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'status' => true,
        ]);

        $online = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
            'last_seen_at' => now()->subSeconds(30),
        ]);

        $offline = User::factory()->create([
            'role' => 'tanod',
            'is_active' => true,
            'status' => true,
            'last_seen_at' => now()->subMinutes(3),
        ]);

        Sanctum::actingAs($admin, ['mobile']);

        $this->getJson('/api/v1/presence/users')
            ->assertOk()
            ->assertJsonPath(
                "users.{$online->id}.online",
                true
            )
            ->assertJsonPath(
                "users.{$offline->id}.online",
                false
            );
    }

    public function test_non_admin_cannot_read_system_wide_presence_snapshot(): void
    {
        $resident = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
        ]);

        Sanctum::actingAs($resident, ['mobile']);

        $this->getJson('/api/v1/presence/users')
            ->assertForbidden();
    }

    public function test_mobile_login_marks_presence_immediately_and_logout_clears_it(): void
    {
        $user = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
            'password' => Hash::make('PresenceTest!12345'),
            'last_seen_at' => null,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'PresenceTest!12345',
            'device_name' => 'Presence Test Device',
        ]);

        $login->assertOk();

        $user->refresh();
        $this->assertNotNull($user->last_seen_at);

        $token = $login->json('access_token');
        $this->assertIsString($token);
        $this->assertNotSame('', $token);

        $this->withHeader(
            'Authorization',
            'Bearer ' . $token
        )
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $user->refresh();
        $this->assertNull($user->last_seen_at);
    }

    public function test_website_user_management_contains_live_presence_hooks(): void
    {
        $view = file_get_contents(
            resource_path('views/admin/users/index.blade.php')
        );

        $this->assertStringContainsString(
            'data-user-presence-id',
            $view
        );
        $this->assertStringContainsString(
            "route('admin.users.presence')",
            $view
        );
        $this->assertStringContainsString(
            'pollMilliseconds = 30000',
            $view
        );
        $this->assertStringContainsString(
            'userPresenceOnlineCount',
            $view
        );
        $this->assertStringContainsString(
            'userPresenceOfflineCount',
            $view
        );
    }
}