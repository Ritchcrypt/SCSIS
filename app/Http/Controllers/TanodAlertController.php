<?php

namespace App\Http\Controllers;

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

        $allowedTypes = [
            'all',
            'dispatch',
            'escalation',
            'emergency',
            'calamity',
            'resolved',
            'announcement',
        ];

        $selectedType = strtolower((string) $request->query('type', 'all'));

        if (! in_array($selectedType, $allowedTypes, true)) {
            $selectedType = 'all';
        }

        $query = UserNotification::query()
            ->with(['user', 'acknowledgedBy'])
            ->whereIn('type', array_filter($allowedTypes, fn ($type) => $type !== 'all'))
            ->latest();

        /*
        |--------------------------------------------------------------------------
        | Role Filtering
        |--------------------------------------------------------------------------
        | Admin can see all tanod alerts.
        | Tanod can only see alerts assigned to their own user account.
        */
        if ($user->role === 'tanod') {
            $query->where('user_id', $user->id);
        }

        if ($selectedType !== 'all') {
            $query->where('type', $selectedType);
        }

        $alerts = $query->paginate(10)->withQueryString();

        $totalAlerts = UserNotification::query()
            ->whereIn('type', array_filter($allowedTypes, fn ($type) => $type !== 'all'))
            ->when($user->role === 'tanod', fn ($q) => $q->where('user_id', $user->id))
            ->count();

        $unreadAlerts = UserNotification::query()
            ->whereIn('type', array_filter($allowedTypes, fn ($type) => $type !== 'all'))
            ->when($user->role === 'tanod', fn ($q) => $q->where('user_id', $user->id))
            ->where('is_read', false)
            ->count();

        $acknowledgedAlerts = UserNotification::query()
            ->whereIn('type', array_filter($allowedTypes, fn ($type) => $type !== 'all'))
            ->when($user->role === 'tanod', fn ($q) => $q->where('user_id', $user->id))
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
                'dispatch' => 'Dispatch',
                'escalation' => 'Escalation',
                'emergency' => 'Emergency',
                'calamity' => 'Calamity',
                'resolved' => 'Resolved',
                'announcement' => 'Announcement',
            ],
        ]);
    }

    public function acknowledge(UserNotification $notification): RedirectResponse
    {
        $user = Auth::user();

        /*
        |--------------------------------------------------------------------------
        | Access Rule
        |--------------------------------------------------------------------------
        | Admin can acknowledge any alert.
        | Tanod can acknowledge only their own alert.
        */
        if ($user->role === 'tanod' && (int) $notification->user_id !== (int) $user->id) {
            abort(403, 'You are not allowed to acknowledge this alert.');
        }

        if (! in_array($user->role, ['admin', 'tanod'], true)) {
            abort(403, 'You are not allowed to acknowledge this alert.');
        }

        $notification->acknowledge($user->id);

        return back()->with('success', 'Alert acknowledged successfully.');
    }
}