<?php

use App\Http\Controllers\Admin\ActivityLogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Activity Log Browser Delete Action
|--------------------------------------------------------------------------
|
| The normal Activity Logs module remains registered in routes/web.php.
| This explicit POST-backed browser action mirrors the working mobile/API
| delete-all behavior without relying on an HTML method override in the
| destructive website form. The established DELETE route remains available
| for compatibility and tests that exercise the canonical REST action.
|
*/

Route::middleware([
    'web',
    'auth',
    'active.user',
    'role:admin',
])->group(function (): void {
    Route::post('/admin/activity-logs/delete-all', [
        ActivityLogController::class,
        'destroyAll',
    ])->name('admin.activity-logs.destroy-all-action');
});
