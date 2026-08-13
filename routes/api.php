<?php

use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DashboardParityController;
use App\Http\Controllers\Api\V1\EmergencyHotlineController;
use App\Http\Controllers\Api\V1\IncidentController;
use App\Http\Controllers\Api\V1\TanodRosterController;
use App\Http\Controllers\Api\V1\ThemePreferenceController;
use App\Http\Controllers\Api\V1\NotificationCenterController;
use App\Http\Controllers\Api\V1\TanodAlertController;
use App\Http\Controllers\Api\V1\SystemBrandingController;
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
            DashboardParityController::class,
            'index',
        ])->name('api.v1.dashboard');
        /*
        |--------------------------------------------------------------------------
        | System Branding
        |--------------------------------------------------------------------------
        */

        Route::get('/system-branding', [
            SystemBrandingController::class,
            'show',
        ])->name('api.v1.system-branding.show');

        Route::get('/system-branding/logo', [
            SystemBrandingController::class,
            'logo',
        ])->name('api.v1.system-branding.logo');

        Route::post('/system-branding', [
            SystemBrandingController::class,
            'update',
        ])->name('api.v1.system-branding.update');

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
        | Tanod Alerts
        |--------------------------------------------------------------------------
        */

        Route::get('/tanod-alerts', [
            TanodAlertController::class,
            'index',
        ])->name('api.v1.tanod-alerts.index');

        Route::patch('/tanod-alerts/mark-all-read', [
            TanodAlertController::class,
            'markAllRead',
        ])->name('api.v1.tanod-alerts.mark-all-read');

        Route::delete('/tanod-alerts/clear', [
            TanodAlertController::class,
            'clear',
        ])->name('api.v1.tanod-alerts.clear');

        Route::patch('/tanod-alerts/{alert}/read', [
            TanodAlertController::class,
            'markRead',
        ])->whereNumber('alert')
            ->name('api.v1.tanod-alerts.read');

        Route::patch('/tanod-alerts/{alert}/acknowledge', [
            TanodAlertController::class,
            'acknowledge',
        ])->whereNumber('alert')
            ->name('api.v1.tanod-alerts.acknowledge');

        Route::delete('/tanod-alerts/{alert}', [
            TanodAlertController::class,
            'destroy',
        ])->whereNumber('alert')
            ->name('api.v1.tanod-alerts.destroy');

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

        /*
        |--------------------------------------------------------------------------
        | Global Theme Preference
        |--------------------------------------------------------------------------
        */

        Route::get('/theme-preference', [
            ThemePreferenceController::class,
            'show',
        ])->name('api.v1.theme-preference.show');

        Route::patch('/theme-preference', [
            ThemePreferenceController::class,
            'update',
        ])->name('api.v1.theme-preference.update');

        /*
        |--------------------------------------------------------------------------
        | Global Notification Center
        |--------------------------------------------------------------------------
        */

        Route::get('/notifications', [
            NotificationCenterController::class,
            'index',
        ])->name('api.v1.notifications.index');

        Route::get('/notifications/pulse', [
            NotificationCenterController::class,
            'pulse',
        ])->name('api.v1.notifications.pulse');

        Route::post('/notifications/{notification}/open', [
            NotificationCenterController::class,
            'open',
        ])->whereNumber('notification')
            ->name('api.v1.notifications.open');

        /*
        |--------------------------------------------------------------------------
        | Tanod Roster
        |--------------------------------------------------------------------------
        | Website policy parity:
        | Admin + Official/DAO only. Controller Gates remain authoritative.
        */

        Route::get('/tanod-roster', [
            TanodRosterController::class,
            'index',
        ])->name('api.v1.tanod-roster.index');

        Route::post('/tanod-roster', [
            TanodRosterController::class,
            'store',
        ])->name('api.v1.tanod-roster.store');

        Route::patch('/tanod-roster/{tanod}', [
            TanodRosterController::class,
            'update',
        ])->whereNumber('tanod')
            ->name('api.v1.tanod-roster.update');

        Route::delete('/tanod-roster/{tanod}', [
            TanodRosterController::class,
            'destroy',
        ])->whereNumber('tanod')
            ->name('api.v1.tanod-roster.destroy');
    });


