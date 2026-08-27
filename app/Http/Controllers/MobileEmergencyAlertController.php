<?php

namespace App\Http\Controllers;

use App\Models\MobileEmergencyAlert;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MobileEmergencyAlertController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeResponder($request);

        $alerts = MobileEmergencyAlert::query()
            ->with('user:id,name,role')
            ->latest('triggered_at')
            ->latest('id')
            ->paginate(20);

        return view('emergency-alerts.index', [
            'alerts' => $alerts,
        ]);
    }

    public function show(Request $request, MobileEmergencyAlert $emergencyAlert): View
    {
        $this->authorizeResponder($request);

        $emergencyAlert->load([
            'user',
            'acknowledgedBy',
            'resolvedBy',
        ]);

        return view('emergency-alerts.show', [
            'alert' => $emergencyAlert,
        ]);
    }

    public function acknowledge(Request $request, MobileEmergencyAlert $emergencyAlert): RedirectResponse
    {
        $user = $this->authorizeResponder($request);

        DB::transaction(function () use ($emergencyAlert, $user): void {
            $locked = MobileEmergencyAlert::query()
                ->lockForUpdate()
                ->findOrFail($emergencyAlert->id);

            if ($locked->status === 'resolved') {
                return;
            }

            $locked->forceFill([
                'status' => 'acknowledged',
                'acknowledged_by' => $locked->acknowledged_by ?: $user->id,
                'acknowledged_at' => $locked->acknowledged_at ?: now(),
            ])->save();
        });

        return back()->with('success', 'Mobile SOS acknowledged.');
    }

    public function resolve(Request $request, MobileEmergencyAlert $emergencyAlert): RedirectResponse
    {
        $user = $this->authorizeResponder($request);

        DB::transaction(function () use ($emergencyAlert, $user): void {
            $locked = MobileEmergencyAlert::query()
                ->lockForUpdate()
                ->findOrFail($emergencyAlert->id);

            $locked->forceFill([
                'status' => 'resolved',
                'acknowledged_by' => $locked->acknowledged_by ?: $user->id,
                'acknowledged_at' => $locked->acknowledged_at ?: now(),
                'resolved_by' => $user->id,
                'resolved_at' => now(),
            ])->save();
        });

        return back()->with('success', 'Mobile SOS resolved.');
    }


    public function destroy(
        Request $request,
        MobileEmergencyAlert $emergencyAlert
    ): RedirectResponse {
        $user = $this->authorizeResponder($request);

        $this->deleteAlertIds([(int) $emergencyAlert->id]);

        return redirect()
            ->to($this->indexUrl($user))
            ->with('success', 'Distress signal deleted.');
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        $this->authorizeResponder($request);

        $ids = MobileEmergencyAlert::query()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $deleted = $this->deleteAlertIds($ids);

        return back()->with(
            'success',
            $deleted === 1
                ? '1 distress signal deleted.'
                : "{$deleted} distress signals deleted."
        );
    }

    private function deleteAlertIds(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            fn (int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return 0;
        }

        return DB::transaction(function () use ($ids): int {
            // Incoming responder alerts open the Distress Signal record.
            // Remove only those source links so deleted SOS records cannot
            // leave stale responder bell items. Reporter lifecycle messages
            // remain as the sender's notification history and open Dashboard.
            UserNotification::query()
                ->where('type', 'mobile_emergency')
                ->whereIn('source_id', $ids)
                ->delete();

            return MobileEmergencyAlert::query()
                ->whereIn('id', $ids)
                ->delete();
        });
    }

    private function indexUrl(User $user): string
    {
        return $user->isAdmin()
            ? route('admin.mobile-sos.index')
            : route('official.mobile-sos.index');
    }
    private function authorizeResponder(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403, 'Authentication is required.');
        abort_unless(
            $user->isActive() && ($user->isAdmin() || $user->isOfficial()),
            403,
            'Only active administrators and officials may manage Mobile SOS alerts.'
        );

        return $user;
    }
}
