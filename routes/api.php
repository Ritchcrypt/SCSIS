<?php

use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\EmergencyHotlineController;
use App\Http\Middleware\EnsureApiUserIsActive;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication API
|--------------------------------------------------------------------------
*/

Route::prefix('v1/auth')->group(function (): void {
    Route::post('/login', [
        AuthController::class,
        'login',
    ])->name('api.v1.auth.login');

    Route::middleware([
        'auth:sanctum',
        EnsureApiUserIsActive::class,
    ])->group(function (): void {
        Route::get('/me', [
            AuthController::class,
            'me',
        ])->name('api.v1.auth.me');

        Route::post('/logout', [
            AuthController::class,
            'logout',
        ])->name('api.v1.auth.logout');
    });
});

/*
|--------------------------------------------------------------------------
| Authenticated Mobile API
|--------------------------------------------------------------------------
*/

Route::prefix('v1')
    ->middleware([
        'auth:sanctum',
        EnsureApiUserIsActive::class,
    ])
    ->group(function (): void {
        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        Route::get('/dashboard', [
            DashboardController::class,
            'index',
        ])->name('api.v1.dashboard');

        /*
        |--------------------------------------------------------------------------
        | Announcements
        |--------------------------------------------------------------------------
        */

        Route::get('/announcements', [
            AnnouncementController::class,
            'index',
        ])->name('api.v1.announcements.index');

        /*
        |--------------------------------------------------------------------------
        | Emergency Hotlines
        |--------------------------------------------------------------------------
        */

        Route::get('/emergency-hotlines', [
            EmergencyHotlineController::class,
            'index',
        ])->name('api.v1.emergency-hotlines.index');
    });