<?php

namespace App\Http\Controllers;

use App\Models\EmergencyAgencyLog;
use App\Models\Incident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmergencyModeController extends Controller
{
    public function index(Request $request): View
    {
        $logs = EmergencyAgencyLog::query()
            ->with(['incident', 'initiator'])
            ->latest('notified_at')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $incidents = Incident::query()
            ->select([
                'id',
                'incident_code',
                'incident_title',
                'title',
            ])
            ->latest()
            ->limit(100)
            ->get();

        return view('emergency-mode.index', [
            'agencies' => $this->agencies(),
            'logs' => $logs,
            'incidents' => $incidents,
            'statuses' => $this->statuses(),
        ]);
    }

    public function notify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'agency' => ['required', Rule::in(array_keys($this->agencies()))],
            'message' => ['nullable', 'string', 'max:3000'],
            'incident_id' => ['nullable', 'integer', 'exists:incidents,id'],
        ]);

        $agency = $this->agencies()[$validated['agency']];

        EmergencyAgencyLog::create([
            'agency' => $validated['agency'],
            'agency_name' => $agency['name'],
            'hotline' => $agency['hotline'],
            'message' => $validated['message'] ?? null,
            'incident_id' => $validated['incident_id'] ?? null,
            'status' => 'contacted',
            'initiated_by' => Auth::id(),
            'notified_at' => now(),
        ]);

        return redirect()
            ->route('admin.emergency-mode.index')
            ->with('success', $agency['name'] . ' has been logged as notified.');
    }

    public function updateStatus(Request $request, EmergencyAgencyLog $emergencyAgencyLog): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys($this->statuses()))],
        ]);

        $emergencyAgencyLog->update([
            'status' => $validated['status'],
        ]);

        return redirect()
            ->route('admin.emergency-mode.index')
            ->with('success', 'Emergency agency log updated successfully.');
    }

    public function destroy(EmergencyAgencyLog $emergencyAgencyLog): RedirectResponse
    {
        $emergencyAgencyLog->delete();

        return redirect()
            ->route('admin.emergency-mode.index')
            ->with('success', 'Emergency agency log deleted successfully.');
    }

    private function agencies(): array
    {
        return [
            'pnp' => [
                'name' => 'PNP (Police)',
                'short_name' => 'PNP',
                'hotline' => '117',
                'color' => 'blue',
            ],
            'bfp' => [
                'name' => 'BFP (Fire)',
                'short_name' => 'BFP',
                'hotline' => '911',
                'color' => 'red',
            ],
            'mdrrmo' => [
                'name' => 'MDRRMO',
                'short_name' => 'MDRRMO',
                'hotline' => '143',
                'color' => 'orange',
            ],
        ];
    }

    private function statuses(): array
    {
        return [
            'pending' => 'Pending',
            'contacted' => 'Contacted',
            'responding' => 'Responding',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];
    }
}