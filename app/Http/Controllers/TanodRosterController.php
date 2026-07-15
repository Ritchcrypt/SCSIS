<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\Employee;
use App\Models\TanodProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TanodRosterController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $tanods = TanodProfile::query()
            ->with(['user', 'employee'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('contact_number', 'like', "%{$search}%")
                        ->orWhere('purok_assignment', 'like', "%{$search}%")
                        ->orWhere('shift', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        $totalTanods = TanodProfile::count();

        $onDutyCount = TanodProfile::where('status', 'on_duty')->count();

        return view('tanods.index', [
            'tanods' => $tanods,
            'totalTanods' => $totalTanods,
            'onDutyCount' => $onDutyCount,
            'shifts' => $this->shifts(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'purok_assignment' => ['nullable', 'string', 'max:100'],
            'date_appointed' => ['nullable', 'date'],
            'shift' => ['required', Rule::in(array_keys($this->shifts()))],
            'status' => ['required', Rule::in(array_keys($this->statuses()))],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        DB::transaction(function () use ($validated) {
            $email = $validated['email']
                ?: $this->generateFallbackEmail($validated['full_name']);

            $user = User::create([
                'name' => $validated['full_name'],
                'email' => $email,
                'password' => Hash::make('password'),
                'role' => 'tanod',
            ]);

            $employee = Employee::create([
                'user_id' => $user->id,
                'barangay_id' => $this->defaultBarangayId(),
                'employee_type' => 'tanod',
                'position' => 'Barangay Tanod',
                'department' => 'Public Safety',
                'is_active' => $validated['status'] !== 'off_duty',
            ]);

            TanodProfile::create([
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'contact_number' => $validated['contact_number'] ?? null,
                'purok_assignment' => $validated['purok_assignment'] ?? null,
                'date_appointed' => $validated['date_appointed'] ?? null,
                'shift' => $validated['shift'],
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        return redirect()
            ->route('admin.tanods.index')
            ->with('success', 'Tanod member added successfully. Default password: password');
    }

    public function update(Request $request, TanodProfile $tanod): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($tanod->user_id),
            ],
            'purok_assignment' => ['nullable', 'string', 'max:100'],
            'date_appointed' => ['nullable', 'date'],
            'shift' => ['required', Rule::in(array_keys($this->shifts()))],
            'status' => ['required', Rule::in(array_keys($this->statuses()))],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        DB::transaction(function () use ($tanod, $validated) {
            $user = $tanod->user;

            if ($user) {
                $user->update([
                    'name' => $validated['full_name'],
                    'email' => $validated['email'] ?: $user->email,
                ]);
            }

            $employee = $tanod->employee;

            if ($employee) {
                $employee->update([
                    'is_active' => $validated['status'] !== 'off_duty',
                ]);
            }

            $tanod->update([
                'contact_number' => $validated['contact_number'] ?? null,
                'purok_assignment' => $validated['purok_assignment'] ?? null,
                'date_appointed' => $validated['date_appointed'] ?? null,
                'shift' => $validated['shift'],
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        return redirect()
            ->route('admin.tanods.index')
            ->with('success', 'Tanod member updated successfully.');
    }

    public function destroy(TanodProfile $tanod): RedirectResponse
    {
        DB::transaction(function () use ($tanod) {
            $user = $tanod->user;
            $employee = $tanod->employee;

            $tanod->delete();

            if ($employee) {
                $employee->delete();
            }

            if ($user) {
                $user->delete();
            }
        });

        return redirect()
            ->route('admin.tanods.index')
            ->with('success', 'Tanod member deleted successfully.');
    }

    private function shifts(): array
    {
        return [
            'day' => 'Day',
            'afternoon' => 'Afternoon',
            'night' => 'Night',
            'floating' => 'Floating',
        ];
    }

    private function statuses(): array
    {
        return [
            'active' => 'Active',
            'on_duty' => 'On Duty',
            'off_duty' => 'Off Duty',
        ];
    }

    private function generateFallbackEmail(string $fullName): string
    {
        $cleanName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $fullName));
        $cleanName = $cleanName ?: 'tanod';

        return $cleanName . time() . '@tanod.local';
    }

    private function defaultBarangayId(): ?int
    {
        return Barangay::query()->value('id') ?? 1;
    }
}