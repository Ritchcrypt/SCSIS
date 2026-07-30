<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Validate('required|string|email|max:255')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->email = Str::lower(trim($this->email));

        $this->validate();

        $this->ensureIsNotRateLimited();

        $credentials = [
            'email' => $this->email,
            'password' => $this->password,
            'is_active' => true,
        ];

        if (! Auth::attempt($credentials, $this->remember)) {
            RateLimiter::hit($this->throttleKey(), 60);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        Session::regenerate();

        Session::put(
            'security.last_activity_at',
            time()
        );

        $this->password = '';

        $this->redirectIntended(
            default: route('dashboard', absolute: false),
            navigate: true
        );
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower(trim($this->email)) . '|' . request()->ip()
        );
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header
        title="Log in to your account"
        description="Enter your email and password below to log in"
    />

    <x-auth-session-status
        class="text-center"
        :status="session('status')"
    />

    <form wire:submit="login" class="flex flex-col gap-6">
        <flux:input
            wire:model="email"
            :label="__('Email address')"
            type="email"
            name="email"
            required
            autofocus
            autocomplete="email"
            placeholder="email@example.com"
        />

        <div class="relative">
            <flux:input
                wire:model="password"
                :label="__('Password')"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                :placeholder="__('Password')"
                viewable
            />

            @if (Route::has('password.request'))
                <x-text-link
                    class="absolute right-0 top-0"
                    href="{{ route('password.request') }}"
                >
                    {{ __('Forgot your password?') }}
                </x-text-link>
            @endif
        </div>

        <label for="remember" class="tn-auth-remember">
            <input
                id="remember"
                name="remember"
                type="checkbox"
                value="1"
                wire:model="remember"
                class="tn-auth-remember-input"
            >

            <span class="tn-auth-remember-label">
                {{ __('Remember me') }}
            </span>
        </label>

        <div class="flex items-center justify-end">
            <flux:button
                variant="primary"
                type="submit"
                class="w-full"
            >
                {{ __('Log in') }}
            </flux:button>
        </div>
    </form>

    <div class="space-x-1 text-center text-sm text-zinc-600 dark:text-zinc-400">
        <span>Don't have an account?</span>

        <x-text-link href="{{ route('register') }}">
            {{ __('Sign up') }}
        </x-text-link>
    </div>
</div>
