<?php

use App\Http\Controllers\Api\V1\EmergencyAlertManagementController;
use App\Http\Controllers\Api\V1\EmergencyDeviceController;
use App\Http\Controllers\Api\V1\EmergencySosController;
use App\Http\Middleware\EnsureApiUserIsActive;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    /*
    | Public emergency endpoint.
    | Deliberately does not require account authentication.
    | Device enrollment is optional and is used only for trusted identity linkage.
    |
    | Resident registration is defined canonically in routes/api.php. Do not
    | duplicate that route here because both API route files are loaded by
    | bootstrap/app.php.
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

        Route::delete('/emergency-alerts', [
            EmergencyAlertManagementController::class,
            'destroyAll',
        ])->name('api.v1.emergency-alerts.destroy-all');

        Route::delete('/emergency-alerts/{emergencyAlert}', [
            EmergencyAlertManagementController::class,
            'destroy',
        ])->name('api.v1.emergency-alerts.destroy');
    });
});
