<?php

use App\Http\Controllers\Api\V1\SystemBrandingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    /*
     * Public read-only branding metadata used by the Android authentication
     * screens before a user has a Sanctum token. The same name/subtitle are
     * already visible on the public website; all branding writes remain behind
     * the authenticated Admin Gate in the canonical API route.
     */
    Route::get('/public/system-branding', [
        SystemBrandingController::class,
        'show',
    ])->name('api.v1.public.system-branding.show');
});
