<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class NotificationOpenController extends Controller
{
    public function open(Request $request, UserNotification $notification): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user && (int) $notification->user_id === (int) $user->id, 403);

        $type = strtolower((string) $notification->type);
        $role = strtolower((string) $user->role);

        $this->markNotificationGroupAsRead($notification);

        /*
        |--------------------------------------------------------------------------
        | Announcement Notifications
        |--------------------------------------------------------------------------
        | Public announcements and calamity announcements must open the
        | Announcements module, not Tanod Alerts.
        */
        if (in_array($type, ['announcement', 'calamity'], true)) {
            return redirect()->to($this->announcementUrl($role));
        }

        /*
        |--------------------------------------------------------------------------
        | Resident Complaint Notifications
        |--------------------------------------------------------------------------
        | Complaint notifications must open the Resident Complaints module.
        */
        if (in_array($type, ['resident_complaint', 'resident_complaint_update'], true)) {
            return redirect()->to($this->residentComplaintUrl($role, $notification));
        }

        /*
        |--------------------------------------------------------------------------
        | Incident Notifications
        |--------------------------------------------------------------------------
        | These open the related incident page when source_id is an incident ID.
        */
        if (in_array($type, [
            'incident',
            'incident_reported',
            'incident_update',
            'dispatch',
            'escalation',
            'emergency',
            'resolved',
        ], true)) {
            return redirect()->to($this->incidentUrl($role, $notification));
        }

        /*
        |--------------------------------------------------------------------------
        | Tanod Task Notifications
        |--------------------------------------------------------------------------
        | Tanod task notifications open the task module.
        */
        if ($type === 'tanod_task') {
            return redirect()->to($this->tanodTaskUrl($role, $notification));
        }

        /*
        |--------------------------------------------------------------------------
        | Tanod Alert Notifications
        |--------------------------------------------------------------------------
        | Only operational tanod alerts should open Tanod Alerts.
        */
        if ($type === 'tanod_alert') {
            return redirect()->to($this->tanodAlertUrl($role));
        }

        return redirect()->to($this->dashboardUrl($role));
    }

    private function markNotificationGroupAsRead(UserNotification $notification): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $updates = [];

        if (Schema::hasColumn('notifications', 'is_read')) {
            $updates['is_read'] = true;
        }

        if (Schema::hasColumn('notifications', 'read_at')) {
            $updates['read_at'] = now();
        }

        if ($updates === []) {
            return;
        }

        $query = UserNotification::query()
            ->where('user_id', $notification->user_id)
            ->where('type', $notification->type);

        if (Schema::hasColumn('notifications', 'source_id')) {
            if (! empty($notification->source_id)) {
                $query->where('source_id', $notification->source_id);
            } else {
                $query->whereNull('source_id');
            }
        }

        $query->update($updates);
    }

    private function announcementUrl(string $role): string
    {
        $routeName = match ($role) {
            'admin' => Route::has('admin.announcements.index')
                ? 'admin.announcements.index'
                : null,

            'official', 'dao' => Route::has('official.announcements.index')
                ? 'official.announcements.index'
                : null,

            'tanod' => Route::has('tanod.announcements.index')
                ? 'tanod.announcements.index'
                : null,

            'resident' => Route::has('resident.announcements.index')
                ? 'resident.announcements.index'
                : null,

            default => null,
        };

        return $routeName ? route($routeName) : $this->dashboardUrl($role);
    }

    private function residentComplaintUrl(string $role, UserNotification $notification): string
    {
        if (! empty($notification->source_id)) {
            $routeName = match ($role) {
                'admin' => Route::has('admin.resident-complaints.show')
                    ? 'admin.resident-complaints.show'
                    : null,

                'official', 'dao' => Route::has('official.resident-complaints.show')
                    ? 'official.resident-complaints.show'
                    : null,

                'resident' => Route::has('resident.resident-complaints.show')
                    ? 'resident.resident-complaints.show'
                    : null,

                default => null,
            };

            if ($routeName) {
                return route($routeName, $notification->source_id);
            }
        }

        $routeName = match ($role) {
            'admin' => Route::has('admin.resident-complaints.index')
                ? 'admin.resident-complaints.index'
                : null,

            'official', 'dao' => Route::has('official.resident-complaints.index')
                ? 'official.resident-complaints.index'
                : null,

            'resident' => Route::has('resident.resident-complaints.index')
                ? 'resident.resident-complaints.index'
                : null,

            default => null,
        };

        return $routeName ? route($routeName) : $this->dashboardUrl($role);
    }

    private function incidentUrl(string $role, UserNotification $notification): string
    {
        if (! empty($notification->source_id)) {
            $routeName = match ($role) {
                'admin' => Route::has('admin.incidents.show')
                    ? 'admin.incidents.show'
                    : null,

                'official', 'dao' => Route::has('official.incidents.show')
                    ? 'official.incidents.show'
                    : null,

                'tanod' => Route::has('tanod.incidents.show')
                    ? 'tanod.incidents.show'
                    : null,

                'resident' => Route::has('resident.incidents.show')
                    ? 'resident.incidents.show'
                    : null,

                default => null,
            };

            if ($routeName) {
                return route($routeName, $notification->source_id);
            }
        }

        return $this->dashboardUrl($role);
    }

    private function tanodTaskUrl(string $role, UserNotification $notification): string
    {
        if ($role === 'admin' && ! empty($notification->source_id) && Route::has('admin.tanod-tasks.show')) {
            return route('admin.tanod-tasks.show', $notification->source_id);
        }

        if ($role === 'tanod' && Route::has('tanod.tanod-tasks.index')) {
            return route('tanod.tanod-tasks.index');
        }

        if ($role === 'admin' && Route::has('admin.tanod-tasks.index')) {
            return route('admin.tanod-tasks.index');
        }

        return $this->dashboardUrl($role);
    }

    private function tanodAlertUrl(string $role): string
    {
        $routeName = match ($role) {
            'admin' => Route::has('admin.tanod-alerts.index')
                ? 'admin.tanod-alerts.index'
                : null,

            'official', 'dao' => Route::has('official.tanod-alerts.index')
                ? 'official.tanod-alerts.index'
                : null,

            'tanod' => Route::has('tanod.tanod-alerts.index')
                ? 'tanod.tanod-alerts.index'
                : null,

            default => null,
        };

        return $routeName ? route($routeName) : $this->dashboardUrl($role);
    }

    private function dashboardUrl(string $role): string
    {
        $routeName = match ($role) {
            'admin' => Route::has('admin.dashboard')
                ? 'admin.dashboard'
                : null,

            'official', 'dao' => Route::has('official.dashboard')
                ? 'official.dashboard'
                : null,

            'tanod' => Route::has('tanod.dashboard')
                ? 'tanod.dashboard'
                : null,

            'resident' => Route::has('resident.dashboard')
                ? 'resident.dashboard'
                : null,

            default => Route::has('dashboard')
                ? 'dashboard'
                : null,
        };

        return $routeName ? route($routeName) : url('/');
    }
}