<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

class RecordAuthenticationActivity
{
    public function __construct(
        private readonly ActivityLogger $activityLogger
    ) {
    }

    public function handle(object $event): void
    {
        match (true) {
            $event instanceof Login => $this->recordLogin($event),
            $event instanceof Logout => $this->recordLogout($event),
            $event instanceof Failed => $this->recordFailedLogin($event),
            $event instanceof Lockout => $this->recordLockout($event),
            $event instanceof Registered => $this->recordRegistration($event),
            $event instanceof PasswordReset => $this->recordPasswordReset($event),
            $event instanceof Verified => $this->recordVerification($event),
            default => null,
        };
    }

    private function recordLogin(Login $event): void
    {
        $user = $event->user instanceof User
            ? $event->user
            : null;

        $this->activityLogger->record(
            event: 'auth.login.succeeded',
            category: 'authentication',
            description: 'User logged in successfully.',
            actor: $user,
            target: $user,
            metadata: [
                'guard' => $event->guard,
                'remember' => (bool) $event->remember,
            ],
        );
    }

    private function recordLogout(Logout $event): void
    {
        $user = $event->user instanceof User
            ? $event->user
            : null;

        $request = $this->currentRequest();

        $reason = $this->logoutReason($request);

        $this->activityLogger->record(
            event: 'auth.logout',
            category: 'authentication',
            description: $this->logoutDescription($reason),
            actor: $user,
            target: $user,
            metadata: [
                'guard' => $event->guard,
                'reason' => $reason,
            ],
            request: $request,
        );
    }

    private function recordFailedLogin(Failed $event): void
    {
        $target = $event->user instanceof User
            ? $event->user
            : null;

        $identifier = $event->credentials['email']
            ?? $event->credentials['username']
            ?? null;

        $this->activityLogger->record(
            event: 'auth.login.failed',
            category: 'authentication',
            description: 'Authentication attempt failed.',
            target: $target,
            metadata: [
                'guard' => $event->guard,
                'identifier_hash' => $this->activityLogger
                    ->hashIdentifier($identifier),
            ],
        );
    }

    private function recordLockout(Lockout $event): void
    {
        $identifier = $event->request->input('email')
            ?? $event->request->input('username');

        $this->activityLogger->record(
            event: 'auth.login.locked_out',
            category: 'authentication',
            description: 'Login attempts were temporarily rate limited.',
            metadata: [
                'identifier_hash' => $this->activityLogger
                    ->hashIdentifier($identifier),
            ],
            request: $event->request,
        );
    }

    private function recordRegistration(Registered $event): void
    {
        $target = $event->user instanceof User
            ? $event->user
            : null;

        $this->activityLogger->record(
            event: 'account.registered',
            category: 'account',
            description: 'A resident account was registered.',
            target: $target,
            metadata: [
                'role' => $target?->role,
                'is_active' => $target?->isActive(),
            ],
        );
    }

    private function recordPasswordReset(PasswordReset $event): void
    {
        $target = $event->user instanceof User
            ? $event->user
            : null;

        $this->activityLogger->record(
            event: 'auth.password_reset.completed',
            category: 'authentication',
            description: 'Account password reset was completed.',
            target: $target,
        );
    }

    private function recordVerification(Verified $event): void
    {
        $user = $event->user instanceof User
            ? $event->user
            : null;

        $this->activityLogger->record(
            event: 'account.email_verified',
            category: 'account',
            description: 'Account email address was verified.',
            actor: $user,
            target: $user,
        );
    }

    private function logoutReason(?Request $request): string
    {
        if (! $request) {
            return 'system_or_security_action';
        }

        if ($request->hasSession()) {
            $reason = $request->session()->pull(
                'security.logout_reason'
            );

            if (is_string($reason) && $reason !== '') {
                return $reason;
            }
        }

        if ($request->routeIs('logout')) {
            return 'user_initiated';
        }

        if ($request->routeIs('profile.self-delete')) {
            return 'account_self_delete';
        }

        return 'system_or_security_action';
    }

    private function logoutDescription(string $reason): string
    {
        return match ($reason) {
            'user_initiated' => 'User logged out.',
            'inactivity_timeout' => 'User was logged out after session inactivity.',
            'account_inactive' => 'User was logged out because the account is inactive.',
            'account_self_delete' => 'User logged out before deleting their account.',
            default => 'User session was terminated by a security action.',
        };
    }

    private function currentRequest(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = app('request');

        return $request instanceof Request
            ? $request
            : null;
    }
}
