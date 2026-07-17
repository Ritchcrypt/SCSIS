<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    private const DELETED_USER_EMAIL = 'deleted-user@tabangnow.local';
    private const ONLINE_WINDOW_MINUTES = 2;

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
        $profilePhotoPath = $this->storeProfilePhoto($request);

        DB::transaction(function () use ($validated, $profilePhotoPath) {
            $user = new User();

            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->password = Hash::make($validated['password']);
            $user->role = $validated['role'];

            if (Schema::hasColumn('users', 'profile_photo_path')) {
                $user->profile_photo_path = $profilePhotoPath;
            }

            $user->contact_number = $this->normalizeContactNumber(
                $validated['contact_number'] ?? null
            );

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
            $user->refresh();

            $this->syncEmployeeProfile($user);
        });

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User account created successfully.');
    }

    public function show(Request $request, User $user): View
    {
        $this->authorizeAdmin($request);

        $user->refresh();

        $employee = Schema::hasTable('employees')
            ? Employee::query()->where('user_id', $user->id)->first()
            : null;

        return view('admin.users.show', [
            'userRecord' => $user,
            'employee' => $employee,
            'barangayName' => $this->barangayName($user->barangay_id ?? null),
        ]);
    }

    public function profilePhoto(Request $request, User $user)
    {
        $authUser = $request->user();

        if (! $authUser) {
            abort(403, 'Unauthorized access.');
        }

        if ((int) $authUser->id !== (int) $user->id && $authUser->role !== 'admin') {
            abort(403, 'Unauthorized access.');
        }

        $profilePhotoPath = $this->normalizeProfilePhotoPath($user->profile_photo_path ?? null);

        if (! $profilePhotoPath || ! Storage::disk('public')->exists($profilePhotoPath)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($profilePhotoPath));
    }

    public function edit(Request $request, User $user): View
    {
        $this->authorizeAdmin($request);

        $user->refresh();

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
        $oldProfilePhotoPath = $user->profile_photo_path ?? null;
        $newProfilePhotoPath = $this->storeProfilePhoto($request);

        DB::transaction(function () use ($validated, $user, $newProfilePhotoPath) {
            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->role = $validated['role'];

            if ($newProfilePhotoPath && Schema::hasColumn('users', 'profile_photo_path')) {
                $user->profile_photo_path = $newProfilePhotoPath;
            }

            $user->contact_number = $this->normalizeContactNumber(
                $validated['contact_number'] ?? null
            );

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
            $user->refresh();

            $this->syncEmployeeProfile($user);
        });

        if ($newProfilePhotoPath && $oldProfilePhotoPath) {
            $oldProfilePhotoPath = $this->normalizeProfilePhotoPath($oldProfilePhotoPath);

            if ($oldProfilePhotoPath && Storage::disk('public')->exists($oldProfilePhotoPath)) {
                Storage::disk('public')->delete($oldProfilePhotoPath);
            }
        }

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

        if (strtolower((string) $user->email) === self::DELETED_USER_EMAIL) {
            return redirect()->route('admin.users.index');
        }

        $deletingCurrentUser = (int) $request->user()->id === (int) $user->id;
        $userName = $user->name;
        $profilePhotoPath = $this->normalizeProfilePhotoPath(
            $user->profile_photo_path ?? null
        );

        DB::transaction(function () use ($user) {
            $deletedUserId = $this->deletedUserId();
            $employeeIds = $this->employeeIdsForUser((int) $user->id);

            $this->reassignHistoricalUserReferences(
                (int) $user->id,
                $deletedUserId
            );

            $this->removeAccountSpecificRecords(
                $user,
                $employeeIds
            );

            $user->delete();
        });

        if (
            $profilePhotoPath
            && Storage::disk('public')->exists($profilePhotoPath)
        ) {
            Storage::disk('public')->delete($profilePhotoPath);
        }

        if ($deletingCurrentUser) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/');
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', "User {$userName} was permanently deleted successfully.");
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
                'Presence',
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
                    $this->isUserOnline($user) ? 'Online' : 'Offline',
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
            ->where('email', '!=', self::DELETED_USER_EMAIL)
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
            ->when(
                $request->filled('status')
                && $request->status !== 'all'
                && Schema::hasColumn('users', 'last_seen_at'),
                function ($query) use ($request) {
                    $onlineThreshold = now()->subMinutes(self::ONLINE_WINDOW_MINUTES);

                    if ($request->query('status') === 'online') {
                        $query->whereNotNull('last_seen_at')
                            ->where('last_seen_at', '>=', $onlineThreshold);
                    }

                    if ($request->query('status') === 'offline') {
                        $query->where(function ($offlineQuery) use ($onlineThreshold) {
                            $offlineQuery->whereNull('last_seen_at')
                                ->orWhere('last_seen_at', '<', $onlineThreshold);
                        });
                    }
                }
            )
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
            'contact_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]*$/'],
            'barangay_id' => $barangayRule,
            'address' => ['nullable', 'string', 'max:1000'],
            'role' => ['required', Rule::in(array_keys($this->roles()))],
            'is_active' => ['required', 'boolean'],
            'password' => $passwordRule,
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:51200'],
        ];

        $validated = $request->validate($rules);

        if ($user && empty($validated['password'])) {
            unset($validated['password']);
        }

        return $validated;
    }

    private function normalizeContactNumber(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $contactNumber = trim((string) $value);

        return $contactNumber !== '' ? $contactNumber : null;
    }

    private function storeProfilePhoto(Request $request): ?string
    {
        if (! Schema::hasColumn('users', 'profile_photo_path')) {
            return null;
        }

        if (! $request->hasFile('profile_photo')) {
            return null;
        }

        $path = $request->file('profile_photo')->store('profile-photos', 'public');

        return $this->normalizeProfilePhotoPath($path);
    }

    private function normalizeProfilePhotoPath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#^/?storage/#', '', $path);
        $path = preg_replace('#^/?public/#', '', $path);
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        return $path;
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

    private function deletedUserId(): int
    {
        $existingId = User::query()
            ->where('email', self::DELETED_USER_EMAIL)
            ->value('id');

        if ($existingId) {
            return (int) $existingId;
        }

        $deletedUser = new User();
        $deletedUser->name = 'Deleted User';
        $deletedUser->email = self::DELETED_USER_EMAIL;
        $deletedUser->password = Hash::make(Str::random(64));
        $deletedUser->role = 'resident';

        if (Schema::hasColumn('users', 'is_active')) {
            $deletedUser->is_active = false;
        }

        if (Schema::hasColumn('users', 'contact_number')) {
            $deletedUser->contact_number = null;
        }

        if (Schema::hasColumn('users', 'barangay_id')) {
            $deletedUser->barangay_id = null;
        }

        if (Schema::hasColumn('users', 'address')) {
            $deletedUser->address = null;
        }

        if (Schema::hasColumn('users', 'profile_photo_path')) {
            $deletedUser->profile_photo_path = null;
        }

        $deletedUser->save();

        return (int) $deletedUser->id;
    }

    private function employeeIdsForUser(int $userId)
    {
        if (
            ! Schema::hasTable('employees')
            || ! Schema::hasColumn('employees', 'user_id')
        ) {
            return collect();
        }

        return DB::table('employees')
            ->where('user_id', $userId)
            ->pluck('id');
    }

    private function reassignHistoricalUserReferences(
        int $oldUserId,
        int $deletedUserId
    ): void {
        $references = [
            ['incidents', 'reporter_id'],
            ['incidents', 'resident_id'],
            ['incident_messages', 'user_id'],
            ['incident_status_histories', 'updated_by'],
            ['case_records', 'created_by'],
            ['case_records', 'creator_id'],
            ['case_status_histories', 'updated_by'],
            ['case_messages', 'user_id'],
            ['announcements', 'created_by'],
            ['tanod_tasks', 'created_by'],
            ['tanod_tasks', 'updated_by'],
            ['emergency_agency_logs', 'contacted_by'],
            ['activity_logs', 'actor_id'],
            ['activity_logs', 'target_user_id'],
            ['evidence', 'uploaded_by'],
            ['incident_evidence', 'uploaded_by'],
            ['incident_evidences', 'uploaded_by'],
            ['incident_attachments', 'uploaded_by'],
        ];

        foreach ($references as [$table, $column]) {
            if (
                ! Schema::hasTable($table)
                || ! Schema::hasColumn($table, $column)
            ) {
                continue;
            }

            DB::table($table)
                ->where($column, $oldUserId)
                ->update([$column => $deletedUserId]);
        }
    }

    private function removeAccountSpecificRecords(
        User $user,
        $employeeIds
    ): void {
        $userDeleteReferences = [
            ['notifications', 'user_id'],
            ['tanod_task_responses', 'user_id'],
            ['tanod_profiles', 'user_id'],
        ];

        foreach ($userDeleteReferences as [$table, $column]) {
            if (
                ! Schema::hasTable($table)
                || ! Schema::hasColumn($table, $column)
            ) {
                continue;
            }

            DB::table($table)
                ->where($column, $user->id)
                ->delete();
        }

        if (
            Schema::hasTable('personal_access_tokens')
            && Schema::hasColumn('personal_access_tokens', 'tokenable_id')
            && Schema::hasColumn('personal_access_tokens', 'tokenable_type')
        ) {
            DB::table('personal_access_tokens')
                ->where('tokenable_id', $user->id)
                ->where('tokenable_type', User::class)
                ->delete();
        }

        if (
            Schema::hasTable('sessions')
            && Schema::hasColumn('sessions', 'user_id')
        ) {
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->delete();
        }

        foreach (['password_reset_tokens', 'password_resets'] as $table) {
            if (
                Schema::hasTable($table)
                && Schema::hasColumn($table, 'email')
            ) {
                DB::table($table)
                    ->where('email', $user->email)
                    ->delete();
            }
        }

        if (
            Schema::hasTable('model_has_roles')
            && Schema::hasColumn('model_has_roles', 'model_id')
        ) {
            $roleQuery = DB::table('model_has_roles')
                ->where('model_id', $user->id);

            if (Schema::hasColumn('model_has_roles', 'model_type')) {
                $roleQuery->where('model_type', User::class);
            }

            $roleQuery->delete();
        }

        if ($employeeIds->isEmpty()) {
            return;
        }

        if (
            Schema::hasTable('incidents')
            && Schema::hasColumn('incidents', 'assigned_to')
        ) {
            DB::table('incidents')
                ->whereIn('assigned_to', $employeeIds)
                ->update(['assigned_to' => null]);
        }

        if (
            Schema::hasTable('tanod_task_responses')
            && Schema::hasColumn('tanod_task_responses', 'employee_id')
        ) {
            DB::table('tanod_task_responses')
                ->whereIn('employee_id', $employeeIds)
                ->delete();
        }

        if (
            Schema::hasTable('tanod_profiles')
            && Schema::hasColumn('tanod_profiles', 'employee_id')
        ) {
            DB::table('tanod_profiles')
                ->whereIn('employee_id', $employeeIds)
                ->delete();
        }

        if (
            Schema::hasTable('employees')
            && Schema::hasColumn('employees', 'id')
        ) {
            DB::table('employees')
                ->whereIn('id', $employeeIds)
                ->delete();
        }
    }

    private function summary(): array
    {
        $users = User::query()
            ->where('email', '!=', self::DELETED_USER_EMAIL);

        $onlineThreshold = now()->subMinutes(self::ONLINE_WINDOW_MINUTES);

        $online = Schema::hasColumn('users', 'last_seen_at')
            ? (clone $users)
                ->whereNotNull('last_seen_at')
                ->where('last_seen_at', '>=', $onlineThreshold)
                ->count()
            : 0;

        return [
            'total' => (clone $users)->count(),
            'online' => $online,
            'offline' => (clone $users)->count() - $online,
            'staff' => (clone $users)
                ->whereIn('role', ['admin', 'official', 'tanod'])
                ->count(),
            'residents' => (clone $users)
                ->where('role', 'resident')
                ->count(),
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
            'online' => 'Online',
            'offline' => 'Offline',
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

    private function isUserOnline(User $user): bool
    {
        if (
            ! Schema::hasColumn('users', 'last_seen_at')
            || ! $user->last_seen_at
        ) {
            return false;
        }

        try {
            return \Carbon\Carbon::parse($user->last_seen_at)
                ->greaterThanOrEqualTo(
                    now()->subMinutes(self::ONLINE_WINDOW_MINUTES)
                );
        } catch (\Throwable $e) {
            return false;
        }
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