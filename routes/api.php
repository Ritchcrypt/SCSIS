<?php

use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\EmergencyHotlineController;
use App\Http\Controllers\Api\V1\IncidentController;
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
        | Incidents
        |--------------------------------------------------------------------------
        */

        Route::get('/incidents/options', [
            IncidentController::class,
            'createOptions',
        ])->name('api.v1.incidents.options');

        Route::get('/incidents', [
            IncidentController::class,
            'index',
        ])->name('api.v1.incidents.index');

        Route::post('/incidents', [
            IncidentController::class,
            'store',
        ])->name('api.v1.incidents.store');

        Route::post('/incidents/barangays', [
            IncidentController::class,
            'quickStoreBarangay',
        ])->name('api.v1.incidents.barangays.store');

        Route::delete('/incidents/barangays/{barangayId}', [
            IncidentController::class,
            'quickDeleteBarangay',
        ])->whereNumber('barangayId')
            ->name('api.v1.incidents.barangays.destroy');

        Route::get('/incidents/{incident}', [
            IncidentController::class,
            'show',
        ])->name('api.v1.incidents.show');

        Route::get('/incidents/{incident}/evidence/{evidence}', [
            IncidentController::class,
            'evidenceFile',
        ])->name('api.v1.incidents.evidence.file');

        Route::patch('/incidents/{incident}/status', [
            IncidentController::class,
            'updateStatus',
        ])->name('api.v1.incidents.status.update');

        Route::post('/incidents/{incident}/escalate', [
            IncidentController::class,
            'escalate',
        ])->name('api.v1.incidents.escalate');

        Route::post('/incidents/{incident}/messages', [
            IncidentController::class,
            'storeMessage',
        ])->name('api.v1.incidents.messages.store');

        Route::delete('/incidents/{incident}', [
            IncidentController::class,
            'destroy',
        ])->name('api.v1.incidents.destroy');

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
