<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\BrevoPasswordResetNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Volt\Volt;
use Tests\TestCase;

/** Cross-role browser/mobile contracts; all notifications are faked (no real emails). */
class PasswordResetAllRolesIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const ROLES = ['admin', 'official', 'tanod', 'resident'];
    private const PASSWORD = 'NewPassword#2026Strong';

    private function userFor(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'status' => true,
        ]);
    }

    private function resetToken(User $user): string
    {
        $notification = Notification::sent($user, BrevoPasswordResetNotification::class)->first();
        $this->assertInstanceOf(BrevoPasswordResetNotification::class, $notification);
        $this->assertSame('brevo', $notification->toMail($user)->mailer);
        return $notification->token;
    }

    public function test_every_role_can_request_reset_from_website(): void
    {
        Notification::fake();
        foreach (self::ROLES as $role) {
            $user = $this->userFor($role);
            Volt::test('auth.forgot-password')
                ->set('email', $user->email)
                ->call('sendPasswordResetLink')
                ->assertHasNoErrors();
            Notification::assertSentTo($user, BrevoPasswordResetNotification::class);
            $this->resetToken($user);
        }
    }

    public function test_every_role_can_request_reset_from_mobile_public_api(): void
    {
        Notification::fake();
        foreach (self::ROLES as $role) {
            $user = $this->userFor($role);
            $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
                ->assertOk()
                ->assertJsonPath('message', 'A reset link will be sent if the account exists.');
            Notification::assertSentTo($user, BrevoPasswordResetNotification::class);
            $this->resetToken($user);
        }
    }

    public function test_every_role_can_finish_reset_and_old_token_cannot_be_reused(): void
    {
        Notification::fake();
        foreach (self::ROLES as $role) {
            $user = $this->userFor($role);
            Volt::test('auth.forgot-password')
                ->set('email', $user->email)
                ->call('sendPasswordResetLink');
            $token = $this->resetToken($user);

            Volt::test('auth.reset-password', ['token' => $token])
                ->set('email', $user->email)
                ->set('password', self::PASSWORD)
                ->set('password_confirmation', self::PASSWORD)
                ->call('resetPassword')
                ->assertHasNoErrors()
                ->assertRedirect(route('login', absolute: false));

            $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password), $role);

            // Consumed tokens cannot change a password a second time.
            Volt::test('auth.reset-password', ['token' => $token])
                ->set('email', $user->email)
                ->set('password', 'SecondPassword#2026Strong')
                ->set('password_confirmation', 'SecondPassword#2026Strong')
                ->call('resetPassword')
                ->assertHasErrors(['email']);
            $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password), $role);
        }
    }

    public function test_password_reset_revokes_mobile_access_and_push_tokens_for_every_role(): void
    {
        Notification::fake();
        foreach (self::ROLES as $role) {
            $user = $this->userFor($role);
            $accessToken = $user->createToken('Android', ['mobile'], now()->addDays(30));
            $tokenId = (int) $accessToken->accessToken->id;

            DB::table('mobile_push_tokens')->insert([
                'user_id' => $user->id,
                'installation_id' => 'test-reset-'.$role,
                'fcm_token' => 'test-only-token-'.$role,
                'token_hash' => hash('sha256', 'test-only-token-'.$role),
                'platform' => 'android',
                'created_at' => now(),
                'updated_at' => now(),
                'revoked_at' => null,
            ]);

            Volt::test('auth.forgot-password')->set('email', $user->email)
                ->call('sendPasswordResetLink');
            $token = $this->resetToken($user);
            Volt::test('auth.reset-password', ['token' => $token])
                ->set('email', $user->email)
                ->set('password', self::PASSWORD)
                ->set('password_confirmation', self::PASSWORD)
                ->call('resetPassword')
                ->assertHasNoErrors();

            $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
            $this->assertDatabaseMissing('mobile_push_tokens', [
                'user_id' => $user->id,
                'revoked_at' => null,
            ]);
        }
    }

    public function test_admin_can_send_reset_links_for_every_target_role_on_web_and_mobile(): void
    {
        Notification::fake();
        config()->set('services.brevo.key', 'dummy-testing-only');
        config()->set('mail.from.address', 'noreply@example.org');
        $admin = $this->userFor('admin');
        $apiToken = $admin->createToken('Android', ['mobile'], now()->addDays(30))->plainTextToken;

        foreach (self::ROLES as $role) {
            $webTarget = $this->userFor($role);
            $this->actingAs($admin)
                ->patch(route('admin.users.reset-password', $webTarget))
                ->assertRedirect()
                ->assertSessionHas('success');
            Notification::assertSentTo($webTarget, BrevoPasswordResetNotification::class);

            $mobileTarget = $this->userFor($role);
            $this->withToken($apiToken)
                ->patchJson('/api/v1/users/'.$mobileTarget->id.'/reset-password')
                ->assertOk();
            Notification::assertSentTo($mobileTarget, BrevoPasswordResetNotification::class);
        }
    }

    public function test_non_admin_roles_cannot_reset_another_users_password_through_management(): void
    {
        Notification::fake();
        $target = $this->userFor('resident');
        foreach (['official', 'tanod', 'resident'] as $role) {
            $actor = $this->userFor($role);
            $apiToken = $actor->createToken('Android', ['mobile'], now()->addDays(30))->plainTextToken;
            $this->actingAs($actor)
                ->patch(route('admin.users.reset-password', $target))
                ->assertForbidden();
            $this->withToken($apiToken)
                ->patchJson('/api/v1/users/'.$target->id.'/reset-password')
                ->assertForbidden();
        }
        Notification::assertNothingSent();
    }

    public function test_expired_reset_token_is_rejected_without_changing_password(): void
    {
        $user = $this->userFor('resident');
        $original = $user->password;
        $token = Password::createToken($user);
        $this->travel(61)->minutes();

        Volt::test('auth.reset-password', ['token' => $token])
            ->set('email', $user->email)
            ->set('password', self::PASSWORD)
            ->set('password_confirmation', self::PASSWORD)
            ->call('resetPassword')
            ->assertHasErrors(['email']);
        $this->assertSame($original, $user->fresh()->password);
    }
}
