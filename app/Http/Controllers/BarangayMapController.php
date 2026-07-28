<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class BarangayMapController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewBarangayMap');

        $viewRouteName = $request->user()->isOfficial()
            ? 'official.incidents.show'
            : 'admin.incidents.show';

        $incidents = Incident::query()
            ->with([
                'barangay',
                'category',
                'currentStatus',
            ])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->latest()
            ->get();

        $mapIncidents = $incidents
            ->filter(function (Incident $incident): bool {
                if (
                    ! is_numeric($incident->latitude)
                    || ! is_numeric($incident->longitude)
                ) {
                    return false;
                }

                $latitude = (float) $incident->latitude;
                $longitude = (float) $incident->longitude;

                return $latitude >= -90
                    && $latitude <= 90
                    && $longitude >= -180
                    && $longitude <= 180;
            })
            ->map(function (Incident $incident) use (
                $viewRouteName
            ): array {
                return [
                    'id' => $incident->id,
                    'code' => $incident->incident_code
                        ?? ('INC-' . $incident->id),
                    'title' => $incident->display_title
                        ?? $incident->incident_title
                        ?? $incident->title
                        ?? 'Untitled Incident',
                    'category' => $this->categoryName(
                        $incident
                    ),
                    'barangay' => $this->barangayName(
                        $incident
                    ),
                    'location' => $incident->map_location_name
                        ?: (
                            $incident->location_address
                            ?? $incident->address
                            ?? 'No location name'
                        ),
                    'status' => $this->incidentStatus(
                        $incident
                    ),
                    'severity' => $incident->map_severity_value,
                    'severity_label' => $incident->map_severity_label,
                    'pin_color' => $incident->map_pin_color,
                    'heat_intensity' => $incident->map_heat_intensity,
                    'latitude' => (float) $incident->latitude,
                    'longitude' => (float) $incident->longitude,
                    'reported_at' => $this->reportedDate(
                        $incident
                    ),
                    'view_url' => Route::has($viewRouteName)
                        ? route($viewRouteName, $incident)
                        : route('dashboard'),
                ];
            })
            ->values();

        return view('map.index', [
            'mapIncidents' => $mapIncidents,
            'recordCount' => $mapIncidents->count(),
            'mapCenter' => [
                'latitude' => 11.3945,
                'longitude' => 122.6858,
                'zoom' => 12,
            ],
        ]);
    }

    private function barangayName(
        Incident $incident
    ): string {
        return $incident->barangay?->barangay_name
            ?? $incident->barangay?->name
            ?? 'Dao, Capiz';
    }

    private function categoryName(
        Incident $incident
    ): string {
        return $incident->category?->category_name
            ?? $incident->category?->name
            ?? 'Not specified';
    }

    private function incidentStatus(
        Incident $incident
    ): string {
        if ($incident->currentStatus) {
            return $incident->currentStatus->status_name
                ?? $incident->currentStatus->name
                ?? $incident->currentStatus->status
                ?? $incident->currentStatus->slug
                ?? 'Pending';
        }

        if (
            isset($incident->status)
            && $incident->status
        ) {
            return (string) $incident->status;
        }

        if (
            isset($incident->current_status)
            && $incident->current_status
        ) {
            return (string) $incident->current_status;
        }

        return 'Pending';
    }

    private function reportedDate(
        Incident $incident
    ): string {
        $value = $incident->reported_at
            ?? $incident->created_at
            ?? null;

        if (! $value) {
            return '—';
        }

        try {
            return Carbon::parse($value)
                ->format('M d, Y h:i A');
        } catch (\Throwable $exception) {
            return '—';
        }
    }
}
