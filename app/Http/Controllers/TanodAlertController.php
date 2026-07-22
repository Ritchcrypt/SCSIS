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
            $query->where('type', $selectedType);
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
        return array_values(array_filter(
            $this->allowedTypes(),
            fn ($type) => $type !== 'all'
        ));
    }

    private function alertTypeLabels(): array
    {
        return [
            'all' => 'All Alerts',

            /*
            |--------------------------------------------------------------------------
            | Assigned Incident / Incoming Incident Alerts
            |--------------------------------------------------------------------------
            */
            'assigned_incident' => 'Assigned Incident',
            'incident_assigned' => 'Assigned Incident',
            'new_assigned_incident' => 'Assigned Incident',
            'incident' => 'Incident',
            'incident_reported' => 'New Incident Report',

            /*
            |--------------------------------------------------------------------------
            | Incident Status / Field Operation Updates
            |--------------------------------------------------------------------------
            */
            'incident_update' => 'Incident Update',
            'incident_updated' => 'Incident Update',
            'incident_status_update' => 'Incident Status Update',
            'status_update' => 'Status Update',
            'dispatch' => 'Dispatch',
            'escalation' => 'Escalation',
            'emergency' => 'Emergency',
            'resolved' => 'Resolved',

            /*
            |--------------------------------------------------------------------------
            | Tanod Task Alerts
            |--------------------------------------------------------------------------
            */
            'tanod_task' => 'Tanod Task',
            'tanod_task_assigned' => 'Tanod Task',
            'tanod_task_update' => 'Tanod Task Update',
            'task_assigned' => 'Tanod Task',
            'task_update' => 'Tanod Task Update',

            /*
            |--------------------------------------------------------------------------
            | Tanod-specific operational alerts
            |--------------------------------------------------------------------------
            */
            'tanod_alert' => 'Tanod Alert',

            /*
            |--------------------------------------------------------------------------
            | Backward Compatibility
            |--------------------------------------------------------------------------
            */
            'community_problem' => 'Community Problem',
            'community' => 'Community',
        ];
    }
}