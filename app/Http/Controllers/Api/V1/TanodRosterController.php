<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\RecordsOperationalActivity;
use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\Employee;
use App\Models\TanodProfile;
use App\Models\User;
use App\Support\SqlLikePattern;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TanodRosterController extends Controller
{
    use RecordsOperationalActivity;

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', TanodProfile::class);

        $search = SqlLikePattern::normalize(
            $request->query('search')
        );

        $searchPattern = SqlLikePattern::contains(
            $search
        );

        $tanodTable = (new TanodProfile())->getTable();

        $tanods = TanodProfile::query()
            ->select("{$tanodTable}.*")
            ->selectSub(
                $this->acceptedResponsesSubquery(
                    $tanodTable
                ),
                'accepted_responses_count'
            )
            ->with([
                'user',
                'employee',
            ])
            ->when(
                $searchPattern !== null,
                function ($query) use ($searchPattern): void {
                    $query->where(
                        function ($searchQuery) use ($searchPattern): void {
                            SqlLikePattern::whereContains(
                                $searchQuery,
                                'contact_number',
                                $searchPattern
                            );

                            foreach ([
                                'purok_assignment',
                                'shift',
                                'status',
                            ] as $column) {
                                SqlLikePattern::orWhereContains(
                                    $searchQuery,
                                    $column,
                                    $searchPattern
                                );
                            }

                            $searchQuery->orWhereHas(
                                'user',
                                function ($userQuery) use ($searchPattern): void {
                                    SqlLikePattern::whereContains(
                                        $userQuery,
                                        'name',
                                        $searchPattern
                                    );

                                    SqlLikePattern::orWhereContains(
                                        $userQuery,
                                        'email',
                                        $searchPattern
                                    );
                                }
                            );
                        }
                    );
                }
            )
            ->orderByDesc('accepted_responses_count')
            ->orderBy("{$tanodTable}.id")
            ->paginate(10)
            ->withQueryString();

        $ranksById = $this->ranksByProfileId(
            $tanodTable
        );

        $tanods->getCollection()->transform(
            function (TanodProfile $tanod) use ($ranksById): TanodProfile {
                $tanod->setAttribute(
                    'response_rank',
                    $ranksById[(int) $tanod->id] ?? null
                );

                return $tanod;
            }
        );

        $user = $request->user();

        return response()
            ->json([
                'data' => $tanods->getCollection()
                    ->map(
                        fn (TanodProfile $tanod): array =>
                            $this->serializeTanod($tanod)
                    )
                    ->values()
                    ->all(),

                'summary' => [
                    'total_tanods' => TanodProfile::query()
                        ->count(),
                    'on_duty_count' => TanodProfile::query()
                        ->where('status', 'on_duty')
                        ->count(),
                ],

                'options' => [
                    'shifts' => $this->optionRows(
                        $this->shifts()
                    ),
                    'statuses' => $this->optionRows(
                        $this->statuses()
                    ),
                ],

                'permissions' => [
                    'can_view' => Gate::forUser($user)
                        ->allows('viewAny', TanodProfile::class),
                    'can_create' => Gate::forUser($user)
                        ->allows('create', TanodProfile::class),
                    'can_update' => true,
                    'can_delete' => true,
                ],

                'filters' => [
                    'search' => $search,
                ],

                'pagination' => [
                    'current_page' => $tanods->currentPage(),
                    'last_page' => $tanods->lastPage(),
                    'per_page' => $tanods->perPage(),
                    'total' => $tanods->total(),
                    'from' => $tanods->firstItem(),
                    'to' => $tanods->lastItem(),
                ],
            ])
            ->header(
                'Cache-Control',
                'no-store, private'
            );
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', TanodProfile::class);

        $this->normalizeEmailInput($request);

        $validated = $request->validate(
            $this->validationRules()
        );

        $providedEmail = $this->nullableText(
            $validated['email'] ?? null
        );

        $barangayId = $this->defaultBarangayId();

        if (! $barangayId) {
            throw ValidationException::withMessages([
                'barangay' =>
                    'Add at least one barangay before creating a tanod account.',
            ]);
        }

        $createdUser = null;
        $createdTanodProfile = null;

        DB::transaction(function () use (
            $validated,
            $providedEmail,
            $barangayId,
            &$createdUser,
            &$createdTanodProfile
        ): void {
            $email = $providedEmail
                ? strtolower($providedEmail)
                : $this->generateFallbackEmail(
                    $validated['full_name']
                );

            $userData = [
                'name' => trim($validated['full_name']),
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
                'role' => 'tanod',
            ];

            if (Schema::hasColumn('users', 'contact_number')) {
                $userData['contact_number'] = $this->nullableText(
                    $validated['contact_number'] ?? null
                );
            }

            if (Schema::hasColumn('users', 'is_active')) {
                $userData['is_active'] = true;
            }

            if (Schema::hasColumn('users', 'status')) {
                $userData['status'] = true;
            }

            if (Schema::hasColumn('users', 'barangay_id')) {
                $userData['barangay_id'] = $barangayId;
            }

            $createdUser = User::create($userData);

            $employee = Employee::create([
                'user_id' => $createdUser->id,
                'barangay_id' => $barangayId,
                'employee_type' => 'tanod',
                'position' => 'Barangay Tanod',
                'department' => 'Public Safety',
                'is_active' =>
                    $validated['status'] !== 'off_duty',
            ]);

            $createdTanodProfile = TanodProfile::create([
                'user_id' => $createdUser->id,
                'employee_id' => $employee->id,
                'contact_number' => $this->nullableText(
                    $validated['contact_number'] ?? null
                ),
                'purok_assignment' => $this->nullableText(
                    $validated['purok_assignment'] ?? null
                ),
                'date_appointed' =>
                    $validated['date_appointed'] ?? null,
                'shift' => $validated['shift'],
                'status' => $validated['status'],
                'notes' => $this->nullableText(
                    $validated['notes'] ?? null
                ),
            ]);
        });

        if (! $createdUser || ! $createdTanodProfile) {
            throw new \RuntimeException(
                'Tanod account creation completed without the required linked records.'
            );
        }

        $message = 'Tanod member added successfully.';

        if ($providedEmail) {
            try {
                $resetStatus = Password::sendResetLink([
                    'email' => $createdUser->email,
                ]);
            } catch (\Throwable $exception) {
                $resetStatus = null;

                Log::warning(
                    'Tanod password setup email could not be sent.',
                    [
                        'user_id' => $createdUser->id,
                        'error' => $exception->getMessage(),
                    ]
                );
            }

            $message .= $resetStatus === Password::RESET_LINK_SENT
                ? ' A secure password setup link was sent to the tanod email.'
                : ' The account was created, but the password setup email could not be sent. Use User Management to resend it.';
        } else {
            $message .=
                ' Add a valid email in User Management before sending a password setup link.';
        }

        $createdTanodProfile->load([
            'user',
            'employee',
        ]);

        $createdTanodProfile->setAttribute(
            'accepted_responses_count',
            0
        );

        $this->recordOperationalActivity(
            event: 'tanod_roster.created',
            category: 'tanod_roster',
            description: 'A tanod roster member was created.',
            metadata: [
                'tanod_profile_id' =>
                    (int) $createdTanodProfile->id,
                'user_id' => (int) $createdUser->id,
                'employee_id' =>
                    $createdTanodProfile->employee_id,
                'shift' => $createdTanodProfile->shift,
                'status' => $createdTanodProfile->status,
                'password_setup_email_requested' =>
                    $providedEmail !== null,
            ],
            request: $request,
        );

        return response()
            ->json([
                'message' => $message,
                'data' => $this->serializeTanod(
                    $createdTanodProfile
                ),
            ], 201)
            ->header(
                'Cache-Control',
                'no-store, private'
            );
    }

    public function update(
        Request $request,
        TanodProfile $tanod
    ): JsonResponse {
        Gate::authorize('update', $tanod);

        $this->normalizeEmailInput($request);

        $validated = $request->validate(
            $this->validationRules($tanod)
        );

        $previousStatus = $tanod->status;
        $previousShift = $tanod->shift;

        DB::transaction(function () use (
            $tanod,
            $validated
        ): void {
            $lockedTanod = TanodProfile::query()
                ->with([
                    'user',
                    'employee',
                ])
                ->lockForUpdate()
                ->findOrFail($tanod->getKey());

            $user = $lockedTanod->user;
            $employee = $lockedTanod->employee;

            if ($user) {
                $newEmail = $this->nullableText(
                    $validated['email'] ?? null
                );

                $userData = [
                    'name' => trim(
                        $validated['full_name']
                    ),
                ];

                if (Schema::hasColumn('users', 'contact_number')) {
                    $userData['contact_number'] =
                        $this->nullableText(
                            $validated['contact_number'] ?? null
                        );
                }

                if ($newEmail) {
                    $newEmail = strtolower($newEmail);

                    if (
                        strcasecmp(
                            $newEmail,
                            (string) $user->email
                        ) !== 0
                    ) {
                        $userData['email'] = $newEmail;

                        if (
                            Schema::hasColumn(
                                'users',
                                'email_verified_at'
                            )
                        ) {
                            $userData['email_verified_at'] = null;
                        }
                    }
                }

                $user->forceFill($userData)->save();
            }

            if ($employee) {
                $employee->update([
                    'is_active' =>
                        $validated['status'] !== 'off_duty',
                ]);
            }

            $lockedTanod->update([
                'contact_number' => $this->nullableText(
                    $validated['contact_number'] ?? null
                ),
                'purok_assignment' => $this->nullableText(
                    $validated['purok_assignment'] ?? null
                ),
                'date_appointed' =>
                    $validated['date_appointed'] ?? null,
                'shift' => $validated['shift'],
                'status' => $validated['status'],
                'notes' => $this->nullableText(
                    $validated['notes'] ?? null
                ),
            ]);
        });

        $tanod->refresh();
        $tanod->load([
            'user',
            'employee',
        ]);

        $tanodTable = (new TanodProfile())->getTable();

        $acceptedCount = (int) (
            DB::table('tanod_task_responses')
                ->where(
                    'employee_id',
                    $tanod->employee_id
                )
                ->whereRaw(
                    "LOWER(COALESCE(response_status, '')) = ?",
                    ['accepted']
                )
                ->count()
        );

        $ranks = $this->ranksByProfileId(
            $tanodTable
        );

        $tanod->setAttribute(
            'accepted_responses_count',
            $acceptedCount
        );

        $tanod->setAttribute(
            'response_rank',
            $ranks[(int) $tanod->id] ?? null
        );

        $this->recordOperationalActivity(
            event: 'tanod_roster.updated',
            category: 'tanod_roster',
            description: 'A tanod roster member was updated.',
            metadata: [
                'tanod_profile_id' => (int) $tanod->id,
                'user_id' => $tanod->user_id,
                'employee_id' => $tanod->employee_id,
                'previous_status' => $previousStatus,
                'new_status' => $tanod->status,
                'previous_shift' => $previousShift,
                'new_shift' => $tanod->shift,
            ],
            request: $request,
        );

        return response()
            ->json([
                'message' =>
                    'Tanod member updated successfully.',
                'data' => $this->serializeTanod($tanod),
            ])
            ->header(
                'Cache-Control',
                'no-store, private'
            );
    }

    public function destroy(
        Request $request,
        TanodProfile $tanod
    ): JsonResponse {
        Gate::authorize('delete', $tanod);

        $auditMetadata = [
            'tanod_profile_id' => (int) $tanod->id,
            'user_id' => $tanod->user_id,
            'employee_id' => $tanod->employee_id,
            'shift' => $tanod->shift,
            'status' => $tanod->status,
        ];

        DB::transaction(function () use ($tanod): void {
            $lockedTanod = TanodProfile::query()
                ->with([
                    'user',
                    'employee',
                ])
                ->lockForUpdate()
                ->findOrFail($tanod->getKey());

            $user = $lockedTanod->user;
            $employee = $lockedTanod->employee;

            $lockedTanod->delete();

            if ($employee) {
                $employee->delete();
            }

            if ($user) {
                $user->delete();
            }
        });

        $this->recordOperationalActivity(
            event: 'tanod_roster.deleted',
            category: 'tanod_roster',
            description: 'A tanod roster member was deleted.',
            metadata: $auditMetadata,
            request: $request,
        );

        return response()
            ->json([
                'message' =>
                    'Tanod member deleted successfully.',
            ])
            ->header(
                'Cache-Control',
                'no-store, private'
            );
    }

    private function acceptedResponsesSubquery(
        string $tanodTable
    ) {
        return DB::table('tanod_task_responses')
            ->selectRaw('COUNT(*)')
            ->whereColumn(
                'tanod_task_responses.employee_id',
                "{$tanodTable}.employee_id"
            )
            ->whereRaw(
                "LOWER(COALESCE(tanod_task_responses.response_status, '')) = ?",
                ['accepted']
            );
    }

    private function ranksByProfileId(
        string $tanodTable
    ): array {
        $rankingRows = TanodProfile::query()
            ->select("{$tanodTable}.id")
            ->selectSub(
                $this->acceptedResponsesSubquery(
                    $tanodTable
                ),
                'accepted_responses_count'
            )
            ->orderByDesc('accepted_responses_count')
            ->orderBy("{$tanodTable}.id")
            ->get();

        $ranksById = [];
        $previousResponseCount = null;
        $currentRank = 0;

        foreach ($rankingRows as $position => $rankingRow) {
            $responseCount = (int) (
                $rankingRow->accepted_responses_count ?? 0
            );

            if (
                $previousResponseCount === null
                || $responseCount !== $previousResponseCount
            ) {
                $currentRank = $position + 1;
            }

            $ranksById[(int) $rankingRow->id] =
                $currentRank;

            $previousResponseCount = $responseCount;
        }

        return $ranksById;
    }

    private function serializeTanod(
        TanodProfile $tanod
    ): array {
        $tanod->loadMissing([
            'user',
            'employee',
        ]);

        $email = trim(
            (string) ($tanod->user?->email ?? '')
        );

        return [
            'id' => (int) $tanod->id,
            'user_id' => $tanod->user_id
                ? (int) $tanod->user_id
                : null,
            'employee_id' => $tanod->employee_id
                ? (int) $tanod->employee_id
                : null,
            'badge_number' =>
                $tanod->badge_number,
            'rank' => $tanod->response_rank !== null
                ? (int) $tanod->response_rank
                : null,
            'accepted_responses_count' => (int) (
                $tanod->accepted_responses_count ?? 0
            ),
            'full_name' =>
                $tanod->user?->name
                ?: 'Unnamed Tanod',
            'contact_number' =>
                $tanod->contact_number
                ?: $tanod->user?->contact_number,
            'email' => $email !== ''
                ? $email
                : null,
            'is_generated_email' =>
                $email !== ''
                && str_ends_with(
                    strtolower($email),
                    '@tanod.invalid'
                ),
            'purok_assignment' =>
                $tanod->purok_assignment,
            'date_appointed' =>
                $tanod->date_appointed?->format('Y-m-d'),
            'shift' =>
                $this->normalizedShift($tanod->shift),
            'shift_label' =>
                $this->displayShift($tanod->shift),
            'status' =>
                $this->normalizedStatus($tanod->status),
            'status_label' =>
                $this->displayStatus($tanod->status),
            'notes' => $tanod->notes,
            'employee_active' =>
                (bool) ($tanod->employee?->is_active ?? false),
        ];
    }

    private function normalizeEmailInput(
        Request $request
    ): void {
        $email = $request->input('email');

        if ($email === null) {
            return;
        }

        $request->merge([
            'email' => strtolower(
                trim((string) $email)
            ),
        ]);
    }

    private function validationRules(
        ?TanodProfile $tanod = null
    ): array {
        return [
            'full_name' => [
                'required',
                'string',
                'max:255',
            ],
            'contact_number' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[0-9+()\-.\s]*$/',
            ],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->ignore($tanod?->user_id),
            ],
            'purok_assignment' => [
                'nullable',
                'string',
                'max:100',
            ],
            'date_appointed' => [
                'nullable',
                'date',
                'before_or_equal:today',
            ],
            'shift' => [
                'required',
                Rule::in(
                    array_keys($this->shifts())
                ),
            ],
            'status' => [
                'required',
                Rule::in(
                    array_keys($this->statuses())
                ),
            ],
            'notes' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ];
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

    private function optionRows(
        array $options
    ): array {
        $rows = [];

        foreach ($options as $value => $label) {
            $rows[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $rows;
    }

    private function normalizedShift(
        mixed $value
    ): string {
        $value = strtolower(
            trim((string) $value)
        );

        return array_key_exists(
            $value,
            $this->shifts()
        )
            ? $value
            : 'day';
    }

    private function normalizedStatus(
        mixed $value
    ): string {
        $value = strtolower(
            trim((string) $value)
        );

        return array_key_exists(
            $value,
            $this->statuses()
        )
            ? $value
            : 'active';
    }

    private function displayShift(
        mixed $value
    ): string {
        $normalized = $this->normalizedShift(
            $value
        );

        return $this->shifts()[$normalized];
    }

    private function displayStatus(
        mixed $value
    ): string {
        $normalized = $this->normalizedStatus(
            $value
        );

        return $this->statuses()[$normalized];
    }

    private function generateFallbackEmail(
        string $fullName
    ): string {
        $cleanName = strtolower(
            preg_replace(
                '/[^a-zA-Z0-9]/',
                '',
                $fullName
            )
        );

        $cleanName = $cleanName ?: 'tanod';

        return $cleanName
            . '-'
            . Str::lower((string) Str::uuid())
            . '@tanod.invalid';
    }

    private function defaultBarangayId(): ?int
    {
        if (! Barangay::query()->exists()) {
            return null;
        }

        return (int) Barangay::query()
            ->value('id');
    }

    private function nullableText(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === ''
            ? null
            : $value;
    }
}
