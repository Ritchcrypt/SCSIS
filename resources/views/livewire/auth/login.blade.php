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

<div>
<p class="tn21-login-description">
        Access your account to continue
    </p>

    <x-auth-session-status
        class="text-center"
        :status="session('status')"
    />

    <form
        wire:submit="login"
        class="tn21-form"
    >

        <flux:input
            wire:model="email"
            :label="__('Email Address')"
            type="email"
            name="email"
            required
            autofocus
            autocomplete="email"
            placeholder="Enter your email"
        />

        <div class="tn21-password-wrap">

            <flux:input
                wire:model="password"
                :label="__('Password')"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                :placeholder="__('Enter your password')"
                viewable
            />

            @if (Route::has('password.request'))

                <div class="tn21-forgot-row">

                    <a
                        href="{{ route('password.request') }}"
                        class="tn21-link"
                    >
                        {{ __('Forgot Password?') }}
                    </a>

                </div>

            @endif

        </div>

        <label
            for="remember"
            class="tn21-remember"
        >

            <input
                id="remember"
                name="remember"
                type="checkbox"
                value="1"
                wire:model="remember"
            >

            <span>
                {{ __('Remember me') }}
            </span>

        </label>

        <div class="tn21-submit">

            <flux:button
                variant="primary"
                type="submit"
            >
                {{ __('Sign In') }}
            </flux:button>

        </div>

        <div class="tn21-divider">
            <span>or</span>
        </div>

        <a
            href="{{ route('register') }}"
            class="tn21-signup"
        >
            {{ __('Sign Up') }}
        </a>

    </form>

</div>
