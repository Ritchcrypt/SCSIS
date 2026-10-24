<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class NotificationBellService
{
    public function pulseForUser(?User $user): array
    {
        if (
            ! $user
            || ! Schema::hasTable('notifications')
            || ! Schema::hasColumn('notifications', 'user_id')
        ) {
            return [
                'latest_notification_id' => null,
                'notification' => null,
                'latest_emergency_notification_id' => null,
                'emergency_notification' => null,
            ];
        }

        $latestNotification = UserNotification::query()
            ->where('user_id', (int) $user->id)
            ->when(
                Schema::hasColumn('notifications', 'type'),
                fn ($query) => $query->whereNotIn(
                    'type',
                    [
                        'case_created',
                        'case_updated',
                        'case_deleted',
                    ]
                )
            )
            ->orderByDesc('id')
            ->first();

        $latestEmergencyNotification = Schema::hasColumn('notifications', 'type')
            ? UserNotification::query()
                ->where('user_id', (int) $user->id)
                ->where('type', 'mobile_emergency')
                ->orderByDesc('id')
                ->first()
            : null;

        return [
            'latest_notification_id' => $latestNotification
                ? (int) $latestNotification->id
                : null,
            'notification' => $this->formatPulseNotification($latestNotification),
            'latest_emergency_notification_id' => $latestEmergencyNotification
                ? (int) $latestEmergencyNotification->id
                : null,
            'emergency_notification' => $this->formatPulseNotification($latestEmergencyNotification),
        ];
    }

    public function forUser(?User $user): array
    {
        if (! $user || ! Schema::hasTable('notifications')) {
            return [
                'unread_count' => 0,
                'notifications' => collect(),
                'fallback_url' => url('/'),
                'can_open' => false,
            ];
        }

        $notifications = $this->latestUnreadNotifications($user);

        return [
            'unread_count' => $notifications->count(),
            'notifications' => $notifications
                ->map(fn (UserNotification $notification) => $this->formatNotification($notification, $user))
                ->values(),
            'fallback_url' => $this->dashboardUrl($user),
            'can_open' => Route::has('notifications.open'),
        ];
    }

    public function markAllUnreadAsRead(?User $user): int
    {
        if (
            ! $user
            || ! Schema::hasTable('notifications')
            || ! Schema::hasColumn('notifications', 'user_id')
        ) {
            return 0;
        }

        $updates = [];

        if (Schema::hasColumn('notifications', 'is_read')) {
            $updates['is_read'] = true;
        }

        if (Schema::hasColumn('notifications', 'read_at')) {
            $updates['read_at'] = now();
        }

        if ($updates === []) {
            return 0;
        }

        $query = UserNotification::query()
            ->where('user_id', (int) $user->id);

        if (Schema::hasColumn('notifications', 'type')) {
            $query->whereNotIn(
                'type',
                [
                    'case_created',
                    'case_updated',
                    'case_deleted',
                ]
            );
        }

        if (Schema::hasColumn('notifications', 'is_read')) {
            $query->where(function ($unreadQuery) {
                $unreadQuery->where('is_read', false)
                    ->orWhere('is_read', 0)
                    ->orWhereNull('is_read');
            });
        } elseif (Schema::hasColumn('notifications', 'read_at')) {
            $query->whereNull('read_at');
        }

        return (int) $query->update($updates);
    }

    public function deleteOwnedNotificationGroup(
        ?User $user,
        UserNotification $notification
    ): int {
        if (
            ! $user
            || ! Schema::hasTable('notifications')
            || ! Schema::hasColumn('notifications', 'user_id')
            || (int) $notification->user_id !== (int) $user->id
        ) {
            return 0;
        }

        $query = UserNotification::query()
            ->where('user_id', (int) $user->id);

        if (
            Schema::hasColumn('notifications', 'source_id')
            && Schema::hasColumn('notifications', 'type')
            && $notification->source_id !== null
        ) {
            $query
                ->where('type', $notification->type)
                ->where('source_id', $notification->source_id);
        } else {
            $query->whereKey((int) $notification->id);
        }

        return $query->delete();
    }
    private function formatPulseNotification(?UserNotification $notification): ?array
    {
        if (! $notification) {
            return null;
        }

        return [
            'id' => (int) $notification->id,
            'type' => strtolower(trim((string) ($notification->type ?? 'notification'))),
            'source_id' => $notification->source_id !== null
                ? (int) $notification->source_id
                : null,
            'title' => $notification->title ?: 'New notification',
            'message' => $notification->message
                ?: $notification->title
                ?: 'You have a new notification.',
            'is_read' => (bool) $notification->is_read,
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }

    private function latestUnreadNotifications(User $user): Collection
    {
        if (! Schema::hasColumn('notifications', 'user_id')) {
            return collect();
        }

        $query = UserNotification::query()
            ->where('user_id', (int) $user->id);

        if (Schema::hasColumn('notifications', 'type')) {
            $query->whereNotIn(
                'type',
                [
                    'case_created',
                    'case_updated',
                    'case_deleted',
                ]
            );
        }

        if (Schema::hasColumn('notifications', 'is_read')) {
            $query->where(function ($unreadQuery) {
                $unreadQuery->where('is_read', false)
                    ->orWhere('is_read', 0)
                    ->orWhereNull('is_read');
            });
        }

        if (Schema::hasColumn('notifications', 'created_at')) {
            $query->orderByDesc('created_at');
        }

        $query->orderByDesc('id');

        return $query
            ->get()
            ->unique(function (UserNotification $notification) {
                $type = strtolower((string) ($notification->type ?? 'notification'));
                $sourceId = $notification->source_id ?? null;

                return $sourceId
                    ? $type.':source:'.$sourceId
                    : $type.':notification:'.$notification->id;
            })
            ->values();
    }

    private function formatNotification(UserNotification $notification, User $user): array
    {
        $type = strtolower((string) ($notification->type ?? 'notification'));

        return [
            'id' => $notification->id,
            'type' => $type,
            'type_label' => $this->typeLabel($type),
            'title' => $notification->title ?: $this->typeLabel($type),
            'message' => $notification->message
                ?: $notification->title
                ?: 'No notification message provided.',
            'age' => $this->notificationAge($notification),
            'fallback_url' => $this->fallbackUrlForNotification($user, $notification),
            'openable' => Route::has('notifications.open')
                && ! empty($notification->id),
        ];
    }

    private function notificationAge(UserNotification $notification): string
    {
        try {
            return $notification->created_at
                ? $notification->created_at->diffForHumans()
                : 'No date';
        } catch (\Throwable $e) {
            return 'No date';
        }
    }

    private function typeLabel(string $type): string
    {
        return [
            'user_registration' => 'New Registration',
            'account_activated' => 'Account Activated',
            'account_deactivated' => 'Account Deactivated',
            'resident_complaint' => 'Resident Feedback',
            'resident_complaint_update' => 'Feedback Update',
            'resident_complaint_status_update' => 'Feedback Status Update',
            'resident_complaint_proof' => 'Feedback Action Evidence',
            'incident' => 'Incident',
            'incident_reported' => 'Incident Report',
            'incident_update' => 'Incident Update',
            'incident_message' => 'Incident Message',
            'incident_updated' => 'Incident Update',
            'incident_status_update' => 'Incident Status Update',
            'status_update' => 'Status Update',
            'assigned_incident' => 'Assigned Incident',
            'incident_assigned' => 'Assigned Incident',
            'new_assigned_incident' => 'Assigned Incident',
            'dispatch' => 'Dispatch',
            'escalation' => 'Escalation',
            'emergency' => 'Emergency',
            'mobile_emergency' => 'Distress Signal',
            'mobile_emergency_received' => 'Distress Signal Received',
            'mobile_emergency_acknowledged' => 'Distress Signal Acknowledged',
            'mobile_emergency_resolved' => 'Distress Signal Resolved',
            'resolved' => 'Resolved',
            'announcement' => 'Announcement',
            'calamity' => 'Calamity',
            'tanod_alert' => 'Tanod Alert',
            'tanod_task' => 'Tanod Task',
            'tanod_task_assigned' => 'Tanod Task',
            'tanod_task_update' => 'Tanod Task Update',
            'tanod_task_response' => 'Tanod Task Response',
            'task_assigned' => 'Tanod Task',
            'task_update' => 'Tanod Task Update',
            'community_problem' => 'Community Problem',
            'community' => 'Community',
            'system' => 'System',
        ][$type] ?? ucwords(str_replace('_', ' ', $type));
    }

    private function fallbackUrlForNotification(
        User $user,
        UserNotification $notification
    ): string {
        $type = strtolower((string) ($notification->type ?? 'notification'));
        $role = strtolower((string) $user->role);
        if ($type === 'user_registration') {
            if ($role !== 'admin') {
                return $this->dashboardUrl($user);
            }

            $sourceId = (int) ($notification->source_id ?? 0);

            if (
                $sourceId > 0
                && User::query()->whereKey($sourceId)->exists()
                && Route::has('admin.users.edit')
            ) {
                return route('admin.users.edit', $sourceId);
            }

            if (Route::has('admin.users.index')) {
                return route('admin.users.index');
            }

            return $this->dashboardUrl($user);
        }

        if (
            $type === 'mobile_emergency'
            && in_array($role, ['admin', 'official', 'dao'], true)
            && ! empty($notification->source_id)
            && Route::has('emergency-alerts.show')
        ) {
            return route('emergency-alerts.show', (int) $notification->source_id);
        }


        if (in_array($type, ['announcement', 'calamity'], true)) {
            return $this->roleRouteUrl($role, 'announcements.index');
        }

        if (in_array($type, [
            'resident_complaint',
            'resident_complaint_update',
            'resident_complaint_status_update',
            'resident_complaint_proof',
        ], true)) {
            return $this->roleRouteUrl($role, 'resident-complaints.index');
        }

        if ($type === 'tanod_task_response') {
            if (
                in_array(
                    $role,
                    ['official', 'dao'],
                    true
                )
            ) {
                return $this->roleRouteUrl(
                    $role,
                    'incidents.index'
                );
            }

            return $this->roleRouteUrl(
                $role,
                'tanod-tasks.index'
            );
        }
        if (in_array($type, [
            'tanod_task',
            'tanod_task_assigned',
            'tanod_task_update',
            'task_assigned',
            'task_update',
        ], true)) {
            return $this->roleRouteUrl($role, 'tanod-tasks.index');
        }

        if (in_array($type, [
            'tanod_alert',
            'community_problem',
            'community',
        ], true)) {
            return $this->roleRouteUrl($role, 'tanod-alerts.index');
        }

        if (in_array($type, [
            'incident',
            'incident_reported',
            'incident_update',
            'incident_message',
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
        ], true)) {
            return $this->roleRouteUrl($role, 'incidents.index');
        }

        return $this->dashboardUrl($user);
    }

    private function roleRouteUrl(string $role, string $suffix): string
    {
        $prefix = match ($role) {
            'admin' => 'admin',
            'official', 'dao' => 'official',
            'tanod' => 'tanod',
            'resident' => 'resident',
            default => null,
        };

        if (! $prefix) {
            return Route::has('dashboard') ? route('dashboard') : url('/');
        }

        $routeName = $prefix.'.'.$suffix;

        if (Route::has($routeName)) {
            return route($routeName);
        }

        return $this->dashboardUrlByRole($role);
    }

    private function dashboardUrl(User $user): string
    {
        return $this->dashboardUrlByRole(strtolower((string) $user->role));
    }

    private function dashboardUrlByRole(string $role): string
    {
        $routeName = match ($role) {
            'admin' => Route::has('admin.dashboard') ? 'admin.dashboard' : null,
            'official', 'dao' => Route::has('official.dashboard') ? 'official.dashboard' : null,
            'tanod' => Route::has('tanod.dashboard') ? 'tanod.dashboard' : null,
            'resident' => Route::has('resident.dashboard') ? 'resident.dashboard' : null,
            default => Route::has('dashboard') ? 'dashboard' : null,
        };

        return $routeName ? route($routeName) : url('/');
    }
}
