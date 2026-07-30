<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RecordsOperationalActivity;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class TanodAlertController extends Controller
{
    use RecordsOperationalActivity;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', UserNotification::class);

        $user = $request->user();

        $allowedTypes = $this->allowedTypes();
        $selectedType = strtolower(trim((string) $request->query('type', 'all')));

        if (! in_array($selectedType, $allowedTypes, true)) {
            $selectedType = 'all';
        }

        $query = $this->baseAlertQuery($user)
            ->with(['user', 'acknowledgedBy'])
            ->latest();

        if ($selectedType !== 'all') {
            $query->whereIn('type', $this->filterTypeAliases($selectedType));
        }

        $alerts = $query
            ->paginate(10)
            ->withQueryString();

        $totalAlerts = $this->baseAlertQuery($user)->count();
        $unreadAlerts = $this->unreadAlertQuery($user)->count();

        $acknowledgedAlerts = Schema::hasColumn('notifications', 'acknowledged_at')
            ? $this->baseAlertQuery($user)
                ->whereNotNull('acknowledged_at')
                ->count()
            : 0;

        return view('tanod-alerts.index', [
            'alerts' => $alerts,
            'selectedType' => $selectedType,
            'totalAlerts' => $totalAlerts,
            'unreadAlerts' => $unreadAlerts,
            'acknowledgedAlerts' => $acknowledgedAlerts,
            'alertTypes' => $this->alertTypeLabels(),
        ]);
    }

    public function acknowledge(
        Request $request,
        UserNotification $notification
    ): RedirectResponse {
        Gate::authorize('acknowledge', $notification);
        $this->ensureOperationalAlert($notification);

        $notification->acknowledge((int) $request->user()->id);

        $this->recordOperationalActivity(
            event: 'tanod_alert.acknowledged',
            category: 'tanod_alert',
            description: 'A tanod alert was acknowledged.',
            metadata: [
                'notification_id' => (int) $notification->id,
                'notification_type' => $notification->type,
                'source_id' => $notification->source_id,
            ],
            request: $request,
        );

        return back()->with(
            'success',
            'Alert acknowledged successfully.'
        );
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        Gate::authorize('markAllRead', UserNotification::class);

        $user = $request->user();

        if (! Schema::hasColumn('notifications', 'is_read')) {
            return back()->with(
                'success',
                'No unread alert field found.'
            );
        }

        $updates = [
            'is_read' => true,
        ];

        if (Schema::hasColumn('notifications', 'read_at')) {
            $updates['read_at'] = now();
        }

        $this->baseAlertQuery($user)
            ->where(function ($query): void {
                $query->where('is_read', false)
                    ->orWhere('is_read', 0)
                    ->orWhereNull('is_read');
            })
            ->update($updates);

        return back()->with(
            'success',
            'All alerts marked as read.'
        );
    }

    public function destroy(
        Request $request,
        UserNotification $notification
    ): RedirectResponse {
        Gate::authorize('delete', $notification);
        $this->ensureOperationalAlert($notification);

        $auditMetadata = [
            'notification_id' => (int) $notification->id,
            'notification_type' => $notification->type,
            'source_id' => $notification->source_id,
        ];

        $notification->delete();

        $this->recordOperationalActivity(
            event: 'tanod_alert.deleted',
            category: 'tanod_alert',
            description: 'A tanod alert was deleted.',
            metadata: $auditMetadata,
            request: $request,
        );

        return back()->with(
            'success',
            'Alert deleted successfully.'
        );
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        Gate::authorize('deleteAny', UserNotification::class);

        $deletedCount = $this->baseAlertQuery($request->user())
            ->delete();

        $this->recordOperationalActivity(
            event: 'tanod_alert.deleted_all',
            category: 'tanod_alert',
            description: 'All visible tanod alerts were deleted.',
            metadata: [
                'deleted_count' => $deletedCount,
            ],
            request: $request,
        );

        return back()->with(
            'success',
            $deletedCount . ' alert notification(s) deleted successfully.'
        );
    }

    /**
     * Ensure alert endpoints cannot be used to alter another category of the
     * current user's notifications, such as announcements or complaints.
     */
    private function ensureOperationalAlert(UserNotification $notification): void
    {
        $type = strtolower(trim((string) $notification->type));

        abort_unless(
            in_array($type, $this->alertTypesOnly(), true),
            404,
            'Alert notification not found.'
        );
    }

    private function baseAlertQuery(User $user)
    {
        /*
        |--------------------------------------------------------------------------
        | Tanod Alerts Module Rule
        |--------------------------------------------------------------------------
        |
        | Every query is restricted to the authenticated user's own records.
        | Announcements and calamity notices remain in their dedicated module
        | and notification bell.
        |
        */

        return UserNotification::query()
            ->where('user_id', $user->id)
            ->whereNotIn('type', [
                'announcement',
                'calamity',
            ])
            ->whereIn('type', $this->alertTypesOnly());
    }

    private function unreadAlertQuery(User $user)
    {
        $query = $this->baseAlertQuery($user);

        if (Schema::hasColumn('notifications', 'is_read')) {
            $query->where(function ($unreadQuery): void {
                $unreadQuery->where('is_read', false)
                    ->orWhere('is_read', 0)
                    ->orWhereNull('is_read');
            });
        }

        return $query;
    }

    private function allowedTypes(): array
    {
        return array_keys($this->alertTypeLabels());
    }

    private function alertTypesOnly(): array
    {
        return collect($this->filterTypeAliases())
            ->flatten()
            ->unique()
            ->reject(fn ($type) => $type === 'all')
            ->values()
            ->all();
    }

    private function filterTypeAliases(?string $selectedType = null): array
    {
        $aliases = [
            'all' => [
                'assigned_incident',
                'incident_assigned',
                'new_assigned_incident',
                'incident',
                'incident_reported',
                'incident_update',
                'incident_updated',
                'incident_status_update',
                'status_update',
                'dispatch',
                'escalation',
                'emergency',
                'resolved',
                'tanod_task',
                'tanod_task_assigned',
                'task_assigned',
                'tanod_task_update',
                'task_update',
                'tanod_alert',
                'community_problem',
                'community',
            ],

            'assigned_incident' => [
                'assigned_incident',
                'incident_assigned',
                'new_assigned_incident',
            ],

            'incident' => [
                'incident',
                'incident_reported',
            ],

            'incident_update' => [
                'incident_update',
                'incident_updated',
                'incident_status_update',
                'status_update',
            ],

            'dispatch' => [
                'dispatch',
            ],

            'escalation' => [
                'escalation',
            ],

            'emergency' => [
                'emergency',
            ],

            'resolved' => [
                'resolved',
            ],

            'tanod_task' => [
                'tanod_task',
                'tanod_task_assigned',
                'task_assigned',
            ],

            'tanod_task_update' => [
                'tanod_task_update',
                'task_update',
            ],

            'tanod_alert' => [
                'tanod_alert',
            ],

            'community' => [
                'community_problem',
                'community',
            ],
        ];

        if ($selectedType === null) {
            return $aliases;
        }

        return $aliases[$selectedType] ?? [$selectedType];
    }

    private function alertTypeLabels(): array
    {
        return [
            'all' => 'All Alerts',
            'assigned_incident' => 'Assigned Incident',
            'incident' => 'Incident',
            'incident_update' => 'Incident Update',
            'dispatch' => 'Dispatch',
            'escalation' => 'Escalation',
            'emergency' => 'Emergency',
            'resolved' => 'Resolved',
            'tanod_task' => 'Tanod Task',
            'tanod_task_update' => 'Tanod Task Update',
            'tanod_alert' => 'Tanod Alert',
            'community' => 'Community',
        ];
    }
}
