<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RecordsOperationalActivity;
use App\Models\EmergencyHotline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmergencyModeController extends Controller
{
    use RecordsOperationalActivity;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', EmergencyHotline::class);

        $hotlines = EmergencyHotline::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('agency_name')
            ->get();

        return view('emergency-mode.index', [
            'hotlines' => $hotlines,
            'canManageHotlines' => Gate::allows(
                'create',
                EmergencyHotline::class
            ),
            'colors' => $this->colors(),
        ]);
    }

    public function store(Request $request): RedirectResponse
{
    Gate::authorize('create', EmergencyHotline::class);

    $validated = $request->validate([
        'agency_name' => [
            'required',
            'string',
            'max:255',
        ],
        'hotline_number' => [
            'required',
            'string',
            'max:50',
            'regex:/^[0-9+()\-.\s\/]*$/',
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

    return back()->with(
        'success',
        'Emergency hotline added successfully.'
    );
}

    public function destroy(
        EmergencyHotline $emergencyHotline
    ): RedirectResponse {
        Gate::authorize('delete', $emergencyHotline);

        $auditMetadata = [
            'emergency_hotline_id' => (int) $emergencyHotline->id,
            'agency_name' => $emergencyHotline->agency_name,
            'color' => $emergencyHotline->color,
            'sort_order' => $emergencyHotline->sort_order,
        ];

        DB::transaction(function () use ($emergencyHotline): void {
            $lockedHotline = EmergencyHotline::query()
                ->lockForUpdate()
                ->findOrFail($emergencyHotline->getKey());

            $lockedHotline->delete();
        });

        $this->recordOperationalActivity(
            event: 'emergency_hotline.deleted',
            category: 'emergency_hotline',
            description: 'An emergency hotline was deleted.',
            metadata: $auditMetadata,
        );

        return back()->with(
            'success',
            'Emergency hotline removed successfully.'
        );
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
