<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Guest Authentication Routes
|--------------------------------------------------------------------------
|
| These routes are only available while the user is logged out.
|
*/

Route::middleware('guest')->group(function () {
    Volt::route('login', 'auth.login')
        ->name('login');

    Volt::route('register', 'auth.register')
        ->name('register');

    Volt::route('forgot-password', 'auth.forgot-password')
        ->name('password.request');

    Volt::route('reset-password/{token}', 'auth.reset-password')
        ->name('password.reset');
});

/*
|--------------------------------------------------------------------------
| Authenticated Account Security Routes
|--------------------------------------------------------------------------
|
| Users must be authenticated and active before accessing account
| verification and password-confirmation pages.
|
*/

Route::middleware(['auth', 'active.user'])->group(function () {
    Volt::route('verify-email', 'auth.verify-email')
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Volt::route('confirm-password', 'auth.confirm-password')
        ->name('password.confirm');
});

/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
|
| Logout requires an authenticated session. The Logout action invalidates
| the session and regenerates the CSRF token.
|
*/

Route::post('logout', Logout::class)
    ->middleware('auth')
    ->name('logout');