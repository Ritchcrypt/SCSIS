<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $unreadAlerts = $this->baseAlertQuery($user)
            ->where('is_read', false)
            ->count();

        $acknowledgedAlerts = $this->baseAlertQuery($user)
            ->whereNotNull('acknowledged_at')
            ->count();

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

        $this->baseAlertQuery($user)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

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
        | This module must only show operational alerts.
        |
        | Announcement notifications are intentionally excluded here because
        | they belong only inside the Announcements module and notification bell.
        */
        return UserNotification::query()
            ->where('user_id', $user->id)
            ->whereIn('type', $this->alertTypesOnly());
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
            | Operational Alert Types Only
            |--------------------------------------------------------------------------
            | Do not include:
            | - announcement
            | - calamity from announcement module
            |
            | Announcements stay in the Announcements module.
            */
            'incident_reported' => 'New Incident Reports',
            'incident_update' => 'Incident Updates',
            'dispatch' => 'Dispatch',
            'escalation' => 'Escalation',
            'emergency' => 'Emergency',
            'resolved' => 'Resolved',
            'tanod_alert' => 'Tanod Alerts',
            'tanod_task' => 'Tanod Tasks',

            /*
            |--------------------------------------------------------------------------
            | Backward Compatibility
            |--------------------------------------------------------------------------
            | Keep old operational labels only if old incident/community records exist.
            | These are not announcement-module records.
            */
            'community_problem' => 'Community Problems',
            'community' => 'Community',
        ];
    }
}