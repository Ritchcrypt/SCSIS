<?php

namespace Tests\Feature\Security;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AuthenticationActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_event_creates_an_activity_record(): void
    {
        /** @var User $user */
        $user = $this->activeResident();

        Event::dispatch(
            new Login(
                'web',
                $user,
                false
            )
        );

        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'target_user_id' => $user->id,
            'event' => 'auth.login.succeeded',
            'category' => 'authentication',
        ]);

        $log = ActivityLog::query()
            ->where('event', 'auth.login.succeeded')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            'web',
            $log->metadata['guard']
        );

        $this->assertFalse(
            $log->metadata['remember']
        );
    }

    public function test_failed_login_does_not_store_raw_credentials(): void
    {
        /** @var User $user */
        $user = $this->activeResident();

        $secretPassword = 'NeverStoreThis#2026';

        Event::dispatch(
            new Failed(
                'web',
                $user,
                [
                    'email' => $user->email,
                    'password' => $secretPassword,
                ]
            )
        );

        $log = ActivityLog::query()
            ->where('event', 'auth.login.failed')
            ->latest('id')
            ->firstOrFail();

        $encodedMetadata = json_encode(
            $log->metadata,
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            $secretPassword,
            $encodedMetadata
        );

        $this->assertStringNotContainsString(
            $user->email,
            $encodedMetadata
        );

        $this->assertSame(
            hash('sha256', strtolower($user->email)),
            $log->metadata['identifier_hash']
        );
    }

    public function test_lockout_records_only_a_hashed_identifier(): void
    {
        $email = 'locked.resident@example.com';

        $request = Request::create(
            '/login',
            'POST',
            [
                'email' => $email,
                'password' => 'DoNotStoreThis#2026',
            ]
        );

        Event::dispatch(
            new Lockout($request)
        );

        $log = ActivityLog::query()
            ->where('event', 'auth.login.locked_out')
            ->latest('id')
            ->firstOrFail();

        $encodedMetadata = json_encode(
            $log->metadata,
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            $email,
            $encodedMetadata
        );

        $this->assertSame(
            hash('sha256', $email),
            $log->metadata['identifier_hash']
        );
    }

    public function test_logout_route_records_a_user_initiated_reason(): void
    {
        /** @var User $user */
        $user = $this->activeResident();

        $response = $this
            ->actingAs($user)
            ->post(route('logout'));

        $response->assertRedirect('/');

        $this->assertGuest();

        $log = ActivityLog::query()
            ->where('event', 'auth.logout')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            $user->id,
            $log->actor_id
        );

        $this->assertSame(
            'user_initiated',
            $log->metadata['reason']
        );
    }

    public function test_inactivity_timeout_records_the_security_reason(): void
    {
        config([
            'session.lifetime' => 1,
        ]);

        /** @var User $user */
        $user = $this->activeResident();

        $response = $this
            ->actingAs($user)
            ->withSession([
                'security.last_activity_at' => time() - 61,
            ])
            ->get('/dashboard');

        $response->assertRedirect(route('login'));

        $this->assertGuest();

        $log = ActivityLog::query()
            ->where('event', 'auth.logout')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            'inactivity_timeout',
            $log->metadata['reason']
        );
    }

    public function test_registration_reset_and_verification_events_are_recorded(): void
    {
        /** @var User $user */
        $user = $this->activeResident();

        Event::dispatch(new Registered($user));
        Event::dispatch(new PasswordReset($user));
        Event::dispatch(new Verified($user));

        $this->assertDatabaseHas('activity_logs', [
            'target_user_id' => $user->id,
            'event' => 'account.registered',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'target_user_id' => $user->id,
            'event' => 'auth.password_reset.completed',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'target_user_id' => $user->id,
            'event' => 'account.email_verified',
        ]);
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
