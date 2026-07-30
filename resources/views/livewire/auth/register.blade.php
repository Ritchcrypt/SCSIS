<?php

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $name = '';

    public string $email = '';

public string $contact_number = '';

public string $address = '';

public string $password = '';

    public string $password_confirmation = '';

    /**
     * Register a new resident account.
     */
    public function register(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Normalize user-supplied values
        |--------------------------------------------------------------------------
        */

        $this->name = trim($this->name);
$this->email = Str::lower(trim($this->email));

$this->contact_number = (string) preg_replace(
    '/[\s\-\(\)]+/',
    '',
    trim($this->contact_number)
);

/*
|--------------------------------------------------------------------------
| Philippine mobile-number normalisation
|--------------------------------------------------------------------------
|
| Accepts +639XXXXXXXXX from the form and stores it consistently as
| 09XXXXXXXXX.
|
*/

if (Str::startsWith($this->contact_number, '+63')) {
    $this->contact_number = '0' . substr($this->contact_number, 3);
}

$this->address = (string) preg_replace(
    '/\s+/',
    ' ',
    trim($this->address)
);

        /*
        |--------------------------------------------------------------------------
        | Registration throttling
        |--------------------------------------------------------------------------
        |
        | Limits successful account creation from the same IP address.
        |
        */

        $this->ensureIsNotRateLimited();

        $validated = $this->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                'unique:users,email',
            ],

                'contact_number' => [
                'required',
                'string',
                'regex:/^09\d{9}$/',
            ],

                'address' => [
                'required',
                'string',
                'max:500',
            ],

                'password' => [
                'required',
                'string',
                'confirmed',
                Rules\Password::defaults(),
            ],
        
            ], [
                'contact_number.required' => 'Contact number is required.',
                'contact_number.regex' => 'Enter a valid Philippine mobile number, such as 09123456789.',
                'address.required' => 'Complete address is required.',
                'address.max' => 'Address must not exceed 500 characters.',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Force safe account properties
        |--------------------------------------------------------------------------
        |
        | Public users cannot select their own role or activation status.
        |
        */

        $user = User::query()->create([
    'name' => $validated['name'],
    'email' => $validated['email'],
    'contact_number' => $validated['contact_number'],
    'address' => $validated['address'],
    'password' => Hash::make($validated['password']),
    'role' => 'resident',
    'is_active' => false,
    'status' => false,
]);

        /*
        |--------------------------------------------------------------------------
        | Notify administrators
        |--------------------------------------------------------------------------
        |
        | Every active administrator receives a notification that a new resident
        | account is awaiting approval.
        |
        */

        if (
            Schema::hasTable('notifications')
            && Schema::hasColumn('notifications', 'user_id')
        ) {
            $adminIds = User::query()
                ->where('role', 'admin')
                ->where('is_active', true)
                ->pluck('id');

            foreach ($adminIds as $adminId) {
                $notification = new UserNotification();

                $notification->forceFill([
                    'user_id' => $adminId,
                    'type' => 'user_registration',
                    'source_id' => $user->id,
                    'title' => 'New resident registration',
                    'message' => "{$user->name} registered a resident account and is awaiting administrator approval.",
                    'is_read' => false,
                ])->save();
            }
        }

        event(new Registered($user));

        /*
        |--------------------------------------------------------------------------
        | Count the successful registration
        |--------------------------------------------------------------------------
        */

        RateLimiter::hit($this->throttleKey(), 600);

        /*
        |--------------------------------------------------------------------------
        | Remove passwords from Livewire component state
        |--------------------------------------------------------------------------
        */

        $this->password = '';
        $this->password_confirmation = '';

        /*
        |--------------------------------------------------------------------------
        | Do not automatically authenticate pending accounts
        |--------------------------------------------------------------------------
        */

        session()->flash(
            'status',
            'Your resident account was created successfully and is awaiting administrator approval.'
        );

        $this->redirectRoute('login', navigate: true);
    }

    /**
     * Prevent excessive account creation.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 3)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Too many registration attempts. Try again in {$seconds} seconds.",
        ]);
    }

    /**
     * Generate a registration throttle key.
     */
    protected function throttleKey(): string
    {
        return 'registration|' . request()->ip();
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header
        title="Create a resident account"
        description="Register for access to TabangNow community services"
    />

    <x-auth-session-status
        class="text-center"
        :status="session('status')"
    />

    <form
    wire:submit="register"
    class="flex flex-col gap-6"
    x-data="{
        passwordValue: '',
        confirmationValue: '',

        strengthScore(value) {
            value = value || '';

            let score = 0;

            if (value.length >= 12) {
                score++;
            }

            if (/[a-z]/.test(value)) {
                score++;
            }

            if (/[A-Z]/.test(value)) {
                score++;
            }

            if (/[0-9]/.test(value)) {
                score++;
            }

            if (/[^A-Za-z0-9]/.test(value)) {
                score++;
            }

            return score;
        },

        strengthLabel(value) {
            const labels = [
                'Start typing',
                'Very weak',
                'Weak',
                'Fair',
                'Strong',
                'Very strong',
            ];

            return labels[this.strengthScore(value)];
        },

        strengthClass(value) {
            const classes = [
                'is-empty',
                'is-very-weak',
                'is-weak',
                'is-fair',
                'is-strong',
                'is-very-strong',
            ];

            return classes[this.strengthScore(value)];
        },

        passwordsMatch() {
            return this.confirmationValue.length > 0
                && this.passwordValue === this.confirmationValue;
        }
    }"
>
    {{-- Full Name --}}
    <div class="grid gap-2">
        <flux:input
            wire:model="name"
            id="name"
            :label="__('Full name')"
            type="text"
            name="name"
            required
            autofocus
            autocomplete="name"
            placeholder="Enter your full name"
        />
    </div>

    {{-- Email Address --}}
    <div class="grid gap-2">
        <flux:input
            wire:model="email"
            id="email"
            :label="__('Email address')"
            type="email"
            name="email"
            required
            autocomplete="email"
            placeholder="email@example.com"
        />
    </div>

{{-- Contact Number and Address --}}
<div class="grid gap-6 sm:grid-cols-2">
    <div class="grid gap-2">
        <flux:input
            wire:model="contact_number"
            id="contact_number"
            :label="__('Contact number')"
            type="tel"
            name="contact_number"
            required
            autocomplete="tel"
            inputmode="tel"
            maxlength="13"
            placeholder="Example: 09123456789"
        />
    </div>

    <div class="grid gap-2">
        <flux:input
            wire:model="address"
            id="address"
            :label="__('Address')"
            type="text"
            name="address"
            required
            autocomplete="street-address"
            maxlength="500"
            placeholder="Enter complete address"
        />
    </div>
</div>

    {{-- Password --}}
    <div class="grid gap-2">
        <flux:input
            wire:model="password"
            x-model="passwordValue"
            id="password"
            :label="__('Password')"
            type="password"
            name="password"
            required
            autocomplete="new-password"
            placeholder="Create a secure password"
            viewable
        />

        <div
            class="tn-password-strength"
            :class="strengthClass(passwordValue)"
        >
            <div class="tn-password-strength-heading">
                <span>Password strength</span>

                <strong
                    x-text="strengthLabel(passwordValue)"
                    aria-live="polite"
                ></strong>
            </div>

            <div
                class="tn-password-strength-bar"
                role="progressbar"
                aria-label="Password strength"
                aria-valuemin="0"
                aria-valuemax="5"
                :aria-valuenow="strengthScore(passwordValue)"
            >
                <span
                    class="tn-password-strength-segment"
                    :class="{ 'is-active': strengthScore(passwordValue) >= 1 }"
                ></span>

                <span
                    class="tn-password-strength-segment"
                    :class="{ 'is-active': strengthScore(passwordValue) >= 2 }"
                ></span>

                <span
                    class="tn-password-strength-segment"
                    :class="{ 'is-active': strengthScore(passwordValue) >= 3 }"
                ></span>

                <span
                    class="tn-password-strength-segment"
                    :class="{ 'is-active': strengthScore(passwordValue) >= 4 }"
                ></span>

                <span
                    class="tn-password-strength-segment"
                    :class="{ 'is-active': strengthScore(passwordValue) >= 5 }"
                ></span>
            </div>

            <div class="tn-password-requirements">
                <span
                    :class="{ 'is-met': passwordValue.length >= 12 }"
                >
                    12+ characters
                </span>

                <span
                    :class="{ 'is-met': /[a-z]/.test(passwordValue) }"
                >
                    Lowercase
                </span>

                <span
                    :class="{ 'is-met': /[A-Z]/.test(passwordValue) }"
                >
                    Uppercase
                </span>

                <span
                    :class="{ 'is-met': /[0-9]/.test(passwordValue) }"
                >
                    Number
                </span>

                <span
                    :class="{ 'is-met': /[^A-Za-z0-9]/.test(passwordValue) }"
                >
                    Symbol
                </span>
            </div>
        </div>
    </div>

    {{-- Confirm Password --}}
    <div class="grid gap-2">
        <flux:input
            wire:model="password_confirmation"
            x-model="confirmationValue"
            id="password_confirmation"
            :label="__('Confirm password')"
            type="password"
            name="password_confirmation"
            required
            autocomplete="new-password"
            placeholder="Repeat your password"
            viewable
        />

        <div
            class="tn-password-strength"
            :class="strengthClass(confirmationValue)"
        >
            <div class="tn-password-strength-heading">
                <span>Confirmation strength</span>

                <strong
                    x-text="strengthLabel(confirmationValue)"
                    aria-live="polite"
                ></strong>
            </div>

            <div
                class="tn-password-strength-bar"
                role="progressbar"
                aria-label="Password confirmation strength"
                aria-valuemin="0"
                aria-valuemax="5"
                :aria-valuenow="strengthScore(confirmationValue)"
            >
                <span
                    class="tn-password-strength-segment"
                    :class="{ 'is-active': strengthScore(confirmationValue) >= 1 }"
                ></span>

                <span
                    class="tn-password-strength-segment"
                    :class="{ 'is-active': strengthScore(confirmationValue) >= 2 }"
                ></span>

                <span
                    class="tn-password-strength-segment"
                    :class="{ 'is-active': strengthScore(confirmationValue) >= 3 }"
                ></span>

                <span
                    class="tn-password-strength-segment"
                    :class="{ 'is-active': strengthScore(confirmationValue) >= 4 }"
                ></span>

                <span
                    class="tn-password-strength-segment"
                    :class="{ 'is-active': strengthScore(confirmationValue) >= 5 }"
                ></span>
            </div>

            <div
                class="tn-password-match"
                x-show="confirmationValue.length > 0"
                x-cloak
                :class="passwordsMatch() ? 'is-match' : 'is-mismatch'"
                aria-live="polite"
            >
                <span
                    x-text="passwordsMatch()
                        ? 'Passwords match'
                        : 'Passwords do not match'"
                ></span>
            </div>
        </div>
    </div>

    {{-- Account Approval Notice --}}
    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
        New resident accounts require administrator approval before login.
    </div>

    {{-- Submit --}}
    <div class="flex items-center justify-end">
        <flux:button
            type="submit"
            variant="primary"
            class="w-full"
        >
            {{ __('Create resident account') }}
        </flux:button>
    </div>
</form>

    <div class="space-x-1 text-center text-sm text-zinc-600 dark:text-zinc-400">
        <span>Already have an approved account?</span>

        <x-text-link href="{{ route('login') }}">
            {{ __('Log in') }}
        </x-text-link>
    </div>
</div>