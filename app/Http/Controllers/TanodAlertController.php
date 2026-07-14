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
            'alertTypes' => [
                'all' => 'All Alerts',
                'incident_reported' => 'New Incident Reports',
                'calamity' => 'Calamity',
                'community_problem' => 'Community Problems',
                'dispatch' => 'Dispatch',
                'escalation' => 'Escalation',
                'emergency' => 'Emergency',
            ],
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

        if ($user->role === 'admin') {
            return;
        }

        if ($user->role === 'tanod' && (int) $notification->user_id === (int) $user->id) {
            return;
        }

        abort(403, 'You are not allowed to manage this alert.');
    }

    private function baseAlertQuery(?User $user)
    {
        $query = UserNotification::query()
            ->whereIn('type', $this->alertTypesOnly());

        if ($user?->role === 'tanod') {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    private function allowedTypes(): array
    {
        return [
            'all',
            'incident_reported',
            'calamity',
            'community_problem',
            'dispatch',
            'escalation',
            'emergency',
        ];
    }

    private function alertTypesOnly(): array
    {
        return array_values(array_filter(
            $this->allowedTypes(),
            fn ($type) => $type !== 'all'
        ));
    }
}