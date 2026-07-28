<?php

namespace App\Providers;

use App\Models\Incident;
use App\Models\ResidentComplaint;
use App\Models\User;
use App\Models\UserNotification;
use App\Policies\IncidentPolicy;
use App\Policies\ResidentComplaintPolicy;
use App\Policies\UserNotificationPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap application services.
     */
    public function boot(): void
    {
        Gate::policy(Incident::class, IncidentPolicy::class);
        Gate::policy(ResidentComplaint::class, ResidentComplaintPolicy::class);
        Gate::policy(UserNotification::class, UserNotificationPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        Password::defaults(function (): Password {
            return Password::min(12)
                ->mixedCase()
                ->numbers()
                ->symbols();
        });
    }
}
