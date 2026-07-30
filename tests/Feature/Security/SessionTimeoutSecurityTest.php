<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionTimeoutSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_request_initializes_session_activity_timestamp(): void
    {
        config(['session.lifetime' => 30]);

        /** @var User $user */
        $user = $this->activeResident();

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertRedirect(route('resident.dashboard'));
        $response->assertSessionHas('security.last_activity_at');
        $this->assertAuthenticatedAs($user);
    }

    public function test_recent_authenticated_activity_refreshes_timestamp(): void
    {
        config(['session.lifetime' => 30]);

        /** @var User $user */
        $user = $this->activeResident();

        $previousTimestamp = time() - 60;

        $response = $this
            ->actingAs($user)
            ->withSession([
                'security.last_activity_at' => $previousTimestamp,
            ])
            ->get('/dashboard');

        $response->assertRedirect(route('resident.dashboard'));

        $response->assertSessionHas(
            'security.last_activity_at',
            fn ($value): bool => (int) $value > $previousTimestamp
        );

        $this->assertAuthenticatedAs($user);
    }

    public function test_expired_session_is_logged_out_and_redirected_to_login(): void
    {
        config(['session.lifetime' => 1]);

        /** @var User $user */
        $user = $this->activeResident();

        $response = $this
            ->actingAs($user)
            ->withSession([
                'security.last_activity_at' => time() - 61,
            ])
            ->get('/dashboard');

        $response->assertRedirect(route('login'));

        $response->assertSessionHas(
            'status',
            'Your session expired due to inactivity. Please log in again.'
        );

        $this->assertGuest();
    }

    public function test_presence_heartbeat_does_not_extend_session_activity(): void
    {
        config(['session.lifetime' => 30]);

        /** @var User $user */
        $user = $this->activeResident();

        $originalTimestamp = time() - 30;

        $response = $this
            ->actingAs($user)
            ->withSession([
                'security.last_activity_at' => $originalTimestamp,
            ])
            ->post('/presence/heartbeat');

        $response->assertSuccessful();
        $response->assertSessionHas(
            'security.last_activity_at',
            $originalTimestamp
        );

        $this->assertAuthenticatedAs($user);
    }

    public function test_expired_presence_heartbeat_returns_unauthorized_and_logs_out(): void
    {
        config(['session.lifetime' => 1]);

        /** @var User $user */
        $user = $this->activeResident();

        $response = $this
            ->actingAs($user)
            ->withSession([
                'security.last_activity_at' => time() - 61,
            ])
            ->postJson('/presence/heartbeat');

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Your session expired due to inactivity.',
            ]);

        $this->assertGuest();
    }

    public function test_session_cookie_security_defaults_remain_enabled(): void
    {
        $this->assertTrue((bool) config('session.http_only'));

        $this->assertContains(
            config('session.same_site'),
            ['lax', 'strict']
        );
    }

    private function activeResident(): User
    {
        /** @var User $user */
        $user = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
        ]);

        return $user;
    }
}
