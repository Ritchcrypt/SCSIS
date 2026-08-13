<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\RecordsOperationalActivity;
use App\Http\Controllers\Controller;
use App\Models\CaseRecord;
use App\Models\Incident;
use App\Support\SqlLikePattern;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CaseManagementController extends Controller
{
    use RecordsOperationalActivity;

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CaseRecord::class);

        $search = SqlLikePattern::normalize($request->query('search'));
        $pattern = SqlLikePattern::contains($search);

        $cases = CaseRecord::query()
            ->with(['incident', 'creator', 'updater'])
            ->when($pattern !== null, function ($query) use ($pattern): void {
                $query->where(function ($searchQuery) use ($pattern): void {
                    SqlLikePattern::whereContains(
                        $searchQuery,
                        'case_number',
                        $pattern
                    );

                    foreach ([
                        'subject_name',
                        'contact',
                        'address',
                        'incident_title',
                        'handled_by',
                    ] as $column) {
                        SqlLikePattern::orWhereContains(
                            $searchQuery,
                            $column,
                            $pattern
                        );
                    }

                    $searchQuery->orWhereHas(
                        'incident',
                        function ($incidentQuery) use ($pattern): void {
                            SqlLikePattern::whereContains(
                                $incidentQuery,
                                'incident_code',
                                $pattern
                            );
                            SqlLikePattern::orWhereContains(
                                $incidentQuery,
                                'incident_title',
                                $pattern
                            );
                            SqlLikePattern::orWhereContains(
                                $incidentQuery,
                                'title',
                                $pattern
                            );
                        }
                    );
                });
            })
            ->orderByRaw('CAST(case_number AS UNSIGNED) ASC')
            ->paginate(10)
            ->withQueryString();

        $incidents = Incident::query()
            ->select(['id', 'incident_code', 'incident_title', 'title'])
            ->latest()
            ->limit(100)
            ->get()
            ->map(function (Incident $incident): array {
                $title = $incident->incident_title
                    ?? $incident->title
                    ?? 'Untitled Incident';
                $code = $incident->incident_code
                    ?? 'INC-' . $incident->id;

                return [
                    'id' => (int) $incident->id,
                    'code' => $code,
                    'title' => $title,
                    'label' => $code . ' — ' . $title,
                ];
            })
            ->values()
            ->all();

        return response()
            ->json([
                'data' => $cases->getCollection()
                    ->map(fn (CaseRecord $case): array => $this->serializeCase($case))
                    ->values()
                    ->all(),
                'options' => [
                    'case_types' => collect($this->caseTypes())
                        ->map(fn (string $label, string $value): array => [
                            'value' => $value,
                            'label' => $label,
                        ])
                        ->values()
                        ->all(),
                    'case_statuses' => collect($this->caseStatuses())
                        ->map(fn (string $label, string $value): array => [
                            'value' => $value,
                            'label' => $label,
                        ])
                        ->values()
                        ->all(),
                    'incidents' => $incidents,
                ],
                'filters' => ['search' => $search ?? ''],
                'permissions' => [
                    'can_create' => true,
                    'can_update' => true,
                    'can_delete' => true,
                ],
                'pagination' => [
                    'current_page' => $cases->currentPage(),
                    'last_page' => $cases->lastPage(),
                    'per_page' => $cases->perPage(),
                    'total' => $cases->total(),
                    'from' => $cases->firstItem(),
                    'to' => $cases->lastItem(),
                ],
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', CaseRecord::class);

        $validated = $request->validate($this->validationRules());
        $createdCase = null;

        DB::transaction(function () use ($request, $validated, &$createdCase): void {
            $caseNumber = trim((string) ($validated['case_number'] ?? ''));

            if (
                $caseNumber === ''
                || strtoupper($caseNumber) === 'AUTO-GENERATED'
            ) {
                $caseNumber = $this->generateCaseNumber();
            }

            if (
                CaseRecord::query()
                    ->where('case_number', $caseNumber)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'case_number' => 'The case number has already been taken.',
                ]);
            }

            $createdCase = CaseRecord::create([
                'case_number' => $caseNumber,
                'case_type' => $validated['case_type'],
                'subject_name' => trim((string) $validated['subject_name']),
                'contact' => $this->nullableText($validated['contact'] ?? null),
                'address' => $this->nullableText($validated['address'] ?? null),
                'incident_id' => $validated['incident_id'] ?? null,
                'incident_title' => $this->incidentTitle(
                    $validated['incident_id'] ?? null,
                    $validated['incident_title'] ?? null
                ),
                'status' => $validated['status'],
                'hearing_date' => $validated['hearing_date'] ?? null,
                'handled_by' => $this->nullableText($validated['handled_by'] ?? null),
                'resolution' => $this->nullableText($validated['resolution'] ?? null),
                'notes' => $this->nullableText($validated['notes'] ?? null),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        });

        if (! $createdCase instanceof CaseRecord || ! $createdCase->exists) {
            throw new \RuntimeException(
                'Case creation completed without a persisted case record.'
            );
        }

        $createdCase->load(['incident', 'creator', 'updater']);

        $this->recordOperationalActivity(
            event: 'case.created',
            category: 'case',
            description: 'A case record was created.',
            metadata: [
                'case_id' => (int) $createdCase->id,
                'case_number' => $createdCase->case_number,
                'case_type' => $createdCase->case_type,
                'status' => $createdCase->status,
                'incident_id' => $createdCase->incident_id,
            ],
            request: $request,
        );

        return response()
            ->json([
                'message' => 'Case record created successfully.',
                'data' => $this->serializeCase($createdCase),
            ], 201)
            ->header('Cache-Control', 'no-store, private');
    }

    public function update(
        Request $request,
        CaseRecord $caseRecord
    ): JsonResponse {
        Gate::authorize('update', $caseRecord);

        $validated = $request->validate(
            $this->validationRules($caseRecord)
        );

        $previousStatus = $caseRecord->status;
        $previousType = $caseRecord->case_type;
        $previousIncidentId = $caseRecord->incident_id;

        DB::transaction(function () use (
            $request,
            $caseRecord,
            $validated
        ): void {
            $lockedCase = CaseRecord::query()
                ->lockForUpdate()
                ->findOrFail($caseRecord->getKey());

            $lockedCase->update([
                'case_number' => trim((string) $validated['case_number']),
                'case_type' => $validated['case_type'],
                'subject_name' => trim((string) $validated['subject_name']),
                'contact' => $this->nullableText($validated['contact'] ?? null),
                'address' => $this->nullableText($validated['address'] ?? null),
                'incident_id' => $validated['incident_id'] ?? null,
                'incident_title' => $this->incidentTitle(
                    $validated['incident_id'] ?? null,
                    $validated['incident_title'] ?? null
                ),
                'status' => $validated['status'],
                'hearing_date' => $validated['hearing_date'] ?? null,
                'handled_by' => $this->nullableText($validated['handled_by'] ?? null),
                'resolution' => $this->nullableText($validated['resolution'] ?? null),
                'notes' => $this->nullableText($validated['notes'] ?? null),
                'updated_by' => $request->user()->id,
            ]);
        });

        $caseRecord->refresh();
        $caseRecord->load(['incident', 'creator', 'updater']);

        $this->recordOperationalActivity(
            event: 'case.updated',
            category: 'case',
            description: 'A case record was updated.',
            metadata: [
                'case_id' => (int) $caseRecord->id,
                'case_number' => $caseRecord->case_number,
                'previous_status' => $previousStatus,
                'new_status' => $caseRecord->status,
                'previous_case_type' => $previousType,
                'new_case_type' => $caseRecord->case_type,
                'previous_incident_id' => $previousIncidentId,
                'new_incident_id' => $caseRecord->incident_id,
            ],
            request: $request,
        );

        return response()
            ->json([
                'message' => 'Case record updated successfully.',
                'data' => $this->serializeCase($caseRecord),
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    public function destroy(
        Request $request,
        CaseRecord $caseRecord
    ): JsonResponse {
        Gate::authorize('delete', $caseRecord);

        $auditMetadata = [
            'case_id' => (int) $caseRecord->id,
            'case_number' => $caseRecord->case_number,
            'case_type' => $caseRecord->case_type,
            'status' => $caseRecord->status,
            'incident_id' => $caseRecord->incident_id,
        ];

        DB::transaction(function () use ($caseRecord): void {
            $lockedCase = CaseRecord::query()
                ->lockForUpdate()
                ->findOrFail($caseRecord->getKey());

            $lockedCase->delete();
        });

        $this->recordOperationalActivity(
            event: 'case.deleted',
            category: 'case',
            description: 'A case record was deleted.',
            metadata: $auditMetadata,
            request: $request,
        );

        return response()
            ->json(['message' => 'Case record deleted successfully.'])
            ->header('Cache-Control', 'no-store, private');
    }

    private function serializeCase(CaseRecord $case): array
    {
        return [
            'id' => (int) $case->id,
            'case_number' => (string) $case->case_number,
            'case_type' => (string) $case->case_type,
            'display_type' => $case->display_type,
            'subject_name' => (string) $case->subject_name,
            'contact' => $case->contact,
            'address' => $case->address,
            'incident_id' => $case->incident_id
                ? (int) $case->incident_id
                : null,
            'incident_title' => $case->incident_title,
            'display_incident_title' => $case->display_incident_title,
            'status' => (string) $case->status,
            'display_status' => $case->display_status,
            'hearing_date' => $case->hearing_date?->format('Y-m-d'),
            'handled_by' => $case->handled_by,
            'resolution' => $case->resolution,
            'notes' => $case->notes,
            'created_by' => $case->created_by ? (int) $case->created_by : null,
            'updated_by' => $case->updated_by ? (int) $case->updated_by : null,
            'created_by_name' => $case->creator?->name,
            'updated_by_name' => $case->updater?->name,
            'created_at' => $case->created_at?->toIso8601String(),
            'updated_at' => $case->updated_at?->toIso8601String(),
        ];
    }

    private function validationRules(
        ?CaseRecord $caseRecord = null
    ): array {
        $caseNumberRule = [
            $caseRecord ? 'required' : 'nullable',
            'string',
            'max:50',
            'not_regex:/[\x00-\x1F\x7F]/',
        ];

        if ($caseRecord) {
            $caseNumberRule[] =
                'not_in:AUTO-GENERATED,auto-generated,Auto-Generated';

            $caseNumberRule[] = Rule::unique(
                'case_records',
                'case_number'
            )->ignore($caseRecord->getKey());
        }

        return [
            'case_number' => $caseNumberRule,
            'case_type' => [
                'required',
                Rule::in(array_keys($this->caseTypes())),
            ],
            'subject_name' => ['required', 'string', 'max:255'],
            'contact' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[0-9+()\-.\\s]*$/',
            ],
            'address' => ['nullable', 'string', 'max:500'],
            'incident_id' => [
                'nullable',
                'integer',
                'exists:incidents,id',
            ],
            'incident_title' => ['nullable', 'string', 'max:255'],
            'status' => [
                'required',
                Rule::in(array_keys($this->caseStatuses())),
            ],
            'hearing_date' => ['nullable', 'date'],
            'handled_by' => ['nullable', 'string', 'max:255'],
            'resolution' => ['nullable', 'string', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    private function incidentTitle(
        int|string|null $incidentId,
        mixed $fallbackTitle
    ): ?string {
        if ($incidentId) {
            $incident = Incident::query()->find((int) $incidentId);

            return $incident?->display_title
                ?? $incident?->incident_title
                ?? $incident?->title;
        }

        return $this->nullableText($fallbackTitle);
    }

    private function nullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function generateCaseNumber(): string
    {
        $lastNumber = CaseRecord::query()
            ->lockForUpdate()
            ->whereRaw("case_number REGEXP '^[0-9]+$'")
            ->selectRaw(
                'MAX(CAST(case_number AS UNSIGNED)) as max_number'
            )
            ->value('max_number');

        return (string) ((int) $lastNumber + 1);
    }

    private function caseTypes(): array
    {
        return [
            'blotter' => 'Blotter',
            'mediation' => 'Mediation',
            'complaint' => 'Complaint',
            'referral' => 'Referral',
        ];
    }

    private function caseStatuses(): array
    {
        return [
            'open' => 'Open',
            'under_investigation' => 'Under Investigation',
            'mediation' => 'Mediation',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
        ];
    }
}
