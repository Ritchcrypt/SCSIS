<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Volt::test('auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        Notification::assertSentTo(
            $user,
            ResetPassword::class
        );
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Volt::test('auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification): bool {
                $response = $this->get(
                    '/reset-password/' . $notification->token
                );

                $response->assertStatus(200);

                return true;
            }
        );
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $newPassword = 'TabangNow#2026Secure';

        Volt::test('auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use (
                $user,
                $newPassword
            ): bool {
                Volt::test('auth.reset-password', [
                    'token' => $notification->token,
                ])
                    ->set('email', $user->email)
                    ->set('password', $newPassword)
                    ->set('password_confirmation', $newPassword)
                    ->call('resetPassword')
                    ->assertHasNoErrors()
                    ->assertRedirect(
                        route('login', absolute: false)
                    );

                return true;
            }
        );

        $this->assertTrue(
            Hash::check(
                $newPassword,
                $user->fresh()->password
            )
        );
    }
}