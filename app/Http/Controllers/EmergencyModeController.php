<?php

namespace App\Http\Controllers;

use App\Models\EmergencyHotline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmergencyModeController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $hotlines = EmergencyHotline::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('agency_name')
            ->get();

        return view('emergency-mode.index', [
            'hotlines' => $hotlines,
            'canManageHotlines' => $this->canManageHotlines($user),
            'colors' => $this->colors(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->canManageHotlines($request->user()), 403);

        $validated = $request->validate([
            'agency_name' => ['required', 'string', 'max:255'],
            'hotline_number' => ['required', 'string', 'max:50'],
            'color' => ['required', Rule::in(array_keys($this->colors()))],
        ]);

        $nextOrder = ((int) EmergencyHotline::query()->max('sort_order')) + 1;

        EmergencyHotline::create([
            'agency_name' => $validated['agency_name'],
            'hotline_number' => $validated['hotline_number'],
            'color' => $validated['color'],
            'is_active' => true,
            'sort_order' => $nextOrder,
        ]);

        return back()->with('success', 'Emergency hotline added successfully.');
    }

    public function destroy(Request $request, EmergencyHotline $emergencyHotline): RedirectResponse
    {
        abort_unless($this->canManageHotlines($request->user()), 403);

        $emergencyHotline->delete();

        return back()->with('success', 'Emergency hotline removed successfully.');
    }

    private function canManageHotlines($user): bool
    {
        return $user && in_array(strtolower((string) $user->role), [
            'admin',
            'official',
            'dao',
        ], true);
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