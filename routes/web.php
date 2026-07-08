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
        'official' => redirect()->route('official.dashboard'),
        'tanod' => redirect()->route('tanod.dashboard'),
        'resident' => redirect()->route('resident.dashboard'),
        default => abort(403, 'Invalid user role.'),
    };
})->middleware(['auth'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/reports', [ReportController::class, 'index'])
        ->name('reports.index');

        Route::get('/reports/pdf', [ReportController::class, 'downloadPdf'])
        ->name('reports.pdf');

        Route::get('/map', [BarangayMapController::class, 'index'])
        ->name('map.index');

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

        Route::patch('/tanod-alerts/{notification}/acknowledge', [TanodAlertController::class, 'acknowledge'])
            ->name('tanod-alerts.acknowledge');

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
Route::middleware(['auth', 'role:official'])
    ->prefix('official')
    ->name('official.')
    ->group(function () {
        Route::get('/dashboard', function () {
            return view('dashboard');
        })->name('dashboard');

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

        Route::post('/incidents/{incident}/escalate', [IncidentController::class, 'escalate'])
            ->name('incidents.escalate');

        Route::post('/incidents/{incident}/messages', [IncidentController::class, 'storeMessage'])
            ->name('incidents.messages.store');
    });

/*
|--------------------------------------------------------------------------
| Barangay Tanod Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:tanod'])
    ->prefix('tanod')
    ->name('tanod.')
    ->group(function () {
        Route::get('/dashboard', function () {
            return view('dashboard');
        })->name('dashboard');

        Route::get('/alerts', [TanodAlertController::class, 'index'])
            ->name('tanod-alerts.index');

        Route::patch('/alerts/{notification}/acknowledge', [TanodAlertController::class, 'acknowledge'])
            ->name('tanod-alerts.acknowledge');

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
Route::middleware(['auth', 'role:resident'])
    ->prefix('resident')
    ->name('resident.')
    ->group(function () {
        Route::get('/dashboard', function () {
            return view('dashboard');
        })->name('dashboard');

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
Route::match(['GET', 'POST'], '/logout', function () {
    Auth::logout();

    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('home');
})->middleware('auth')->name('logout');

if (file_exists(__DIR__ . '/auth.php')) {
    require __DIR__ . '/auth.php';
}

if (file_exists(__DIR__ . '/settings.php')) {
    require __DIR__ . '/settings.php';
}