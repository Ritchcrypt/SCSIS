<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class TanodAlertController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();

        $allowedTypes = $this->allowedTypes();

        $selectedType = strtolower((string) $request->query('type', 'all'));

        if (! in_array($selectedType, $allowedTypes, true)) {
            $selectedType = 'all';
        }

        $query = $this->baseAlertQuery($user)
            ->with(['user', 'acknowledgedBy'])
            ->latest();

        if ($selectedType !== 'all') {
            $query->whereIn('type', $this->filterTypeAliases($selectedType));
        }

        $alerts = $query->paginate(10)->withQueryString();

        $totalAlerts = $this->baseAlertQuery($user)->count();

        $unreadAlerts = $this->unreadAlertQuery($user)->count();

        $acknowledgedAlerts = Schema::hasColumn('notifications', 'acknowledged_at')
            ? $this->baseAlertQuery($user)->whereNotNull('acknowledged_at')->count()
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

    public function acknowledge(UserNotification $notification): RedirectResponse
    {
        $user = Auth::user();

        $this->authorizeNotificationAccess($user, $notification);

        $notification->acknowledge($user->id);

        return back()->with('success', 'Alert acknowledged successfully.');
    }

    public function markAllRead(): RedirectResponse
    {
        $user = Auth::user();

        if (! Schema::hasColumn('notifications', 'is_read')) {
            return back()->with('success', 'No unread alert field found.');
        }

        $updates = [
            'is_read' => true,
        ];

        if (Schema::hasColumn('notifications', 'read_at')) {
            $updates['read_at'] = now();
        }

        $this->baseAlertQuery($user)
            ->where(function ($query) {
                $query->where('is_read', false)
                    ->orWhere('is_read', 0)
                    ->orWhereNull('is_read');
            })
            ->update($updates);

        return back()->with('success', 'All alerts marked as read.');
    }

    public function destroy(UserNotification $notification): RedirectResponse
    {
        $user = Auth::user();

        $this->authorizeNotificationAccess($user, $notification);

        $notification->delete();

        return back()->with('success', 'Alert deleted successfully.');
    }

    public function destroyAll(): RedirectResponse
    {
        $user = Auth::user();

        $deletedCount = $this->baseAlertQuery($user)->delete();

        return back()->with('success', $deletedCount . ' alert notification(s) deleted successfully.');
    }

    private function authorizeNotificationAccess(?User $user, UserNotification $notification): void
    {
        if (! $user) {
            abort(403, 'Unauthorized access.');
        }

        if ((int) $notification->user_id === (int) $user->id) {
            return;
        }

        abort(403, 'You are not allowed to manage this alert.');
    }

    private function baseAlertQuery(?User $user)
    {
        if (! $user) {
            abort(403, 'Unauthorized access.');
        }

        /*
        |--------------------------------------------------------------------------
        | Tanod Alerts Module Rule
        |--------------------------------------------------------------------------
        | Shows operational tanod alerts only.
        |
        | Excluded:
        | - announcement
        | - calamity
        |
        | Those belong to the Announcements module and notification bell only.
        |
        | UI filters are clean grouped labels, but the query still supports older
        | notification aliases already stored in the database.
        */

        return UserNotification::query()
            ->where('user_id', $user->id)
            ->whereNotIn('type', [
                'announcement',
                'calamity',
            ])
            ->whereIn('type', $this->alertTypesOnly());
    }

    private function unreadAlertQuery(?User $user)
    {
        $query = $this->baseAlertQuery($user);

        if (Schema::hasColumn('notifications', 'is_read')) {
            $query->where(function ($unreadQuery) {
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