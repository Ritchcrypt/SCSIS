<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\AdminMfaController;
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

    // All authenticated active roles may opt in. Keep the original admin-mfa
    // routes below for already-open forms and bookmarks.
    Route::get('security/account-mfa', [AdminMfaController::class, 'show'])
        ->name('security.account-mfa.show');
    Route::post('security/account-mfa/start', [AdminMfaController::class, 'begin'])
        ->middleware('throttle:5,1')->name('security.account-mfa.begin');
    Route::post('security/account-mfa/confirm', [AdminMfaController::class, 'confirm'])
        ->middleware('throttle:5,1')->name('security.account-mfa.confirm');
    Route::post('security/account-mfa/disable', [AdminMfaController::class, 'disable'])
        ->middleware('throttle:5,1')->name('security.account-mfa.disable');

    Route::post('security/account-mfa/recovery', [AdminMfaController::class, 'regenerate'])
        ->middleware('throttle:5,1')->name('security.account-mfa.recovery');

    // Legacy administrator URLs retained for compatibility.
    Route::get('security/admin-mfa', [AdminMfaController::class, 'show'])
        ->name('security.admin-mfa.show');
    Route::post('security/admin-mfa/start', [AdminMfaController::class, 'begin'])
        ->middleware('throttle:5,1')->name('security.admin-mfa.begin');
    Route::post('security/admin-mfa/confirm', [AdminMfaController::class, 'confirm'])
        ->middleware('throttle:5,1')->name('security.admin-mfa.confirm');
    Route::post('security/admin-mfa/disable', [AdminMfaController::class, 'disable'])
        ->middleware('throttle:5,1')->name('security.admin-mfa.disable');
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