<?php

use App\Http\Controllers\MobileEmergencyAlertController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'active.user'])->group(function (): void {
    Route::get('/admin/mobile-sos', [
        MobileEmergencyAlertController::class,
        'index',
    ])->middleware('role:admin')
        ->name('admin.mobile-sos.index');

    Route::get('/official/mobile-sos', [
        MobileEmergencyAlertController::class,
        'index',
    ])->middleware('role:official,dao')
        ->name('official.mobile-sos.index');

    Route::get('/emergency-alerts/{emergencyAlert}', [
        MobileEmergencyAlertController::class,
        'show',
    ])->name('emergency-alerts.show');

    Route::patch('/emergency-alerts/{emergencyAlert}/acknowledge', [
        MobileEmergencyAlertController::class,
        'acknowledge',
    ])->name('emergency-alerts.acknowledge');

    Route::patch('/emergency-alerts/{emergencyAlert}/resolve', [
        MobileEmergencyAlertController::class,
        'resolve',
    ])->name('emergency-alerts.resolve');

    /*
     * Browser forms use explicit POST action routes for deletion so the
     * responder UI does not depend on HTTP method spoofing. The existing
     * DELETE routes remain available for backwards compatibility.
     */
    Route::post('/emergency-alerts/delete-all', [
        MobileEmergencyAlertController::class,
        'destroyAll',
    ])->name('emergency-alerts.destroy-all.post');

    Route::post('/emergency-alerts/{emergencyAlert}/delete', [
        MobileEmergencyAlertController::class,
        'destroy',
    ])->name('emergency-alerts.destroy.post');

    Route::delete('/emergency-alerts', [
        MobileEmergencyAlertController::class,
        'destroyAll',
    ])->name('emergency-alerts.destroy-all');

    Route::delete('/emergency-alerts/{emergencyAlert}', [
        MobileEmergencyAlertController::class,
        'destroy',
    ])->name('emergency-alerts.destroy');
});
