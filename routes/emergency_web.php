<?php

use App\Http\Controllers\MobileEmergencyAlertController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'active.user'])->group(function (): void {
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
