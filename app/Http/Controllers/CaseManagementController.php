<?php

namespace App\Http\Controllers;

use App\Models\CaseRecord;
use App\Models\Incident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CaseManagementController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $cases = CaseRecord::query()
            ->with(['incident', 'creator', 'updater'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('case_number', 'like', "%{$search}%")
                        ->orWhere('subject_name', 'like', "%{$search}%")
                        ->orWhere('contact', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhere('incident_title', 'like', "%{$search}%")
                        ->orWhere('handled_by', 'like', "%{$search}%")
                        ->orWhereHas('incident', function ($incidentQuery) use ($search) {
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
        $validated = $request->validate([
            'case_number' => ['nullable', 'string', 'max:50'],
            'case_type' => ['required', Rule::in(array_keys($this->caseTypes()))],
            'subject_name' => ['required', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'incident_id' => ['nullable', 'integer', 'exists:incidents,id'],
            'incident_title' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(array_keys($this->caseStatuses()))],
            'hearing_date' => ['nullable', 'date'],
            'handled_by' => ['nullable', 'string', 'max:255'],
            'resolution' => ['nullable', 'string', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        DB::transaction(function () use ($validated) {
            $incidentTitle = $validated['incident_title'] ?? null;

            if (! empty($validated['incident_id']) && empty($incidentTitle)) {
                $incident = Incident::find($validated['incident_id']);
                $incidentTitle = $incident?->display_title;
            }

            $caseNumber = trim((string) ($validated['case_number'] ?? ''));

            if ($caseNumber === '' || strtoupper($caseNumber) === 'AUTO-GENERATED') {
                $caseNumber = $this->generateCaseNumber();
            }

            if (CaseRecord::query()->where('case_number', $caseNumber)->exists()) {
                throw ValidationException::withMessages([
                    'case_number' => 'The case number has already been taken.',
                ]);
            }

            CaseRecord::create([
                'case_number' => $caseNumber,
                'case_type' => $validated['case_type'],
                'subject_name' => $validated['subject_name'],
                'contact' => $validated['contact'] ?? null,
                'address' => $validated['address'] ?? null,
                'incident_id' => $validated['incident_id'] ?? null,
                'incident_title' => $incidentTitle,
                'status' => $validated['status'],
                'hearing_date' => $validated['hearing_date'] ?? null,
                'handled_by' => $validated['handled_by'] ?? null,
                'resolution' => $validated['resolution'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
        });

        return redirect()
            ->route('admin.cases.index')
            ->with('success', 'Case record created successfully.');
    }

    public function update(Request $request, CaseRecord $caseRecord): RedirectResponse
    {
        $validated = $request->validate([
            'case_number' => [
                'required',
                'string',
                'max:50',
                'not_in:AUTO-GENERATED,auto-generated,Auto-Generated',
                Rule::unique('case_records', 'case_number')->ignore($caseRecord->getKey()),
            ],
            'case_type' => ['required', Rule::in(array_keys($this->caseTypes()))],
            'subject_name' => ['required', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'incident_id' => ['nullable', 'integer', 'exists:incidents,id'],
            'incident_title' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(array_keys($this->caseStatuses()))],
            'hearing_date' => ['nullable', 'date'],
            'handled_by' => ['nullable', 'string', 'max:255'],
            'resolution' => ['nullable', 'string', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        DB::transaction(function () use ($caseRecord, $validated) {
            $incidentTitle = $validated['incident_title'] ?? null;

            if (! empty($validated['incident_id']) && empty($incidentTitle)) {
                $incident = Incident::find($validated['incident_id']);
                $incidentTitle = $incident?->display_title;
            }

            $caseRecord->update([
                'case_number' => $validated['case_number'],
                'case_type' => $validated['case_type'],
                'subject_name' => $validated['subject_name'],
                'contact' => $validated['contact'] ?? null,
                'address' => $validated['address'] ?? null,
                'incident_id' => $validated['incident_id'] ?? null,
                'incident_title' => $incidentTitle,
                'status' => $validated['status'],
                'hearing_date' => $validated['hearing_date'] ?? null,
                'handled_by' => $validated['handled_by'] ?? null,
                'resolution' => $validated['resolution'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'updated_by' => Auth::id(),
            ]);
        });

        return redirect()
            ->route('admin.cases.index')
            ->with('success', 'Case record updated successfully.');
    }

    public function destroy(CaseRecord $caseRecord): RedirectResponse
    {
        $caseRecord->delete();

        return redirect()
            ->route('admin.cases.index')
            ->with('success', 'Case record deleted successfully.');
    }

    private function generateCaseNumber(): string
    {
        $lastNumber = CaseRecord::query()
            ->lockForUpdate()
            ->whereRaw("case_number REGEXP '^[0-9]+$'")
            ->selectRaw('MAX(CAST(case_number AS UNSIGNED)) as max_number')
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