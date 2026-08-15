<?php

namespace App\Providers;

use App\Listeners\RecordAuthenticationActivity;
use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\CaseRecord;
use App\Models\EmergencyHotline;
use App\Models\Incident;
use App\Models\ResidentComplaint;
use App\Models\TanodProfile;
use App\Models\User;
use App\Models\UserNotification;
use App\Policies\ActivityLogPolicy;
use App\Policies\AnnouncementPolicy;
use App\Policies\CaseRecordPolicy;
use App\Policies\EmergencyHotlinePolicy;
use App\Policies\IncidentPolicy;
use App\Policies\ResidentComplaintPolicy;
use App\Policies\TanodProfilePolicy;
use App\Policies\UserNotificationPolicy;
use App\Policies\UserPolicy;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        foreach ([
            Login::class,
            Logout::class,
            Failed::class,
            Lockout::class,
            Registered::class,
            PasswordReset::class,
            Verified::class,
        ] as $authenticationEvent) {
            Event::listen(
                $authenticationEvent,
                RecordAuthenticationActivity::class
            );
        }

        Gate::policy(ActivityLog::class, ActivityLogPolicy::class);
        Gate::policy(Announcement::class, AnnouncementPolicy::class);
        Gate::policy(CaseRecord::class, CaseRecordPolicy::class);
        Gate::policy(EmergencyHotline::class, EmergencyHotlinePolicy::class);
        Gate::policy(Incident::class, IncidentPolicy::class);
        Gate::policy(ResidentComplaint::class, ResidentComplaintPolicy::class);
        Gate::policy(TanodProfile::class, TanodProfilePolicy::class);
        Gate::policy(UserNotification::class, UserNotificationPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        Gate::define(
            'viewReports',
            fn (User $user): bool => $user->isActive() && $user->isAdmin()
        );

        Gate::define(
            'manageSystemBranding',
            fn (User $user): bool => $user->isActive() && $user->isAdmin()
        );

        Gate::define(
            'viewUserPresence',
            fn (User $user): bool => $user->isActive() && $user->isAdmin()
        );

        Gate::define(
            'viewBarangayMap',
            fn (User $user): bool => $user->isActive()
                && ($user->isAdmin() || $user->isOfficial())
        );

        Gate::define(
            'manageBarangays',
            fn (User $user): bool => $user->isActive()
                && ($user->isAdmin() || $user->isOfficial())
        );

        Password::defaults(function (): Password {
            return Password::min(12)
                ->mixedCase()
                ->numbers()
                ->symbols();
        });
    }

    public function boot(): void
    {
        // Routes are registered centrally in bootstrap/app.php.
    }
}
