<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => [
                'required',
                'string',
            ],
        ]);

        $this->ensureIsNotRateLimited();

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            RateLimiter::hit(
                $this->throttleKey(),
                60
            );

            $this->password = '';

            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        $this->password = '';

        session([
            'auth.password_confirmed_at' => time(),
        ]);

        $this->redirectIntended(
            default: route('dashboard', absolute: false),
            navigate: true
        );
    }

    /**
     * Block repeated password-confirmation attempts.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts(
            $this->throttleKey(),
            5
        )) {
            return;
        }

        $seconds = RateLimiter::availableIn(
            $this->throttleKey()
        );

        $this->password = '';

        throw ValidationException::withMessages([
            'password' => "Too many confirmation attempts. Try again in {$seconds} seconds.",
        ]);
    }

    /**
     * Generate a user-and-IP-specific throttle key.
     */
    protected function throttleKey(): string
    {
        return 'password-confirmation|'
            . (string) Auth::id()
            . '|'
            . request()->ip();
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header
        title="Confirm password"
        description="This is a secure area of the application. Please confirm your password before continuing."
    />

    <x-auth-session-status
        class="text-center"
        :status="session('status')"
    />

    <form wire:submit="confirmPassword" class="flex flex-col gap-6">
        <div class="grid gap-2">
            <flux:input
                wire:model="password"
                id="password"
                label="{{ __('Password') }}"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                placeholder="Password"
            />
        </div>

        <flux:button
            variant="primary"
            type="submit"
            class="w-full"
        >
            {{ __('Confirm') }}
        </flux:button>
    </form>
</div>