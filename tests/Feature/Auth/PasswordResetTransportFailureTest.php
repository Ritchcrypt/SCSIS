<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Password;
use Livewire\Volt\Volt;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

/**
 * Database-free and network-free regression tests for password reset delivery.
 */
class PasswordResetTransportFailureTest extends TestCase
{
    public function test_brevo_transport_failure_shows_safe_message_instead_of_http_500(): void
    {
        Password::shouldReceive('sendResetLink')
            ->once()
            ->with(['email' => 'resident@example.com'])
            ->andThrow(new TransportException('Simulated Brevo IP rejection with private provider diagnostics.'));

        Volt::test('auth.forgot-password')
            ->set('email', 'resident@example.com')
            ->call('sendPasswordResetLink')
            ->assertHasNoErrors()
            ->assertSee('We could not send the password reset email right now.')
            ->assertDontSee('private provider diagnostics')
            ->assertDontSee('A reset link will be sent if the account exists.');
    }

    public function test_successful_password_reset_request_preserves_generic_confirmation(): void
    {
        Password::shouldReceive('sendResetLink')
            ->once()
            ->with(['email' => 'resident@example.com'])
            ->andReturn(Password::RESET_LINK_SENT);

        Volt::test('auth.forgot-password')
            ->set('email', 'resident@example.com')
            ->call('sendPasswordResetLink')
            ->assertHasNoErrors()
            ->assertSee('A reset link will be sent if the account exists.')
            ->assertDontSee('We could not send the password reset email right now.');
    }
}
