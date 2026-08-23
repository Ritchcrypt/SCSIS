<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\RecordsOperationalActivity;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Evidence;
use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\IncidentEscalation;
use App\Models\IncidentMessage;
use App\Models\IncidentStatusHistory;
use App\Models\Status;
use App\Models\TanodTask;
use App\Models\TanodTaskResponse;
use App\Models\User;
use App\Models\UserNotification;
use App\Rules\SecureUploadedFile;
use App\Services\SecureUploadService;
use App\Support\SafeDatabaseIdentifier;
use App\Support\SqlLikePattern;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class IncidentController extends Controller
{
    use RecordsOperationalActivity;

    private const SEVERITY_OPTIONS = [
        'low' => 'Low',
        'moderate' => 'Moderate',
        'high' => 'High',
        'critical' => 'Critical',
    ];

    public function __construct(
        private readonly SecureUploadService $secureUploads
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Incident::class);

        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $search = SqlLikePattern::normalize(
            $request->query('search')
        );

        $searchPattern = SqlLikePattern::contains($search);

        $query = Incident::query()
            ->with([
                'barangay',
                'category',
                'currentStatus',
                'location',
                'reporter',
                'resident.user',
                'assignedTanod.user',
            ]);

        if ($user->isTanod()) {
            $employeeId = $user->employee?->id;

            if ($employeeId) {
                $query->where('assigned_to', $employeeId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($user->isResident()) {
            $this->applyResidentIncidentOwnerFilter(
                $query,
                (int) $user->id
            );
        }

        if ($searchPattern !== null) {
            $query->where(
                function (EloquentBuilder $searchQuery) use ($searchPattern): void {
                    SqlLikePattern::whereContains(
                        $searchQuery,
                        'incident_title',
                        $searchPattern
                    );

                    SqlLikePattern::orWhereContains(
                        $searchQuery,
                        'incident_description',
                        $searchPattern
                    );

                    $searchQuery->orWhereHas(
                        'barangay',
                        function (EloquentBuilder $barangayQuery) use ($searchPattern): void {
                            SqlLikePattern::whereContains(
                                $barangayQuery,
                                'barangay_name',
                                $searchPattern
                            );
                        }
                    );

                    $searchQuery->orWhereHas(
                        'category',
                        function (EloquentBuilder $categoryQuery) use ($searchPattern): void {
                            SqlLikePattern::whereContains(
                                $categoryQuery,
                                'category_name',
                                $searchPattern
                            );
                        }
                    );

                    $searchQuery->orWhereHas(
                        'currentStatus',
                        function (EloquentBuilder $statusQuery) use ($searchPattern): void {
                            SqlLikePattern::whereContains(
                                $statusQuery,
                                'status_name',
                                $searchPattern
                            );
                        }
                    );
                }
            );
        }

        $categoryId = $this->integerFilter(
            $request,
            ['category_id', 'type']
        );

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        $statusId = $this->integerFilter(
            $request,
            ['status_id', 'status']
        );

        if ($statusId !== null) {
            $query->where('status_id', $statusId);
        }

        $severity = strtolower(
            trim(
                (string) (
                    $request->query('priority')
                    ?? $request->query('severity')
                    ?? ''
                )
            )
        );

        if (
            $severity !== ''
            && $severity !== 'all'
            && array_key_exists($severity, self::SEVERITY_OPTIONS)
        ) {
            $query->where('priority', $severity);
        }

        if (Schema::hasColumn('incidents', 'incident_datetime')) {
            $query->orderByDesc('incident_datetime');
        }

        $incidents = $query
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return response()->json([
            'data' => collect($incidents->items())
                ->map(
                    fn (Incident $incident): array =>
                        $this->incidentData($incident)
                )
                ->values(),
            'pagination' => [
                'current_page' => $incidents->currentPage(),
                'last_page' => $incidents->lastPage(),
                'per_page' => $incidents->perPage(),
                'total' => $incidents->total(),
                'has_more' => $incidents->hasMorePages(),
            ],
            'filters' => [
                'search' => $search ?? '',
                'category_id' => $categoryId,
                'status_id' => $statusId,
                'priority' => $severity !== 'all' ? $severity : '',
            ],
            'can_create' => Gate::allows('create', Incident::class),
            'can_delete' => $user->isAdmin(),
            'filter_options' => [
                'categories' => IncidentCategory::active()
                    ->orderBy('category_name')
                    ->get()
                    ->map(
                        fn (IncidentCategory $category): array => [
                            'id' => (int) $category->id,
                            'name' => (string) (
                                $category->category_name
                                ?? $category->name
                                ?? 'Category'
                            ),
                        ]
                    )
                    ->values(),
                'statuses' => Status::query()
                    ->active()
                    ->ordered()
                    ->get()
                    ->map(
                        fn (Status $status): array => [
                            'id' => (int) $status->id,
                            'name' => (string) $status->status_name,
                        ]
                    )
                    ->values(),
                'severity_options' => collect(self::SEVERITY_OPTIONS)
                    ->map(
                        fn (string $label, string $value): array => [
                            'value' => $value,
                            'label' => $label,
                        ]
                    )
                    ->values(),
            ],
        ]);
    }

    public function createOptions(Request $request): JsonResponse
    {
        Gate::authorize('create', Incident::class);

        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $categories = IncidentCategory::query()
            ->active()
            ->orderBy('category_name')
            ->get()
            ->map(
                fn (IncidentCategory $category): array => [
                    'id' => (int) $category->id,
                    'name' => (string) (
                        $category->category_name
                        ?? $category->name
                        ?? 'Category'
                    ),
                ]
            )
            ->values();

        $barangays = Schema::hasTable('barangays')
            ? DB::table('barangays')
                ->orderBy('barangay_name')
                ->get()
                ->map(
                    fn (object $barangay): array => [
                        'id' => (int) $barangay->id,
                        'name' => (string) (
                            $barangay->barangay_name
                            ?? $barangay->name
                            ?? 'Barangay'
                        ),
                    ]
                )
                ->values()
            : collect();

        return response()->json([
            'data' => [
                'categories' => $categories,
                'barangays' => $barangays,
                'severity_options' => collect(self::SEVERITY_OPTIONS)
                    ->map(
                        fn (string $label, string $value): array => [
                            'value' => $value,
                            'label' => $label,
                        ]
                    )
                    ->values(),
                'evidence' => [
                    'optional' => true,
                    'max_files' => (int) config(
                        'secure_uploads.policies.incident_evidence.max_files',
                        5
                    ),
                    'max_kilobytes_each' => (int) config(
                        'secure_uploads.policies.incident_evidence.max_kilobytes',
                        10240
                    ),
                    'allowed_extensions' => array_values(
                        (array) config(
                            'secure_uploads.policies.incident_evidence.allowed_extensions',
                            []
                        )
                    ),
                ],
                'submission_info' => [
                    'reporter' => [
                        'id' => (int) $user->id,
                        'name' => (string) $user->name,
                        'email' => $user->email,
                        'contact_number' => $user->contact_number,
                    ],
                    'initial_status' => 'Reported',
                    'date' => now()->toIso8601String(),
                ],
                'permissions' => [
                    'can_manage_barangays' => Gate::allows('manageBarangays'),
                ],
            ],
        ]);
    }

    public function show(
        Request $request,
        Incident $incident
    ): JsonResponse {
        Gate::authorize('view', $incident);

        $incident->load([
            'barangay',
            'category',
            'currentStatus',
            'location',
            'reporter',
            'resident.user',
            'assignedTanod.user',
            'evidence.uploader',
            'statusHistories.status',
            'statusHistories.updatedBy',
            'escalations.escalatedBy',
            'messages.user',
        ]);

        if ($request->user()?->isAdmin() || $request->user()?->isOfficial()) {
            $incident->load('caseRecords.creator');
        } else {
            $incident->setRelation('caseRecords', collect());
        }

        $canUpdate = Gate::allows('update', $incident);
        $canAssign = Gate::allows('assign', $incident);
        $canEscalate = Gate::allows('escalate', $incident);
        $canMessage = Gate::allows('addMessage', $incident);

        $statuses = $canUpdate
            ? Status::query()
                ->active()
                ->ordered()
                ->get()
                ->map(
                    fn (Status $status): array => [
                        'id' => (int) $status->id,
                        'name' => (string) $status->status_name,
                    ]
                )
                ->values()
            : collect();

        $responders = $canAssign
            ? Employee::query()
                ->with('user:id,name')
                ->tanods()
                ->active()
                ->orderBy('id')
                ->get()
                ->map(
                    fn (Employee $employee): array => [
                        'id' => (int) $employee->id,
                        'name' => (string) (
                            $employee->user?->name
                            ?? 'Tanod #' . $employee->id
                        ),
                    ]
                )
                ->values()
            : collect();

        $agencies = $canEscalate
            ? collect($this->agencyOptions())
                ->map(
                    fn (string $label, string $value): array => [
                        'value' => $value,
                        'label' => $label,
                    ]
                )
                ->values()
            : collect();

        return response()->json([
            'data' => $this->incidentData(
                $incident,
                detailed: true
            ),
            'permissions' => [
                'can_update' => $canUpdate,
                'can_assign' => $canAssign,
                'can_escalate' => $canEscalate,
                'can_message' => $canMessage,
                'can_delete' => Gate::allows('delete', $incident),
                'can_manage_barangays' => Gate::allows('manageBarangays'),
                'can_view_related_cases' => (
                    $request->user()?->isAdmin()
                    || $request->user()?->isOfficial()
                ),
                'can_create_case' => $request->user()?->isAdmin() ?? false,
            ],
            'options' => [
                'statuses' => $statuses,
                'responders' => $responders,
                'agencies' => $agencies,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Incident::class);

        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'incident_title' => [
                'required',
                'string',
                'max:255',
            ],
            'incident_description' => [
                'required',
                'string',
                'max:3000',
            ],
            'category_id' => [
                'required',
                'integer',
                Rule::exists(
                    'incident_categories',
                    'id'
                )->where(
                    fn (QueryBuilder $query): QueryBuilder =>
                        $query->where('is_active', true)
                ),
            ],
            'barangay_id' => [
                'required',
                'integer',
                'exists:barangays,id',
            ],
            'priority' => [
                'required',
                Rule::in(
                    array_keys(self::SEVERITY_OPTIONS)
                ),
            ],
            'location_address' => [
                'required',
                'string',
                'max:500',
            ],
            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],
            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],
            'evidence' => [
                'nullable',
                'array',
                'max:' . (int) config(
                    'secure_uploads.policies.incident_evidence.max_files',
                    5
                ),
            ],
            'evidence.*' => [
                'nullable',
                'file',
                new SecureUploadedFile(
                    'incident_evidence'
                ),
            ],
        ], [
            'category_id.required' =>
                'Please select an incident category.',
            'barangay_id.required' =>
                'Please select a barangay.',
            'priority.required' =>
                'Please select a severity level.',
            'location_address.required' =>
                'Please provide the incident location or landmark.',
        ]);

        $initialStatus = Status::query()
            ->active()
            ->whereRaw(
                'LOWER(status_name) = ?',
                ['reported']
            )
            ->first();

        if (! $initialStatus) {
            return response()->json([
                'message' =>
                    'Incident status configuration is unavailable. Please contact an administrator.',
            ], 422);
        }

        $incident = null;
        $storedEvidencePaths = [];

        try {
            DB::transaction(function () use (
                $request,
                $validated,
                $initialStatus,
                &$incident,
                &$storedEvidencePaths
            ): void {
                $incident = new Incident();

                $this->attachIncidentOwner(
                    $incident,
                    $request
                );

                if (Schema::hasColumn('incidents', 'incident_code')) {
                    $incident->incident_code = null;
                }

                if (Schema::hasColumn('incidents', 'category_id')) {
                    $incident->category_id =
                        (int) $validated['category_id'];
                }

                $selectedCategory = IncidentCategory::query()
                    ->find(
                        (int) $validated['category_id']
                    );

                $selectedCategoryName =
                    $selectedCategory?->category_name
                    ?? $selectedCategory?->name
                    ?? null;

                if ($selectedCategoryName) {
                    if (Schema::hasColumn('incidents', 'type')) {
                        $incident->setAttribute(
                            'type',
                            $selectedCategoryName
                        );
                    }

                    if (Schema::hasColumn('incidents', 'incident_type')) {
                        $incident->setAttribute(
                            'incident_type',
                            $selectedCategoryName
                        );
                    }

                    if (Schema::hasColumn('incidents', 'category_name')) {
                        $incident->setAttribute(
                            'category_name',
                            $selectedCategoryName
                        );
                    }
                }

                if (Schema::hasColumn('incidents', 'barangay_id')) {
                    $incident->barangay_id =
                        (int) $validated['barangay_id'];
                }

                if (Schema::hasColumn('incidents', 'status_id')) {
                    $incident->status_id =
                        (int) $initialStatus->id;
                }

                if (Schema::hasColumn('incidents', 'status')) {
                    $incident->setAttribute(
                        'status',
                        $initialStatus->status_name
                            ?? 'pending'
                    );
                }

                if (Schema::hasColumn('incidents', 'incident_title')) {
                    $incident->incident_title =
                        trim($validated['incident_title']);
                }

                if (Schema::hasColumn('incidents', 'title')) {
                    $incident->title =
                        trim($validated['incident_title']);
                }

                if (
                    Schema::hasColumn(
                        'incidents',
                        'incident_description'
                    )
                ) {
                    $incident->incident_description =
                        trim($validated['incident_description']);
                }

                if (Schema::hasColumn('incidents', 'description')) {
                    $incident->description =
                        trim($validated['incident_description']);
                }

                if (Schema::hasColumn('incidents', 'priority')) {
                    $incident->priority =
                        $validated['priority'];
                }

                if (
                    Schema::hasColumn(
                        'incidents',
                        'incident_datetime'
                    )
                ) {
                    $incident->incident_datetime = now();
                }

                if (Schema::hasColumn('incidents', 'reported_at')) {
                    $incident->reported_at = now();
                }

                if (Schema::hasColumn('incidents', 'assigned_to')) {
                    $incident->assigned_to = null;
                }

                $incident->save();

                $this->storeIncidentLocation(
                    $incident,
                    $validated
                );

                $this->storeIncidentEvidence(
                    $request,
                    $incident,
                    $storedEvidencePaths
                );

                IncidentStatusHistory::query()->create([
                    'incident_id' => $incident->id,
                    'status_id' => $initialStatus->id,
                    'updated_by' => $request->user()->id,
                    'remarks' => 'Incident report submitted.',
                    'status_changed_at' => now(),
                ]);

                $this->notifyIncidentCreated($incident);

                $this->createTanodTaskFromIncident(
                    $incident,
                    $request
                );
            });
        } catch (Throwable $exception) {
            $this->secureUploads->deleteMany(
                $storedEvidencePaths,
                [
                    'incidents/evidence',
                ]
            );

            throw $exception;
        }

        if (! $incident instanceof Incident || ! $incident->exists) {
            throw new \RuntimeException(
                'Incident creation completed without a persisted incident record.'
            );
        }

        $this->recordOperationalActivity(
            event: 'incident.created',
            category: 'incident',
            description: 'An incident report was created.',
            metadata: [
                'incident_id' => (int) $incident->id,
                'incident_code' => $incident->incident_code,
                'category_id' => $incident->category_id,
                'barangay_id' => $incident->barangay_id,
                'priority' => $incident->priority,
                'status_id' => $incident->status_id,
                'evidence_attached' =>
                    $request->hasFile('evidence'),
            ],
            request: $request,
        );

        $incident->load([
            'barangay',
            'category',
            'currentStatus',
            'location',
            'reporter',
            'resident.user',
            'assignedTanod.user',
            'evidence.uploader',
            'statusHistories.status',
            'statusHistories.updatedBy',
            'escalations.escalatedBy',
            'messages.user',
        ]);

        if ($request->user()?->isAdmin() || $request->user()?->isOfficial()) {
            $incident->load('caseRecords.creator');
        } else {
            $incident->setRelation('caseRecords', collect());
        }

        return response()->json([
            'message' =>
                'Incident report submitted successfully.',
            'data' => $this->incidentData(
                $incident,
                detailed: true
            ),
        ], 201);
    }


    public function updateStatus(
        Request $request,
        Incident $incident
    ): JsonResponse {
        Gate::authorize('update', $incident);

        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $rules = [
            'status_id' => [
                'required',
                'integer',
                Rule::exists(
                    'statuses',
                    'id'
                )->where(
                    fn (QueryBuilder $query): QueryBuilder =>
                        $query->where('is_active', true)
                ),
            ],
            'remarks' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ];

        if ($user->isAdmin()) {
            $rules['assigned_to'] = [
                'nullable',
                'integer',
                Rule::exists('employees', 'id'),
            ];
        }

        $validated = $request->validate($rules);

        $status = Status::query()
            ->active()
            ->findOrFail(
                (int) $validated['status_id']
            );

        $previousStatusId = $incident->status_id !== null
            ? (int) $incident->status_id
            : null;

        $previousAssignedTo = $incident->assigned_to !== null
            ? (int) $incident->assigned_to
            : null;

        $requestedAssignedTo = null;
        $assignmentWasSubmitted = false;

        if (
            $user->isAdmin()
            && array_key_exists('assigned_to', $validated)
        ) {
            $assignmentWasSubmitted = true;

            $requestedAssignedTo =
                $validated['assigned_to'] !== null
                    ? (int) $validated['assigned_to']
                    : null;

            if ($requestedAssignedTo !== null) {
                $assignedTanodExists = Employee::query()
                    ->tanods()
                    ->active()
                    ->whereKey($requestedAssignedTo)
                    ->exists();

                if (! $assignedTanodExists) {
                    throw ValidationException::withMessages([
                        'assigned_to' =>
                            'The selected responder must be an active barangay tanod.',
                    ]);
                }
            }
        }

        DB::transaction(function () use (
            $request,
            $incident,
            $status,
            $validated,
            $assignmentWasSubmitted,
            $requestedAssignedTo
        ): void {
            $lockedIncident = Incident::query()
                ->whereKey($incident->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::authorize('update', $lockedIncident);

            $oldAssignedTo =
                $lockedIncident->assigned_to !== null
                    ? (int) $lockedIncident->assigned_to
                    : null;

            $newAssignedTo = $assignmentWasSubmitted
                ? $requestedAssignedTo
                : $oldAssignedTo;

            $incidentUpdateData = [
                'status_id' => $status->id,
            ];

            if (Schema::hasColumn('incidents', 'status')) {
                $incidentUpdateData['status'] =
                    $status->status_name;
            }

            if (Schema::hasColumn('incidents', 'assigned_to')) {
                $incidentUpdateData['assigned_to'] =
                    $newAssignedTo;
            }

            $lockedIncident->update($incidentUpdateData);

            IncidentStatusHistory::query()->create([
                'incident_id' => $lockedIncident->id,
                'status_id' => $status->id,
                'updated_by' => $request->user()->id,
                'remarks' => $validated['remarks'] ?? null,
                'status_changed_at' => now(),
            ]);

            $freshIncident = $lockedIncident->fresh([
                'reporter',
                'assignedTanod.user',
                'currentStatus',
                'category',
                'barangay',
            ]);

            if (! $freshIncident instanceof Incident) {
                throw new \RuntimeException(
                    'Incident could not be reloaded after the status update.'
                );
            }

            $statusName =
                $status->status_name ?: 'Updated';

            $this->notifyIncidentUsers(
                incident: $freshIncident,
                title: 'Incident status updated',
                message:
                    'Incident status changed to '
                    . $statusName
                    . '.'
            );

            if (
                $newAssignedTo !== null
                && $oldAssignedTo !== $newAssignedTo
            ) {
                $this->createTanodAlert(
                    incident: $freshIncident,
                    type: 'dispatch',
                    title: 'Tanod Dispatch Alert',
                    message:
                        'You have been assigned to respond to incident: '
                        . $freshIncident->display_title
                        . '.'
                );
            }
        });

        $incident->refresh();

        $this->recordOperationalActivity(
            event: 'incident.status_updated',
            category: 'incident',
            description:
                'An incident status or responder assignment was updated.',
            metadata: [
                'incident_id' => (int) $incident->id,
                'incident_code' => $incident->incident_code,
                'previous_status_id' => $previousStatusId,
                'new_status_id' => (int) $status->id,
                'new_status' => $status->status_name,
                'previous_assigned_to' => $previousAssignedTo,
                'new_assigned_to' =>
                    $incident->assigned_to !== null
                        ? (int) $incident->assigned_to
                        : null,
                'remarks_provided' =>
                    ! empty($validated['remarks']),
            ],
            request: $request,
        );

        $incident->load([
            'barangay',
            'category',
            'currentStatus',
            'location',
            'reporter',
            'resident.user',
            'assignedTanod.user',
            'evidence.uploader',
            'statusHistories.status',
            'statusHistories.updatedBy',
            'escalations.escalatedBy',
            'messages.user',
        ]);

        if ($request->user()?->isAdmin() || $request->user()?->isOfficial()) {
            $incident->load('caseRecords.creator');
        } else {
            $incident->setRelation('caseRecords', collect());
        }

        return response()->json([
            'message' =>
                'Incident status updated successfully.',
            'data' => $this->incidentData(
                $incident,
                detailed: true
            ),
        ]);
    }

    public function escalate(
        Request $request,
        Incident $incident
    ): JsonResponse {
        Gate::authorize('escalate', $incident);

        $validated = $request->validate([
            'agency' => [
                'required',
                'string',
                Rule::in(
                    array_keys(
                        $this->agencyOptions()
                    )
                ),
            ],
            'reason' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ]);

        DB::transaction(function () use (
            $request,
            $incident,
            $validated
        ): void {
            IncidentEscalation::query()->create([
                'incident_id' => $incident->id,
                'escalated_by' => $request->user()->id,
                'agency' => $validated['agency'],
                'reason' => $validated['reason'] ?? null,
                'escalated_at' => now(),
            ]);

            $escalatedStatus = Status::query()
                ->whereRaw(
                    'LOWER(status_name) = ?',
                    ['escalated']
                )
                ->first();

            if ($escalatedStatus) {
                $incidentUpdates = [
                    'status_id' => $escalatedStatus->id,
                ];

                if (Schema::hasColumn('incidents', 'status')) {
                    $incidentUpdates['status'] =
                        $escalatedStatus->status_name;
                }

                $incident->update($incidentUpdates);

                IncidentStatusHistory::query()->create([
                    'incident_id' => $incident->id,
                    'status_id' => $escalatedStatus->id,
                    'updated_by' => $request->user()->id,
                    'remarks' =>
                        'Escalated to '
                        . $validated['agency']
                        . '. '
                        . ($validated['reason'] ?? ''),
                    'status_changed_at' => now(),
                ]);
            }

            $freshIncident = $incident->fresh([
                'reporter',
                'assignedTanod.user',
                'currentStatus',
                'category',
                'barangay',
            ]);

            if (! $freshIncident instanceof Incident) {
                throw new \RuntimeException(
                    'Incident could not be reloaded after escalation.'
                );
            }

            $this->notifyIncidentUsers(
                incident: $freshIncident,
                title: 'Incident escalated',
                message:
                    'Incident has been escalated to '
                    . $validated['agency']
                    . '.'
            );

            $this->createTanodAlert(
                incident: $freshIncident,
                type: 'escalation',
                title: 'Incident Escalated',
                message:
                    'Incident has been escalated to '
                    . $validated['agency']
                    . '.'
            );
        });

        $this->recordOperationalActivity(
            event: 'incident.escalated',
            category: 'incident',
            description:
                'An incident was escalated to an external agency.',
            metadata: [
                'incident_id' => (int) $incident->id,
                'incident_code' => $incident->incident_code,
                'agency' => $validated['agency'],
                'reason_provided' =>
                    ! empty($validated['reason']),
            ],
            request: $request,
        );

        $incident->refresh()->load([
            'barangay',
            'category',
            'currentStatus',
            'location',
            'reporter',
            'resident.user',
            'assignedTanod.user',
            'evidence.uploader',
            'statusHistories.status',
            'statusHistories.updatedBy',
            'escalations.escalatedBy',
            'messages.user',
        ]);

        if ($request->user()?->isAdmin() || $request->user()?->isOfficial()) {
            $incident->load('caseRecords.creator');
        } else {
            $incident->setRelation('caseRecords', collect());
        }

        return response()->json([
            'message' =>
                'Incident escalated successfully.',
            'data' => $this->incidentData(
                $incident,
                detailed: true
            ),
        ]);
    }

    public function storeMessage(
        Request $request,
        Incident $incident
    ): JsonResponse {
        Gate::authorize('addMessage', $incident);

        $validated = $request->validate([
            'message' => [
                'required',
                'string',
                'max:3000',
            ],
        ]);

        $message = IncidentMessage::query()->create([
            'incident_id' => $incident->id,
            'user_id' => $request->user()->id,
            'message' => trim($validated['message']),
        ]);

        $message->load('user');

        $this->recordOperationalActivity(
            event: 'incident.message_added',
            category: 'incident',
            description:
                'A message was added to an incident.',
            metadata: [
                'incident_id' => (int) $incident->id,
                'incident_code' => $incident->incident_code,
            ],
            request: $request,
        );

        return response()->json([
            'message' => 'Message added successfully.',
            'data' => [
                'id' => (int) $message->id,
                'message' => (string) $message->message,
                'user' => $message->user?->name,
                'created_at' =>
                    $message->created_at?->toIso8601String(),
            ],
        ], 201);
    }


    public function destroy(
        Request $request,
        Incident $incident
    ): JsonResponse {
        Gate::authorize('delete', $incident);

        $auditMetadata = [
            'incident_id' => (int) $incident->id,
            'incident_code' => $incident->incident_code,
            'priority' => $incident->priority,
            'status_id' => $incident->status_id,
        ];

        $filePaths = $this->incidentRelatedFilePaths(
            (int) $incident->id
        );

        DB::transaction(function () use ($incident): void {
            $this->deleteIncidentRelatedRows(
                (int) $incident->id
            );

            $incident->delete();
        });

        $this->secureUploads->deleteMany(
            $filePaths,
            [
                'incidents/evidence',
            ]
        );

        $this->recordOperationalActivity(
            event: 'incident.deleted',
            category: 'incident',
            description: 'An incident record was deleted.',
            metadata: $auditMetadata,
            request: $request,
        );

        return response()->json([
            'message' => 'Incident deleted successfully.',
        ]);
    }

    public function quickStoreBarangay(
        Request $request
    ): JsonResponse {
        Gate::authorize('manageBarangays');

        if (! Schema::hasTable('barangays')) {
            return response()->json([
                'message' => 'Barangays table does not exist.',
            ], 422);
        }

        $validated = $request->validate([
            'barangay_name' => [
                'required',
                'string',
                'max:255',
                'not_regex:/^\s*$/',
            ],
        ], [
            'barangay_name.not_regex' =>
                'Barangay name is required.',
        ]);

        $barangayName = trim(
            (string) $validated['barangay_name']
        );

        $columns = Schema::getColumnListing('barangays');

        $nameColumn = in_array(
            'barangay_name',
            $columns,
            true
        )
            ? 'barangay_name'
            : (
                in_array('name', $columns, true)
                    ? 'name'
                    : null
            );

        if (! $nameColumn) {
            return response()->json([
                'message' =>
                    'No barangay name column was found.',
            ], 422);
        }

        $wrappedNameColumn = SafeDatabaseIdentifier::wrap(
            $nameColumn
        );

        $exists = DB::table('barangays')
            ->whereRaw(
                "LOWER({$wrappedNameColumn}) = ?",
                [
                    mb_strtolower($barangayName),
                ]
            )
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'barangay_name' => 'Barangay already exists.',
            ]);
        }

        $data = [
            $nameColumn => $barangayName,
        ];

        if (
            $nameColumn !== 'barangay_name'
            && in_array(
                'barangay_name',
                $columns,
                true
            )
        ) {
            $data['barangay_name'] = $barangayName;
        }

        if (
            $nameColumn !== 'name'
            && in_array('name', $columns, true)
        ) {
            $data['name'] = $barangayName;
        }

        if (in_array('created_at', $columns, true)) {
            $data['created_at'] = now();
        }

        if (in_array('updated_at', $columns, true)) {
            $data['updated_at'] = now();
        }

        $barangayId = DB::transaction(
            function () use (
                $wrappedNameColumn,
                $barangayName,
                $data
            ): int {
                $duplicate = DB::table('barangays')
                    ->whereRaw(
                        "LOWER({$wrappedNameColumn}) = ?",
                        [
                            mb_strtolower($barangayName),
                        ]
                    )
                    ->lockForUpdate()
                    ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'barangay_name' =>
                            'Barangay already exists.',
                    ]);
                }

                return (int) DB::table('barangays')
                    ->insertGetId($data);
            }
        );

        $this->recordOperationalActivity(
            event: 'barangay.created',
            category: 'configuration',
            description: 'A barangay record was added.',
            metadata: [
                'barangay_id' => $barangayId,
                'barangay_name' => $barangayName,
            ],
            request: $request,
        );

        return response()->json([
            'message' => 'Barangay added successfully.',
            'data' => [
                'id' => $barangayId,
                'name' => $barangayName,
            ],
        ], 201);
    }

    public function quickDeleteBarangay(
        Request $request,
        int $barangayId
    ): JsonResponse {
        Gate::authorize('manageBarangays');

        if (! Schema::hasTable('barangays')) {
            return response()->json([
                'message' => 'Barangays table does not exist.',
            ], 422);
        }

        $barangay = DB::table('barangays')
            ->where('id', $barangayId)
            ->first();

        if (! $barangay) {
            return response()->json([
                'message' => 'Barangay record was not found.',
            ], 404);
        }

        if ($this->barangayIsReferenced($barangayId)) {
            throw ValidationException::withMessages([
                'barangay' =>
                    'This barangay cannot be removed because system records are already linked to it.',
            ]);
        }

        DB::transaction(function () use ($barangayId): void {
            $lockedBarangay = DB::table('barangays')
                ->where('id', $barangayId)
                ->lockForUpdate()
                ->first();

            if (! $lockedBarangay) {
                return;
            }

            if ($this->barangayIsReferenced($barangayId)) {
                throw ValidationException::withMessages([
                    'barangay' =>
                        'This barangay is already linked to system records and cannot be removed.',
                ]);
            }

            DB::table('barangays')
                ->where('id', $barangayId)
                ->delete();
        });

        $this->recordOperationalActivity(
            event: 'barangay.deleted',
            category: 'configuration',
            description: 'A barangay record was removed.',
            metadata: [
                'barangay_id' => $barangayId,
            ],
            request: $request,
        );

        return response()->json([
            'message' => 'Barangay removed successfully.',
        ]);
    }

    public function evidenceFile(
        Incident $incident,
        Evidence $evidence
    ): BinaryFileResponse {
        Gate::authorize('view', $incident);

        if ((int) $evidence->incident_id !== (int) $incident->id) {
            abort(404, 'Evidence record not found.');
        }

        $storedFile = $this->secureUploads->resolve(
            $evidence->file_path,
            [
                'incidents/evidence',
            ],
            (array) config(
                'secure_uploads.policies.incident_evidence.allowed_mime_types',
                []
            )
        );

        if (! $storedFile) {
            abort(404, 'Evidence file not found in storage.');
        }

        $fileName = $this->secureUploads->safeOriginalName(
            $evidence->file_name,
            basename($storedFile['path'])
        );

        $response = response()->file(
            $storedFile['absolute_path'],
            [
                'Content-Type' => (string) $storedFile['mime_type'],
                'Content-Disposition' =>
                    'inline; filename="' . $fileName . '"',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );

        $response->setPrivate();
        $response->headers->addCacheControlDirective(
            'no-store',
            true
        );

        return $response;
    }

    private function incidentData(
        Incident $incident,
        bool $detailed = false
    ): array {
        $categoryRelation = $incident->relationLoaded('category')
            ? $incident->getRelation('category')
            : null;

        $reportedAt = $incident->reported_at
            ?? $incident->incident_datetime
            ?? $incident->created_at;

        $locationAddress =
            $this->incidentLocationAddress($incident);

        $data = [
            'id' => (int) $incident->id,
            'incident_code' => $incident->display_code,
            'title' => $incident->display_title,
            'description' => $incident->display_description,
            'priority' => (string) $incident->priority,
            'severity_label' => $incident->severity_label,
            'status' => $incident->currentStatus?->status_name
                ?? (
                    is_string($incident->getAttribute('status'))
                        ? $incident->getAttribute('status')
                        : null
                ),
            'status_id' => $incident->status_id !== null
                ? (int) $incident->status_id
                : null,
            'category' => $categoryRelation instanceof IncidentCategory
                ? (
                    $categoryRelation->category_name
                    ?? $categoryRelation->name
                    ?? null
                )
                : (
                    $incident->getAttribute('category_name')
                    ?? $incident->getAttribute('incident_type')
                    ?? $incident->getAttribute('type')
                ),
            'category_id' => $incident->category_id !== null
                ? (int) $incident->category_id
                : null,
            'barangay' => $incident->barangay?->barangay_name
                ?? $incident->barangay?->name,
            'barangay_id' => $incident->barangay_id !== null
                ? (int) $incident->barangay_id
                : null,
            'location_address' => $locationAddress,
            'reporter' => $incident->reporter
                ? [
                    'id' => (int) $incident->reporter->id,
                    'name' => (string) $incident->reporter->name,
                    'email' => $incident->reporter->email,
                    'contact_number' =>
                        $incident->reporter->contact_number,
                ]
                : null,
            'assigned_tanod' => $incident->assignedTanod
                ? [
                    'id' => (int) $incident->assignedTanod->id,
                    'user_id' =>
                        $incident->assignedTanod->user_id !== null
                            ? (int) $incident->assignedTanod->user_id
                            : null,
                    'name' => (string) (
                        $incident->assignedTanod->user?->name
                        ?? $incident->assignedTanod->name
                        ?? 'Tanod #' . $incident->assignedTanod->id
                    ),
                ]
                : null,
            'assigned_to' => $incident->assigned_to !== null
                ? (int) $incident->assigned_to
                : null,
            'incident_datetime' =>
                $incident->incident_datetime?->toIso8601String(),
            'reported_at' =>
                $reportedAt?->toIso8601String(),
            'created_at' =>
                $incident->created_at?->toIso8601String(),
            'updated_at' =>
                $incident->updated_at?->toIso8601String(),
        ];

        if (! $detailed) {
            return $data;
        }

        $data['persons_involved'] =
            $incident->persons_involved;

        $data['location'] = [
            'address' => $locationAddress,
            'barangay' => $incident->barangay?->barangay_name
                ?? $incident->barangay?->name,
            'latitude' => $this->incidentLatitude($incident),
            'longitude' => $this->incidentLongitude($incident),
            'map_location_name' =>
                $incident->map_location_name,
        ];

        $data['evidence'] = $incident->evidence
            ->sortBy('id')
            ->values()
            ->map(
                fn (Evidence $evidence): array => [
                    'id' => (int) $evidence->id,
                    'file_name' => (string) (
                        $evidence->file_name
                        ?? 'Evidence'
                    ),
                    'file_type' => $evidence->file_type,
                    'mime_type' => $evidence->mime_type,
                    'file_size' => $evidence->file_size !== null
                        ? (int) $evidence->file_size
                        : null,
                    'uploaded_at' =>
                        $evidence->uploaded_at?->toIso8601String()
                        ?? $evidence->created_at?->toIso8601String(),
                    'uploaded_by' => $evidence->uploader
                        ? [
                            'id' => (int) $evidence->uploader->id,
                            'name' => (string) $evidence->uploader->name,
                        ]
                        : null,
                    'url' => '/api/v1/incidents/'
                        . $incident->id
                        . '/evidence/'
                        . $evidence->id,
                ]
            );

        $data['status_history'] = $incident->statusHistories
            ->sortByDesc(
                fn (IncidentStatusHistory $history) =>
                    $history->status_changed_at
                    ?? $history->created_at
            )
            ->values()
            ->map(
                fn (IncidentStatusHistory $history): array => [
                    'id' => (int) $history->id,
                    'status_id' => $history->status_id !== null
                        ? (int) $history->status_id
                        : null,
                    'status' => $history->status?->status_name,
                    'remarks' => $history->remarks,
                    'updated_by' => $history->updatedBy
                        ? [
                            'id' => (int) $history->updatedBy->id,
                            'name' => (string) $history->updatedBy->name,
                        ]
                        : null,
                    'status_changed_at' =>
                        $history->status_changed_at?->toIso8601String()
                        ?? $history->created_at?->toIso8601String(),
                ]
            );

        $data['escalations'] = $incident->escalations
            ->sortByDesc(
                fn (IncidentEscalation $escalation) =>
                    $escalation->escalated_at
                    ?? $escalation->created_at
            )
            ->values()
            ->map(
                fn (IncidentEscalation $escalation): array => [
                    'id' => (int) $escalation->id,
                    'agency' => (string) $escalation->agency,
                    'reason' => $escalation->reason,
                    'escalated_by' => $escalation->escalatedBy
                        ? [
                            'id' => (int) $escalation->escalatedBy->id,
                            'name' => (string) $escalation->escalatedBy->name,
                        ]
                        : null,
                    'escalated_at' =>
                        $escalation->escalated_at?->toIso8601String()
                        ?? $escalation->created_at?->toIso8601String(),
                ]
            );

        $data['messages'] = $incident->messages
            ->sortBy('created_at')
            ->values()
            ->map(
                fn (IncidentMessage $message): array => [
                    'id' => (int) $message->id,
                    'message' => (string) $message->message,
                    'user' => $message->user
                        ? [
                            'id' => (int) $message->user->id,
                            'name' => (string) $message->user->name,
                            'role' => (string) $message->user->role,
                        ]
                        : null,
                    'created_at' =>
                        $message->created_at?->toIso8601String(),
                ]
            );

        $data['related_cases'] = $incident->caseRecords
            ->sortByDesc('created_at')
            ->values()
            ->map(
                fn ($caseRecord): array => [
                    'id' => (int) $caseRecord->id,
                    'case_number' => $caseRecord->case_number
                        ?? 'Case #' . $caseRecord->id,
                    'case_type' => $caseRecord->case_type,
                    'case_type_label' =>
                        $caseRecord->display_type,
                    'status' => $caseRecord->status,
                    'status_label' =>
                        $caseRecord->display_status,
                    'hearing_date' =>
                        $caseRecord->hearing_date?->toDateString(),
                    'handled_by' => $caseRecord->handled_by,
                    'resolution' => $caseRecord->resolution,
                    'notes' => $caseRecord->notes,
                    'created_at' =>
                        $caseRecord->created_at?->toIso8601String(),
                ]
            );

        return $data;
    }

    private function attachIncidentOwner(
        Incident $incident,
        Request $request
    ): void {
        $user = $request->user();

        if (! $user instanceof User) {
            return;
        }

        $userId = (int) $user->id;
        $role = strtolower((string) $user->role);

        foreach ([
            'reporter_id',
            'created_by',
            'submitted_by',
            'reported_by',
        ] as $column) {
            if (Schema::hasColumn('incidents', $column)) {
                $incident->setAttribute($column, $userId);
            }
        }

        if ($role !== 'resident') {
            return;
        }

        foreach ([
            'user_id',
            'resident_user_id',
        ] as $column) {
            if (Schema::hasColumn('incidents', $column)) {
                $incident->setAttribute($column, $userId);
            }
        }

        if (! Schema::hasColumn('incidents', 'resident_id')) {
            return;
        }

        $residentProfileId =
            $this->residentProfileIdForUser($userId);

        if ($residentProfileId) {
            $incident->resident_id = $residentProfileId;
        } elseif (
            ! Schema::hasTable('residents')
            || ! Schema::hasColumn('residents', 'user_id')
        ) {
            $incident->resident_id = $userId;
        }
    }

    private function applyResidentIncidentOwnerFilter(
        EloquentBuilder $query,
        int $userId
    ): void {
        $residentIds =
            $this->residentProfileIdsForUser($userId);

        $query->where(
            function (EloquentBuilder $ownerQuery) use (
                $userId,
                $residentIds
            ): void {
                $hasCondition = false;

                foreach ([
                    'reporter_id',
                    'user_id',
                    'created_by',
                    'submitted_by',
                    'reported_by',
                    'resident_user_id',
                ] as $column) {
                    if (
                        ! Schema::hasColumn(
                            'incidents',
                            $column
                        )
                    ) {
                        continue;
                    }

                    if (! $hasCondition) {
                        $ownerQuery->where(
                            'incidents.' . $column,
                            $userId
                        );
                        $hasCondition = true;
                    } else {
                        $ownerQuery->orWhere(
                            'incidents.' . $column,
                            $userId
                        );
                    }
                }

                if (
                    Schema::hasColumn(
                        'incidents',
                        'resident_id'
                    )
                ) {
                    if (! $hasCondition) {
                        $ownerQuery->whereIn(
                            'incidents.resident_id',
                            $residentIds
                        );
                        $hasCondition = true;
                    } else {
                        $ownerQuery->orWhereIn(
                            'incidents.resident_id',
                            $residentIds
                        );
                    }
                }

                if (! $hasCondition) {
                    $ownerQuery->whereRaw('1 = 0');
                }
            }
        );
    }

    private function residentProfileIdForUser(
        int $userId
    ): ?int {
        if (
            ! Schema::hasTable('residents')
            || ! Schema::hasColumn('residents', 'user_id')
        ) {
            return null;
        }

        $residentId = DB::table('residents')
            ->where('user_id', $userId)
            ->value('id');

        return $residentId
            ? (int) $residentId
            : null;
    }

    private function residentProfileIdsForUser(
        int $userId
    ): array {
        $ids = collect([$userId]);

        if (
            Schema::hasTable('residents')
            && Schema::hasColumn('residents', 'user_id')
        ) {
            $ids = $ids->merge(
                DB::table('residents')
                    ->where('user_id', $userId)
                    ->pluck('id')
            );
        }

        return $ids
            ->filter()
            ->map(
                fn ($id): int => (int) $id
            )
            ->unique()
            ->values()
            ->all();
    }

    private function storeIncidentLocation(
        Incident $incident,
        array $validated
    ): void {
        $incidentUpdates = [];

        if (Schema::hasColumn('incidents', 'location_address')) {
            $incidentUpdates['location_address'] =
                $validated['location_address'];
        }

        if (Schema::hasColumn('incidents', 'location')) {
            $incidentUpdates['location'] =
                $validated['location_address'];
        }

        if (Schema::hasColumn('incidents', 'latitude')) {
            $incidentUpdates['latitude'] =
                $validated['latitude'] ?? null;
        }

        if (Schema::hasColumn('incidents', 'longitude')) {
            $incidentUpdates['longitude'] =
                $validated['longitude'] ?? null;
        }

        if (Schema::hasColumn('incidents', 'map_location_name')) {
            $incidentUpdates['map_location_name'] =
                $validated['location_address'];
        }

        if (Schema::hasColumn('incidents', 'map_severity')) {
            $incidentUpdates['map_severity'] =
                $validated['priority'];
        }

        if ($incidentUpdates !== []) {
            $incident->forceFill($incidentUpdates)->save();
        }

        if (! Schema::hasTable('incident_locations')) {
            return;
        }

        $columns = Schema::getColumnListing(
            'incident_locations'
        );

        $locationData = [];

        if (in_array('incident_id', $columns, true)) {
            $locationData['incident_id'] = $incident->id;
        }

        if (in_array('barangay_id', $columns, true)) {
            $locationData['barangay_id'] =
                $validated['barangay_id'];
        }

        if (in_array('location_address', $columns, true)) {
            $locationData['location_address'] =
                $validated['location_address'];
        }

        if (in_array('address', $columns, true)) {
            $locationData['address'] =
                $validated['location_address'];
        }

        if (in_array('latitude', $columns, true)) {
            $locationData['latitude'] =
                $validated['latitude'] ?? null;
        }

        if (in_array('longitude', $columns, true)) {
            $locationData['longitude'] =
                $validated['longitude'] ?? null;
        }

        if (in_array('created_at', $columns, true)) {
            $locationData['created_at'] = now();
        }

        if (in_array('updated_at', $columns, true)) {
            $locationData['updated_at'] = now();
        }

        if ($locationData !== []) {
            DB::table('incident_locations')->insert(
                $locationData
            );
        }
    }

    private function storeIncidentEvidence(
        Request $request,
        Incident $incident,
        array &$storedPaths
    ): void {
        if (! Schema::hasTable('evidence')) {
            return;
        }

        $uploadedFiles = $request->file('evidence', []);

        if ($uploadedFiles instanceof \Illuminate\Http\UploadedFile) {
            $uploadedFiles = [$uploadedFiles];
        }

        if (! is_array($uploadedFiles)) {
            $uploadedFiles = [];
        }

        $maxFiles = (int) config(
            'secure_uploads.policies.incident_evidence.max_files',
            5
        );

        $uploadedFiles = collect($uploadedFiles)
            ->flatten()
            ->filter(
                fn ($file): bool =>
                    $file instanceof \Illuminate\Http\UploadedFile
                    && $file->isValid()
            )
            ->take($maxFiles)
            ->values();

        foreach ($uploadedFiles as $file) {
            $path = $this->secureUploads->store(
                $file,
                'incident_evidence'
            );

            $storedPaths[] = $path;

            Evidence::query()->create([
                'incident_id' => $incident->id,
                'uploaded_by' => $request->user()->id,
                'file_name' =>
                    $this->secureUploads->safeOriginalName(
                        $file->getClientOriginalName(),
                        basename($path)
                    ),
                'file_path' => $path,
                'file_type' => pathinfo(
                    $path,
                    PATHINFO_EXTENSION
                ),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_at' => now(),
            ]);
        }
    }

    private function notifyIncidentCreated(
        Incident $incident
    ): void {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $incident->loadMissing([
            'category',
            'reporter',
        ]);

        $receiverIds = User::query()
            ->whereIn(
                'role',
                ['admin', 'official', 'dao']
            )
            ->when(
                Schema::hasColumn('users', 'is_active'),
                function (EloquentBuilder $query): void {
                    $query->where('is_active', true);
                }
            )
            ->pluck('id')
            ->push($incident->reporter_id)
            ->filter()
            ->unique()
            ->values();

        foreach ($receiverIds as $userId) {
            UserNotification::query()->updateOrCreate(
                [
                    'user_id' => $userId,
                    'type' => 'incident_reported',
                    'source_id' => $incident->id,
                ],
                [
                    'user_id' => $userId,
                    'type' => 'incident_reported',
                    'source_id' => $incident->id,
                    'title' => 'New incident report',
                    'message' =>
                        'A new incident report was submitted: '
                        . $incident->display_title
                        . '.',
                    'is_read' => false,
                    'read_at' => null,
                ]
            );
        }
    }

    private function createTanodTaskFromIncident(
        Incident $incident,
        Request $request
    ): void {
        if (
            ! Schema::hasTable('tanod_tasks')
            || ! Schema::hasTable('tanod_task_responses')
            || ! Schema::hasColumn(
                'tanod_tasks',
                'incident_id'
            )
        ) {
            return;
        }

        $task = TanodTask::query()->create([
            'incident_id' => $incident->id,
            'created_by' => $request->user()->id,
            'title' => $incident->display_title,
            'description' => $incident->display_description,
            'location' =>
                $this->incidentLocationAddress($incident),
            'task_datetime' => now(),
            'due_at' => null,
            'priority' => match ($incident->priority ?? 'normal') {
                'critical' => 'urgent',
                'high' => 'high',
                'moderate' => 'normal',
                'low' => 'low',
                default => 'normal',
            },
            'status' => 'open',
        ]);

        $tanods = Employee::query()
            ->with('user')
            ->tanods()
            ->active()
            ->get();

        foreach ($tanods as $tanod) {
            TanodTaskResponse::query()->create([
                'tanod_task_id' => $task->id,
                'employee_id' => $tanod->id,
                'user_id' => $tanod->user_id,
                'response_status' => 'pending',
                'response_note' => null,
                'responded_at' => null,
            ]);

            if ($tanod->user_id) {
                $this->notifyTanodAboutIncidentTask(
                    $task,
                    $incident,
                    (int) $tanod->user_id
                );
            }
        }
    }

    private function notifyTanodAboutIncidentTask(
        TanodTask $task,
        Incident $incident,
        int $tanodUserId
    ): void {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        UserNotification::query()->updateOrCreate(
            [
                'user_id' => $tanodUserId,
                'type' => 'tanod_task',
                'source_id' => $task->id,
            ],
            [
                'user_id' => $tanodUserId,
                'type' => 'tanod_task',
                'source_id' => $task->id,
                'title' => 'New incident response task',
                'message' =>
                    'A new incident response task is waiting for your response: '
                    . $incident->display_title
                    . '.',
                'is_read' => false,
                'read_at' => null,
            ]
        );
    }


    private function notifyIncidentUsers(
        Incident $incident,
        string $title,
        string $message,
        string $type = 'incident_update'
    ): void {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $incident->loadMissing([
            'reporter',
            'assignedTanod.user',
        ]);

        $managementUserIds = User::query()
            ->whereIn(
                'role',
                ['admin', 'official', 'dao']
            )
            ->when(
                Schema::hasColumn('users', 'is_active'),
                function (EloquentBuilder $query): void {
                    $query->where('is_active', true);
                }
            )
            ->pluck('id');

        $userIds = collect()
            ->merge($managementUserIds)
            ->push($incident->reporter_id)
            ->push($incident->assignedTanod?->user_id)
            ->filter()
            ->unique()
            ->values();

        foreach ($userIds as $userId) {
            $notificationData = [
                'user_id' => $userId,
                'type' => $type,
                'source_id' => $incident->id,
                'title' => $title,
                'message' => $message,
                'is_read' => false,
                'read_at' => null,
            ];

            if (
                Schema::hasColumn(
                    'notifications',
                    'acknowledged_by'
                )
            ) {
                $notificationData['acknowledged_by'] =
                    null;
            }

            if (
                Schema::hasColumn(
                    'notifications',
                    'acknowledged_at'
                )
            ) {
                $notificationData['acknowledged_at'] =
                    null;
            }

            UserNotification::query()->create(
                $notificationData
            );
        }
    }

    private function createTanodAlert(
        Incident $incident,
        string $type,
        string $title,
        string $message
    ): void {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $incident->loadMissing('assignedTanod.user');

        $assignedTanodUser =
            $incident->assignedTanod?->user;

        if (! $assignedTanodUser) {
            return;
        }

        if (
            (int) $assignedTanodUser->id
            === (int) $incident->reporter_id
        ) {
            return;
        }

        $notificationData = [
            'user_id' => $assignedTanodUser->id,
            'type' => $type,
            'source_id' => $incident->id,
            'title' => $title,
            'message' => $message,
            'is_read' => false,
            'read_at' => null,
        ];

        if (
            Schema::hasColumn(
                'notifications',
                'acknowledged_by'
            )
        ) {
            $notificationData['acknowledged_by'] =
                null;
        }

        if (
            Schema::hasColumn(
                'notifications',
                'acknowledged_at'
            )
        ) {
            $notificationData['acknowledged_at'] =
                null;
        }

        UserNotification::query()->create(
            $notificationData
        );
    }

    private function incidentRelatedFilePaths(
        int $incidentId
    ): array {
        $fileTables = [
            'evidence',
            'incident_evidence',
            'incident_evidences',
            'incident_attachments',
            'attachments',
        ];

        $fileColumns = [
            'file_path',
            'path',
            'file_url',
            'url',
        ];

        $paths = [];

        foreach ($fileTables as $table) {
            if (
                ! Schema::hasTable($table)
                || ! Schema::hasColumn(
                    $table,
                    'incident_id'
                )
            ) {
                continue;
            }

            $existingFileColumns = array_values(
                array_filter(
                    $fileColumns,
                    fn (string $column): bool =>
                        Schema::hasColumn(
                            $table,
                            $column
                        )
                )
            );

            if ($existingFileColumns === []) {
                continue;
            }

            $records = DB::table($table)
                ->where(
                    'incident_id',
                    $incidentId
                )
                ->get($existingFileColumns);

            foreach ($records as $record) {
                foreach ($existingFileColumns as $column) {
                    $path = $record->{$column} ?? null;

                    if (
                        ! is_scalar($path)
                        || trim((string) $path) === ''
                        || str_starts_with(
                            (string) $path,
                            'http'
                        )
                    ) {
                        continue;
                    }

                    $paths[] = (string) $path;
                }
            }
        }

        return array_values(
            array_unique($paths)
        );
    }

    private function deleteIncidentRelatedRows(
        int $incidentId
    ): void {
        $relatedTables = [
            'evidence',
            'incident_evidence',
            'incident_evidences',
            'incident_attachments',
            'attachments',
            'incident_messages',
            'incident_status_histories',
            'incident_status_history',
            'incident_escalations',
            'case_records',
        ];

        foreach ($relatedTables as $table) {
            if (
                Schema::hasTable($table)
                && Schema::hasColumn(
                    $table,
                    'incident_id'
                )
            ) {
                DB::table($table)
                    ->where(
                        'incident_id',
                        $incidentId
                    )
                    ->delete();
            }
        }

        if (
            Schema::hasTable('notifications')
            && Schema::hasColumn(
                'notifications',
                'source_id'
            )
        ) {
            DB::table('notifications')
                ->where(
                    'source_id',
                    $incidentId
                )
                ->when(
                    Schema::hasColumn(
                        'notifications',
                        'type'
                    ),
                    function (QueryBuilder $query): void {
                        $query->whereIn(
                            'type',
                            [
                                'incident',
                                'incident_reported',
                                'incident_update',
                                'incident_updated',
                                'incident_status_update',
                                'status_update',
                                'dispatch',
                                'escalation',
                                'emergency',
                                'resolved',
                                'community_problem',
                                'community',
                            ]
                        );
                    }
                )
                ->delete();
        }
    }

    private function barangayIsReferenced(
        int $barangayId
    ): bool {
        $references = [
            ['incidents', 'barangay_id'],
            ['users', 'barangay_id'],
            ['employees', 'barangay_id'],
            ['residents', 'barangay_id'],
            ['incident_locations', 'barangay_id'],
        ];

        foreach ($references as [$table, $column]) {
            if (
                Schema::hasTable($table)
                && Schema::hasColumn(
                    $table,
                    $column
                )
                && DB::table($table)
                    ->where(
                        $column,
                        $barangayId
                    )
                    ->exists()
            ) {
                return true;
            }
        }

        return false;
    }

    private function agencyOptions(): array
    {
        return [
            'PNP' => 'PNP',
            'BFP' => 'BFP',
            'MDRRMO' => 'MDRRMO',
            'DSWD' => 'DSWD',
            'DOH' => 'DOH',
            'Red Cross' => 'Red Cross',
            'Municipal Government' =>
                'Municipal Government',
        ];
    }

    private function incidentLocationAddress(
        Incident $incident
    ): ?string {
        $locationRelation = $incident->relationLoaded('location')
            ? $incident->getRelation('location')
            : null;

        foreach ([
            $locationRelation?->location_address,
            $locationRelation?->address,
            $incident->getAttribute('location_address'),
            $incident->getAttribute('location'),
            $incident->map_location_name,
        ] as $value) {
            if (
                is_scalar($value)
                && trim((string) $value) !== ''
            ) {
                return (string) $value;
            }
        }

        return null;
    }

    private function incidentLatitude(
        Incident $incident
    ): ?float {
        $locationRelation = $incident->relationLoaded('location')
            ? $incident->getRelation('location')
            : null;

        $value = $locationRelation?->latitude
            ?? $incident->latitude;

        return is_numeric($value)
            ? (float) $value
            : null;
    }

    private function incidentLongitude(
        Incident $incident
    ): ?float {
        $locationRelation = $incident->relationLoaded('location')
            ? $incident->getRelation('location')
            : null;

        $value = $locationRelation?->longitude
            ?? $incident->longitude;

        return is_numeric($value)
            ? (float) $value
            : null;
    }

    private function integerFilter(
        Request $request,
        array $keys
    ): ?int {
        foreach ($keys as $key) {
            $raw = trim(
                (string) $request->query($key, '')
            );

            if ($raw === '' || strtolower($raw) === 'all') {
                continue;
            }

            if (ctype_digit($raw) && (int) $raw > 0) {
                return (int) $raw;
            }
        }

        return null;
    }
}
