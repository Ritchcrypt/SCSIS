<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\TanodAlertController;
use App\Http\Controllers\CaseManagementController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\EmergencyModeController;
use App\Http\Controllers\TanodRosterController;
use App\Http\Controllers\BarangayMapController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TanodTaskController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\RoleDashboardController;
use App\Http\Controllers\SystemBrandingController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return view('welcome');
})->name('home');

/*
|--------------------------------------------------------------------------
| Main Dashboard Redirect
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', function () {
    $user = Auth::user();

    if (! $user) {
        return redirect()->route('login');
    }

    return match ($user->role) {
        'admin' => redirect()->route('admin.dashboard'),
        'official', 'dao' => redirect()->route('official.dashboard'),
        'tanod' => redirect()->route('tanod.dashboard'),
        'resident' => redirect()->route('resident.dashboard'),
        default => abort(403, 'Invalid user role.'),
    };
})->middleware(['auth', 'active.user'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| Shared Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->get('/users/{user}/profile-photo', [UserManagementController::class, 'profilePhoto'])
    ->name('users.profile-photo');

Route::middleware(['auth', 'active.user'])
    ->get('/incident-evidence/{evidenceId}/file', [IncidentController::class, 'showEvidenceFile'])
    ->name('incident-evidence.file');

Route::middleware(['auth', 'active.user'])
    ->get('/system-branding/logo', [SystemBrandingController::class, 'logo'])
    ->name('system-branding.logo');

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active.user', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');

        Route::post('/barangays/quick-store', [IncidentController::class, 'quickStoreBarangay'])
            ->name('barangays.quick-store');

        Route::delete('/barangays/{barangayId}/quick-delete', [IncidentController::class, 'quickDeleteBarangay'])
            ->name('barangays.quick-delete');

        Route::get('/system-branding', [SystemBrandingController::class, 'edit'])
            ->name('system-branding.edit');

        Route::put('/system-branding', [SystemBrandingController::class, 'update'])
            ->name('system-branding.update');

        Route::get('/users', [UserManagementController::class, 'index'])
            ->name('users.index');

        Route::get('/users/export', [UserManagementController::class, 'export'])
            ->name('users.export');

        Route::get('/users/create', [UserManagementController::class, 'create'])
            ->name('users.create');

        Route::post('/users', [UserManagementController::class, 'store'])
            ->name('users.store');

        Route::get('/users/{user}', [UserManagementController::class, 'show'])
            ->name('users.show');

        Route::delete('/incidents/{incident}', [IncidentController::class, 'destroy'])
            ->name('incidents.destroy');

        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])
            ->name('users.edit');

        Route::patch('/users/{user}', [UserManagementController::class, 'update'])
            ->name('users.update');

        Route::patch('/users/{user}/activate', [UserManagementController::class, 'activate'])
            ->name('users.activate');

        Route::patch('/users/{user}/deactivate', [UserManagementController::class, 'deactivate'])
            ->name('users.deactivate');

        Route::patch('/users/{user}/reset-password', [UserManagementController::class, 'resetPassword'])
            ->name('users.reset-password');

        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])
            ->name('users.destroy');

        Route::delete('/tanod-tasks/{tanodTask}', [TanodTaskController::class, 'destroy'])
            ->name('tanod-tasks.destroy');

        Route::get('/tanod-tasks', [TanodTaskController::class, 'index'])
            ->name('tanod-tasks.index');

        Route::get('/tanod-tasks/create', [TanodTaskController::class, 'create'])
            ->name('tanod-tasks.create');

        Route::post('/tanod-tasks', [TanodTaskController::class, 'store'])
            ->name('tanod-tasks.store');

        Route::get('/tanod-tasks/{tanodTask}', [TanodTaskController::class, 'show'])
            ->name('tanod-tasks.show');

        Route::patch('/tanod-tasks/{tanodTask}/close', [TanodTaskController::class, 'close'])
            ->name('tanod-tasks.close');

        Route::patch('/tanod-tasks/{tanodTask}/cancel', [TanodTaskController::class, 'cancel'])
            ->name('tanod-tasks.cancel');

        Route::get('/reports', [ReportController::class, 'index'])
            ->name('reports.index');

        Route::get('/reports/pdf', [ReportController::class, 'downloadPdf'])
            ->name('reports.pdf');

        Route::get('/map', [BarangayMapController::class, 'index'])
            ->name('map.index');

        Route::patch('/map/incidents/{incident}/location', [BarangayMapController::class, 'updateLocation'])
            ->name('map.incidents.location');

        Route::get('/tanods', [TanodRosterController::class, 'index'])
            ->name('tanods.index');

        Route::post('/tanods', [TanodRosterController::class, 'store'])
            ->name('tanods.store');

        Route::patch('/tanods/{tanod}', [TanodRosterController::class, 'update'])
            ->name('tanods.update');

        Route::delete('/tanods/{tanod}', [TanodRosterController::class, 'destroy'])
            ->name('tanods.destroy');

        Route::get('/emergency-mode', [EmergencyModeController::class, 'index'])
            ->name('emergency-mode.index');

        Route::post('/emergency-mode/notify', [EmergencyModeController::class, 'notify'])
            ->name('emergency-mode.notify');

        Route::patch('/emergency-mode/{emergencyAgencyLog}/status', [EmergencyModeController::class, 'updateStatus'])
            ->name('emergency-mode.update-status');

        Route::delete('/emergency-mode/{emergencyAgencyLog}', [EmergencyModeController::class, 'destroy'])
            ->name('emergency-mode.destroy');

        Route::get('/announcements', [AnnouncementController::class, 'index'])
            ->name('announcements.index');

        Route::post('/announcements', [AnnouncementController::class, 'store'])
            ->name('announcements.store');

        Route::patch('/announcements/{announcement}/toggle', [AnnouncementController::class, 'toggle'])
            ->name('announcements.toggle');

        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])
            ->name('announcements.destroy');

        Route::get('/tanod-alerts', [TanodAlertController::class, 'index'])
            ->name('tanod-alerts.index');

        Route::patch('/tanod-alerts/read-all', [TanodAlertController::class, 'markAllRead'])
            ->name('tanod-alerts.read-all');

        Route::delete('/tanod-alerts/delete-all', [TanodAlertController::class, 'destroyAll'])
            ->name('tanod-alerts.destroy-all');

        Route::patch('/tanod-alerts/{notification}/acknowledge', [TanodAlertController::class, 'acknowledge'])
            ->name('tanod-alerts.acknowledge');

        Route::delete('/tanod-alerts/{notification}', [TanodAlertController::class, 'destroy'])
            ->name('tanod-alerts.destroy');

        Route::get('/incidents', [IncidentController::class, 'index'])
            ->name('incidents.index');

        Route::get('/incidents/create', [IncidentController::class, 'create'])
            ->name('incidents.create');

        Route::post('/incidents', [IncidentController::class, 'store'])
            ->name('incidents.store');

        Route::get('/incidents/{incident}', [IncidentController::class, 'show'])
            ->name('incidents.show');

        Route::patch('/incidents/{incident}/status', [IncidentController::class, 'updateStatus'])
            ->name('incidents.update-status');

        Route::match(['post', 'patch'], '/incidents/{incident}/escalate', [IncidentController::class, 'escalate'])
            ->name('incidents.escalate');

        Route::post('/incidents/{incident}/messages', [IncidentController::class, 'storeMessage'])
            ->name('incidents.messages.store');

        Route::get('/cases', [CaseManagementController::class, 'index'])
            ->name('cases.index');

        Route::post('/cases', [CaseManagementController::class, 'store'])
            ->name('cases.store');

        Route::patch('/cases/{caseRecord}', [CaseManagementController::class, 'update'])
            ->name('cases.update');

        Route::delete('/cases/{caseRecord}', [CaseManagementController::class, 'destroy'])
            ->name('cases.destroy');
    });

/*
|--------------------------------------------------------------------------
| Barangay Official Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active.user', 'role:official,dao'])
    ->prefix('official')
    ->name('official.')
    ->group(function () {
        Route::get('/dashboard', [RoleDashboardController::class, 'official'])
            ->name('dashboard');

        Route::get('/incidents', [IncidentController::class, 'index'])
            ->name('incidents.index');

        Route::get('/incidents/create', [IncidentController::class, 'create'])
            ->name('incidents.create');

        Route::post('/incidents', [IncidentController::class, 'store'])
            ->name('incidents.store');

        Route::get('/incidents/{incident}', [IncidentController::class, 'show'])
            ->name('incidents.show');

        Route::patch('/incidents/{incident}/status', [IncidentController::class, 'updateStatus'])
            ->name('incidents.update-status');

        Route::match(['post', 'patch'], '/incidents/{incident}/escalate', [IncidentController::class, 'escalate'])
            ->name('incidents.escalate');

        Route::post('/incidents/{incident}/messages', [IncidentController::class, 'storeMessage'])
            ->name('incidents.messages.store');
    });

/*
|--------------------------------------------------------------------------
| Barangay Tanod Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active.user', 'role:tanod'])
    ->prefix('tanod')
    ->name('tanod.')
    ->group(function () {
        Route::get('/dashboard', [RoleDashboardController::class, 'tanod'])
            ->name('dashboard');

        Route::get('/tanod-tasks', [TanodTaskController::class, 'tanodIndex'])
            ->name('tanod-tasks.index');

        Route::patch('/tanod-tasks/responses/{response}', [TanodTaskController::class, 'respond'])
            ->name('tanod-tasks.respond');

        Route::get('/alerts', [TanodAlertController::class, 'index'])
            ->name('tanod-alerts.index');

        Route::patch('/alerts/read-all', [TanodAlertController::class, 'markAllRead'])
            ->name('tanod-alerts.read-all');

        Route::delete('/alerts/delete-all', [TanodAlertController::class, 'destroyAll'])
            ->name('tanod-alerts.destroy-all');

        Route::patch('/alerts/{notification}/acknowledge', [TanodAlertController::class, 'acknowledge'])
            ->name('tanod-alerts.acknowledge');

        Route::delete('/alerts/{notification}', [TanodAlertController::class, 'destroy'])
            ->name('tanod-alerts.destroy');

        Route::get('/incidents', [IncidentController::class, 'index'])
            ->name('incidents.index');

        Route::get('/incidents/{incident}', [IncidentController::class, 'show'])
            ->name('incidents.show');

        Route::patch('/incidents/{incident}/status', [IncidentController::class, 'updateStatus'])
            ->name('incidents.update-status');

        Route::post('/incidents/{incident}/messages', [IncidentController::class, 'storeMessage'])
            ->name('incidents.messages.store');
    });

/*
|--------------------------------------------------------------------------
| Resident Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active.user', 'role:resident'])
    ->prefix('resident')
    ->name('resident.')
    ->group(function () {
        Route::get('/dashboard', [RoleDashboardController::class, 'resident'])
            ->name('dashboard');

        Route::get('/incidents', [IncidentController::class, 'index'])
            ->name('incidents.index');

        Route::get('/incidents/create', [IncidentController::class, 'create'])
            ->name('incidents.create');

        Route::post('/incidents', [IncidentController::class, 'store'])
            ->name('incidents.store');

        Route::get('/incidents/{incident}', [IncidentController::class, 'show'])
            ->name('incidents.show');

        Route::post('/incidents/{incident}/messages', [IncidentController::class, 'storeMessage'])
            ->name('incidents.messages.store');
    });

/*
|--------------------------------------------------------------------------
| Authentication / Logout
|--------------------------------------------------------------------------
*/
Route::post('/logout', function () {
    Auth::logout();

    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

if (file_exists(__DIR__ . '/auth.php')) {
    require __DIR__ . '/auth.php';
}

if (file_exists(__DIR__ . '/settings.php')) {
    require __DIR__ . '/settings.php';
}