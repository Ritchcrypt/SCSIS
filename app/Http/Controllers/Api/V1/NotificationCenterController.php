<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use App\Services\NotificationBellService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class NotificationCenterController extends Controller
{
    public function index(
        Request $request,
        NotificationBellService $notificationBell
    ): JsonResponse {
        $user = $request->user();

        abort_unless($user, 401, 'Authentication is required.');

        $bell = $notificationBell->forUser($user);

        return response()
            ->json([
                'data' => [
                    'unread_count' => (int) ($bell['unread_count'] ?? 0),
                    'notifications' => collect(
                        $bell['notifications'] ?? []
                    )->values()->all(),
                ],
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    public function pulse(
        Request $request,
        NotificationBellService $notificationBell
    ): JsonResponse {
        $user = $request->user();

        abort_unless($user, 401, 'Authentication is required.');

        return response()
            ->json([
                'version' => 1,
                'data' => $notificationBell->pulseForUser($user),
                'server_time' => now()->toIso8601String(),
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    public function open(
        Request $request,
        UserNotification $notification,
        NotificationBellService $notificationBell
    ): JsonResponse {
        $user = $request->user();

        abort_unless($user, 401, 'Authentication is required.');

        Gate::authorize('view', $notification);

        $this->markNotificationGroupAsRead($notification);

        $bell = $notificationBell->forUser($user);

        return response()
            ->json([
                'message' => 'Notification opened.',
                'data' => [
                    'target' => $this->targetFor(
                        strtolower(trim((string) $user->role)),
                        $notification
                    ),
                    'unread_count' => (int) ($bell['unread_count'] ?? 0),
                ],
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    private function markNotificationGroupAsRead(
        UserNotification $notification
    ): void {
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

        DB::transaction(function () use ($notification, $updates): void {
            $query = UserNotification::query()
                ->where('user_id', (int) $notification->user_id);

            if (
                Schema::hasColumn('notifications', 'source_id')
                && ! empty($notification->source_id)
            ) {
                $query
                    ->where('type', $notification->type)
                    ->where('source_id', $notification->source_id);
            } else {
                $query->where('id', (int) $notification->id);
            }

            $query->update($updates);
        });
    }

    private function targetFor(
        string $role,
        UserNotification $notification
    ): array {
        $type = strtolower(trim((string) $notification->type));
        $sourceId = ! empty($notification->source_id)
            ? (int) $notification->source_id
            : null;

        if ($type === 'user_registration') {
            return $role === 'admin'
                ? $this->target('userManagement', $sourceId)
                : $this->target('dashboard');
        }

        if (in_array($type, ['announcement', 'calamity'], true)) {
            return $this->target('announcements', $sourceId);
        }

        if (
            in_array(
                $type,
                ['resident_complaint', 'resident_complaint_update'],
                true
            )
        ) {
            return in_array($role, ['admin', 'official', 'dao', 'resident'], true)
                ? $this->target('residentComplaints', $sourceId)
                : $this->target('dashboard');
        }

        if (
            in_array(
                $type,
                [
                    'tanod_task',
                    'tanod_task_assigned',
                    'tanod_task_update',
                    'task_assigned',
                    'task_update',
                ],
                true
            )
        ) {
            return in_array($role, ['admin', 'tanod'], true)
                ? $this->target('tanodTasks', $sourceId)
                : $this->target('dashboard');
        }

        if ($type === 'tanod_alert') {
            return in_array($role, ['admin', 'tanod'], true)
                ? $this->target('tanodAlerts', $sourceId)
                : $this->target('dashboard');
        }

        if (
            in_array(
                $type,
                [
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
                ],
                true
            )
        ) {
            return $this->target('incidents', $sourceId);
        }

        return $this->target('dashboard');
    }

    private function target(
        string $module,
        ?int $sourceId = null
    ): array {
        return [
            'module' => $module,
            'source_id' => $sourceId,
        ];
    }
}
