<?php

namespace App\Http\Controllers;

use App\Models\CaseRecord;
use App\Models\Incident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CaseManagementController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', CaseRecord::class);

        $search = trim((string) $request->query('search', ''));

        $cases = CaseRecord::query()
            ->with(['incident', 'creator', 'updater'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('case_number', 'like', "%{$search}%")
                        ->orWhere('subject_name', 'like', "%{$search}%")
                        ->orWhere('contact', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhere('incident_title', 'like', "%{$search}%")
                        ->orWhere('handled_by', 'like', "%{$search}%")
                        ->orWhereHas('incident', function ($incidentQuery) use ($search): void {
                            $incidentQuery
                                ->where('incident_code', 'like', "%{$search}%")
                                ->orWhere('incident_title', 'like', "%{$search}%")
                                ->orWhere('title', 'like', "%{$search}%");
                        });
                });
            })
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
                'search' => $search,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', CaseRecord::class);

        $validated = $request->validate($this->validationRules());

        DB::transaction(function () use ($request, $validated): void {
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

            CaseRecord::create([
                'case_number' => $caseNumber,
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
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        });

        return redirect()
            ->route('admin.cases.index')
            ->with('success', 'Case record created successfully.');
    }

    public function update(
        Request $request,
        CaseRecord $caseRecord
    ): RedirectResponse {
        Gate::authorize('update', $caseRecord);

        $validated = $request->validate(
            $this->validationRules($caseRecord)
        );

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

        return redirect()
            ->route('admin.cases.index')
            ->with('success', 'Case record updated successfully.');
    }

    public function destroy(CaseRecord $caseRecord): RedirectResponse
    {
        Gate::authorize('delete', $caseRecord);

        DB::transaction(function () use ($caseRecord): void {
            $lockedCase = CaseRecord::query()
                ->lockForUpdate()
                ->findOrFail($caseRecord->getKey());

            $lockedCase->delete();
        });

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
            'contact' => ['nullable', 'string', 'max:50'],
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
