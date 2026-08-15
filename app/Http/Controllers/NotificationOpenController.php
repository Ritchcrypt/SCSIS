<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class NotificationOpenController extends Controller
{
    public function open(Request $request, UserNotification $notification): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user, 403, 'Unauthenticated request.');

        Gate::authorize('view', $notification);

        $type = strtolower(trim((string) $notification->type));
        $role = strtolower((string) $user->role);

        $this->markNotificationGroupAsRead($notification);

        if ($type === 'user_registration') {
            return redirect()->to(
                $this->userRegistrationUrl($role, $notification)
            );
        }

        if ($type === 'mobile_emergency') {
            return redirect()->to(
                $this->mobileEmergencyUrl($role, $notification)
            );
        }

        if (in_array($type, ['announcement', 'calamity'], true)) {
            return redirect()->to($this->announcementUrl($role));
        }

        if (in_array($type, ['resident_complaint', 'resident_complaint_update'], true)) {
            return redirect()->to($this->residentComplaintUrl($role, $notification));
        }

        if (in_array($type, [
            'tanod_task',
            'tanod_task_assigned',
            'tanod_task_update',
            'task_assigned',
            'task_update',
        ], true)) {
            return redirect()->to($this->tanodTaskUrl($role, $notification));
        }

        if ($type === 'tanod_alert') {
            return redirect()->to($this->tanodAlertUrl($role));
        }

        if (in_array($type, [
            'incident',
            'incident_reported',
            'incident_update',
            'incident_updated',
            'incident_status_update',
            'status_update',
            'assigned_incident',
            'incident_assigned',
            'new_assigned_incident',
            'dispatch',
            'escalation',
            'emergency',
            'resolved',
            'community_problem',
            'community',
        ], true)) {
            return redirect()->to($this->incidentUrl($role, $notification));
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
            ->where('user_id', $notification->user_id);

        if (
            Schema::hasColumn('notifications', 'source_id')
            && ! empty($notification->source_id)
        ) {
            $query->where('type', $notification->type)
                ->where('source_id', $notification->source_id);
        } else {
            $query->where('id', $notification->id);
        }

        $query->update($updates);
    }

    private function userRegistrationUrl(
        string $role,
        UserNotification $notification
    ): string {
        if ($role !== 'admin') {
            return $this->dashboardUrl($role);
        }

        $sourceId = $this->sourceId($notification);

        if (
            $sourceId
            && $this->recordExists('users', $sourceId)
            && Route::has('admin.users.edit')
        ) {
            return route('admin.users.edit', $sourceId);
        }

        if (Route::has('admin.users.index')) {
            return route('admin.users.index');
        }

        return $this->dashboardUrl($role);
    }

    private function mobileEmergencyUrl(
        string $role,
        UserNotification $notification
    ): string {
        if (! in_array($role, ['admin', 'official', 'dao'], true)) {
            return $this->dashboardUrl($role);
        }

        $sourceId = $this->sourceId($notification);

        if (
            $sourceId
            && $this->recordExists('mobile_emergency_alerts', $sourceId)
            && Route::has('emergency-alerts.show')
        ) {
            return route('emergency-alerts.show', $sourceId);
        }

        return $this->dashboardUrl($role);
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
        $sourceId = $this->sourceId($notification);

        if ($sourceId && $this->recordExists('resident_complaints', $sourceId)) {
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
                return route($routeName, $sourceId);
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
        $sourceId = $this->sourceId($notification);

        if ($sourceId && $this->recordExists('incidents', $sourceId)) {
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
                return route($routeName, $sourceId);
            }
        }

        return match ($role) {
            'admin' => Route::has('admin.incidents.index')
                ? route('admin.incidents.index')
                : $this->dashboardUrl($role),

            'official', 'dao' => Route::has('official.incidents.index')
                ? route('official.incidents.index')
                : $this->dashboardUrl($role),

            'tanod' => Route::has('tanod.incidents.index')
                ? route('tanod.incidents.index')
                : $this->dashboardUrl($role),

            'resident' => Route::has('resident.incidents.index')
                ? route('resident.incidents.index')
                : $this->dashboardUrl($role),

            default => $this->dashboardUrl($role),
        };
    }

    private function tanodTaskUrl(string $role, UserNotification $notification): string
    {
        $sourceId = $this->sourceId($notification);

        if (
            $role === 'admin'
            && $sourceId
            && $this->recordExists('tanod_tasks', $sourceId)
            && Route::has('admin.tanod-tasks.show')
        ) {
            return route('admin.tanod-tasks.show', $sourceId);
        }

        if ($role === 'admin' && Route::has('admin.tanod-tasks.index')) {
            return route('admin.tanod-tasks.index');
        }

        if ($role === 'tanod' && Route::has('tanod.tanod-tasks.index')) {
            return route('tanod.tanod-tasks.index');
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

    private function sourceId(UserNotification $notification): ?int
    {
        if (empty($notification->source_id)) {
            return null;
        }

        return (int) $notification->source_id;
    }

    private function recordExists(string $table, int $id): bool
    {
        if (
            ! Schema::hasTable($table)
            || ! Schema::hasColumn($table, 'id')
        ) {
            return false;
        }

        return DB::table($table)
            ->where('id', $id)
            ->exists();
    }
}
