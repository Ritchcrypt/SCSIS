<?php

namespace App\Providers;

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
        /*
        |--------------------------------------------------------------------------
        | TabangNow password policy
        |--------------------------------------------------------------------------
        |
        | This central rule is used by registration, password reset,
        | profile password changes, and administrator-created accounts.
        |
        */

        Password::defaults(function (): Password {
            return Password::min(12)
                ->mixedCase()
                ->numbers()
                ->symbols();
        });
    }
}