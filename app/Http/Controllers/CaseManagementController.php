<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RecordsOperationalActivity;
use App\Models\CaseRecord;
use App\Models\Incident;
use App\Support\SqlLikePattern;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CaseManagementController extends Controller
{
    use RecordsOperationalActivity;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', CaseRecord::class);

        $search = SqlLikePattern::normalize(
            $request->query('search')
        );

        $searchPattern = SqlLikePattern::contains(
            $search
        );

        $cases = CaseRecord::query()
            ->with(['incident', 'creator', 'updater'])
            ->when(
                $searchPattern !== null,
                function ($query) use ($searchPattern): void {
                    $query->where(
                        function ($searchQuery) use ($searchPattern): void {
                            SqlLikePattern::whereContains(
                                $searchQuery,
                                'case_number',
                                $searchPattern
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
                                    $searchPattern
                                );
                            }

                            $searchQuery->orWhereHas(
                                'incident',
                                function ($incidentQuery) use ($searchPattern): void {
                                    SqlLikePattern::whereContains(
                                        $incidentQuery,
                                        'incident_code',
                                        $searchPattern
                                    );

                                    SqlLikePattern::orWhereContains(
                                        $incidentQuery,
                                        'incident_title',
                                        $searchPattern
                                    );

                                    SqlLikePattern::orWhereContains(
                                        $incidentQuery,
                                        'title',
                                        $searchPattern
                                    );
                                }
                            );
                        }
                    );
                }
            )
            ->orderByRaw('CAST(case_number AS UNSIGNED) ASC')
            ->paginate(10)
            ->withQueryString();

        $incidents = Incident::query()
            ->select([
                'id',
                'incident_code',
                'incident_title',
                'title',
            ])
            ->latest()
            ->limit(100)
            ->get();

        return view('cases.index', [
            'cases' => $cases,
            'incidents' => $incidents,
            'caseTypes' => $this->caseTypes(),
            'caseStatuses' => $this->caseStatuses(),
            'filters' => [
                'search' => $search ?? '',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
{
    Gate::authorize('create', CaseRecord::class);

    $validated = $request->validate(
        $this->validationRules()
    );

    $createdCase = null;

    DB::transaction(function () use (
        $request,
        $validated,
        &$createdCase
    ): void {
        $caseNumber = trim(
            (string) ($validated['case_number'] ?? '')
        );

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
            'subject_name' => $validated['subject_name'],

            'contact' => $this->nullableText(
                $validated['contact'] ?? null
            ),

            'address' => $this->nullableText(
                $validated['address'] ?? null
            ),

            'incident_id' => $validated['incident_id'] ?? null,

            'incident_title' => $this->incidentTitle(
                $validated['incident_id'] ?? null,
                $validated['incident_title'] ?? null
            ),

            'status' => $validated['status'],

            'hearing_date' =>
                $validated['hearing_date'] ?? null,

            'handled_by' => $this->nullableText(
                $validated['handled_by'] ?? null
            ),

            'resolution' => $this->nullableText(
                $validated['resolution'] ?? null
            ),

            'notes' => $this->nullableText(
                $validated['notes'] ?? null
            ),

            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | Case creation integrity check
    |--------------------------------------------------------------------------
    |
    | The transaction above must return a persisted CaseRecord. This guard
    | prevents the application from continuing with an incomplete result
    | and tells static analysis that $createdCase is no longer nullable.
    |
    */
    if (
        ! $createdCase instanceof CaseRecord
        || ! $createdCase->exists
    ) {
        throw new \RuntimeException(
            'Case creation completed without a persisted case record.'
        );
    }

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

    return redirect()
        ->route('admin.cases.index')
        ->with(
            'success',
            'Case record created successfully.'
        );
}

    public function update(
        Request $request,
        CaseRecord $caseRecord
    ): RedirectResponse {
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
                'case_number' => trim($validated['case_number']),
                'case_type' => $validated['case_type'],
                'subject_name' => $validated['subject_name'],
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

        return redirect()
            ->route('admin.cases.index')
            ->with('success', 'Case record updated successfully.');
    }

    public function destroy(CaseRecord $caseRecord): RedirectResponse
    {
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
        );

        return redirect()
            ->route('admin.cases.index')
            ->with('success', 'Case record deleted successfully.');
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
                'regex:/^[0-9+()\-.\s]*$/',
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
