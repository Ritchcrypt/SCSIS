<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use App\Rules\SecureUploadedFile;
use App\Services\ActivityLogger;
use App\Services\SecureUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly SecureUploadService $secureUploads
    ) {
    }

    private const DELETED_USER_EMAIL = 'deleted-user@tabangnow.local';
    private const ONLINE_WINDOW_MINUTES = 2;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

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
        Gate::authorize('create', User::class);

        return view('admin.users.form', [
            'userRecord' => null,
            'barangays' => $this->barangays(),
            'roles' => $this->roles(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $validated = $this->validateUser($request);
        $profilePhotoPath = $this->storeProfilePhoto($request);

        try {
            $createdUser = DB::transaction(function () use (
                $validated,
                $profilePhotoPath
            ): User {
                $user = new User();

                $user->name = $validated['name'];
                $user->email = strtolower(trim($validated['email']));
                $user->password = Hash::make($validated['password']);
                $user->role = strtolower(trim($validated['role']));

                if (Schema::hasColumn('users', 'profile_photo_path')) {
                    $user->profile_photo_path = $profilePhotoPath;
                }

                if (Schema::hasColumn('users', 'contact_number')) {
                    $user->contact_number = $this->normalizeContactNumber(
                        $validated['contact_number'] ?? null
                    );
                }

                if (Schema::hasColumn('users', 'barangay_id')) {
                    $user->barangay_id = $validated['barangay_id'] ?? null;
                }

                if (Schema::hasColumn('users', 'address')) {
                    $user->address = $validated['address'] ?? null;
                }

                $this->setUserActiveState(
                    $user,
                    (bool) ($validated['is_active'] ?? true)
                );

                $user->save();
                $user->refresh();

                $this->syncEmployeeProfile($user);

                return $user;
            });
        } catch (\Throwable $exception) {
            $this->deletePublicFile($profilePhotoPath);

            throw $exception;
        }

        $this->activityLogger->record(
            event: 'user_management.created',
            category: 'user_management',
            description: 'Administrator created a user account.',
            actor: $request->user(),
            target: $createdUser,
            metadata: [
                'role' => strtolower((string) $createdUser->role),
                'is_active' => $this->isUserActive($createdUser),
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User account created successfully.');
    }

    public function show(Request $request, User $user): View
    {
        Gate::authorize('view', $user);
        $this->ensureNotDeletedPlaceholder($user);

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
        Gate::authorize('viewProfilePhoto', $user);
        $this->ensureNotDeletedPlaceholder($user);

        $profilePhotoPath = $this->normalizeProfilePhotoPath(
            $user->profile_photo_path ?? null
        );

        $storedFile = $this->secureUploads->resolve(
            $profilePhotoPath,
            [
                'profile-photos',
            ],
            (array) config(
                'secure_uploads.policies.profile_photo.allowed_mime_types',
                []
            )
        );

        if (! $storedFile) {
            abort(404, 'Profile photo not found.');
        }

        $absolutePath = $storedFile['absolute_path'];
        $mimeType = $storedFile['mime_type'];
        $fileName = preg_replace(
            '/[^A-Za-z0-9._-]/',
            '_',
            basename($storedFile['path'])
        );

        $response = response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Private browser caching
        |--------------------------------------------------------------------------
        |
        | BinaryFileResponse may mark a file response as public while preparing
        | its cache headers. Apply the private directive after constructing the
        | response so authenticated profile photos are never shared-cacheable.
        |
        */

        $response->setPrivate();
        $response->setMaxAge(3600);

        return $response;
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('update', $user);
        $this->ensureNotDeletedPlaceholder($user);

        $user->refresh();

        return view('admin.users.form', [
            'userRecord' => $user,
            'barangays' => $this->barangays(),
            'roles' => $this->roles(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function update(
        Request $request,
        User $user
    ): RedirectResponse {
        Gate::authorize('update', $user);
        $this->ensureNotDeletedPlaceholder($user);

        $validated = $this->validateUser($request, $user);
        $newRole = strtolower(trim($validated['role']));
        $newActive = (bool) $validated['is_active'];

        /*
        |--------------------------------------------------------------------------
        | Fast validation before storing a replacement profile photo
        |--------------------------------------------------------------------------
        */

        $this->assertSafeAdministratorStateChange(
            $request->user(),
            $user,
            $newRole,
            $newActive,
            false
        );

        $oldProfilePhotoPath = $this->normalizeProfilePhotoPath(
            $user->profile_photo_path ?? null
        );

        $newProfilePhotoPath = $this->storeProfilePhoto($request);

        try {
            $auditContext = DB::transaction(function () use (
                $request,
                $user,
                $validated,
                $newRole,
                $newActive,
                $newProfilePhotoPath
            ): array {
                $lockedUser = User::query()
                    ->whereKey($user->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureNotDeletedPlaceholder($lockedUser);

                /*
                |------------------------------------------------------------------
                | Race-safe administrator protection
                |------------------------------------------------------------------
                */

                $this->assertSafeAdministratorStateChange(
                    $request->user(),
                    $lockedUser,
                    $newRole,
                    $newActive,
                    true
                );

                $oldRole = strtolower(trim((string) $lockedUser->role));
                $oldActive = $this->isUserActive($lockedUser);
                $oldEmail = (string) $lockedUser->email;

                $newEmail = strtolower(
                    trim(
                        (string) $validated['email']
                    )
                );

                $emailChanged = strcasecmp(
                    $oldEmail,
                    $newEmail
                ) !== 0;

                $passwordChanged = array_key_exists(
                    'password',
                    $validated
                );

                $changedFields = [];

                if (
                    (string) $lockedUser->name
                    !== (string) $validated['name']
                ) {
                    $changedFields[] = 'name';
                }

                if ($emailChanged) {
                    $changedFields[] = 'email';
                }

                if ($oldRole !== $newRole) {
                    $changedFields[] = 'role';
                }

                if ($oldActive !== $newActive) {
                    $changedFields[] = 'is_active';
                }

                if ($passwordChanged) {
                    $changedFields[] = 'password';
                }

                if (
                    Schema::hasColumn('users', 'contact_number')
                    && (string) ($lockedUser->contact_number ?? '')
                        !== (string) $this->normalizeContactNumber(
                            $validated['contact_number'] ?? null
                        )
                ) {
                    $changedFields[] = 'contact_number';
                }

                if (
                    Schema::hasColumn('users', 'barangay_id')
                    && (string) ($lockedUser->barangay_id ?? '')
                        !== (string) ($validated['barangay_id'] ?? '')
                ) {
                    $changedFields[] = 'barangay_id';
                }

                if (
                    Schema::hasColumn('users', 'address')
                    && (string) ($lockedUser->address ?? '')
                        !== (string) ($validated['address'] ?? '')
                ) {
                    $changedFields[] = 'address';
                }

                if ($newProfilePhotoPath) {
                    $changedFields[] = 'profile_photo';
                }

                $lockedUser->name = $validated['name'];
                $lockedUser->email = $newEmail;

                if (
                    $emailChanged
                    && Schema::hasColumn(
                        'users',
                        'email_verified_at'
                    )
                ) {
                    $lockedUser->email_verified_at = null;
                }

                $lockedUser->role = $newRole;

                if ($passwordChanged) {
                    $lockedUser->password = Hash::make(
                        $validated['password']
                    );
                    $lockedUser->remember_token = null;
                }

                if (
                    $newProfilePhotoPath
                    && Schema::hasColumn('users', 'profile_photo_path')
                ) {
                    $lockedUser->profile_photo_path = $newProfilePhotoPath;
                }

                if (Schema::hasColumn('users', 'contact_number')) {
                    $lockedUser->contact_number = $this->normalizeContactNumber(
                        $validated['contact_number'] ?? null
                    );
                }

                if (Schema::hasColumn('users', 'barangay_id')) {
                    $lockedUser->barangay_id =
                        $validated['barangay_id'] ?? null;
                }

                if (Schema::hasColumn('users', 'address')) {
                    $lockedUser->address =
                        $validated['address'] ?? null;
                }

                $this->setUserActiveState(
                    $lockedUser,
                    $newActive
                );

                if (
                    ! $newActive
                    && Schema::hasColumn('users', 'last_seen_at')
                ) {
                    $lockedUser->last_seen_at = null;
                }

                if (! $newActive) {
                    $lockedUser->remember_token = null;
                }

                $lockedUser->save();
                $lockedUser->refresh();

                $this->syncEmployeeProfile($lockedUser);

                $roleChanged = $oldRole !== $newRole;
                $activeStateChanged = $oldActive !== $newActive;
                if (
                    $roleChanged
                    || $activeStateChanged
                    || $passwordChanged
                ) {
                    $this->revokeUserAuthentication(
                        (int) $lockedUser->id,
                        $oldEmail
                    );
                } elseif ($emailChanged) {
                    $this->deletePasswordResetTokens($oldEmail);
                }

                return [
                    'target' => $lockedUser,
                    'changed_fields' => array_values(
                        array_unique($changedFields)
                    ),
                    'old_role' => $oldRole,
                    'new_role' => $newRole,
                    'old_active' => $oldActive,
                    'new_active' => $newActive,
                    'password_changed' => $passwordChanged,
                ];
            });
        } catch (\Throwable $exception) {
            $this->deletePublicFile($newProfilePhotoPath);

            throw $exception;
        }

        if ($newProfilePhotoPath && $oldProfilePhotoPath) {
            $this->deletePublicFile($oldProfilePhotoPath);
        }

        /** @var User $updatedUser */
        $updatedUser = $auditContext['target'];

        $this->activityLogger->record(
            event: 'user_management.updated',
            category: 'user_management',
            description: 'Administrator updated a user account.',
            actor: $request->user(),
            target: $updatedUser,
            metadata: [
                'changed_fields' => $auditContext['changed_fields'],
                'old_role' => $auditContext['old_role'],
                'new_role' => $auditContext['new_role'],
                'old_active' => $auditContext['old_active'],
                'new_active' => $auditContext['new_active'],
                'password_changed' =>
                    $auditContext['password_changed'],
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User account updated successfully.');
    }

    public function activate(
        Request $request,
        User $user
    ): RedirectResponse {
        Gate::authorize('activate', $user);
        $this->ensureNotDeletedPlaceholder($user);

        DB::transaction(function () use ($user): void {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->setUserActiveState($lockedUser, true);
            $lockedUser->save();
            $lockedUser->refresh();

            $this->syncEmployeeProfile($lockedUser);
        });

        $user->refresh();

        $this->activityLogger->record(
            event: 'user_management.activated',
            category: 'user_management',
            description: 'Administrator activated a user account.',
            actor: $request->user(),
            target: $user,
            metadata: [
                'is_active' => true,
            ],
            request: $request,
        );

        return back()->with(
            'success',
            'User account activated successfully.'
        );
    }

    public function deactivate(
        Request $request,
        User $user
    ): RedirectResponse {
        Gate::authorize('deactivate', $user);
        $this->ensureNotDeletedPlaceholder($user);

        DB::transaction(function () use (
            $request,
            $user
        ): void {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertSafeAdministratorStateChange(
                $request->user(),
                $lockedUser,
                strtolower(trim((string) $lockedUser->role)),
                false,
                true
            );

            $this->setUserActiveState($lockedUser, false);
            $lockedUser->remember_token = null;

            if (Schema::hasColumn('users', 'last_seen_at')) {
                $lockedUser->last_seen_at = null;
            }

            $lockedUser->save();
            $lockedUser->refresh();

            $this->syncEmployeeProfile($lockedUser);

            $this->revokeUserAuthentication(
                (int) $lockedUser->id,
                (string) $lockedUser->email
            );
        });

        $user->refresh();

        $this->activityLogger->record(
            event: 'user_management.deactivated',
            category: 'user_management',
            description: 'Administrator deactivated a user account.',
            actor: $request->user(),
            target: $user,
            metadata: [
                'is_active' => false,
                'sessions_revoked' => true,
            ],
            request: $request,
        );

        return back()->with(
            'success',
            'User account deactivated successfully.'
        );
    }

    public function resetPassword(
        Request $request,
        User $user
    ): RedirectResponse {
        Gate::authorize('resetPassword', $user);
        $this->ensureNotDeletedPlaceholder($user);

        $status = PasswordBroker::sendResetLink([
            'email' => $user->email,
        ]);

        if ($status === PasswordBroker::RESET_LINK_SENT) {
            $this->activityLogger->record(
                event: 'user_management.password_reset_link_sent',
                category: 'user_management',
                description: 'Administrator sent a password reset link.',
                actor: $request->user(),
                target: $user,
                metadata: [
                    'delivery_channel' => 'email',
                ],
                request: $request,
            );

            return back()->with(
                'success',
                'A secure password reset link was sent to '
                    . $user->email
                    . '.'
            );
        }

        return back()->with(
            'error',
            'The password reset link could not be sent. Check the mail configuration and try again.'
        );
    }

    public function destroy(
        Request $request,
        User $user
    ): RedirectResponse {
        Gate::authorize('delete', $user);
        $this->ensureNotDeletedPlaceholder($user);

        $userName = $user->name;

        $profilePhotoPath = $this->normalizeProfilePhotoPath(
            $user->profile_photo_path ?? null
        );

        DB::transaction(function () use (
            $request,
            $user
        ): void {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertSafeAdministratorStateChange(
                $request->user(),
                $lockedUser,
                strtolower(trim((string) $lockedUser->role)),
                false,
                true
            );

            $deletedUserId = $this->deletedUserId();

            $employeeIds = $this->employeeIdsForUser(
                (int) $lockedUser->id
            );

            $this->reassignHistoricalUserReferences(
                (int) $lockedUser->id,
                $deletedUserId
            );

            $this->removeAccountSpecificRecords(
                $lockedUser,
                $employeeIds
            );

            $deleted = $lockedUser->delete();

            if (! $deleted) {
                throw new \RuntimeException(
                    'The user account could not be deleted.'
                );
            }

            $this->activityLogger->record(
                event: 'user_management.deleted',
                category: 'user_management',
                description: 'Administrator permanently deleted a user account.',
                actor: $request->user(),
                target: $lockedUser,
                metadata: [
                    'deleted_role' => strtolower(
                        (string) $lockedUser->role
                    ),
                ],
                request: $request,
            );
        });

        $this->deletePublicFile($profilePhotoPath);

        return redirect()
            ->route('admin.users.index')
            ->with(
                'success',
                "User {$userName} was permanently deleted successfully."
            );
    }

    public function export(Request $request)
    {
        Gate::authorize('export', User::class);

        $users = $this->filteredUsersQuery($request)
            ->orderByDesc('created_at')
            ->get();

        $barangays = $this->barangays();
        $fileName = 'users-' . now()->format('Ymd-His') . '.csv';

        $this->activityLogger->record(
            event: 'user_management.exported',
            category: 'user_management',
            description: 'Administrator exported user account data.',
            actor: $request->user(),
            metadata: [
                'record_count' => $users->count(),
                'filters_applied' => $request->filled('search')
                    || $request->filled('role')
                    || $request->filled('status')
                    || $request->filled('date'),
            ],
            request: $request,
        );

        return response()->streamDownload(function () use ($users, $barangays): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                throw new \RuntimeException('Unable to open the CSV output stream.');
            }

            /* UTF-8 BOM for spreadsheet compatibility. */
            fwrite($output, "\xEF\xBB\xBF");

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
                $barangay = $barangays->firstWhere(
                    'id',
                    $user->barangay_id ?? null
                );

                fputcsv($output, [
                    $this->csvSafeValue($user->name),
                    $this->csvSafeValue($user->email),
                    $this->csvSafeValue($user->contact_number ?? ''),
                    $this->csvSafeValue(
                        $barangay->barangay_name
                            ?? $barangay->name
                            ?? ''
                    ),
                    $this->csvSafeValue(ucfirst((string) $user->role)),
                    $this->isUserOnline($user) ? 'Online' : 'Offline',
                    optional($user->created_at)->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
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

    private function validateUser(
        Request $request,
        ?User $user = null
    ): array {
        $request->merge([
            'email' => strtolower(
                trim(
                    (string) $request->input(
                        'email'
                    )
                )
            ),
        ]);

        $barangayRule = Schema::hasTable('barangays')
            ? ['nullable', 'integer', 'exists:barangays,id']
            : ['nullable'];

        $passwordRule = $user
            ? ['nullable', 'string', PasswordRule::defaults()]
            : ['required', 'string', PasswordRule::defaults()];

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'contact_number' => [
                'nullable',
                'string',
                'max:30',
                'regex:/^[0-9+()\\-\\s]*$/',
            ],
            'barangay_id' => $barangayRule,
            'address' => ['nullable', 'string', 'max:1000'],
            'role' => ['required', 'string', Rule::in(array_keys($this->roles()))],
            'is_active' => ['required', 'boolean'],
            'password' => $passwordRule,
            'profile_photo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                new SecureUploadedFile(
                    'profile_photo'
                ),
            ],
        ];

        $validated = $request->validate($rules, [
            'password.required' => 'An initial password is required.',
            'profile_photo.max' => 'The profile picture must not exceed 5 MB.',
        ]);

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

        $path = $this->secureUploads->store(
            $request->file('profile_photo'),
            'profile_photo'
        );

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
    $employee->refresh();

    /*
    |--------------------------------------------------------------------------
    | Auto-create Tanod Roster placeholder
    |--------------------------------------------------------------------------
    | When a user is created as Tanod from User Management, this creates the
    | linked tanod profile automatically. Remaining roster details can be filled
    | later from the Tanod Roster module.
    */

    if ($user->role !== 'tanod') {
        return;
    }

    if (! Schema::hasTable('tanod_profiles')) {
        return;
    }

    $profileData = [];

    if (Schema::hasColumn('tanod_profiles', 'user_id')) {
        $profileData['user_id'] = $user->id;
    }

    if (Schema::hasColumn('tanod_profiles', 'employee_id')) {
        $profileData['employee_id'] = $employee->id;
    }

    if (Schema::hasColumn('tanod_profiles', 'badge_number')) {
        $profileData['badge_number'] = 'TND-' . str_pad((string) $user->id, 4, '0', STR_PAD_LEFT);
    }

    if (Schema::hasColumn('tanod_profiles', 'duty_status')) {
        $profileData['duty_status'] = 'off_duty';
    }

    if (Schema::hasColumn('tanod_profiles', 'created_at')) {
        $profileData['created_at'] = now();
    }

    if (Schema::hasColumn('tanod_profiles', 'updated_at')) {
        $profileData['updated_at'] = now();
    }

    if (empty($profileData)) {
        return;
    }

    if (Schema::hasColumn('tanod_profiles', 'user_id')) {
        DB::table('tanod_profiles')->updateOrInsert(
            ['user_id' => $user->id],
            $profileData
        );

        return;
    }

    if (Schema::hasColumn('tanod_profiles', 'employee_id')) {
        DB::table('tanod_profiles')->updateOrInsert(
            ['employee_id' => $employee->id],
            $profileData
        );
    }
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

        $this->setUserActiveState($deletedUser, false);

        if (Schema::hasColumn('users', 'remember_token')) {
            $deletedUser->remember_token = null;
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

            /*
            |--------------------------------------------------------------------------
            | Activity logs are intentionally excluded
            |--------------------------------------------------------------------------
            |
            | actor_id and target_user_id are historical identifiers, not live
            | foreign keys. Rewriting them during account deletion would alter
            | the audit trail and destroy its evidentiary value.
            |
            */

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
    \Illuminate\Support\Collection $employeeIds
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
        if (Schema::hasColumn('users', 'is_active')) {
            return (bool) $user->is_active;
        }

        if (Schema::hasColumn('users', 'status')) {
            return (bool) $user->status;
        }

        return true;
    }

    private function ensureNotDeletedPlaceholder(User $user): void
    {
        if (strcasecmp((string) $user->email, self::DELETED_USER_EMAIL) === 0) {
            abort(404);
        }
    }

    private function setUserActiveState(User $user, bool $active): void
    {
        if (Schema::hasColumn('users', 'is_active')) {
            $user->is_active = $active;
        }

        if (Schema::hasColumn('users', 'status')) {
            $user->status = $active;
        }
    }

    private function assertSafeAdministratorStateChange(
        ?User $actor,
        User $target,
        string $newRole,
        bool $newActive,
        bool $lockActiveAdministrators
    ): void {
        if (! $actor || ! $actor->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        $targetRole = strtolower(trim((string) $target->role));
        $targetIsActive = $this->isUserActive($target);
        $isSelf = (int) $actor->id === (int) $target->id;

        if ($isSelf && ($newRole !== 'admin' || ! $newActive)) {
            throw ValidationException::withMessages([
                'role' => 'You cannot remove your own administrator access or deactivate your own account.',
            ]);
        }

        $removesActiveAdministrator = $targetRole === 'admin'
            && $targetIsActive
            && ($newRole !== 'admin' || ! $newActive);

        if (! $removesActiveAdministrator) {
            return;
        }

        $activeAdministratorQuery = User::query()
            ->where('role', 'admin')
            ->where('email', '!=', self::DELETED_USER_EMAIL);

        if (Schema::hasColumn('users', 'is_active')) {
            $activeAdministratorQuery->where('is_active', true);
        } elseif (Schema::hasColumn('users', 'status')) {
            $activeAdministratorQuery->where('status', true);
        }

        $activeAdministrators = $lockActiveAdministrators
            ? $activeAdministratorQuery->lockForUpdate()->get(['id'])
            : $activeAdministratorQuery->get(['id']);

        if ($activeAdministrators->count() <= 1) {
            throw ValidationException::withMessages([
                'role' => 'The final active administrator cannot be demoted, deactivated, or deleted.',
            ]);
        }
    }

    private function revokeUserAuthentication(int $userId, string $email): void
    {
        if (
            Schema::hasTable('sessions')
            && Schema::hasColumn('sessions', 'user_id')
        ) {
            DB::table('sessions')
                ->where('user_id', $userId)
                ->delete();
        }

        if (
            Schema::hasTable('personal_access_tokens')
            && Schema::hasColumn('personal_access_tokens', 'tokenable_id')
            && Schema::hasColumn('personal_access_tokens', 'tokenable_type')
        ) {
            DB::table('personal_access_tokens')
                ->where('tokenable_id', $userId)
                ->where('tokenable_type', User::class)
                ->delete();
        }

        $this->deletePasswordResetTokens($email);
    }

    private function deletePasswordResetTokens(string $email): void
    {
        foreach (['password_reset_tokens', 'password_resets'] as $table) {
            if (
                Schema::hasTable($table)
                && Schema::hasColumn($table, 'email')
            ) {
                DB::table($table)
                    ->where('email', $email)
                    ->delete();
            }
        }
    }

    private function deletePublicFile(?string $path): void
    {
        $path = $this->normalizeProfilePhotoPath($path);

        if ($path) {
            $this->secureUploads->delete(
                $path,
                [
                    'profile-photos',
                ]
            );
        }
    }

    private function csvSafeValue(mixed $value): string
    {
        $value = (string) ($value ?? '');

        if ($value !== '' && preg_match('/^[=+\-@]/', ltrim($value)) === 1) {
            return "'" . $value;
        }

        return $value;
    }

}
