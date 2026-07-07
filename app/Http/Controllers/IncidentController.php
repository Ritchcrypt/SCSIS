<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\IncidentEscalation;
use App\Models\IncidentMessage;
use App\Models\IncidentStatusHistory;
use App\Models\Status;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IncidentController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $incidents = Incident::query()
            ->with([
                'barangay',
                'category',
                'currentStatus',
                'location',
                'reporter',
                'resident.user',
                'assignedTanod.user',
            ])
            ->when($user->role === 'tanod', function ($query) use ($user) {
                $employeeId = $user->employee?->id;

                if ($employeeId) {
                    $query->where('assigned_to', $employeeId);
                } else {
                    $query->whereRaw('1 = 0');
                }
            })
            ->when($user->role === 'resident', function ($query) use ($user) {
                $query->where('reporter_id', $user->id);
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('incident_code', 'like', "%{$search}%")
                        ->orWhere('incident_title', 'like', "%{$search}%")
                        ->orWhere('incident_description', 'like', "%{$search}%")
                        ->orWhereHas('barangay', function ($barangayQuery) use ($search) {
                            $barangayQuery->where('barangay_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('category', function ($categoryQuery) use ($search) {
                            $categoryQuery->where('category_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('currentStatus', function ($statusQuery) use ($search) {
                            $statusQuery->where('status_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('type') && $request->type !== 'all', function ($query) use ($request) {
                $query->where('category_id', $request->integer('type'));
            })
            ->when($request->filled('status') && $request->status !== 'all', function ($query) use ($request) {
                $query->where('status_id', $request->integer('status'));
            })
            ->when($request->filled('severity') && $request->severity !== 'all', function ($query) use ($request) {
                $query->where('priority', $request->string('severity')->toString());
            })
            ->latest('incident_datetime')
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        $categories = IncidentCategory::active()
            ->orderBy('category_name')
            ->get();

        $statuses = Status::active()
            ->ordered()
            ->get();

        return view('incidents.index', [
            'incidents' => $incidents,
            'categories' => $categories,
            'statuses' => $statuses,
            'severityOptions' => $this->severityOptions(),
            'filters' => [
                'search' => $request->query('search'),
                'type' => $request->query('type', 'all'),
                'status' => $request->query('status', 'all'),
                'severity' => $request->query('severity', 'all'),
            ],
        ]);
    }

    public function show(Request $request, Incident $incident): View
    {
        $this->authorizeIncidentAccess($request, $incident);

        $incident->load([
            'barangay',
            'category',
            'currentStatus',
            'location',
            'reporter',
            'resident.user',
            'assignedTanod.user',
            'evidence.uploader',
            'evidences.uploader',
            'attachments.uploader',
            'statusHistory.status',
            'statusHistory.updatedBy',
            'statusHistories.status',
            'statusHistories.updatedBy',
            'escalations.escalatedBy',
            'messages.user',
            'caseRecords.creator',
        ]);

        $statuses = Status::active()
            ->ordered()
            ->get();

        $tanods = Employee::query()
            ->with('user')
            ->tanods()
            ->active()
            ->orderBy('id')
            ->get();

        return view('incidents.show', [
            'incident' => $incident,
            'statuses' => $statuses,
            'tanods' => $tanods,
            'responders' => $tanods,
            'severityOptions' => $this->severityOptions(),
            'agencyOptions' => $this->agencyOptions(),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        if (! in_array($user->role, ['resident', 'admin', 'official'], true)) {
            abort(403, 'Unauthorized access.');
        }

        $categories = IncidentCategory::active()
            ->orderBy('category_name')
            ->get();

        $barangays = Schema::hasTable('barangays')
            ? DB::table('barangays')
                ->orderBy('barangay_name')
                ->get()
            : collect();

        return view('incidents.create', [
            'categories' => $categories,
            'barangays' => $barangays,
            'severityOptions' => $this->severityOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! in_array($user->role, ['resident', 'admin', 'official'], true)) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'incident_title' => ['required', 'string', 'max:255'],
            'incident_description' => ['required', 'string', 'max:3000'],
            'category_id' => ['required', 'exists:incident_categories,id'],
            'barangay_id' => ['required', 'exists:barangays,id'],
            'priority' => ['required', Rule::in(array_keys($this->severityOptions()))],
            'location_address' => ['required', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'evidence' => ['nullable', 'array', 'max:5'],
            'evidence.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'category_id.required' => 'Please select an incident category.',
            'barangay_id.required' => 'Please select a barangay.',
            'priority.required' => 'Please select a severity level.',
            'location_address.required' => 'Please provide the incident location or landmark.',
            'evidence.*.mimes' => 'Evidence files must be JPG, JPEG, PNG, WEBP, or PDF.',
            'evidence.*.max' => 'Each evidence file must not exceed 5MB.',
        ]);

        $pendingStatus = Status::query()
            ->whereRaw('LOWER(status_name) = ?', ['pending'])
            ->first();

        if (! $pendingStatus) {
            return back()
                ->withInput()
                ->with('error', 'Pending status is missing. Please add a Pending status record first.');
        }

        $incident = null;

        DB::transaction(function () use ($request, $validated, $pendingStatus, &$incident) {
            $incident = new Incident();

            if (Schema::hasColumn('incidents', 'incident_code')) {
                $incident->incident_code = $this->generateIncidentCode();
            }

            if (Schema::hasColumn('incidents', 'reporter_id')) {
                $incident->reporter_id = $request->user()->id;
            }

            if (Schema::hasColumn('incidents', 'category_id')) {
                $incident->category_id = $validated['category_id'];
            }

            if (Schema::hasColumn('incidents', 'barangay_id')) {
                $incident->barangay_id = $validated['barangay_id'];
            }

            if (Schema::hasColumn('incidents', 'status_id')) {
                $incident->status_id = $pendingStatus->id;
            }

            if (Schema::hasColumn('incidents', 'incident_title')) {
                $incident->incident_title = $validated['incident_title'];
            }

            if (Schema::hasColumn('incidents', 'title')) {
                $incident->title = $validated['incident_title'];
            }

            if (Schema::hasColumn('incidents', 'incident_description')) {
                $incident->incident_description = $validated['incident_description'];
            }

            if (Schema::hasColumn('incidents', 'description')) {
                $incident->description = $validated['incident_description'];
            }

            if (Schema::hasColumn('incidents', 'priority')) {
                $incident->priority = $validated['priority'];
            }

            if (Schema::hasColumn('incidents', 'incident_datetime')) {
                $incident->incident_datetime = now();
            }

            if (Schema::hasColumn('incidents', 'reported_at')) {
                $incident->reported_at = now();
            }

            if (Schema::hasColumn('incidents', 'assigned_to')) {
                $incident->assigned_to = null;
            }

            $incident->save();

            $this->storeIncidentLocation($incident, $validated);

            if ($request->hasFile('evidence')) {
                $this->storeIncidentEvidence($request, $incident);
            }

            IncidentStatusHistory::create([
                'incident_id' => $incident->id,
                'status_id' => $pendingStatus->id,
                'updated_by' => $request->user()->id,
                'remarks' => 'Incident report submitted.',
                'status_changed_at' => now(),
            ]);

            $this->notifyIncidentCreated($incident);
        });

        $showRoute = match ($user->role) {
            'resident' => Route::has('resident.incidents.show') ? 'resident.incidents.show' : null,
            'admin' => Route::has('admin.incidents.show') ? 'admin.incidents.show' : null,
            'official' => Route::has('official.incidents.show') ? 'official.incidents.show' : null,
            default => null,
        };

        if ($showRoute) {
            return redirect()
                ->route($showRoute, $incident)
                ->with('success', 'Incident report submitted successfully.');
        }

        return redirect()
            ->route('resident.incidents.index')
            ->with('success', 'Incident report submitted successfully.');
    }

    public function updateStatus(Request $request, Incident $incident): RedirectResponse
    {
        $this->authorizeIncidentManagement($request, $incident);

        $validated = $request->validate([
            'status_id' => ['required', 'exists:statuses,id'],
            'assigned_to' => ['nullable', 'exists:employees,id'],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ]);

        $oldAssignedTo = $incident->assigned_to;
        $oldStatusId = $incident->status_id;
        $newAssignedTo = $validated['assigned_to'] ?? null;

        DB::transaction(function () use ($request, $incident, $validated, $oldAssignedTo, $oldStatusId, $newAssignedTo) {
            $incident->update([
                'status_id' => $validated['status_id'],
                'assigned_to' => $newAssignedTo,
            ]);

            IncidentStatusHistory::create([
                'incident_id' => $incident->id,
                'status_id' => $validated['status_id'],
                'updated_by' => $request->user()->id,
                'remarks' => $validated['remarks'] ?? null,
                'status_changed_at' => now(),
            ]);

            $status = Status::find($validated['status_id']);

            $freshIncident = $incident->fresh([
                'reporter',
                'assignedTanod.user',
                'currentStatus',
                'category',
                'barangay',
            ]);

            $this->notifyIncidentUsers(
                incident: $freshIncident,
                title: 'Incident status updated',
                message: 'Incident ' . $freshIncident->display_code . ' status changed to ' . ($status?->status_name ?? 'Updated') . '.'
            );

            if ($newAssignedTo && (int) $oldAssignedTo !== (int) $newAssignedTo) {
                $this->createTanodAlert(
                    incident: $freshIncident,
                    type: 'dispatch',
                    title: 'Tanod Dispatch Alert',
                    message: 'You have been assigned to respond to incident ' . $freshIncident->display_code . ': ' . $freshIncident->display_title . '.'
                );
            }

            $statusName = strtolower((string) ($status?->status_name ?? ''));

            if ((int) $oldStatusId !== (int) $validated['status_id']
                && in_array($statusName, ['resolved', 'closed', 'completed'], true)) {
                $this->createTanodAlert(
                    incident: $freshIncident,
                    type: 'resolved',
                    title: 'Incident Resolved',
                    message: 'Incident ' . $freshIncident->display_code . ' has been marked as ' . ($status?->status_name ?? 'resolved') . '.'
                );
            }
        });

        return back()->with('success', 'Incident status updated successfully.');
    }

    public function escalate(Request $request, Incident $incident): RedirectResponse
    {
        $this->authorizeIncidentManagement($request, $incident);

        $validated = $request->validate([
            'agency' => ['required', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:3000'],
        ]);

        DB::transaction(function () use ($request, $incident, $validated) {
            IncidentEscalation::create([
                'incident_id' => $incident->id,
                'escalated_by' => $request->user()->id,
                'agency' => $validated['agency'],
                'reason' => $validated['reason'] ?? null,
                'escalated_at' => now(),
            ]);

            $escalatedStatus = Status::where('status_name', 'Escalated')->first();

            if ($escalatedStatus) {
                $incident->update([
                    'status_id' => $escalatedStatus->id,
                ]);

                IncidentStatusHistory::create([
                    'incident_id' => $incident->id,
                    'status_id' => $escalatedStatus->id,
                    'updated_by' => $request->user()->id,
                    'remarks' => 'Escalated to ' . $validated['agency'] . '. ' . ($validated['reason'] ?? ''),
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

            $this->notifyIncidentUsers(
                incident: $freshIncident,
                title: 'Incident escalated',
                message: 'Incident ' . $freshIncident->display_code . ' has been escalated to ' . $validated['agency'] . '.'
            );

            $this->createTanodAlert(
                incident: $freshIncident,
                type: 'escalation',
                title: 'Incident Escalated',
                message: 'Incident ' . $freshIncident->display_code . ' has been escalated to ' . $validated['agency'] . '.'
            );
        });

        return back()->with('success', 'Incident escalated successfully.');
    }

    public function storeMessage(Request $request, Incident $incident): RedirectResponse
    {
        $this->authorizeIncidentAccess($request, $incident);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:3000'],
        ]);

        IncidentMessage::create([
            'incident_id' => $incident->id,
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
        ]);

        return back()->with('success', 'Message added successfully.');
    }

    private function authorizeIncidentAccess(Request $request, Incident $incident): void
    {
        $user = $request->user();

        if ($user->role === 'admin' || $user->role === 'official') {
            return;
        }

        if ($user->role === 'tanod') {
            $employeeId = $user->employee?->id;

            if ($employeeId && (int) $incident->assigned_to === (int) $employeeId) {
                return;
            }
        }

        if ($user->role === 'resident' && (int) $incident->reporter_id === (int) $user->id) {
            return;
        }

        abort(403, 'Unauthorized access.');
    }

    private function authorizeIncidentManagement(Request $request, Incident $incident): void
    {
        $user = $request->user();

        if ($user->role === 'admin' || $user->role === 'official') {
            return;
        }

        if ($user->role === 'tanod') {
            $employeeId = $user->employee?->id;

            if ($employeeId && (int) $incident->assigned_to === (int) $employeeId) {
                return;
            }
        }

        abort(403, 'Unauthorized access.');
    }

    private function notifyIncidentUsers(Incident $incident, string $title, string $message): void
    {
        $userIds = collect([
            $incident->reporter_id,
            $incident->assignedTanod?->user_id,
        ])
            ->filter()
            ->unique()
            ->values();

        foreach ($userIds as $userId) {
            UserNotification::create([
                'user_id' => $userId,
                'type' => 'incident',
                'source_id' => $incident->id,
                'title' => $title,
                'message' => $message,
                'is_read' => false,
            ]);
        }
    }

    private function createTanodAlert(Incident $incident, string $type, string $title, string $message): void
    {
        $incident->loadMissing('assignedTanod.user');

        $assignedTanodUser = $incident->assignedTanod?->user;

        if (! $assignedTanodUser) {
            return;
        }

        UserNotification::create([
            'user_id' => $assignedTanodUser->id,
            'type' => $type,
            'source_id' => $incident->id,
            'title' => $title,
            'message' => $message,
            'is_read' => false,
            'read_at' => null,
            'acknowledged_by' => null,
            'acknowledged_at' => null,
        ]);
    }

    private function generateIncidentCode(): string
    {
        $datePart = now()->format('Ymd');

        $todayCount = Incident::query()
            ->whereDate('created_at', today())
            ->count() + 1;

        return 'INC-' . $datePart . '-' . str_pad((string) $todayCount, 5, '0', STR_PAD_LEFT);
    }

    private function storeIncidentLocation(Incident $incident, array $validated): void
    {
        $incidentUpdates = [];

        if (Schema::hasColumn('incidents', 'location_address')) {
            $incidentUpdates['location_address'] = $validated['location_address'];
        }

        if (Schema::hasColumn('incidents', 'location')) {
            $incidentUpdates['location'] = $validated['location_address'];
        }

        if (Schema::hasColumn('incidents', 'latitude')) {
            $incidentUpdates['latitude'] = $validated['latitude'] ?? null;
        }

        if (Schema::hasColumn('incidents', 'longitude')) {
            $incidentUpdates['longitude'] = $validated['longitude'] ?? null;
        }

        if (Schema::hasColumn('incidents', 'map_location_name')) {
            $incidentUpdates['map_location_name'] = $validated['location_address'];
        }

        if (Schema::hasColumn('incidents', 'map_severity')) {
            $incidentUpdates['map_severity'] = $validated['priority'];
        }

        if (! empty($incidentUpdates)) {
            $incident->forceFill($incidentUpdates)->save();
        }

        if (! Schema::hasTable('incident_locations')) {
            return;
        }

        $columns = Schema::getColumnListing('incident_locations');
        $locationData = [];

        if (in_array('incident_id', $columns, true)) {
            $locationData['incident_id'] = $incident->id;
        }

        if (in_array('barangay_id', $columns, true)) {
            $locationData['barangay_id'] = $validated['barangay_id'];
        }

        if (in_array('location_address', $columns, true)) {
            $locationData['location_address'] = $validated['location_address'];
        }

        if (in_array('address', $columns, true)) {
            $locationData['address'] = $validated['location_address'];
        }

        if (in_array('latitude', $columns, true)) {
            $locationData['latitude'] = $validated['latitude'] ?? null;
        }

        if (in_array('longitude', $columns, true)) {
            $locationData['longitude'] = $validated['longitude'] ?? null;
        }

        if (in_array('created_at', $columns, true)) {
            $locationData['created_at'] = now();
        }

        if (in_array('updated_at', $columns, true)) {
            $locationData['updated_at'] = now();
        }

        if (! empty($locationData)) {
            DB::table('incident_locations')->insert($locationData);
        }
    }

    private function storeIncidentEvidence(Request $request, Incident $incident): void
    {
        $evidenceTable = null;

        if (Schema::hasTable('evidence')) {
            $evidenceTable = 'evidence';
        } elseif (Schema::hasTable('incident_evidence')) {
            $evidenceTable = 'incident_evidence';
        } elseif (Schema::hasTable('incident_evidences')) {
            $evidenceTable = 'incident_evidences';
        } elseif (Schema::hasTable('incident_attachments')) {
            $evidenceTable = 'incident_attachments';
        }

        if (! $evidenceTable) {
            return;
        }

        $columns = Schema::getColumnListing($evidenceTable);

        foreach ($request->file('evidence', []) as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $path = $file->store('incidents/evidence', 'public');
            $evidenceData = [];

            if (in_array('incident_id', $columns, true)) {
                $evidenceData['incident_id'] = $incident->id;
            }

            if (in_array('uploaded_by', $columns, true)) {
                $evidenceData['uploaded_by'] = $request->user()->id;
            }

            if (in_array('user_id', $columns, true)) {
                $evidenceData['user_id'] = $request->user()->id;
            }

            if (in_array('file_path', $columns, true)) {
                $evidenceData['file_path'] = $path;
            }

            if (in_array('path', $columns, true)) {
                $evidenceData['path'] = $path;
            }

            if (in_array('file_name', $columns, true)) {
                $evidenceData['file_name'] = $file->getClientOriginalName();
            }

            if (in_array('name', $columns, true)) {
                $evidenceData['name'] = $file->getClientOriginalName();
            }

            if (in_array('file_type', $columns, true)) {
                $evidenceData['file_type'] = $file->getClientOriginalExtension();
            }

            if (in_array('mime_type', $columns, true)) {
                $evidenceData['mime_type'] = $file->getMimeType();
            }

            if (in_array('file_size', $columns, true)) {
                $evidenceData['file_size'] = $file->getSize();
            }

            if (in_array('created_at', $columns, true)) {
                $evidenceData['created_at'] = now();
            }

            if (in_array('updated_at', $columns, true)) {
                $evidenceData['updated_at'] = now();
            }

            if (! empty($evidenceData)) {
                DB::table($evidenceTable)->insert($evidenceData);
            }
        }
    }

    private function notifyIncidentCreated(Incident $incident): void
    {
        $adminAndOfficialIds = User::query()
            ->whereIn('role', ['admin', 'official'])
            ->pluck('id');

        foreach ($adminAndOfficialIds as $userId) {
            UserNotification::create([
                'user_id' => $userId,
                'type' => 'incident',
                'source_id' => $incident->id,
                'title' => 'New incident report',
                'message' => 'A new incident report has been submitted: ' . $incident->display_code . '.',
                'is_read' => false,
            ]);
        }
    }

    private function severityOptions(): array
    {
        return [
            'low' => 'Low',
            'moderate' => 'Moderate',
            'high' => 'High',
            'critical' => 'Critical',
        ];
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
            'Municipal Government' => 'Municipal Government',
        ];
    }
}