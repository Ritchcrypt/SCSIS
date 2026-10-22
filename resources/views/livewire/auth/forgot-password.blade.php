<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        try {
            Password::sendResetLink($this->only('email'));
        } catch (TransportExceptionInterface $exception) {
            // PASSWORD_RESET_EMAIL_FAILURE_HANDLED: do not expose provider diagnostics to visitors.
            try {
                Log::warning('Password reset email delivery failed.', [
                    'mailer' => (string) config('mail.default'),
                    'exception_type' => get_class($exception),
                    'exception_code' => (string) $exception->getCode(),
                ]);
            } catch (\Throwable) {
                // A logging outage must not trigger an HTTP 500 either.
            }

            session()->flash('mail_error', __('We could not send the password reset email right now. Please try again later.'));

            return;
        }

        session()->flash('status', __('A reset link will be sent if the account exists.'));
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Forgot password" description="Enter your email to receive a password reset link" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    @if (session('mail_error'))
        <div role="alert" class="rounded-lg border border-red-400/40 bg-red-950/30 px-4 py-3 text-sm text-red-100">
            {{ session('mail_error') }}
        </div>
    @endif

    <form wire:submit="sendPasswordResetLink" class="flex flex-col gap-6">
        <!-- Email Address -->
        <div class="grid gap-2">
            <flux:input wire:model="email" label="{{ __('Email Address') }}" type="email" name="email" required autofocus placeholder="email@example.com" />
        </div>

        <flux:button variant="primary" type="submit" class="w-full">{{ __('Email password reset link') }}</flux:button>
    </form>

    <div class="space-x-1 text-center text-sm text-zinc-400">
        Or, return to
        <x-text-link href="{{ route('login') }}">log in</x-text-link>
    </div>
</div>
