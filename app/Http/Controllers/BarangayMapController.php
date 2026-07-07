<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BarangayMapController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', 'all'));
        $search = trim((string) $request->query('search', ''));

        $incidentsQuery = Incident::query()
            ->with(['barangay', 'category', 'currentStatus'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($status !== '' && $status !== 'all') {
            $incidentsQuery->where(function ($query) use ($status) {
                if (Schema::hasColumn('incidents', 'status')) {
                    $query->orWhere('status', $status);
                }

                if (Schema::hasColumn('incidents', 'current_status')) {
                    $query->orWhere('current_status', $status);
                }

                if (Schema::hasColumn('incidents', 'status_id')) {
                    $query->orWhereHas('currentStatus', function ($statusQuery) use ($status) {
                        if (Schema::hasColumn('statuses', 'status_name')) {
                            $statusQuery->orWhere('status_name', $status);
                        }

                        if (Schema::hasColumn('statuses', 'name')) {
                            $statusQuery->orWhere('name', $status);
                        }

                        if (Schema::hasColumn('statuses', 'status')) {
                            $statusQuery->orWhere('status', $status);
                        }

                        if (Schema::hasColumn('statuses', 'slug')) {
                            $statusQuery->orWhere('slug', $status);
                        }
                    });
                }
            });
        }

        if ($search !== '') {
            $incidentsQuery->where(function ($query) use ($search) {
                if (Schema::hasColumn('incidents', 'incident_code')) {
                    $query->orWhere('incident_code', 'like', "%{$search}%");
                }

                if (Schema::hasColumn('incidents', 'incident_title')) {
                    $query->orWhere('incident_title', 'like', "%{$search}%");
                }

                if (Schema::hasColumn('incidents', 'title')) {
                    $query->orWhere('title', 'like', "%{$search}%");
                }

                if (Schema::hasColumn('incidents', 'location_address')) {
                    $query->orWhere('location_address', 'like', "%{$search}%");
                }

                if (Schema::hasColumn('incidents', 'address')) {
                    $query->orWhere('address', 'like', "%{$search}%");
                }

                if (Schema::hasColumn('incidents', 'map_location_name')) {
                    $query->orWhere('map_location_name', 'like', "%{$search}%");
                }

                if (
                    Schema::hasTable('barangays')
                    && (
                        Schema::hasColumn('barangays', 'barangay_name')
                        || Schema::hasColumn('barangays', 'name')
                    )
                ) {
                    $query->orWhereHas('barangay', function ($barangayQuery) use ($search) {
                        if (Schema::hasColumn('barangays', 'barangay_name')) {
                            $barangayQuery->orWhere('barangay_name', 'like', "%{$search}%");
                        }

                        if (Schema::hasColumn('barangays', 'name')) {
                            $barangayQuery->orWhere('name', 'like', "%{$search}%");
                        }
                    });
                }

                if (
                    Schema::hasTable('incident_categories')
                    && (
                        Schema::hasColumn('incident_categories', 'category_name')
                        || Schema::hasColumn('incident_categories', 'name')
                    )
                ) {
                    $query->orWhereHas('category', function ($categoryQuery) use ($search) {
                        if (Schema::hasColumn('incident_categories', 'category_name')) {
                            $categoryQuery->orWhere('category_name', 'like', "%{$search}%");
                        }

                        if (Schema::hasColumn('incident_categories', 'name')) {
                            $categoryQuery->orWhere('name', 'like', "%{$search}%");
                        }
                    });
                }
            });
        }

        $incidents = $incidentsQuery
            ->latest()
            ->get();

        $mapIncidents = $incidents->map(function (Incident $incident) {
            return [
                'id' => $incident->id,
                'code' => $incident->incident_code ?? ('INC-' . $incident->id),
                'title' => $incident->display_title ?? $incident->incident_title ?? $incident->title ?? 'Untitled Incident',
                'category' => $this->categoryName($incident),
                'barangay' => $this->barangayName($incident),
                'location' => $incident->map_location_name
                    ?: ($incident->location_address ?? $incident->address ?? 'No location name'),
                'status' => $this->incidentStatus($incident),
                'severity' => $incident->map_severity_value,
                'severity_label' => $incident->map_severity_label,
                'pin_color' => $incident->map_pin_color,
                'heat_intensity' => $incident->map_heat_intensity,
                'latitude' => (float) $incident->latitude,
                'longitude' => (float) $incident->longitude,
                'reported_at' => $this->reportedDate($incident),
                'view_url' => route('admin.incidents.show', $incident),
            ];
        })->values();

        $locationIncidentOptions = Incident::query()
            ->with(['barangay', 'category'])
            ->latest()
            ->limit(200)
            ->get()
            ->map(function (Incident $incident) {
                return [
                    'id' => $incident->id,
                    'code' => $incident->incident_code ?? ('INC-' . $incident->id),
                    'title' => $incident->display_title ?? $incident->incident_title ?? $incident->title ?? 'Untitled Incident',
                    'location' => $incident->map_location_name
                        ?: ($incident->location_address ?? $incident->address ?? ''),
                    'latitude' => $incident->latitude,
                    'longitude' => $incident->longitude,
                    'severity' => $incident->map_severity_value,
                    'update_url' => route('admin.map.incidents.location', $incident),
                ];
            })
            ->values();

        return view('map.index', [
            'mapIncidents' => $mapIncidents,
            'locationIncidentOptions' => $locationIncidentOptions,
            'recordCount' => $mapIncidents->count(),
            'selectedStatus' => $status,
            'search' => $search,
            'statuses' => $this->statuses(),
            'severityOptions' => $this->severityOptions(),
            'mapCenter' => [
                'latitude' => 11.3945,
                'longitude' => 122.6858,
                'zoom' => 12,
            ],
        ]);
    }

    public function updateLocation(Request $request, Incident $incident): RedirectResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'map_location_name' => ['nullable', 'string', 'max:255'],
            'map_severity' => ['required', Rule::in(array_keys($this->severityOptions()))],
        ]);

        $incidentUpdates = [];

        if (Schema::hasColumn('incidents', 'latitude')) {
            $incidentUpdates['latitude'] = $validated['latitude'];
        }

        if (Schema::hasColumn('incidents', 'longitude')) {
            $incidentUpdates['longitude'] = $validated['longitude'];
        }

        if (Schema::hasColumn('incidents', 'map_location_name')) {
            $incidentUpdates['map_location_name'] = $validated['map_location_name'] ?? null;
        }

        if (Schema::hasColumn('incidents', 'map_severity')) {
            $incidentUpdates['map_severity'] = $validated['map_severity'];
        }

        if (! empty($incidentUpdates)) {
            $incident->forceFill($incidentUpdates)->save();
        }

        return redirect()
            ->route('admin.map.index')
            ->with('success', 'Incident map location saved successfully.');
    }

    private function statuses(): array
    {
        return [
            'all' => 'All Status',
            'pending' => 'Pending',
            'verified' => 'Verified',
            'assigned' => 'Assigned',
            'in_progress' => 'In Progress',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
        ];
    }

    private function severityOptions(): array
    {
        return [
            'low' => 'Low',
            'moderate' => 'Moderate',
            'high' => 'High',
            'critical' => 'Critical',
        ];
    }

    private function barangayName(Incident $incident): string
    {
        return $incident->barangay?->barangay_name
            ?? $incident->barangay?->name
            ?? 'Dao, Capiz';
    }

    private function categoryName(Incident $incident): string
    {
        return $incident->category?->category_name
            ?? $incident->category?->name
            ?? 'Uncategorized';
    }

    private function incidentStatus(Incident $incident): string
    {
        if ($incident->currentStatus) {
            return $incident->currentStatus->status_name
                ?? $incident->currentStatus->name
                ?? $incident->currentStatus->status
                ?? $incident->currentStatus->slug
                ?? 'Pending';
        }

        if (isset($incident->status) && $incident->status) {
            return (string) $incident->status;
        }

        if (isset($incident->current_status) && $incident->current_status) {
            return (string) $incident->current_status;
        }

        return 'Pending';
    }

    private function reportedDate(Incident $incident): string
    {
        if (isset($incident->reported_at) && $incident->reported_at) {
            return $incident->reported_at->format('M d, Y h:i A');
        }

        if ($incident->created_at) {
            return $incident->created_at->format('M d, Y h:i A');
        }

        return '—';
    }
}