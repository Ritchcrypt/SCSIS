<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EmergencyAlertManagementController;
use App\Http\Controllers\Api\V1\EmergencyDeviceController;
use App\Http\Controllers\Api\V1\EmergencySosController;
use App\Http\Middleware\EnsureApiUserIsActive;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:10,1')
        ->name('api.v1.auth.register');

    /*
    | Public emergency endpoint.
    | Deliberately does not require account authentication.
    | Device enrollment is optional and is used only for trusted identity linkage.
    */
    Route::post('/emergency/sos', [EmergencySosController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('api.v1.emergency.sos');

    Route::middleware([
        'auth:sanctum',
        EnsureApiUserIsActive::class,
    ])->group(function (): void {
        Route::post('/emergency/device/enroll', [
            EmergencyDeviceController::class,
            'enroll',
        ])->name('api.v1.emergency.device.enroll');

        Route::get('/emergency-alerts', [
            EmergencyAlertManagementController::class,
            'index',
        ])->name('api.v1.emergency-alerts.index');

        Route::get('/emergency-alerts/{emergencyAlert}', [
            EmergencyAlertManagementController::class,
            'show',
        ])->name('api.v1.emergency-alerts.show');

        Route::patch('/emergency-alerts/{emergencyAlert}/acknowledge', [
            EmergencyAlertManagementController::class,
            'acknowledge',
        ])->name('api.v1.emergency-alerts.acknowledge');

        Route::patch('/emergency-alerts/{emergencyAlert}/resolve', [
            EmergencyAlertManagementController::class,
            'resolve',
        ])->name('api.v1.emergency-alerts.resolve');
    });
});
