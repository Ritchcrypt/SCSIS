<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\RecordsOperationalActivity;
use App\Http\Controllers\Controller;
use App\Models\EmergencyHotline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EmergencyHotlineController extends Controller
{
    use RecordsOperationalActivity;

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EmergencyHotline::class);

        $canManage = Gate::allows(
            'create',
            EmergencyHotline::class
        );

        $hotlines = EmergencyHotline::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('agency_name')
            ->get()
            ->map(fn (EmergencyHotline $hotline): array => [
                'id' => (int) $hotline->id,
                'agency_name' => (string) $hotline->agency_name,
                'hotline_number' => (string) $hotline->hotline_number,
                'color' => (string) $hotline->color,
                'is_active' => (bool) $hotline->is_active,
                'sort_order' => (int) $hotline->sort_order,
            ])
            ->values()
            ->all();

        return response()
            ->json([
                'data' => $hotlines,
                'options' => [
                    'colors' => collect($this->colors())
                        ->map(fn (string $label, string $value): array => [
                            'value' => $value,
                            'label' => $label,
                        ])
                        ->values()
                        ->all(),
                ],
                'permissions' => [
                    'can_create' => $canManage,
                    'can_delete' => $canManage,
                ],
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', EmergencyHotline::class);

        $validated = $request->validate([
            'agency_name' => ['required', 'string', 'max:255'],
            'hotline_number' => [
                'required',
                'string',
                'max:50',
                'regex:/^[0-9+()\\-.\\s\\/]*$/',
            ],
            'color' => [
                'required',
                Rule::in(array_keys($this->colors())),
            ],
        ]);

        $createdHotline = null;

        DB::transaction(function () use (
            $validated,
            &$createdHotline
        ): void {
            $lastOrder = EmergencyHotline::query()
                ->orderByDesc('sort_order')
                ->lockForUpdate()
                ->value('sort_order');

            $createdHotline = EmergencyHotline::create([
                'agency_name' => trim($validated['agency_name']),
                'hotline_number' => trim($validated['hotline_number']),
                'color' => $validated['color'],
                'is_active' => true,
                'sort_order' => ((int) $lastOrder) + 1,
            ]);
        });

        if (
            ! $createdHotline instanceof EmergencyHotline
            || ! $createdHotline->exists
        ) {
            throw new \RuntimeException(
                'Emergency hotline creation completed without a persisted hotline record.'
            );
        }

        $this->recordOperationalActivity(
            event: 'emergency_hotline.created',
            category: 'emergency_hotline',
            description: 'An emergency hotline was created.',
            metadata: [
                'emergency_hotline_id' => (int) $createdHotline->id,
                'agency_name' => $createdHotline->agency_name,
                'color' => $createdHotline->color,
                'sort_order' => $createdHotline->sort_order,
            ],
            request: $request,
        );

        return response()
            ->json([
                'message' => 'Emergency hotline added successfully.',
                'data' => [
                    'id' => (int) $createdHotline->id,
                    'agency_name' => (string) $createdHotline->agency_name,
                    'hotline_number' => (string) $createdHotline->hotline_number,
                    'color' => (string) $createdHotline->color,
                    'is_active' => (bool) $createdHotline->is_active,
                    'sort_order' => (int) $createdHotline->sort_order,
                ],
            ], 201)
            ->header('Cache-Control', 'no-store, private');
    }

    public function destroy(
        Request $request,
        EmergencyHotline $emergencyHotline
    ): JsonResponse {
        Gate::authorize('delete', $emergencyHotline);

        $metadata = [
            'emergency_hotline_id' => (int) $emergencyHotline->id,
            'agency_name' => $emergencyHotline->agency_name,
            'color' => $emergencyHotline->color,
            'sort_order' => $emergencyHotline->sort_order,
        ];

        DB::transaction(function () use ($emergencyHotline): void {
            $locked = EmergencyHotline::query()
                ->lockForUpdate()
                ->findOrFail($emergencyHotline->getKey());

            $locked->delete();
        });

        $this->recordOperationalActivity(
            event: 'emergency_hotline.deleted',
            category: 'emergency_hotline',
            description: 'An emergency hotline was deleted.',
            metadata: $metadata,
            request: $request,
        );

        return response()
            ->json([
                'message' => 'Emergency hotline removed successfully.',
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    private function colors(): array
    {
        return [
            'blue' => 'Blue',
            'red' => 'Red',
            'orange' => 'Orange',
            'green' => 'Green',
            'purple' => 'Purple',
            'slate' => 'Gray',
        ];
    }
}
