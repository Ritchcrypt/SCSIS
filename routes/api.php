<?php

use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ActivityLogController;
use App\Http\Controllers\Api\V1\UserManagementController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\BarangayMapController;
use App\Http\Controllers\Api\V1\ResidentComplaintController;
use App\Http\Controllers\Api\V1\CaseManagementController;
use App\Http\Controllers\Api\V1\DevSessionController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DashboardParityController;
use App\Http\Controllers\Api\V1\EmergencyHotlineController;
use App\Http\Controllers\Api\V1\IncidentController;
use App\Http\Controllers\Api\V1\TanodRosterController;
use App\Http\Controllers\Api\V1\TanodTaskController;
use App\Http\Controllers\Api\V1\ThemePreferenceController;
use App\Http\Controllers\Api\V1\NotificationCenterController;
use App\Http\Controllers\Api\V1\PushTokenController;
use App\Http\Controllers\Api\V1\TanodAlertController;
use App\Http\Controllers\Api\V1\SystemBrandingController;
use App\Http\Controllers\Api\V1\MobileVersionController;
use App\Http\Middleware\EnsureApiUserIsActive;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Mobile Version Policy
|--------------------------------------------------------------------------
*/

Route::get('/v1/mobile/version', [
    MobileVersionController::class,
    'show',
])->name('api.v1.mobile.version');

/*
|--------------------------------------------------------------------------
| Authentication API
|--------------------------------------------------------------------------
*/

Route::prefix('v1/auth')->group(function (): void {
    Route::post('/register', [
        AuthController::class,
        'register',
    ])->name('api.v1.auth.register');

    Route::post('/login', [
        AuthController::class,
        'login',
    ])->name('api.v1.auth.login');
    Route::post('/forgot-password', [
        AuthController::class,
        'forgotPassword',
    ])->middleware('throttle:5,1')
        ->name('api.v1.auth.forgot-password');

    /*
    | Temporary local development bypass.
    | This route is NOT registered outside APP_ENV=local.
    */
    if (app()->environment('local')) {
        Route::post('/dev-session', [
            DevSessionController::class,
            'create',
        ])->name('api.v1.auth.dev-session');
    }

    Route::middleware([
        'auth:sanctum',
        EnsureApiUserIsActive::class,
    ])->group(function (): void {
        Route::get('/me', [
            AuthController::class,
            'me',
        ])->name('api.v1.auth.me');

        Route::post('/logout', [
            AuthController::class,
            'logout',
        ])->name('api.v1.auth.logout');
    });
});

/*
|--------------------------------------------------------------------------
| Authenticated Mobile API
|--------------------------------------------------------------------------
*/

Route::prefix('v1')
    ->middleware([
        'auth:sanctum',
        EnsureApiUserIsActive::class,
    ])
    ->group(function (): void {
        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        Route::get('/dashboard', [
            DashboardParityController::class,
            'index',
        ])->name('api.v1.dashboard');

        /*
        |--------------------------------------------------------------------------
        | Current Account Profile
        |--------------------------------------------------------------------------
        | Every active authenticated role can view/update only its own profile.
        */

        Route::get('/profile', [
            ProfileController::class,
            'show',
        ])->name('api.v1.profile.show');

        Route::post('/profile/update', [
            ProfileController::class,
            'update',
        ])->name('api.v1.profile.update');

        Route::get('/profile/photo', [
            ProfileController::class,
            'photo',
        ])->name('api.v1.profile.photo');

        Route::patch('/profile/password', [
            ProfileController::class,
            'updatePassword',
        ])->name('api.v1.profile.password.update');

        Route::delete('/profile/other-sessions', [
            ProfileController::class,
            'destroyOtherSessions',
        ])->name('api.v1.profile.other-sessions.destroy');

        Route::delete('/profile/self-delete', [
            ProfileController::class,
            'destroyOwnAccount',
        ])->name('api.v1.profile.self-delete');
        /*
        |--------------------------------------------------------------------------
        | System Branding
        |--------------------------------------------------------------------------
        */

        Route::get('/system-branding', [
            SystemBrandingController::class,
            'show',
        ])->name('api.v1.system-branding.show');

        Route::get('/system-branding/logo', [
            SystemBrandingController::class,
            'logo',
        ])->name('api.v1.system-branding.logo');

        Route::post('/system-branding', [
            SystemBrandingController::class,
            'update',
        ])->name('api.v1.system-branding.update');

        /*
        |--------------------------------------------------------------------------
        | Incidents
        |--------------------------------------------------------------------------
        */

        Route::get('/incidents/options', [
            IncidentController::class,
            'createOptions',
        ])->name('api.v1.incidents.options');

        Route::get('/incidents', [
            IncidentController::class,
            'index',
        ])->name('api.v1.incidents.index');

        Route::post('/incidents', [
            IncidentController::class,
            'store',
        ])->name('api.v1.incidents.store');

        Route::post('/incidents/barangays', [
            IncidentController::class,
            'quickStoreBarangay',
        ])->name('api.v1.incidents.barangays.store');

        Route::delete('/incidents/barangays/{barangayId}', [
            IncidentController::class,
            'quickDeleteBarangay',
        ])->whereNumber('barangayId')
            ->name('api.v1.incidents.barangays.destroy');

        Route::get('/incidents/{incident}', [
            IncidentController::class,
            'show',
        ])->name('api.v1.incidents.show');

        Route::get('/incidents/{incident}/evidence/{evidence}', [
            IncidentController::class,
            'evidenceFile',
        ])->name('api.v1.incidents.evidence.file');

        Route::patch('/incidents/{incident}/status', [
            IncidentController::class,
            'updateStatus',
        ])->name('api.v1.incidents.status.update');

        Route::post('/incidents/{incident}/escalate', [
            IncidentController::class,
            'escalate',
        ])->name('api.v1.incidents.escalate');

        Route::post('/incidents/{incident}/messages', [
            IncidentController::class,
            'storeMessage',
        ])->name('api.v1.incidents.messages.store');

        Route::delete('/incidents/{incident}', [
            IncidentController::class,
            'destroy',
        ])->name('api.v1.incidents.destroy');

        /*
        |--------------------------------------------------------------------------
        | Tanod Alerts
        |--------------------------------------------------------------------------
        */

        Route::get('/tanod-alerts', [
            TanodAlertController::class,
            'index',
        ])->name('api.v1.tanod-alerts.index');

        Route::patch('/tanod-alerts/mark-all-read', [
            TanodAlertController::class,
            'markAllRead',
        ])->name('api.v1.tanod-alerts.mark-all-read');

        Route::delete('/tanod-alerts/clear', [
            TanodAlertController::class,
            'clear',
        ])->name('api.v1.tanod-alerts.clear');

        Route::patch('/tanod-alerts/{alert}/read', [
            TanodAlertController::class,
            'markRead',
        ])->whereNumber('alert')
            ->name('api.v1.tanod-alerts.read');

        Route::patch('/tanod-alerts/{alert}/acknowledge', [
            TanodAlertController::class,
            'acknowledge',
        ])->whereNumber('alert')
            ->name('api.v1.tanod-alerts.acknowledge');

        Route::delete('/tanod-alerts/{alert}', [
            TanodAlertController::class,
            'destroy',
        ])->whereNumber('alert')
            ->name('api.v1.tanod-alerts.destroy');

        /*
        |--------------------------------------------------------------------------
        | Announcements
        |--------------------------------------------------------------------------
        */

        Route::get('/announcements', [
            AnnouncementController::class,
            'index',
        ])->name('api.v1.announcements.index');

        Route::post('/announcements', [
            AnnouncementController::class,
            'store',
        ])->name('api.v1.announcements.store');

        Route::patch('/announcements/{announcement}/toggle', [
            AnnouncementController::class,
            'toggle',
        ])->whereNumber('announcement')
            ->name('api.v1.announcements.toggle');

        Route::delete('/announcements/{announcement}', [
            AnnouncementController::class,
            'destroy',
        ])->whereNumber('announcement')
            ->name('api.v1.announcements.destroy');

        /*
        |--------------------------------------------------------------------------
        | Emergency Hotlines
        |--------------------------------------------------------------------------
        */

        Route::get('/emergency-hotlines', [
            EmergencyHotlineController::class,
            'index',
        ])->name('api.v1.emergency-hotlines.index');

        Route::post('/emergency-hotlines', [
            EmergencyHotlineController::class,
            'store',
        ])->name('api.v1.emergency-hotlines.store');

        Route::delete('/emergency-hotlines/{emergencyHotline}', [
            EmergencyHotlineController::class,
            'destroy',
        ])->whereNumber('emergencyHotline')
            ->name('api.v1.emergency-hotlines.destroy');

        /*
        |--------------------------------------------------------------------------
        | Global Theme Preference
        |--------------------------------------------------------------------------
        */

        Route::get('/theme-preference', [
            ThemePreferenceController::class,
            'show',
        ])->name('api.v1.theme-preference.show');

        Route::patch('/theme-preference', [
            ThemePreferenceController::class,
            'update',
        ])->name('api.v1.theme-preference.update');

        /*
        |--------------------------------------------------------------------------
        | Global Notification Center
        |--------------------------------------------------------------------------
        */

        Route::get('/notifications', [
            NotificationCenterController::class,
            'index',
        ])->name('api.v1.notifications.index');

        Route::get('/notifications/pulse', [
            NotificationCenterController::class,
            'pulse',
        ])->name('api.v1.notifications.pulse');

        Route::post('/notifications/{notification}/open', [
            NotificationCenterController::class,
            'open',
        ])->whereNumber('notification')
            ->name('api.v1.notifications.open');

        Route::post('/push-tokens', [
            PushTokenController::class,
            'store',
        ])->name('api.v1.push-tokens.store');

        Route::delete('/push-tokens/current', [
            PushTokenController::class,
            'destroyCurrent',
        ])->name('api.v1.push-tokens.current.destroy');

        /*
        |--------------------------------------------------------------------------
        | Tanod Tasks
        |--------------------------------------------------------------------------
        | Admin: create/list/detail/close/cancel/delete.
        | Tanod: own assigned responses + one final accept/decline response.
        */

        Route::get('/tanod-tasks', [
            TanodTaskController::class,
            'index',
        ])->name('api.v1.tanod-tasks.index');

        Route::post('/tanod-tasks', [
            TanodTaskController::class,
            'store',
        ])->name('api.v1.tanod-tasks.store');

        Route::patch('/tanod-tasks/responses/{response}', [
            TanodTaskController::class,
            'respond',
        ])->whereNumber('response')
            ->name('api.v1.tanod-tasks.respond');

        Route::get('/tanod-tasks/{tanodTask}', [
            TanodTaskController::class,
            'show',
        ])->whereNumber('tanodTask')
            ->name('api.v1.tanod-tasks.show');

        Route::patch('/tanod-tasks/{tanodTask}/close', [
            TanodTaskController::class,
            'close',
        ])->whereNumber('tanodTask')
            ->name('api.v1.tanod-tasks.close');

        Route::patch('/tanod-tasks/{tanodTask}/cancel', [
            TanodTaskController::class,
            'cancel',
        ])->whereNumber('tanodTask')
            ->name('api.v1.tanod-tasks.cancel');

        Route::delete('/tanod-tasks/{tanodTask}', [
            TanodTaskController::class,
            'destroy',
        ])->whereNumber('tanodTask')
            ->name('api.v1.tanod-tasks.destroy');

        /*
        |--------------------------------------------------------------------------
        | Tanod Roster
        |--------------------------------------------------------------------------
        | Website policy parity:
        | Admin + Official/DAO only. Controller Gates remain authoritative.
        */

        Route::get('/tanod-roster', [
            TanodRosterController::class,
            'index',
        ])->name('api.v1.tanod-roster.index');

        Route::post('/tanod-roster', [
            TanodRosterController::class,
            'store',
        ])->name('api.v1.tanod-roster.store');

        Route::patch('/tanod-roster/{tanod}', [
            TanodRosterController::class,
            'update',
        ])->whereNumber('tanod')
            ->name('api.v1.tanod-roster.update');

        Route::delete('/tanod-roster/{tanod}', [
            TanodRosterController::class,
            'destroy',
        ])->whereNumber('tanod')
            ->name('api.v1.tanod-roster.destroy');

        /*
        |--------------------------------------------------------------------------
        | Case Management
        |--------------------------------------------------------------------------
        | CaseRecordPolicy: Administrator only.
        */

        Route::get('/cases', [
            CaseManagementController::class,
            'index',
        ])->name('api.v1.cases.index');

        Route::post('/cases', [
            CaseManagementController::class,
            'store',
        ])->name('api.v1.cases.store');

        Route::patch('/cases/{caseRecord}', [
            CaseManagementController::class,
            'update',
        ])->whereNumber('caseRecord')
            ->name('api.v1.cases.update');

        Route::delete('/cases/{caseRecord}', [
            CaseManagementController::class,
            'destroy',
        ])->whereNumber('caseRecord')
            ->name('api.v1.cases.destroy');

        /*
        |--------------------------------------------------------------------------
        | Resident Complaints
        |--------------------------------------------------------------------------
        | Admin + Official/DAO process. Resident owns/submits only their records.
        | Laravel ResidentComplaintPolicy is the final security boundary.
        */

        Route::get('/resident-complaints', [
            ResidentComplaintController::class,
            'index',
        ])->name('api.v1.resident-complaints.index');

        Route::post('/resident-complaints', [
            ResidentComplaintController::class,
            'store',
        ])->name('api.v1.resident-complaints.store');

        Route::get('/resident-complaints/{residentComplaint}', [
            ResidentComplaintController::class,
            'show',
        ])->whereNumber('residentComplaint')
            ->name('api.v1.resident-complaints.show');

        Route::get('/resident-complaints/{residentComplaint}/evidence', [
            ResidentComplaintController::class,
            'evidence',
        ])->whereNumber('residentComplaint')
            ->name('api.v1.resident-complaints.evidence');

        Route::post('/resident-complaints/{residentComplaint}/proofs', [
            ResidentComplaintController::class,
            'storeProof',
        ])->whereNumber('residentComplaint')
            ->name('api.v1.resident-complaints.proofs.store');

        Route::patch('/resident-complaints/{residentComplaint}/status', [
            ResidentComplaintController::class,
            'updateStatus',
        ])->whereNumber('residentComplaint')
            ->name('api.v1.resident-complaints.status.update');

        Route::delete('/resident-complaints/{residentComplaint}', [
            ResidentComplaintController::class,
            'destroy',
        ])->whereNumber('residentComplaint')
            ->name('api.v1.resident-complaints.destroy');

        Route::get('/resident-complaint-proofs/{proof}/file', [
            ResidentComplaintController::class,
            'proofFile',
        ])->whereNumber('proof')
            ->name('api.v1.resident-complaints.proofs.file');

        /*
        |--------------------------------------------------------------------------
        | Barangay Map
        |--------------------------------------------------------------------------
        | Admin + Official/DAO only through Gate::viewBarangayMap.
        */

        Route::get('/barangay-map', [
            BarangayMapController::class,
            'index',
        ])->name('api.v1.barangay-map.index');

        /*
        |--------------------------------------------------------------------------
        | Reports
        |--------------------------------------------------------------------------
        | Admin only through Gate::viewReports. PDF output is generated by the
        | existing website ReportController and existing DomPDF templates.
        */

        Route::get('/reports', [
            ReportController::class,
            'index',
        ])->name('api.v1.reports.index');

        Route::get('/reports/pdf', [
            ReportController::class,
            'downloadPdf',
        ])->name('api.v1.reports.pdf');

        Route::get('/reports/incidents/{incident}/pdf', [
            ReportController::class,
            'downloadIncidentPdf',
        ])->whereNumber('incident')
            ->name('api.v1.reports.incident-pdf');

        Route::get('/reports/cases/{caseRecord}/pdf', [
            ReportController::class,
            'downloadCasePdf',
        ])->whereNumber('caseRecord')
            ->name('api.v1.reports.case-pdf');

        Route::get('/reports/complaints/{residentComplaint}/pdf', [
            ReportController::class,
            'downloadComplaintPdf',
        ])->whereNumber('residentComplaint')
            ->name('api.v1.reports.complaint-pdf');

        /*
        |--------------------------------------------------------------------------
        | User Management
        |--------------------------------------------------------------------------
        | Admin only. UserPolicy and transaction-level final-admin checks are
        | authoritative. Activation state changes use dedicated endpoints.
        */

        Route::get('/users', [
            UserManagementController::class,
            'index',
        ])->name('api.v1.users.index');

        Route::post('/users', [
            UserManagementController::class,
            'store',
        ])->name('api.v1.users.store');

        Route::get('/users/export', [
            UserManagementController::class,
            'export',
        ])->name('api.v1.users.export');

        Route::get('/users/{user}', [
            UserManagementController::class,
            'show',
        ])->whereNumber('user')
            ->name('api.v1.users.show');

        /*
         * Multipart profile-photo updates are sent as POST to an explicit update
         * endpoint. The business behavior still matches website update semantics.
         */
        Route::post('/users/{user}/update', [
            UserManagementController::class,
            'update',
        ])->whereNumber('user')
            ->name('api.v1.users.update');

        Route::get('/users/{user}/profile-photo', [
            UserManagementController::class,
            'profilePhoto',
        ])->whereNumber('user')
            ->name('api.v1.users.profile-photo');

        Route::patch('/users/{user}/activate', [
            UserManagementController::class,
            'activate',
        ])->whereNumber('user')
            ->name('api.v1.users.activate');

        Route::patch('/users/{user}/deactivate', [
            UserManagementController::class,
            'deactivate',
        ])->whereNumber('user')
            ->name('api.v1.users.deactivate');

        Route::patch('/users/{user}/reset-password', [
            UserManagementController::class,
            'resetPassword',
        ])->whereNumber('user')
            ->name('api.v1.users.reset-password');

        Route::delete('/users/{user}', [
            UserManagementController::class,
            'destroy',
        ])->whereNumber('user')
            ->name('api.v1.users.destroy');

        /*
        |--------------------------------------------------------------------------
        | Activity Logs
        |--------------------------------------------------------------------------
        | Read-only Admin audit trail. ActivityLogPolicy is authoritative.
        */

        Route::get('/activity-logs', [
            ActivityLogController::class,
            'index',
        ])->name('api.v1.activity-logs.index');

        Route::get('/activity-logs/{activityLog}', [
            ActivityLogController::class,
            'show',
        ])->whereNumber('activityLog')
            ->name('api.v1.activity-logs.show');
    });
