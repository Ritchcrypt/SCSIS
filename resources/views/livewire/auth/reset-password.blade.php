<?php

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Mount the password-reset component.
     */
    public function mount(string $token): void
    {
        $this->token = $token;

        $this->email = Str::lower(
            trim(request()->string('email')->toString())
        );
    }

    /**
     * Reset the account password.
     */
    public function resetPassword(): void
    {
        $this->email = Str::lower(trim($this->email));

        $this->validate([
            'token' => [
                'required',
                'string',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                Rules\Password::defaults(),
            ],
        ]);

        $status = Password::reset(
            $this->only(
                'email',
                'password',
                'password_confirmation',
                'token'
            ),
            function (User $user): void {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ]);

                if (Schema::hasColumn('users', 'last_seen_at')) {
                    $user->last_seen_at = null;
                }

                $user->save();

                /*
                |--------------------------------------------------------------------------
                | Terminate existing authentication credentials
                |--------------------------------------------------------------------------
                |
                | A completed password reset invalidates all authenticated
                | sessions, remember-me cookies, and API tokens.
                |
                */

                if (
                    Schema::hasTable('sessions')
                    && Schema::hasColumn('sessions', 'user_id')
                ) {
                    DB::table('sessions')
                        ->where('user_id', $user->id)
                        ->delete();
                }

                if (
                    Schema::hasTable('personal_access_tokens')
                    && Schema::hasColumn(
                        'personal_access_tokens',
                        'tokenable_id'
                    )
                    && Schema::hasColumn(
                        'personal_access_tokens',
                        'tokenable_type'
                    )
                ) {
                    DB::table('personal_access_tokens')
                        ->where('tokenable_id', $user->id)
                        ->where('tokenable_type', User::class)
                        ->delete();
                }

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PasswordReset) {
            $this->password = '';
            $this->password_confirmation = '';

            $this->addError('email', __($status));

            return;
        }

        $this->password = '';
        $this->password_confirmation = '';

        Session::flash('status', __($status));

        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header
        title="Reset password"
        description="Please enter your new password below"
    />

    <x-auth-session-status
        class="text-center"
        :status="session('status')"
    />

    <form wire:submit="resetPassword" class="flex flex-col gap-6">
        <div class="grid gap-2">
            <flux:input
                wire:model="email"
                id="email"
                label="{{ __('Email') }}"
                type="email"
                name="email"
                required
                autocomplete="email"
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
                placeholder="Password"
                viewable
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
                placeholder="Confirm password"
                viewable
            />
        </div>

        <div class="flex items-center justify-end">
            <flux:button
                type="submit"
                variant="primary"
                class="w-full"
            >
                {{ __('Reset password') }}
            </flux:button>
        </div>
    </form>
</div>