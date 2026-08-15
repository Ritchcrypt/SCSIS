<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class BarangayMapController extends Controller
{
    public function index(
        Request $request
    ): JsonResponse {
        Gate::authorize(
            'viewBarangayMap'
        );

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

        $mapped = $incidents
            ->filter(
                function (
                    Incident $incident
                ): bool {
                    if (
                        ! is_numeric(
                            $incident->latitude
                        )
                        || ! is_numeric(
                            $incident->longitude
                        )
                    ) {
                        return false;
                    }

                    $latitude =
                        (float) $incident
                            ->latitude;

                    $longitude =
                        (float) $incident
                            ->longitude;

                    return $latitude >= -90
                        && $latitude <= 90
                        && $longitude >= -180
                        && $longitude <= 180;
                }
            )
            ->map(
                function (
                    Incident $incident
                ): array {
                    return [
                        'id' =>
                            (int) $incident->id,
                        'code' =>
                            $incident
                                ->incident_code
                            ?? (
                                'INC-'
                                . $incident->id
                            ),
                        'title' =>
                            $incident
                                ->display_title
                            ?? $incident
                                ->incident_title
                            ?? $incident->title
                            ?? 'Untitled Incident',
                        'category' =>
                            $this->categoryName(
                                $incident
                            ),
                        'barangay' =>
                            $this->barangayName(
                                $incident
                            ),
                        'location' =>
                            $incident
                                ->map_location_name
                            ?: (
                                $incident
                                    ->location_address
                                ?? $incident
                                    ->address
                                ?? 'No location name'
                            ),
                        'status' =>
                            $this->incidentStatus(
                                $incident
                            ),
                        'severity' =>
                            $incident
                                ->map_severity_value,
                        'severity_label' =>
                            $incident
                                ->map_severity_label,
                        'pin_color' =>
                            $incident
                                ->map_pin_color,
                        'heat_intensity' =>
                            (float) $incident
                                ->map_heat_intensity,
                        'latitude' =>
                            (float) $incident
                                ->latitude,
                        'longitude' =>
                            (float) $incident
                                ->longitude,
                        'reported_at' =>
                            $this->reportedDate(
                                $incident
                            ),
                    ];
                }
            )
            ->values()
            ->all();

        return response()
            ->json([
                'data' => $mapped,
                'record_count' =>
                    count($mapped),
                'map_center' => [
                    'latitude' => 11.3945,
                    'longitude' => 122.6858,
                    'zoom' => 12,
                ],
                'legend' => [
                    [
                        'value' => 'low',
                        'label' => 'Low',
                        'color' => '#22c55e',
                        'heat_intensity' => 0.35,
                    ],
                    [
                        'value' => 'moderate',
                        'label' => 'Moderate',
                        'color' => '#eab308',
                        'heat_intensity' => 0.55,
                    ],
                    [
                        'value' => 'high',
                        'label' => 'High',
                        'color' => '#f97316',
                        'heat_intensity' => 0.75,
                    ],
                    [
                        'value' => 'critical',
                        'label' => 'Critical',
                        'color' => '#ef4444',
                        'heat_intensity' => 1.0,
                    ],
                ],
            ])
            ->header(
                'Cache-Control',
                'no-store, private'
            );
    }

    private function barangayName(
        Incident $incident
    ): string {
        return $incident
            ->barangay
            ?->barangay_name
            ?? $incident
                ->barangay
                ?->name
            ?? 'Dao, Capiz';
    }

    private function categoryName(
        Incident $incident
    ): string {
        return $incident
            ->category
            ?->category_name
            ?? $incident
                ->category
                ?->name
            ?? 'Not specified';
    }

    private function incidentStatus(
        Incident $incident
    ): string {
        if ($incident->currentStatus) {
            return $incident
                ->currentStatus
                ->status_name
                ?? $incident
                    ->currentStatus
                    ->name
                ?? $incident
                    ->currentStatus
                    ->status
                ?? $incident
                    ->currentStatus
                    ->slug
                ?? 'Pending';
        }

        if (
            isset($incident->status)
            && $incident->status
        ) {
            return (string)
                $incident->status;
        }

        if (
            isset(
                $incident->current_status
            )
            && $incident->current_status
        ) {
            return (string)
                $incident->current_status;
        }

        return 'Pending';
    }

    private function reportedDate(
        Incident $incident
    ): string {
        $value =
            $incident->reported_at
            ?? $incident->created_at
            ?? null;

        if (! $value) {
            return '—';
        }

        try {
            return Carbon::parse($value)
                ->format(
                    'M d, Y h:i A'
                );
        } catch (\Throwable) {
            return '—';
        }
    }
}
