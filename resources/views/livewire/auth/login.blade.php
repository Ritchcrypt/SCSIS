<?php

use App\Models\User;
use App\Services\AdminTotpService;
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

    public bool $awaitingMfa = false;

    public string $mfaCode = '';

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

        // Authenticate the password without creating an authenticated session
        // until the second factor is verified for any enrolled account.
        $user = User::query()->where('email', $this->email)->first();
        if ($user instanceof User && ! empty($user->mfa_secret)) {
            if (! Auth::validate($credentials)) {
                RateLimiter::hit($this->throttleKey(), 60);
                throw ValidationException::withMessages(['email' => __('auth.failed')]);
            }

            // Rotate the pre-auth session ID without rotating its CSRF token.
            // Livewire must retain the same token until MFA completes.
            Session::migrate(true);
            Session::put('security.admin_mfa_pending', [
                'user_id' => $user->id,
                'remember' => $this->remember,
                'expires_at' => time() + 300,
            ]);
            $this->password = '';
            $this->awaitingMfa = true;
            return;
        }

        if (! Auth::attempt($credentials, $this->remember)) {
            RateLimiter::hit($this->throttleKey(), 60);
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
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

    public function verifyMfa(): void
    {
        $pending = Session::get('security.admin_mfa_pending');
        if (! is_array($pending) || (int) ($pending['expires_at'] ?? 0) < time()) {
            Session::forget('security.admin_mfa_pending');
            $this->awaitingMfa = false;
            throw ValidationException::withMessages(['mfaCode' => 'Verification expired. Sign in again.']);
        }

        $key = 'admin-mfa|'.(int) $pending['user_id'].'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['mfaCode' => 'Too many attempts. Try again later.']);
        }

        $user = User::query()->find((int) $pending['user_id']);
        if (! $user instanceof User || ! $user->isActive()
            || empty($user->mfa_secret)
            || ! app(AdminTotpService::class)->verifyAndConsume($user, $this->mfaCode)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['mfaCode' => 'Incorrect or previously used authenticator code.']);
        }

        RateLimiter::clear($key);
        RateLimiter::clear($this->throttleKey());
        Session::forget('security.admin_mfa_pending');
        Auth::login($user, (bool) ($pending['remember'] ?? false));
        Session::regenerate();
        Session::put('security.last_activity_at', time());
        $this->mfaCode = '';
        $this->awaitingMfa = false;
        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    public function cancelMfa(): void
    {
        Session::forget('security.admin_mfa_pending');
        $this->awaitingMfa = false;
        $this->mfaCode = '';
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

    @if($awaitingMfa)
      <form wire:submit="verifyMfa" class="tn21-form">
        <p>Enter the 6-digit code from your authenticator app, or an unused recovery code.</p>
        <flux:input wire:model="mfaCode" :label="__('Authenticator code')" name="mfaCode" type="text" required autocomplete="one-time-code" />
        @error('mfaCode') <p role="alert">{{ $message }}</p> @enderror
        <div class="tn21-submit"><flux:button variant="primary" type="submit">Verify and sign in</flux:button></div>
        <button type="button" wire:click="cancelMfa" class="tn21-link">Back to sign in</button>
      </form>
    @else
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
    @endif

</div>
