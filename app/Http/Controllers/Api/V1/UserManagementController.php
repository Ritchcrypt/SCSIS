<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Rules\SecureUploadedFile;
use App\Services\ActivityLogger;
use App\Services\SecureUploadService;
use App\Support\SqlLikePattern;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class UserManagementController extends Controller
{
    private const DELETED_USER_EMAIL = 'deleted-user@tabangnow.local';
    private const ONLINE_WINDOW_MINUTES = 2;

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly SecureUploadService $secureUploads
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $perPage = (int) $request->query('per_page', 25);

        if (! in_array($perPage, [10, 25, 50, 100, 250], true)) {
            $perPage = 25;
        }

        $users = $this->filteredUsersQuery($request)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $barangays = $this->barangays();
        $barangayMap = $barangays->keyBy('id');

        return response()
            ->json([
                'data' => $users->getCollection()
                    ->map(
                        fn (User $user): array =>
                            $this->serializeUser(
                                $user,
                                $barangayMap,
                                false
                            )
                    )
                    ->values()
                    ->all(),

                'summary' => $this->summary(),

                'options' => [
                    'roles' => $this->optionRows(
                        $this->roles()
                    ),
                    'presence' => $this->optionRows(
                        $this->statusOptions()
                    ),
                    'dates' => $this->optionRows(
                        $this->dateOptions()
                    ),
                    'barangays' => $barangays
                        ->map(
                            fn ($barangay): array => [
                                'id' => (int) $barangay->id,
                                'label' =>
                                    (string) (
                                        $barangay->barangay_name
                                        ?? $barangay->name
                                        ?? ('Barangay #' . $barangay->id)
                                    ),
                            ]
                        )
                        ->values()
                        ->all(),
                    'per_page' => [10, 25, 50, 100, 250],
                ],

                'filters' => [
                    'search' => trim(
                        (string) $request->query(
                            'search',
                            ''
                        )
                    ),
                    'role' => strtolower(
                        trim(
                            (string) $request->query(
                                'role',
                                'all'
                            )
                        )
                    ),
                    'status' => strtolower(
                        trim(
                            (string) $request->query(
                                'status',
                                'all'
                            )
                        )
                    ),
                    'date' => strtolower(
                        trim(
                            (string) $request->query(
                                'date',
                                'all'
                            )
                        )
                    ),
                    'per_page' => $perPage,
                ],

                'permissions' => [
                    'can_create' => Gate::allows(
                        'create',
                        User::class
                    ),
                    'can_export' => Gate::allows(
                        'export',
                        User::class
                    ),
                ],

                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ],
            ])
            ->header(
                'Cache-Control',
                'no-store, private'
            );
    }

    public function show(
        Request $request,
        User $user
    ): JsonResponse {
        Gate::authorize('view', $user);
        $this->ensureNotDeletedPlaceholder($user);

        $user->refresh();

        $employee = Schema::hasTable('employees')
            && Schema::hasColumn('employees', 'user_id')
            ? Employee::query()
                ->where('user_id', $user->id)
                ->first()
            : null;

        $barangays = $this->barangays();
        $barangayMap = $barangays->keyBy('id');

        return response()
            ->json([
                'data' => $this->serializeUser(
                    $user,
                    $barangayMap,
                    true,
                    $employee
                ),

                'options' => [
                    'roles' => $this->optionRows(
                        $this->roles()
                    ),
                    'barangays' => $barangays
                        ->map(
                            fn ($barangay): array => [
                                'id' => (int) $barangay->id,
                                'label' =>
                                    (string) (
                                        $barangay->barangay_name
                                        ?? $barangay->name
                                        ?? ('Barangay #' . $barangay->id)
                                    ),
                            ]
                        )
                        ->values()
                        ->all(),
                ],

                'permissions' =>
                    $this->recordPermissions(
                        $request,
                        $user
                    ),
            ])
            ->header(
                'Cache-Control',
                'no-store, private'
            );
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', User::class);

        $validated = $this->validateCreate($request);
        $profilePhotoPath = $this->storeProfilePhoto($request);

        try {
            $createdUser = DB::transaction(
                function () use (
                    $validated,
                    $profilePhotoPath
                ): User {
                    $user = new User();
                    $user->name = trim(
                        (string) $validated['name']
                    );
                    $user->email = strtolower(
                        trim(
                            (string) $validated['email']
                        )
                    );
                    $user->password = Hash::make(
                        (string) $validated['password']
                    );
                    $user->role = strtolower(
                        trim(
                            (string) $validated['role']
                        )
                    );

                    if (
                        Schema::hasColumn(
                            'users',
                            'profile_photo_path'
                        )
                    ) {
                        $user->profile_photo_path =
                            $profilePhotoPath;
                    }

                    if (
                        Schema::hasColumn(
                            'users',
                            'contact_number'
                        )
                    ) {
                        $user->contact_number =
                            $this->normalizeContactNumber(
                                $validated[
                                    'contact_number'
                                ] ?? null
                            );
                    }

                    if (
                        Schema::hasColumn(
                            'users',
                            'barangay_id'
                        )
                    ) {
                        $user->barangay_id =
                            $validated[
                                'barangay_id'
                            ] ?? null;
                    }

                    if (
                        Schema::hasColumn(
                            'users',
                            'address'
                        )
                    ) {
                        $user->address =
                            $this->nullableText(
                                $validated[
                                    'address'
                                ] ?? null
                            );
                    }

                    /*
                     * The current hardened website Add User form
                     * creates an approved account. Activation state
                     * is not an editable form control.
                     */
                    $this->setUserActiveState(
                        $user,
                        true
                    );

                    $user->save();
                    $user->refresh();

                    $this->syncEmployeeProfile(
                        $user
                    );

                    return $user;
                }
            );
        } catch (Throwable $exception) {
            $this->deletePublicFile(
                $profilePhotoPath
            );

            throw $exception;
        }

        $this->activityLogger->record(
            event: 'user_management.created',
            category: 'user_management',
            description:
                'Administrator created a user account.',
            actor: $request->user(),
            target: $createdUser,
            metadata: [
                'role' => strtolower(
                    (string) $createdUser->role
                ),
                'is_active' =>
                    $this->isUserActive(
                        $createdUser
                    ),
            ],
            request: $request,
        );

        $barangayMap =
            $this->barangays()->keyBy('id');

        return response()
            ->json([
                'message' =>
                    'User account created successfully.',
                'data' =>
                    $this->serializeUser(
                        $createdUser,
                        $barangayMap,
                        true,
                        $this->employeeForUser(
                            $createdUser
                        )
                    ),
            ], 201)
            ->header(
                'Cache-Control',
                'no-store, private'
            );
    }

    public function update(
        Request $request,
        User $user
    ): JsonResponse {
        Gate::authorize('update', $user);
        $this->ensureNotDeletedPlaceholder($user);

        $validated = $this->validateUpdate(
            $request,
            $user
        );

        $newRole = strtolower(
            trim(
                (string) $validated['role']
            )
        );

        /*
         * Website parity: activation status is read-only in
         * Edit User. Dedicated activate/deactivate endpoints
         * own this state change.
         */
        $newActive = $this->isUserActive($user);

        $this->assertSafeAdministratorStateChange(
            $request->user(),
            $user,
            $newRole,
            $newActive,
            false
        );

        $oldProfilePhotoPath =
            $this->normalizeProfilePhotoPath(
                $user->profile_photo_path
                    ?? null
            );

        $newProfilePhotoPath =
            $this->storeProfilePhoto(
                $request
            );

        try {
            $auditContext = DB::transaction(
                function () use (
                    $request,
                    $user,
                    $validated,
                    $newRole,
                    $newActive,
                    $newProfilePhotoPath
                ): array {
                    $lockedUser =
                        User::query()
                            ->whereKey(
                                $user->id
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $this
                        ->ensureNotDeletedPlaceholder(
                            $lockedUser
                        );

                    $this
                        ->assertSafeAdministratorStateChange(
                            $request->user(),
                            $lockedUser,
                            $newRole,
                            $newActive,
                            true
                        );

                    $oldRole = strtolower(
                        trim(
                            (string) $lockedUser
                                ->role
                        )
                    );

                    $oldActive =
                        $this->isUserActive(
                            $lockedUser
                        );

                    $oldEmail =
                        (string) $lockedUser
                            ->email;

                    $newEmail = strtolower(
                        trim(
                            (string) $validated[
                                'email'
                            ]
                        )
                    );

                    $emailChanged =
                        strcasecmp(
                            $oldEmail,
                            $newEmail
                        ) !== 0;

                    $changedFields = [];

                    if (
                        (string) $lockedUser
                            ->name
                        !== trim(
                            (string) $validated[
                                'name'
                            ]
                        )
                    ) {
                        $changedFields[] =
                            'name';
                    }

                    if ($emailChanged) {
                        $changedFields[] =
                            'email';
                    }

                    if (
                        $oldRole !==
                        $newRole
                    ) {
                        $changedFields[] =
                            'role';
                    }

                    if (
                        Schema::hasColumn(
                            'users',
                            'contact_number'
                        )
                        && (string) (
                            $lockedUser
                                ->contact_number
                                ?? ''
                        )
                        !== (string)
                            $this
                                ->normalizeContactNumber(
                                    $validated[
                                        'contact_number'
                                    ] ?? null
                                )
                    ) {
                        $changedFields[] =
                            'contact_number';
                    }

                    if (
                        Schema::hasColumn(
                            'users',
                            'barangay_id'
                        )
                        && (string) (
                            $lockedUser
                                ->barangay_id
                                ?? ''
                        )
                        !== (string) (
                            $validated[
                                'barangay_id'
                            ] ?? ''
                        )
                    ) {
                        $changedFields[] =
                            'barangay_id';
                    }

                    if (
                        Schema::hasColumn(
                            'users',
                            'address'
                        )
                        && (string) (
                            $lockedUser
                                ->address
                                ?? ''
                        )
                        !== (string) (
                            $this->nullableText(
                                $validated[
                                    'address'
                                ] ?? null
                            ) ?? ''
                        )
                    ) {
                        $changedFields[] =
                            'address';
                    }

                    if (
                        $newProfilePhotoPath
                    ) {
                        $changedFields[] =
                            'profile_photo';
                    }

                    $lockedUser->name = trim(
                        (string) $validated[
                            'name'
                        ]
                    );

                    $lockedUser->email =
                        $newEmail;

                    if (
                        $emailChanged
                        && Schema::hasColumn(
                            'users',
                            'email_verified_at'
                        )
                    ) {
                        $lockedUser
                            ->email_verified_at =
                            null;
                    }

                    $lockedUser->role =
                        $newRole;

                    if (
                        $newProfilePhotoPath
                        && Schema::hasColumn(
                            'users',
                            'profile_photo_path'
                        )
                    ) {
                        $lockedUser
                            ->profile_photo_path =
                            $newProfilePhotoPath;
                    }

                    if (
                        Schema::hasColumn(
                            'users',
                            'contact_number'
                        )
                    ) {
                        $lockedUser
                            ->contact_number =
                            $this
                                ->normalizeContactNumber(
                                    $validated[
                                        'contact_number'
                                    ] ?? null
                                );
                    }

                    if (
                        Schema::hasColumn(
                            'users',
                            'barangay_id'
                        )
                    ) {
                        $lockedUser
                            ->barangay_id =
                            $validated[
                                'barangay_id'
                            ] ?? null;
                    }

                    if (
                        Schema::hasColumn(
                            'users',
                            'address'
                        )
                    ) {
                        $lockedUser->address =
                            $this->nullableText(
                                $validated[
                                    'address'
                                ] ?? null
                            );
                    }

                    $this->setUserActiveState(
                        $lockedUser,
                        $newActive
                    );

                    $lockedUser->save();
                    $lockedUser->refresh();

                    $this->syncEmployeeProfile(
                        $lockedUser
                    );

                    $roleChanged =
                        $oldRole !== $newRole;

                    if ($roleChanged) {
                        $this
                            ->revokeUserAuthentication(
                                (int) $lockedUser
                                    ->id,
                                $oldEmail
                            );
                    } elseif (
                        $emailChanged
                    ) {
                        $this
                            ->deletePasswordResetTokens(
                                $oldEmail
                            );
                    }

                    return [
                        'target' =>
                            $lockedUser,
                        'changed_fields' =>
                            array_values(
                                array_unique(
                                    $changedFields
                                )
                            ),
                        'old_role' =>
                            $oldRole,
                        'new_role' =>
                            $newRole,
                        'old_active' =>
                            $oldActive,
                        'new_active' =>
                            $newActive,
                    ];
                }
            );
        } catch (Throwable $exception) {
            $this->deletePublicFile(
                $newProfilePhotoPath
            );

            throw $exception;
        }

        if (
            $newProfilePhotoPath
            && $oldProfilePhotoPath
        ) {
            $this->deletePublicFile(
                $oldProfilePhotoPath
            );
        }

        /** @var User $updatedUser */
        $updatedUser =
            $auditContext['target'];

        $this->activityLogger->record(
            event: 'user_management.updated',
            category: 'user_management',
            description:
                'Administrator updated a user account.',
            actor: $request->user(),
            target: $updatedUser,
            metadata: [
                'changed_fields' =>
                    $auditContext[
                        'changed_fields'
                    ],
                'old_role' =>
                    $auditContext['old_role'],
                'new_role' =>
                    $auditContext['new_role'],
                'old_active' =>
                    $auditContext['old_active'],
                'new_active' =>
                    $auditContext['new_active'],
                'password_changed' => false,
            ],
            request: $request,
        );

        $barangayMap =
            $this->barangays()->keyBy('id');

        return response()
            ->json([
                'message' =>
                    'User account updated successfully.',
                'data' =>
                    $this->serializeUser(
                        $updatedUser,
                        $barangayMap,
                        true,
                        $this->employeeForUser(
                            $updatedUser
                        )
                    ),
                'permissions' =>
                    $this->recordPermissions(
                        $request,
                        $updatedUser
                    ),
            ])
            ->header(
                'Cache-Control',
                'no-store, private'
            );
    }

    public function activate(
        Request $request,
        User $user
    ): JsonResponse {
        Gate::authorize('activate', $user);
        $this->ensureNotDeletedPlaceholder($user);

        DB::transaction(
            function () use ($user): void {
                $lockedUser =
                    User::query()
                        ->whereKey($user->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                $this
                    ->ensureNotDeletedPlaceholder(
                        $lockedUser
                    );

                $this->setUserActiveState(
                    $lockedUser,
                    true
                );

                $lockedUser->save();
                $lockedUser->refresh();

                $this->syncEmployeeProfile(
                    $lockedUser
                );
            }
        );

        $user->refresh();

        $this->activityLogger->record(
            event: 'user_management.activated',
            category: 'user_management',
            description:
                'Administrator activated a user account.',
            actor: $request->user(),
            target: $user,
            metadata: [
                'is_active' => true,
            ],
            request: $request,
        );

        return response()
            ->json([
                'message' =>
                    'User account activated successfully.',
                'data' =>
                    $this->serializeUser(
                        $user,
                        $this->barangays()
                            ->keyBy('id'),
                        true,
                        $this->employeeForUser(
                            $user
                        )
                    ),
                'permissions' =>
                    $this->recordPermissions(
                        $request,
                        $user
                    ),
            ])
            ->header(
                'Cache-Control',
                'no-store, private'
            );
    }

    public function deactivate(
        Request $request,
        User $user
    ): JsonResponse {
        Gate::authorize('deactivate', $user);
        $this->ensureNotDeletedPlaceholder($user);

        DB::transaction(
            function () use (
                $request,
                $user
            ): void {
                $lockedUser =
                    User::query()
                        ->whereKey($user->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                $this
                    ->ensureNotDeletedPlaceholder(
                        $lockedUser
                    );

                $this
                    ->assertSafeAdministratorStateChange(
                        $request->user(),
                        $lockedUser,
                        strtolower(
                            trim(
                                (string)
                                    $lockedUser->role
                            )
                        ),
                        false,
                        true
                    );

                $this->setUserActiveState(
                    $lockedUser,
                    false
                );

                if (
                    Schema::hasColumn(
                        'users',
                        'remember_token'
                    )
                ) {
                    $lockedUser
                        ->remember_token = null;
                }

                if (
                    Schema::hasColumn(
                        'users',
                        'last_seen_at'
                    )
                ) {
                    $lockedUser
                        ->last_seen_at = null;
                }

                $lockedUser->save();
                $lockedUser->refresh();

                $this->syncEmployeeProfile(
                    $lockedUser
                );

                $this
                    ->revokeUserAuthentication(
                        (int) $lockedUser->id,
                        (string) $lockedUser
                            ->email
                    );
            }
        );

        $user->refresh();

        $this->activityLogger->record(
            event:
                'user_management.deactivated',
            category: 'user_management',
            description:
                'Administrator deactivated a user account.',
            actor: $request->user(),
            target: $user,
            metadata: [
                'is_active' => false,
                'sessions_revoked' => true,
            ],
            request: $request,
        );

        return response()
            ->json([
                'message' =>
                    'User account deactivated successfully.',
                'data' =>
                    $this->serializeUser(
                        $user,
                        $this->barangays()
                            ->keyBy('id'),
                        true,
                        $this->employeeForUser(
                            $user
                        )
                    ),
                'permissions' =>
                    $this->recordPermissions(
                        $request,
                        $user
                    ),
            ])
            ->header(
                'Cache-Control',
                'no-store, private'
            );
    }

    public function resetPassword(
        Request $request,
        User $user
    ): JsonResponse {
        Gate::authorize(
            'resetPassword',
            $user
        );

        $this->ensureNotDeletedPlaceholder(
            $user
        );

        $status =
            PasswordBroker::sendResetLink([
                'email' => $user->email,
            ]);

        if (
            $status ===
            PasswordBroker::RESET_LINK_SENT
        ) {
            $this->activityLogger->record(
                event:
                    'user_management.password_reset_link_sent',
                category: 'user_management',
                description:
                    'Administrator sent a password reset link.',
                actor: $request->user(),
                target: $user,
                metadata: [
                    'delivery_channel' =>
                        'email',
                ],
                request: $request,
            );

            return response()
                ->json([
                    'message' =>
                        'A secure password reset link was sent to '
                        . $user->email
                        . '.',
                ])
                ->header(
                    'Cache-Control',
                    'no-store, private'
                );
        }

        return response()
            ->json([
                'message' =>
                    'The password reset link could not be sent. Check the mail configuration and try again.',
            ], 422)
            ->header(
                'Cache-Control',
                'no-store, private'
            );
    }

    public function destroy(
        Request $request,
        User $user
    ): JsonResponse {
        Gate::authorize('delete', $user);
        $this->ensureNotDeletedPlaceholder($user);

        $userName = (string) $user->name;

        $profilePhotoPath =
            $this->normalizeProfilePhotoPath(
                $user->profile_photo_path
                    ?? null
            );

        DB::transaction(
            function () use (
                $request,
                $user
            ): void {
                $lockedUser =
                    User::query()
                        ->whereKey($user->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                $this
                    ->ensureNotDeletedPlaceholder(
                        $lockedUser
                    );

                $this
                    ->assertSafeAdministratorStateChange(
                        $request->user(),
                        $lockedUser,
                        strtolower(
                            trim(
                                (string)
                                    $lockedUser->role
                            )
                        ),
                        false,
                        true
                    );

                $deletedUserId =
                    $this->deletedUserId();

                $employeeIds =
                    $this->employeeIdsForUser(
                        (int) $lockedUser->id
                    );

                $this
                    ->reassignHistoricalUserReferences(
                        (int) $lockedUser->id,
                        $deletedUserId
                    );

                $this
                    ->removeAccountSpecificRecords(
                        $lockedUser,
                        $employeeIds
                    );

                $deleted =
                    $lockedUser->delete();

                if (! $deleted) {
                    throw new \RuntimeException(
                        'The user account could not be deleted.'
                    );
                }

                $this->activityLogger->record(
                    event:
                        'user_management.deleted',
                    category:
                        'user_management',
                    description:
                        'Administrator permanently deleted a user account.',
                    actor:
                        $request->user(),
                    target:
                        $lockedUser,
                    metadata: [
                        'deleted_role' =>
                            strtolower(
                                (string)
                                    $lockedUser
                                        ->role
                            ),
                    ],
                    request: $request,
                );
            }
        );

        $this->deletePublicFile(
            $profilePhotoPath
        );

        return response()
            ->json([
                'message' =>
                    "User {$userName} was permanently deleted successfully.",
            ])
            ->header(
                'Cache-Control',
                'no-store, private'
            );
    }

    public function profilePhoto(
        Request $request,
        User $user
    ): BinaryFileResponse {
        Gate::authorize(
            'viewProfilePhoto',
            $user
        );

        $this->ensureNotDeletedPlaceholder(
            $user
        );

        $profilePhotoPath =
            $this->normalizeProfilePhotoPath(
                $user->profile_photo_path
                    ?? null
            );

        if (! $profilePhotoPath) {
            abort(
                404,
                'Profile photo not found.'
            );
        }

        $storedFile =
            $this->secureUploads->resolve(
                $profilePhotoPath,
                ['profile-photos'],
                (array) config(
                    'secure_uploads.policies.profile_photo.allowed_mime_types',
                    []
                )
            );

        if (! $storedFile) {
            abort(
                404,
                'Profile photo not found.'
            );
        }

        $fileName = preg_replace(
            '/[^A-Za-z0-9._-]/',
            '_',
            basename(
                $storedFile['path']
            )
        );

        $response = response()->file(
            $storedFile['absolute_path'],
            [
                'Content-Type' =>
                    $storedFile['mime_type'],
                'Content-Disposition' =>
                    'inline; filename="'
                    . $fileName
                    . '"',
                'X-Content-Type-Options' =>
                    'nosniff',
            ]
        );

        $response->setPrivate();
        $response->setMaxAge(3600);

        return $response;
    }

    public function export(
        Request $request
    ): StreamedResponse {
        Gate::authorize(
            'export',
            User::class
        );

        $usersQuery =
            $this->filteredUsersQuery(
                $request
            )
                ->orderByDesc(
                    'created_at'
                )
                ->orderByDesc('id');

        $recordCount =
            (clone $usersQuery)->count();

        $barangays =
            $this->barangays();

        $fileName =
            'users-'
            . now()->format('Ymd-His')
            . '.csv';

        $this->activityLogger->record(
            event: 'user_management.exported',
            category: 'user_management',
            description:
                'Administrator exported user account data.',
            actor: $request->user(),
            metadata: [
                'record_count' =>
                    $recordCount,
                'filters_applied' =>
                    $request->filled('search')
                    || $request->filled('role')
                    || $request->filled('status')
                    || $request->filled('date'),
            ],
            request: $request,
        );

        $response =
            response()->streamDownload(
                function () use (
                    $usersQuery,
                    $barangays
                ): void {
                    $output = fopen(
                        'php://output',
                        'w'
                    );

                    if ($output === false) {
                        throw new
                            \RuntimeException(
                                'Unable to open the CSV output stream.'
                            );
                    }

                    try {
                        fwrite(
                            $output,
                            "\xEF\xBB\xBF"
                        );

                        fputcsv(
                            $output,
                            [
                                'Name',
                                'Email',
                                'Contact Number',
                                'Barangay',
                                'Role',
                                'Presence',
                                'Joined Date',
                            ]
                        );

                        foreach (
                            $usersQuery->cursor()
                            as $user
                        ) {
                            $barangay =
                                $barangays
                                    ->firstWhere(
                                        'id',
                                        $user
                                            ->barangay_id
                                            ?? null
                                    );

                            fputcsv(
                                $output,
                                [
                                    $this
                                        ->csvSafeValue(
                                            $user->name
                                        ),
                                    $this
                                        ->csvSafeValue(
                                            $user->email
                                        ),
                                    $this
                                        ->csvSafeValue(
                                            $user
                                                ->contact_number
                                                ?? ''
                                        ),
                                    $this
                                        ->csvSafeValue(
                                            $barangay
                                                ->barangay_name
                                                ?? $barangay
                                                    ->name
                                                ?? ''
                                        ),
                                    $this
                                        ->csvSafeValue(
                                            ucfirst(
                                                (string)
                                                    $user
                                                        ->role
                                            )
                                        ),
                                    $this
                                        ->isUserOnline(
                                            $user
                                        )
                                        ? 'Online'
                                        : 'Offline',
                                    optional(
                                        $user
                                            ->created_at
                                    )->format(
                                        'Y-m-d H:i:s'
                                    ),
                                ]
                            );
                        }
                    } finally {
                        fclose($output);
                    }
                },
                $fileName,
                [
                    'Content-Type' =>
                        'text/csv; charset=UTF-8',
                    'X-Content-Type-Options' =>
                        'nosniff',
                ]
            );

        $response->setPrivate();
        $response->headers
            ->addCacheControlDirective(
                'no-store',
                true
            );

        return $response;
    }

    private function validateCreate(
        Request $request
    ): array {
        $this->normalizeEmailInput(
            $request
        );

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(
                    'users',
                    'email'
                ),
            ],
            'contact_number' => [
                'nullable',
                'string',
                'max:30',
                'regex:/^[0-9+()\\-\\s]*$/',
            ],
            'barangay_id' =>
                $this->barangayRule(),
            'address' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'role' => [
                'required',
                'string',
                Rule::in(
                    array_keys(
                        $this->roles()
                    )
                ),
            ],
            'password' => [
                'required',
                'string',
                PasswordRule::defaults(),
            ],
            'profile_photo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                new SecureUploadedFile(
                    'profile_photo'
                ),
            ],
        ], [
            'password.required' =>
                'An initial password is required.',
            'profile_photo.max' =>
                'The profile picture must not exceed 5 MB.',
        ]);
    }

    private function validateUpdate(
        Request $request,
        User $user
    ): array {
        $this->normalizeEmailInput(
            $request
        );

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(
                    'users',
                    'email'
                )->ignore($user->id),
            ],
            'contact_number' => [
                'nullable',
                'string',
                'max:30',
                'regex:/^[0-9+()\\-\\s]*$/',
            ],
            'barangay_id' =>
                $this->barangayRule(),
            'address' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'role' => [
                'required',
                'string',
                Rule::in(
                    array_keys(
                        $this->roles()
                    )
                ),
            ],
            'profile_photo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                new SecureUploadedFile(
                    'profile_photo'
                ),
            ],
        ], [
            'profile_photo.max' =>
                'The profile picture must not exceed 5 MB.',
        ]);
    }

    private function filteredUsersQuery(
        Request $request
    ) {
        $normalizedSearch =
            SqlLikePattern::normalize(
                $request->query(
                    'search'
                )
            );

        $searchPattern =
            SqlLikePattern::contains(
                $normalizedSearch
            );

        $role = strtolower(
            trim(
                (string) $request->query(
                    'role',
                    ''
                )
            )
        );

        if (
            ! array_key_exists(
                $role,
                $this->roles()
            )
        ) {
            $role = null;
        }

        $status = strtolower(
            trim(
                (string) $request->query(
                    'status',
                    ''
                )
            )
        );

        if (
            ! array_key_exists(
                $status,
                $this->statusOptions()
            )
        ) {
            $status = null;
        }

        $date = strtolower(
            trim(
                (string) $request->query(
                    'date',
                    ''
                )
            )
        );

        if (
            ! array_key_exists(
                $date,
                $this->dateOptions()
            )
        ) {
            $date = null;
        }

        return User::query()
            ->where(
                'email',
                '!=',
                self::DELETED_USER_EMAIL
            )
            ->when(
                $searchPattern !== null,
                function ($query) use (
                    $searchPattern
                ): void {
                    $query->where(
                        function (
                            $searchQuery
                        ) use (
                            $searchPattern
                        ): void {
                            SqlLikePattern
                                ::whereContains(
                                    $searchQuery,
                                    'name',
                                    $searchPattern
                                );

                            SqlLikePattern
                                ::orWhereContains(
                                    $searchQuery,
                                    'email',
                                    $searchPattern
                                );

                            if (
                                Schema::hasColumn(
                                    'users',
                                    'contact_number'
                                )
                            ) {
                                SqlLikePattern
                                    ::orWhereContains(
                                        $searchQuery,
                                        'contact_number',
                                        $searchPattern
                                    );
                            }

                            if (
                                Schema::hasColumn(
                                    'users',
                                    'address'
                                )
                            ) {
                                SqlLikePattern
                                    ::orWhereContains(
                                        $searchQuery,
                                        'address',
                                        $searchPattern
                                    );
                            }
                        }
                    );
                }
            )
            ->when(
                $role !== null,
                fn ($query) =>
                    $query->where(
                        'role',
                        $role
                    )
            )
            ->when(
                $status !== null
                && Schema::hasColumn(
                    'users',
                    'last_seen_at'
                ),
                function ($query) use (
                    $status
                ): void {
                    $onlineThreshold =
                        now()->subMinutes(
                            self::ONLINE_WINDOW_MINUTES
                        );

                    if (
                        $status ===
                        'online'
                    ) {
                        $query
                            ->whereNotNull(
                                'last_seen_at'
                            )
                            ->where(
                                'last_seen_at',
                                '>=',
                                $onlineThreshold
                            );
                    }

                    if (
                        $status ===
                        'offline'
                    ) {
                        $query->where(
                            function (
                                $offlineQuery
                            ) use (
                                $onlineThreshold
                            ): void {
                                $offlineQuery
                                    ->whereNull(
                                        'last_seen_at'
                                    )
                                    ->orWhere(
                                        'last_seen_at',
                                        '<',
                                        $onlineThreshold
                                    );
                            }
                        );
                    }
                }
            )
            ->when(
                $date !== null,
                function ($query) use (
                    $date
                ): void {
                    match ($date) {
                        'today' =>
                            $query->whereDate(
                                'created_at',
                                today()
                            ),
                        'week' =>
                            $query->whereBetween(
                                'created_at',
                                [
                                    now()
                                        ->startOfWeek(),
                                    now()
                                        ->endOfWeek(),
                                ]
                            ),
                        'month' =>
                            $query->whereBetween(
                                'created_at',
                                [
                                    now()
                                        ->startOfMonth(),
                                    now()
                                        ->endOfMonth(),
                                ]
                            ),
                        'year' =>
                            $query->whereBetween(
                                'created_at',
                                [
                                    now()
                                        ->startOfYear(),
                                    now()
                                        ->endOfYear(),
                                ]
                            ),
                    };
                }
            );
    }

    private function serializeUser(
        User $user,
        Collection $barangayMap,
        bool $includeDetail,
        ?Employee $employee = null
    ): array {
        $barangay = $barangayMap->get(
            $user->barangay_id ?? null
        );

        $result = [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'contact_number' =>
                Schema::hasColumn(
                    'users',
                    'contact_number'
                )
                ? $user->contact_number
                : null,
            'address' =>
                Schema::hasColumn(
                    'users',
                    'address'
                )
                ? $user->address
                : null,
            'barangay_id' =>
                Schema::hasColumn(
                    'users',
                    'barangay_id'
                )
                ? $user->barangay_id
                : null,
            'barangay_name' =>
                $barangay
                    ? (
                        $barangay
                            ->barangay_name
                        ?? $barangay->name
                        ?? '—'
                    )
                    : '—',
            'role' => strtolower(
                (string) $user->role
            ),
            'role_label' => ucfirst(
                (string) $user->role
            ),
            'is_active' =>
                $this->isUserActive($user),
            'account_status' =>
                $this->isUserActive($user)
                ? 'Active'
                : 'Inactive',
            'online' =>
                $this->isUserOnline($user),
            'presence' =>
                $this->isUserOnline($user)
                ? 'Online'
                : 'Offline',
            'last_seen_at' =>
                Schema::hasColumn(
                    'users',
                    'last_seen_at'
                )
                && $user->last_seen_at
                ? $this->isoDateTime(
                    $user->last_seen_at
                )
                : null,
            'created_at' =>
                $this->isoDateTime(
                    $user->created_at
                ),
            'joined_date' =>
                $user->created_at
                ? $user->created_at
                    ->format('M d, Y')
                : '—',
            'has_profile_photo' =>
                Schema::hasColumn(
                    'users',
                    'profile_photo_path'
                )
                && filled(
                    $user->profile_photo_path
                        ?? null
                ),
            'profile_photo_url' =>
                Schema::hasColumn(
                    'users',
                    'profile_photo_path'
                )
                && filled(
                    $user->profile_photo_path
                        ?? null
                )
                ? route(
                    'api.v1.users.profile-photo',
                    $user
                )
                : null,
        ];

        if ($includeDetail) {
            $result['employee'] =
                $employee
                ? $this->serializeEmployee(
                    $employee
                )
                : null;
        }

        return $result;
    }

    private function serializeEmployee(
        Employee $employee
    ): array {
        return [
            'id' => (int) $employee->id,
            'employee_type' =>
                $employee->employee_type
                    ?? null,
            'position' =>
                $employee->position
                    ?? null,
            'department' =>
                $employee->department
                    ?? null,
            'is_active' =>
                Schema::hasColumn(
                    'employees',
                    'is_active'
                )
                ? (bool)
                    $employee->is_active
                : null,
            'barangay_id' =>
                Schema::hasColumn(
                    'employees',
                    'barangay_id'
                )
                ? $employee->barangay_id
                : null,
        ];
    }

    private function recordPermissions(
        Request $request,
        User $target
    ): array {
        return [
            'can_update' =>
                Gate::allows(
                    'update',
                    $target
                ),
            'can_activate' =>
                Gate::allows(
                    'activate',
                    $target
                ),
            'can_deactivate' =>
                Gate::allows(
                    'deactivate',
                    $target
                ),
            'can_reset_password' =>
                Gate::allows(
                    'resetPassword',
                    $target
                ),
            'can_delete' =>
                Gate::allows(
                    'delete',
                    $target
                ),
            'is_self' =>
                (int) $request->user()->id
                === (int) $target->id,
        ];
    }

    private function summary(): array
    {
        $users = User::query()
            ->where(
                'email',
                '!=',
                self::DELETED_USER_EMAIL
            );

        $onlineThreshold =
            now()->subMinutes(
                self::ONLINE_WINDOW_MINUTES
            );

        $online =
            Schema::hasColumn(
                'users',
                'last_seen_at'
            )
            ? (clone $users)
                ->whereNotNull(
                    'last_seen_at'
                )
                ->where(
                    'last_seen_at',
                    '>=',
                    $onlineThreshold
                )
                ->count()
            : 0;

        $total =
            (clone $users)->count();

        return [
            'total' => $total,
            'online' => $online,
            'offline' =>
                max(0, $total - $online),
            'staff' => (clone $users)
                ->whereIn(
                    'role',
                    [
                        'admin',
                        'official',
                        'tanod',
                    ]
                )
                ->count(),
            'residents' => (clone $users)
                ->where(
                    'role',
                    'resident'
                )
                ->count(),
        ];
    }

    private function syncEmployeeProfile(
        User $user
    ): void {
        if (
            ! Schema::hasTable('employees')
            || ! Schema::hasColumn(
                'employees',
                'user_id'
            )
        ) {
            return;
        }

        $employee = Employee::query()
            ->where(
                'user_id',
                $user->id
            )
            ->first();

        if (
            ! in_array(
                $user->role,
                ['official', 'tanod'],
                true
            )
        ) {
            if (
                $employee
                && Schema::hasColumn(
                    'employees',
                    'is_active'
                )
            ) {
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

        if (
            Schema::hasColumn(
                'employees',
                'barangay_id'
            )
        ) {
            $data['barangay_id'] =
                $user->barangay_id
                    ?? null;
        }

        if (
            Schema::hasColumn(
                'employees',
                'employee_type'
            )
        ) {
            $data['employee_type'] =
                $user->role;
        }

        if (
            Schema::hasColumn(
                'employees',
                'position'
            )
        ) {
            $data['position'] =
                $user->role === 'tanod'
                ? 'Tanod'
                : 'Barangay Official';
        }

        if (
            Schema::hasColumn(
                'employees',
                'department'
            )
        ) {
            $data['department'] =
                $user->role === 'tanod'
                ? 'Barangay Tanod'
                : 'Barangay Office';
        }

        if (
            Schema::hasColumn(
                'employees',
                'is_active'
            )
        ) {
            $data['is_active'] =
                $this->isUserActive(
                    $user
                );
        }

        $employee->forceFill($data)
            ->save();

        $employee->refresh();

        if ($user->role !== 'tanod') {
            return;
        }

        if (
            ! Schema::hasTable(
                'tanod_profiles'
            )
        ) {
            return;
        }

        $profileData = [];

        if (
            Schema::hasColumn(
                'tanod_profiles',
                'user_id'
            )
        ) {
            $profileData['user_id'] =
                $user->id;
        }

        if (
            Schema::hasColumn(
                'tanod_profiles',
                'employee_id'
            )
        ) {
            $profileData['employee_id'] =
                $employee->id;
        }

        if (
            Schema::hasColumn(
                'tanod_profiles',
                'badge_number'
            )
        ) {
            $profileData['badge_number'] =
                'TND-'
                . str_pad(
                    (string) $user->id,
                    4,
                    '0',
                    STR_PAD_LEFT
                );
        }

        if (
            Schema::hasColumn(
                'tanod_profiles',
                'duty_status'
            )
        ) {
            $profileData['duty_status'] =
                'off_duty';
        }

        if (
            Schema::hasColumn(
                'tanod_profiles',
                'created_at'
            )
        ) {
            $profileData['created_at'] =
                now();
        }

        if (
            Schema::hasColumn(
                'tanod_profiles',
                'updated_at'
            )
        ) {
            $profileData['updated_at'] =
                now();
        }

        if (empty($profileData)) {
            return;
        }

        if (
            Schema::hasColumn(
                'tanod_profiles',
                'user_id'
            )
        ) {
            DB::table('tanod_profiles')
                ->updateOrInsert(
                    [
                        'user_id' =>
                            $user->id,
                    ],
                    $profileData
                );

            return;
        }

        if (
            Schema::hasColumn(
                'tanod_profiles',
                'employee_id'
            )
        ) {
            DB::table('tanod_profiles')
                ->updateOrInsert(
                    [
                        'employee_id' =>
                            $employee->id,
                    ],
                    $profileData
                );
        }
    }

    private function assertSafeAdministratorStateChange(
        ?User $actor,
        User $target,
        string $newRole,
        bool $newActive,
        bool $lockActiveAdministrators
    ): void {
        if (
            ! $actor
            || ! $actor->isAdmin()
        ) {
            abort(
                403,
                'Unauthorized access.'
            );
        }

        $targetRole = strtolower(
            trim(
                (string) $target->role
            )
        );

        $targetIsActive =
            $this->isUserActive(
                $target
            );

        $isSelf =
            (int) $actor->id
            === (int) $target->id;

        if (
            $isSelf
            && (
                $newRole !== 'admin'
                || ! $newActive
            )
        ) {
            throw ValidationException
                ::withMessages([
                    'role' =>
                        'You cannot remove your own administrator access or deactivate your own account.',
                ]);
        }

        $removesActiveAdministrator =
            $targetRole === 'admin'
            && $targetIsActive
            && (
                $newRole !== 'admin'
                || ! $newActive
            );

        if (
            ! $removesActiveAdministrator
        ) {
            return;
        }

        $query = User::query()
            ->where(
                'role',
                'admin'
            )
            ->where(
                'email',
                '!=',
                self::DELETED_USER_EMAIL
            );

        if (
            Schema::hasColumn(
                'users',
                'is_active'
            )
        ) {
            $query->where(
                'is_active',
                true
            );
        } elseif (
            Schema::hasColumn(
                'users',
                'status'
            )
        ) {
            $query->where(
                'status',
                true
            );
        }

        $activeAdministrators =
            $lockActiveAdministrators
            ? $query
                ->lockForUpdate()
                ->get(['id'])
            : $query->get(['id']);

        if (
            $activeAdministrators
                ->count() <= 1
        ) {
            throw ValidationException
                ::withMessages([
                    'role' =>
                        'The final active administrator cannot be demoted, deactivated, or deleted.',
                ]);
        }
    }

    private function deletedUserId(): int
    {
        $existingId =
            User::query()
                ->where(
                    'email',
                    self::DELETED_USER_EMAIL
                )
                ->value('id');

        if ($existingId) {
            return (int) $existingId;
        }

        $deletedUser = new User();
        $deletedUser->name =
            'Deleted User';
        $deletedUser->email =
            self::DELETED_USER_EMAIL;
        $deletedUser->password =
            Hash::make(
                Str::random(64)
            );
        $deletedUser->role =
            'resident';

        $this->setUserActiveState(
            $deletedUser,
            false
        );

        if (
            Schema::hasColumn(
                'users',
                'remember_token'
            )
        ) {
            $deletedUser
                ->remember_token = null;
        }

        foreach (
            [
                'contact_number',
                'barangay_id',
                'address',
                'profile_photo_path',
            ] as $column
        ) {
            if (
                Schema::hasColumn(
                    'users',
                    $column
                )
            ) {
                $deletedUser->{$column} =
                    null;
            }
        }

        $deletedUser->save();

        return (int) $deletedUser->id;
    }

    private function reassignHistoricalUserReferences(
        int $oldUserId,
        int $deletedUserId
    ): void {
        $references = [
            ['incidents', 'reporter_id'],
            ['incidents', 'resident_id'],
            ['incident_messages', 'user_id'],
            [
                'incident_status_histories',
                'updated_by',
            ],
            ['case_records', 'created_by'],
            ['case_records', 'creator_id'],
            [
                'case_status_histories',
                'updated_by',
            ],
            ['case_messages', 'user_id'],
            ['announcements', 'created_by'],
            ['tanod_tasks', 'created_by'],
            ['tanod_tasks', 'updated_by'],
            [
                'emergency_agency_logs',
                'contacted_by',
            ],
            ['evidence', 'uploaded_by'],
            [
                'incident_evidence',
                'uploaded_by',
            ],
            [
                'incident_evidences',
                'uploaded_by',
            ],
            [
                'incident_attachments',
                'uploaded_by',
            ],
        ];

        foreach (
            $references
            as [$table, $column]
        ) {
            if (
                ! Schema::hasTable($table)
                || ! Schema::hasColumn(
                    $table,
                    $column
                )
            ) {
                continue;
            }

            DB::table($table)
                ->where(
                    $column,
                    $oldUserId
                )
                ->update([
                    $column =>
                        $deletedUserId,
                ]);
        }
    }

    private function removeAccountSpecificRecords(
        User $user,
        Collection $employeeIds
    ): void {
        $userDeleteReferences = [
            ['notifications', 'user_id'],
            [
                'tanod_task_responses',
                'user_id',
            ],
            ['tanod_profiles', 'user_id'],
        ];

        foreach (
            $userDeleteReferences
            as [$table, $column]
        ) {
            if (
                ! Schema::hasTable($table)
                || ! Schema::hasColumn(
                    $table,
                    $column
                )
            ) {
                continue;
            }

            DB::table($table)
                ->where(
                    $column,
                    $user->id
                )
                ->delete();
        }

        if (
            Schema::hasTable(
                'personal_access_tokens'
            )
            && Schema::hasColumn(
                'personal_access_tokens',
                'tokenable_id'
            )
            && Schema::hasColumn(
                'personal_access_tokens',
                'tokenable_type'
            )
        ) {
            DB::table(
                'personal_access_tokens'
            )
                ->where(
                    'tokenable_id',
                    $user->id
                )
                ->where(
                    'tokenable_type',
                    User::class
                )
                ->delete();
        }

        if (
            Schema::hasTable('sessions')
            && Schema::hasColumn(
                'sessions',
                'user_id'
            )
        ) {
            DB::table('sessions')
                ->where(
                    'user_id',
                    $user->id
                )
                ->delete();
        }

        $this->deletePasswordResetTokens(
            (string) $user->email
        );

        if (
            Schema::hasTable(
                'model_has_roles'
            )
            && Schema::hasColumn(
                'model_has_roles',
                'model_id'
            )
        ) {
            $roleQuery =
                DB::table(
                    'model_has_roles'
                )
                    ->where(
                        'model_id',
                        $user->id
                    );

            if (
                Schema::hasColumn(
                    'model_has_roles',
                    'model_type'
                )
            ) {
                $roleQuery->where(
                    'model_type',
                    User::class
                );
            }

            $roleQuery->delete();
        }

        if ($employeeIds->isEmpty()) {
            return;
        }

        if (
            Schema::hasTable('incidents')
            && Schema::hasColumn(
                'incidents',
                'assigned_to'
            )
        ) {
            DB::table('incidents')
                ->whereIn(
                    'assigned_to',
                    $employeeIds
                )
                ->update([
                    'assigned_to' => null,
                ]);
        }

        if (
            Schema::hasTable(
                'tanod_task_responses'
            )
            && Schema::hasColumn(
                'tanod_task_responses',
                'employee_id'
            )
        ) {
            DB::table(
                'tanod_task_responses'
            )
                ->whereIn(
                    'employee_id',
                    $employeeIds
                )
                ->delete();
        }

        if (
            Schema::hasTable(
                'tanod_profiles'
            )
            && Schema::hasColumn(
                'tanod_profiles',
                'employee_id'
            )
        ) {
            DB::table('tanod_profiles')
                ->whereIn(
                    'employee_id',
                    $employeeIds
                )
                ->delete();
        }

        if (
            Schema::hasTable('employees')
            && Schema::hasColumn(
                'employees',
                'id'
            )
        ) {
            DB::table('employees')
                ->whereIn(
                    'id',
                    $employeeIds
                )
                ->delete();
        }
    }

    private function revokeUserAuthentication(
        int $userId,
        string $email
    ): void {
        if (
            Schema::hasTable('sessions')
            && Schema::hasColumn(
                'sessions',
                'user_id'
            )
        ) {
            DB::table('sessions')
                ->where(
                    'user_id',
                    $userId
                )
                ->delete();
        }

        if (
            Schema::hasTable(
                'personal_access_tokens'
            )
            && Schema::hasColumn(
                'personal_access_tokens',
                'tokenable_id'
            )
            && Schema::hasColumn(
                'personal_access_tokens',
                'tokenable_type'
            )
        ) {
            DB::table(
                'personal_access_tokens'
            )
                ->where(
                    'tokenable_id',
                    $userId
                )
                ->where(
                    'tokenable_type',
                    User::class
                )
                ->delete();
        }

        $this->deletePasswordResetTokens(
            $email
        );
    }

    private function deletePasswordResetTokens(
        string $email
    ): void {
        foreach (
            [
                'password_reset_tokens',
                'password_resets',
            ] as $table
        ) {
            if (
                Schema::hasTable($table)
                && Schema::hasColumn(
                    $table,
                    'email'
                )
            ) {
                DB::table($table)
                    ->where(
                        'email',
                        $email
                    )
                    ->delete();
            }
        }
    }

    private function storeProfilePhoto(
        Request $request
    ): ?string {
        if (
            ! Schema::hasColumn(
                'users',
                'profile_photo_path'
            )
            || ! $request->hasFile(
                'profile_photo'
            )
        ) {
            return null;
        }

        $path = $this->secureUploads
            ->store(
                $request->file(
                    'profile_photo'
                ),
                'profile_photo'
            );

        return $this
            ->normalizeProfilePhotoPath(
                $path
            );
    }

    private function deletePublicFile(
        ?string $path
    ): void {
        $path =
            $this->normalizeProfilePhotoPath(
                $path
            );

        if ($path) {
            $this->secureUploads->delete(
                $path,
                ['profile-photos']
            );
        }
    }

    private function normalizeProfilePhotoPath(
        ?string $path
    ): ?string {
        if (! $path) {
            return null;
        }

        $path = str_replace(
            '\\',
            '/',
            trim($path)
        );

        $path = preg_replace(
            '#^/?storage/#',
            '',
            $path
        );

        $path = preg_replace(
            '#^/?public/#',
            '',
            $path
        );

        $path = ltrim(
            (string) $path,
            '/'
        );

        if (
            $path === ''
            || str_contains(
                $path,
                '..'
            )
        ) {
            return null;
        }

        return $path;
    }

    private function employeeIdsForUser(
        int $userId
    ): Collection {
        if (
            ! Schema::hasTable('employees')
            || ! Schema::hasColumn(
                'employees',
                'user_id'
            )
        ) {
            return collect();
        }

        return DB::table('employees')
            ->where(
                'user_id',
                $userId
            )
            ->pluck('id');
    }

    private function employeeForUser(
        User $user
    ): ?Employee {
        if (
            ! Schema::hasTable('employees')
            || ! Schema::hasColumn(
                'employees',
                'user_id'
            )
        ) {
            return null;
        }

        return Employee::query()
            ->where(
                'user_id',
                $user->id
            )
            ->first();
    }

    private function barangays(): Collection
    {
        if (
            ! Schema::hasTable(
                'barangays'
            )
        ) {
            return collect();
        }

        $query = DB::table('barangays');

        if (
            Schema::hasColumn(
                'barangays',
                'barangay_name'
            )
        ) {
            $query->orderBy(
                'barangay_name'
            );
        } elseif (
            Schema::hasColumn(
                'barangays',
                'name'
            )
        ) {
            $query->orderBy('name');
        } else {
            $query->orderBy('id');
        }

        return $query->get();
    }

    private function barangayRule(): array
    {
        return Schema::hasTable(
            'barangays'
        )
            ? [
                'nullable',
                'integer',
                'exists:barangays,id',
            ]
            : ['nullable'];
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

    private function optionRows(
        array $options
    ): array {
        $rows = [];

        foreach (
            $options
            as $value => $label
        ) {
            $rows[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $rows;
    }

    private function isUserOnline(
        User $user
    ): bool {
        if (
            ! Schema::hasColumn(
                'users',
                'last_seen_at'
            )
            || ! $user->last_seen_at
        ) {
            return false;
        }

        try {
            return \Carbon\Carbon::parse(
                $user->last_seen_at
            )->greaterThanOrEqualTo(
                now()->subMinutes(
                    self::ONLINE_WINDOW_MINUTES
                )
            );
        } catch (Throwable) {
            return false;
        }
    }

    private function isUserActive(
        User $user
    ): bool {
        if (
            Schema::hasColumn(
                'users',
                'is_active'
            )
        ) {
            return (bool) $user
                ->is_active;
        }

        if (
            Schema::hasColumn(
                'users',
                'status'
            )
        ) {
            return (bool) $user->status;
        }

        return true;
    }

    private function setUserActiveState(
        User $user,
        bool $active
    ): void {
        if (
            Schema::hasColumn(
                'users',
                'is_active'
            )
        ) {
            $user->is_active = $active;
        }

        if (
            Schema::hasColumn(
                'users',
                'status'
            )
        ) {
            $user->status = $active;
        }
    }

    private function normalizeEmailInput(
        Request $request
    ): void {
        $request->merge([
            'email' => strtolower(
                trim(
                    (string) $request->input(
                        'email'
                    )
                )
            ),
        ]);
    }

    private function normalizeContactNumber(
        mixed $value
    ): ?string {
        return $this->nullableText(
            $value
        );
    }

    private function nullableText(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $text = trim(
            (string) $value
        );

        return $text !== ''
            ? $text
            : null;
    }

    private function ensureNotDeletedPlaceholder(
        User $user
    ): void {
        if (
            strcasecmp(
                (string) $user->email,
                self::DELETED_USER_EMAIL
            ) === 0
        ) {
            abort(404);
        }
    }

    private function csvSafeValue(
        mixed $value
    ): string {
        $value = (string) (
            $value ?? ''
        );

        if (
            $value !== ''
            && preg_match(
                '/^[=+\-@]/',
                ltrim($value)
            ) === 1
        ) {
            return "'" . $value;
        }

        return $value;
    }

    private function isoDateTime(
        mixed $value
    ): ?string {
        if (! $value) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse(
                $value
            )->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }
}
