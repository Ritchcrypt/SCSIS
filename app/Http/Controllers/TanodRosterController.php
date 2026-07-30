<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RecordsOperationalActivity;
use App\Models\Barangay;
use App\Models\Employee;
use App\Models\TanodProfile;
use App\Models\User;
use App\Support\SqlLikePattern;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TanodRosterController extends Controller
{
    use RecordsOperationalActivity;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', TanodProfile::class);

        $search = SqlLikePattern::normalize(
            $request->query('search')
        );

        $searchPattern = SqlLikePattern::contains(
            $search
        );

        $tanods = TanodProfile::query()
            ->with(['user', 'employee'])
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
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        return view('tanods.index', [
            'tanods' => $tanods,
            'totalTanods' => TanodProfile::query()->count(),
            'onDutyCount' => TanodProfile::query()
                ->where('status', 'on_duty')
                ->count(),
            'shifts' => $this->shifts(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', TanodProfile::class);

        $this->normalizeEmailInput(
            $request
        );

        $validated = $request->validate(
            $this->validationRules()
        );

        $providedEmail = $this->nullableText(
            $validated['email'] ?? null
        );

        $barangayId = $this->defaultBarangayId();

        if (! $barangayId) {
            return back()->with(
                'error',
                'Add at least one barangay before creating a tanod account.'
            );
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

            $createdUser = User::create([
                'name' => trim($validated['full_name']),
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
                'role' => 'tanod',
                'contact_number' => $this->nullableText(
                    $validated['contact_number'] ?? null
                ),
                'is_active' => true,
                'status' => true,
            ]);

            $employee = Employee::create([
                'user_id' => $createdUser->id,
                'barangay_id' => $barangayId,
                'employee_type' => 'tanod',
                'position' => 'Barangay Tanod',
                'department' => 'Public Safety',
                'is_active' => $validated['status'] !== 'off_duty',
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
                'date_appointed' => $validated['date_appointed'] ?? null,
                'shift' => $validated['shift'],
                'status' => $validated['status'],
                'notes' => $this->nullableText(
                    $validated['notes'] ?? null
                ),
            ]);
        });

        $message = 'Tanod member added successfully.';

        if ($createdUser && $providedEmail) {
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
            $message .= ' Add a valid email in User Management before sending a password setup link.';
        }

        $this->recordOperationalActivity(
            event: 'tanod_roster.created',
            category: 'tanod_roster',
            description: 'A tanod roster member was created.',
            metadata: [
                'tanod_profile_id' => (int) $createdTanodProfile->id,
                'user_id' => (int) $createdUser->id,
                'employee_id' => $createdTanodProfile->employee_id,
                'shift' => $createdTanodProfile->shift,
                'status' => $createdTanodProfile->status,
                'password_setup_email_requested' => $providedEmail !== null,
            ],
            request: $request,
        );

        return redirect()
            ->to($this->rosterIndexUrl($request))
            ->with('success', $message);
    }

    public function update(
        Request $request,
        TanodProfile $tanod
    ): RedirectResponse {
        Gate::authorize('update', $tanod);

        $this->normalizeEmailInput(
            $request
        );

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
                ->with(['user', 'employee'])
                ->lockForUpdate()
                ->findOrFail($tanod->getKey());

            $user = $lockedTanod->user;
            $employee = $lockedTanod->employee;

            if ($user) {
                $newEmail = $this->nullableText(
                    $validated['email'] ?? null
                );

                $userData = [
                    'name' => trim($validated['full_name']),
                    'contact_number' => $this->nullableText(
                        $validated['contact_number'] ?? null
                    ),
                ];

                if ($newEmail) {
                    $newEmail = strtolower($newEmail);

                    if (strcasecmp($newEmail, (string) $user->email) !== 0) {
                        $userData['email'] = $newEmail;

                        if (array_key_exists(
                            'email_verified_at',
                            $user->getAttributes()
                        )) {
                            $userData['email_verified_at'] = null;
                        }
                    }
                }

                $user->forceFill($userData)->save();
            }

            if ($employee) {
                $employee->update([
                    'is_active' => $validated['status'] !== 'off_duty',
                ]);
            }

            $lockedTanod->update([
                'contact_number' => $this->nullableText(
                    $validated['contact_number'] ?? null
                ),
                'purok_assignment' => $this->nullableText(
                    $validated['purok_assignment'] ?? null
                ),
                'date_appointed' => $validated['date_appointed'] ?? null,
                'shift' => $validated['shift'],
                'status' => $validated['status'],
                'notes' => $this->nullableText(
                    $validated['notes'] ?? null
                ),
            ]);
        });

        $tanod->refresh();

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

        return redirect()
            ->to($this->rosterIndexUrl($request))
            ->with('success', 'Tanod member updated successfully.');
    }

    public function destroy(
        Request $request,
        TanodProfile $tanod
    ): RedirectResponse {
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
                ->with(['user', 'employee'])
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

        return redirect()
            ->to($this->rosterIndexUrl($request))
            ->with('success', 'Tanod member deleted successfully.');
    }

    private function normalizeEmailInput(
        Request $request
    ): void {
        $email = $request->input(
            'email'
        );

        if ($email === null) {
            return;
        }

        $request->merge([
            'email' => strtolower(
                trim(
                    (string) $email
                )
            ),
        ]);
    }

    private function validationRules(
        ?TanodProfile $tanod = null
    ): array {
        return [
            'full_name' => ['required', 'string', 'max:255'],
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
                Rule::in(array_keys($this->shifts())),
            ],
            'status' => [
                'required',
                Rule::in(array_keys($this->statuses())),
            ],
            'notes' => ['nullable', 'string', 'max:3000'],
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

    private function generateFallbackEmail(string $fullName): string
    {
        $cleanName = strtolower(
            preg_replace('/[^a-zA-Z0-9]/', '', $fullName)
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

        return (int) Barangay::query()->value('id');
    }

    private function rosterIndexUrl(Request $request): string
    {
        $routeName = $request->user()->isOfficial()
            ? 'official.tanods.index'
            : 'admin.tanods.index';

        return Route::has($routeName)
            ? route($routeName)
            : route('dashboard');
    }

    private function nullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
