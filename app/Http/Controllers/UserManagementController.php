<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        $perPage = (int) $request->query('per_page', 10);

if (! in_array($perPage, [10, 25, 50, 100], true)) {
    $perPage = 10;
}

$users = $this->filteredUsersQuery($request)
    ->orderByDesc('created_at')
    ->paginate($perPage)
    ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'barangays' => $this->barangays(),
            'roles' => $this->roles(),
            'statusOptions' => $this->statusOptions(),
            'dateOptions' => $this->dateOptions(),
            'summary' => $this->summary(),
            'perPage' => $perPage,
            'perPageOptions' => [10, 25, 50, 100],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('admin.users.form', [
            'userRecord' => null,
            'barangays' => $this->barangays(),
            'roles' => $this->roles(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $this->validateUser($request);

        DB::transaction(function () use ($validated) {
            $user = new User();

            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->password = Hash::make($validated['password']);
            $user->role = $validated['role'];

            if (Schema::hasColumn('users', 'contact_number')) {
                $user->contact_number = $validated['contact_number'] ?? null;
            }

            if (Schema::hasColumn('users', 'barangay_id')) {
                $user->barangay_id = $validated['barangay_id'] ?? null;
            }

            if (Schema::hasColumn('users', 'address')) {
                $user->address = $validated['address'] ?? null;
            }

            if (Schema::hasColumn('users', 'is_active')) {
                $user->is_active = (bool) ($validated['is_active'] ?? true);
            }

            $user->save();

            $this->syncEmployeeProfile($user);
        });

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User account created successfully.');
    }

    public function show(Request $request, User $user): View
    {
        $this->authorizeAdmin($request);

        $employee = Schema::hasTable('employees')
            ? Employee::query()->where('user_id', $user->id)->first()
            : null;

        return view('admin.users.show', [
            'userRecord' => $user,
            'employee' => $employee,
            'barangayName' => $this->barangayName($user->barangay_id ?? null),
        ]);
    }

    public function edit(Request $request, User $user): View
    {
        $this->authorizeAdmin($request);

        return view('admin.users.form', [
            'userRecord' => $user,
            'barangays' => $this->barangays(),
            'roles' => $this->roles(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $this->validateUser($request, $user);

        DB::transaction(function () use ($validated, $user) {
            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->role = $validated['role'];

            if (Schema::hasColumn('users', 'contact_number')) {
                $user->contact_number = $validated['contact_number'] ?? null;
            }

            if (Schema::hasColumn('users', 'barangay_id')) {
                $user->barangay_id = $validated['barangay_id'] ?? null;
            }

            if (Schema::hasColumn('users', 'address')) {
                $user->address = $validated['address'] ?? null;
            }

            if (Schema::hasColumn('users', 'is_active')) {
                $user->is_active = (bool) ($validated['is_active'] ?? true);
            }

            $user->save();

            $this->syncEmployeeProfile($user);
        });

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User account updated successfully.');
    }

    public function activate(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request);

        if (Schema::hasColumn('users', 'is_active')) {
            $user->forceFill([
                'is_active' => true,
            ])->save();
        }

        return back()->with('success', 'User account activated successfully.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request);

        if ((int) $request->user()->id === (int) $user->id) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        if (Schema::hasColumn('users', 'is_active')) {
            $user->forceFill([
                'is_active' => false,
            ])->save();
        }

        return back()->with('success', 'User account deactivated successfully.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $temporaryPassword = 'Temp-' . Str::upper(Str::random(4)) . '-' . random_int(1000, 9999);

        $user->forceFill([
            'password' => Hash::make($temporaryPassword),
        ])->save();

        return back()
            ->with('success', 'Password reset successfully.')
            ->with('temporary_password', $temporaryPassword)
            ->with('temporary_password_user', $user->name);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request);

        if ((int) $request->user()->id === (int) $user->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $blockingRecords = $this->deleteBlockingRecords($user);

        if (! empty($blockingRecords)) {
            return back()->with(
                'error',
                'This user cannot be deleted because they are connected to existing records: ' . implode(', ', $blockingRecords) . '. Deactivate instead.'
            );
        }

        DB::transaction(function () use ($user) {
            if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'user_id')) {
                DB::table('employees')->where('user_id', $user->id)->delete();
            }

            if (Schema::hasTable('tanod_profiles') && Schema::hasColumn('tanod_profiles', 'user_id')) {
                DB::table('tanod_profiles')->where('user_id', $user->id)->delete();
            }

            $user->delete();
        });

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User account deleted successfully.');
    }

    public function export(Request $request)
    {
        $this->authorizeAdmin($request);

        $users = $this->filteredUsersQuery($request)
            ->orderByDesc('created_at')
            ->get();

        $barangays = $this->barangays();

        $fileName = 'users-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($users, $barangays) {
            $output = fopen('php://output', 'w');

            fputcsv($output, [
                'Name',
                'Email',
                'Contact Number',
                'Barangay',
                'Role',
                'Status',
                'Joined Date',
            ]);

            foreach ($users as $user) {
                $barangay = $barangays->firstWhere('id', $user->barangay_id ?? null);

                fputcsv($output, [
                    $user->name,
                    $user->email,
                    $user->contact_number ?? '',
                    $barangay->barangay_name ?? $barangay->name ?? '',
                    ucfirst((string) $user->role),
                    $this->isUserActive($user) ? 'Active' : 'Inactive',
                    optional($user->created_at)->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function filteredUsersQuery(Request $request)
    {
        return User::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");

                    if (Schema::hasColumn('users', 'contact_number')) {
                        $searchQuery->orWhere('contact_number', 'like', "%{$search}%");
                    }

                    if (Schema::hasColumn('users', 'address')) {
                        $searchQuery->orWhere('address', 'like', "%{$search}%");
                    }
                });
            })
            ->when($request->filled('role') && $request->role !== 'all', function ($query) use ($request) {
                $query->where('role', $request->query('role'));
            })
            ->when($request->filled('status') && $request->status !== 'all' && Schema::hasColumn('users', 'is_active'), function ($query) use ($request) {
                $query->where('is_active', $request->query('status') === 'active');
            })
            ->when($request->filled('date') && $request->date !== 'all', function ($query) use ($request) {
                match ($request->query('date')) {
                    'today' => $query->whereDate('created_at', today()),
                    'week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
                    'month' => $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),
                    'year' => $query->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()]),
                    default => null,
                };
            });
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        $barangayRule = Schema::hasTable('barangays')
            ? ['nullable', 'exists:barangays,id']
            : ['nullable'];

        $passwordRule = $user
            ? ['nullable', 'string', 'min:8']
            : ['required', 'string', 'min:8'];

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'barangay_id' => $barangayRule,
            'address' => ['nullable', 'string', 'max:1000'],
            'role' => ['required', Rule::in(array_keys($this->roles()))],
            'is_active' => ['required', 'boolean'],
            'password' => $passwordRule,
        ];

        $validated = $request->validate($rules);

        if ($user && empty($validated['password'])) {
            unset($validated['password']);
        }

        return $validated;
    }

    private function syncEmployeeProfile(User $user): void
    {
        if (! Schema::hasTable('employees') || ! Schema::hasColumn('employees', 'user_id')) {
            return;
        }

        $employee = Employee::query()
            ->where('user_id', $user->id)
            ->first();

        if (! in_array($user->role, ['official', 'tanod'], true)) {
            if ($employee && Schema::hasColumn('employees', 'is_active')) {
                $employee->forceFill([
                    'is_active' => false,
                ])->save();
            }

            return;
        }

        if (! $employee) {
            $employee = new Employee();
        }

        $data = [
            'user_id' => $user->id,
        ];

        if (Schema::hasColumn('employees', 'barangay_id')) {
            $data['barangay_id'] = $user->barangay_id ?? null;
        }

        if (Schema::hasColumn('employees', 'employee_type')) {
            $data['employee_type'] = $user->role;
        }

        if (Schema::hasColumn('employees', 'position')) {
            $data['position'] = $user->role === 'tanod' ? 'Tanod' : 'Barangay Official';
        }

        if (Schema::hasColumn('employees', 'department')) {
            $data['department'] = $user->role === 'tanod' ? 'Barangay Tanod' : 'Barangay Office';
        }

        if (Schema::hasColumn('employees', 'is_active')) {
            $data['is_active'] = $this->isUserActive($user);
        }

        $employee->forceFill($data)->save();
    }

    private function deleteBlockingRecords(User $user): array
    {
        $blocks = [];

        $userChecks = [
            ['incidents', 'reporter_id', 'reported incidents'],
            ['incident_messages', 'user_id', 'incident messages'],
            ['incident_status_histories', 'updated_by', 'incident status history'],
            ['notifications', 'user_id', 'notifications'],
            ['tanod_task_responses', 'user_id', 'tanod task responses'],
            ['case_records', 'created_by', 'case records'],
            ['case_records', 'creator_id', 'case records'],
            ['announcements', 'created_by', 'announcements'],
            ['emergency_agency_logs', 'contacted_by', 'emergency logs'],
        ];

        foreach ($userChecks as [$table, $column, $label]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                if (DB::table($table)->where($column, $user->id)->exists()) {
                    $blocks[] = $label;
                }
            }
        }

        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'user_id')) {
            $employeeIds = DB::table('employees')
                ->where('user_id', $user->id)
                ->pluck('id');

            if ($employeeIds->isNotEmpty()) {
                if (Schema::hasTable('incidents') && Schema::hasColumn('incidents', 'assigned_to')) {
                    if (DB::table('incidents')->whereIn('assigned_to', $employeeIds)->exists()) {
                        $blocks[] = 'assigned incidents';
                    }
                }

                if (Schema::hasTable('tanod_task_responses') && Schema::hasColumn('tanod_task_responses', 'employee_id')) {
                    if (DB::table('tanod_task_responses')->whereIn('employee_id', $employeeIds)->exists()) {
                        $blocks[] = 'tanod task responses';
                    }
                }
            }
        }

        return array_values(array_unique($blocks));
    }

    private function summary(): array
    {
        return [
            'total' => User::count(),
            'active' => Schema::hasColumn('users', 'is_active')
                ? User::where('is_active', true)->count()
                : User::count(),
            'inactive' => Schema::hasColumn('users', 'is_active')
                ? User::where('is_active', false)->count()
                : 0,
            'staff' => User::whereIn('role', ['admin', 'official', 'tanod'])->count(),
            'residents' => User::where('role', 'resident')->count(),
        ];
    }

    private function barangays()
    {
        if (! Schema::hasTable('barangays')) {
            return collect();
        }

        return DB::table('barangays')
            ->orderBy('barangay_name')
            ->get();
    }

    private function barangayName(int|string|null $barangayId): string
    {
        if (! $barangayId || ! Schema::hasTable('barangays')) {
            return '—';
        }

        $barangay = DB::table('barangays')
            ->where('id', $barangayId)
            ->first();

        return $barangay->barangay_name ?? $barangay->name ?? '—';
    }

    private function roles(): array
    {
        return [
            'admin' => 'Admin',
            'official' => 'Official',
            'tanod' => 'Tanod',
            'resident' => 'Resident',
        ];
    }

    private function statusOptions(): array
    {
        return [
            'active' => 'Active',
            'inactive' => 'Inactive',
        ];
    }

    private function dateOptions(): array
    {
        return [
            'today' => 'Today',
            'week' => 'This Week',
            'month' => 'This Month',
            'year' => 'This Year',
        ];
    }

    private function isUserActive(User $user): bool
    {
        if (! Schema::hasColumn('users', 'is_active')) {
            return true;
        }

        return (bool) $user->is_active;
    }

    private function authorizeAdmin(Request $request): void
    {
        if ($request->user()?->role !== 'admin') {
            abort(403, 'Unauthorized access.');
        }
    }
}