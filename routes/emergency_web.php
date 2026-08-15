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
});
