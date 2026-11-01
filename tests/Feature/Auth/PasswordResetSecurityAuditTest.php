<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\BrevoPasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PasswordResetSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_ends_database_sessions_and_rotates_remember_token_for_every_role(): void
    {
        Notification::fake();
        foreach (['admin', 'official', 'tanod', 'resident'] as $role) {
            $user = User::factory()->create([
                'role' => $role,
                'status' => true,
                'is_active' => true,
                'remember_token' => 'prior-remember-token-'.$role,
            ]);
            $sessionId = 'reset-audit-'.$role;
            DB::table('sessions')->insert([
                'id' => $sessionId,
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'TabangNow test',
                'payload' => 'testing',
                'last_activity' => now()->timestamp,
            ]);

            Volt::test('auth.forgot-password')
                ->set('email', $user->email)
                ->call('sendPasswordResetLink')
                ->assertHasNoErrors();
            $sent = Notification::sent($user, BrevoPasswordResetNotification::class)->first();
            $this->assertInstanceOf(BrevoPasswordResetNotification::class, $sent);

            Volt::test('auth.reset-password', ['token' => $sent->token])
                ->set('email', $user->email)
                ->set('password', 'SecureReset#2026Pass')
                ->set('password_confirmation', 'SecureReset#2026Pass')
                ->call('resetPassword')
                ->assertHasNoErrors();

            $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);
            $this->assertNotSame('prior-remember-token-'.$role, $user->fresh()->remember_token);
            $this->assertTrue(Hash::check('SecureReset#2026Pass', $user->fresh()->password));
        }
    }

    public function test_brevo_health_returns_success_on_authorized_ip_and_key(): void
    {
        config()->set('services.brevo.key', 'test-only-not-a-secret');
        config()->set('mail.from.address', 'sender@example.org');
        config()->set('auth.passwords.users.expire', 60);
        config()->set('auth.passwords.users.throttle', 60);
        Http::fake(['api.brevo.com/v3/account' => Http::response(['plan' => []], 200)]);
        $this->assertSame(0, Artisan::call('tabangnow:brevo-health', ['--live' => true]));
        Http::assertSentCount(1);
    }

    public function test_brevo_health_fails_on_ip_authorization_and_does_not_send_email(): void
    {
        config()->set('services.brevo.key', 'test-only-not-a-secret');
        config()->set('mail.from.address', 'sender@example.org');
        config()->set('auth.passwords.users.expire', 60);
        config()->set('auth.passwords.users.throttle', 60);
        Http::fake(['api.brevo.com/v3/account' => Http::response(['message' => 'not authorized'], 401)]);
        $this->assertSame(1, Artisan::call('tabangnow:brevo-health', ['--live' => true]));
        Http::assertSentCount(1);
    }

    public function test_production_url_rejects_non_https_even_when_provider_key_present(): void
    {
        config()->set('services.brevo.key', 'test-only-not-a-secret');
        config()->set('mail.from.address', 'sender@example.org');
        config()->set('app.url', 'http://127.0.0.1:8000');
        config()->set('auth.passwords.users.expire', 60);
        config()->set('auth.passwords.users.throttle', 60);
        app()->detectEnvironment(fn () => 'production');
        Http::fake();
        $this->assertSame(1, Artisan::call('tabangnow:brevo-health', ['--live' => true]));
        Http::assertNothingSent();
    }
}
