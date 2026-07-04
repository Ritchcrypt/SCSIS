<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\IncidentController;
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
| This route checks the logged-in user's role and sends them to the correct
| dashboard area.
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
| Administrator manages users, barangays, categories, announcements,
| reports, settings, and system maintenance.
*/
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])
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

        Route::post('/incidents/{incident}/escalate', [IncidentController::class, 'escalate'])
            ->name('incidents.escalate');

        Route::post('/incidents/{incident}/messages', [IncidentController::class, 'storeMessage'])
            ->name('incidents.messages.store');
    });

/*
|--------------------------------------------------------------------------
| Barangay Official Routes
|--------------------------------------------------------------------------
| Barangay Officials validate incidents, assign responders, manage reports,
| and monitor barangay-level safety activity.
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
| Tanods view assigned incidents, receive alerts, update response status,
| and add response remarks.
*/
Route::middleware(['auth', 'role:tanod'])
    ->prefix('tanod')
    ->name('tanod.')
    ->group(function () {
        Route::get('/dashboard', function () {
            return view('dashboard');
        })->name('dashboard');

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
| Residents submit incident reports, track their own reports, receive
| notifications, and view safety announcements.
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
| This creates the missing route named "logout".
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