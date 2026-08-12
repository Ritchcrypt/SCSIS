<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EmergencyHotline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EmergencyHotlineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize(
            'viewAny',
            EmergencyHotline::class
        );

        $hotlines = EmergencyHotline::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('agency_name')
            ->get()
            ->map(
                fn (EmergencyHotline $hotline): array => [
                    'id' => (int) $hotline->id,

                    'agency_name' =>
                        (string) $hotline->agency_name,

                    'hotline_number' =>
                        (string) $hotline->hotline_number,

                    'color' =>
                        (string) $hotline->color,

                    'sort_order' =>
                        (int) $hotline->sort_order,
                ]
            )
            ->values();

        return response()->json([
            'data' => $hotlines,
        ]);
    }
}