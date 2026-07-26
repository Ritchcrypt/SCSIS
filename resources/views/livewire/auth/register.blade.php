<?php

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;


new #[Layout('components.layouts.auth')] class extends Component {
    public string $name = '';

    public string $email = '';

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

            'password' => [
                'required',
                'string',
                'confirmed',
                Rules\Password::defaults(),
            ],
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

    <form wire:submit="register" class="flex flex-col gap-6">
        <div class="grid gap-2">
            <flux:input
                wire:model="name"
                id="name"
                label="{{ __('Full name') }}"
                type="text"
                name="name"
                required
                autofocus
                autocomplete="name"
                placeholder="Enter your full name"
            />
        </div>

        <div class="grid gap-2">
            <flux:input
                wire:model="email"
                id="email"
                label="{{ __('Email address') }}"
                type="email"
                name="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />
        </div>

        <div class="grid gap-2">
            <flux:input
                wire:model="password"
                id="password"
                label="{{ __('Password') }}"
                type="password"
                name="password"
                required
                autocomplete="new-password"
                placeholder="Create a secure password"
            />
        </div>

        <div class="grid gap-2">
            <flux:input
                wire:model="password_confirmation"
                id="password_confirmation"
                label="{{ __('Confirm password') }}"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
                placeholder="Repeat your password"
            />
        </div>

        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
            New resident accounts require administrator approval before login.
        </div>

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
        Already have an approved account?

        <x-text-link href="{{ route('login') }}">
            Log in
        </x-text-link>
    </div>
</div>