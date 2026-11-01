<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\BrevoPasswordResetNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordResetBrevoParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_notification_is_pinned_to_brevo_https_mailer(): void
    {
        $user = new User(['name' => 'Test Resident', 'email' => 'resident@example.com']);
        $notification = new BrevoPasswordResetNotification('test-token');
        $this->assertInstanceOf(ResetPassword::class, $notification);
        $this->assertSame('brevo', $notification->toMail($user)->mailer);
    }

    public function test_brevo_readiness_does_not_trust_log_mailer_as_delivered(): void
    {
        // The notification always chooses Brevo, regardless of default mailer.
        config()->set('mail.default', 'log');
        config()->set('services.brevo.key', 'fake-test-key');
        config()->set('mail.from.address', 'no-reply@example.org');
        // No DB is needed here: run schema checks only in integration tests.
        $this->assertSame('brevo', (new BrevoPasswordResetNotification('x'))
            ->toMail(new User(['email' => 'resident@example.com']))->mailer);
    }

    public function test_web_and_mobile_admin_actions_use_the_same_brevo_notification(): void
    {
        Notification::fake();
        config()->set('services.brevo.key', 'fake-key-not-used-with-notification-fake');
        config()->set('mail.from.address', 'no-reply@example.org');

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $webTarget = User::factory()->create(['role' => 'resident', 'is_active' => true]);
        $mobileTarget = User::factory()->create(['role' => 'resident', 'is_active' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.users.reset-password', $webTarget))
            ->assertRedirect();
        Notification::assertSentTo($webTarget, BrevoPasswordResetNotification::class);

        $token = $admin->createToken('Android', ['mobile'], now()->addDays(30))->plainTextToken;
        $this->withToken($token)
            ->patchJson('/api/v1/users/'.$mobileTarget->id.'/reset-password')
            ->assertOk();
        Notification::assertSentTo($mobileTarget, BrevoPasswordResetNotification::class);
    }

    public function test_admin_web_and_mobile_fail_closed_without_brevo_key(): void
    {
        Notification::fake();
        config()->set('services.brevo.key', '');
        config()->set('mail.from.address', 'no-reply@example.org');

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $target = User::factory()->create(['role' => 'resident', 'is_active' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.users.reset-password', $target))
            ->assertRedirect()
            ->assertSessionHas('error');

        $token = $admin->createToken('Android', ['mobile'], now()->addDays(30))->plainTextToken;
        $this->withToken($token)
            ->patchJson('/api/v1/users/'.$target->id.'/reset-password')
            ->assertStatus(503);

        Notification::assertNothingSent();
    }

    public function test_mobile_public_forgot_password_returns_safe_503_on_brevo_failure(): void
    {
        Password::shouldReceive('sendResetLink')
            ->once()
            ->andThrow(new \RuntimeException('Simulated Brevo API error with private diagnostic'));

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'resident@example.com'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Password reset email is temporarily unavailable. Please try again later.')
            ->assertDontSee('private diagnostic');
    }

    public function test_mobile_public_forgot_password_does_not_disclose_unknown_email(): void
    {
        Password::shouldReceive('sendResetLink')->once()->andReturn(Password::INVALID_USER);
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unknown@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'A reset link will be sent if the account exists.');
    }
}
