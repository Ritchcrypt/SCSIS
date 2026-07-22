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
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IncidentController extends Controller
{
    public function destroy(Request $request, Incident $incident): RedirectResponse
{
    $user = $request->user();

    if (! $user || $user->role !== 'admin') {
        abort(403, 'Only admin can delete incidents.');
    }

    DB::transaction(function () use ($incident) {
        $this->deleteIncidentRelatedFiles((int) $incident->id);
        $this->deleteIncidentRelatedRows((int) $incident->id);

        $incident->delete();
    });

    return redirect()
        ->route('admin.incidents.index')
        ->with('success', 'Incident deleted successfully.');
}
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
    ->where('incident_title', 'like', "%{$search}%")
    ->orWhere('incident_description', 'like', "%{$search}%")
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
            'evidence.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:51200'],
        ], [
            'category_id.required' => 'Please select an incident category.',
            'barangay_id.required' => 'Please select a barangay.',
            'priority.required' => 'Please select a severity level.',
            'location_address.required' => 'Please provide the incident location or landmark.',
            'evidence.*.mimes' => 'Evidence files must be JPG, JPEG, PNG, WEBP, or PDF.',
            'evidence.*.max' => 'Each evidence file must not exceed 50MB.',
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
               $incident->incident_code = null;
            }

            if (Schema::hasColumn('incidents', 'reporter_id')) {
                $incident->reporter_id = $request->user()->id;
            }

            if (Schema::hasColumn('incidents', 'category_id')) {
    $incident->category_id = $validated['category_id'];
}

$selectedCategory = IncidentCategory::find($validated['category_id']);

$selectedCategoryName = $selectedCategory?->category_name
    ?? $selectedCategory?->name
    ?? null;

if ($selectedCategoryName) {
    if (Schema::hasColumn('incidents', 'type')) {
        $incident->type = $selectedCategoryName;
    }

    if (Schema::hasColumn('incidents', 'incident_type')) {
        $incident->incident_type = $selectedCategoryName;
    }

    if (Schema::hasColumn('incidents', 'category_name')) {
        $incident->category_name = $selectedCategoryName;
    }
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
            $this->createTanodTaskFromIncident($incident, $request);
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

    private function createTanodTaskFromIncident(Incident $incident, Request $request): void
    {
        if (
            ! Schema::hasTable('tanod_tasks')
            || ! Schema::hasTable('tanod_task_responses')
            || ! Schema::hasColumn('tanod_tasks', 'incident_id')
        ) {
            return;
        }

        $incident->loadMissing(['category', 'barangay']);

        $task = \App\Models\TanodTask::create([
            'incident_id' => $incident->id,
            'created_by' => $request->user()->id,
            'title' => $incident->incident_title ?? $incident->title ?? 'Incident Response Task',
            'description' => $incident->incident_description ?? $incident->description ?? null,
            'location' => $incident->location_address ?? $incident->location ?? null,
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
            \App\Models\TanodTaskResponse::create([
                'tanod_task_id' => $task->id,
                'employee_id' => $tanod->id,
                'user_id' => $tanod->user_id,
                'response_status' => 'pending',
                'response_note' => null,
                'responded_at' => null,
            ]);

            if ($tanod->user_id) {
                $this->notifyTanodAboutIncidentTask(
                    task: $task,
                    incident: $incident,
                    tanodUserId: (int) $tanod->user_id
                );
            }
        }
    }

    public function updateStatus(Request $request, Incident $incident): RedirectResponse
    {
        $this->authorizeIncidentManagement($request, $incident);

        $user = $request->user();

        $rules = [
            'status_id' => ['required', 'exists:statuses,id'],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ];

        if ($user->role === 'admin') {
            $rules['assigned_to'] = ['nullable', 'exists:employees,id'];
        }

        $validated = $request->validate($rules);

        $oldAssignedTo = $incident->assigned_to;
        $oldStatusId = $incident->status_id;

        /*
        |--------------------------------------------------------------------------
        | Assignment Rule
        |--------------------------------------------------------------------------
        | Admin can assign or reassign responders.
        | Official and tanod can update status, but cannot change assigned responder.
        */
        $newAssignedTo = $request->has('assigned_to')
    ? ($validated['assigned_to'] ?? null)
    : $incident->assigned_to;

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
                message: 'Incident status changed to ' . ($status?->status_name ?? 'Updated') . '.'
            );

            if ($newAssignedTo && (int) $oldAssignedTo !== (int) $newAssignedTo) {
                $this->createTanodAlert(
                    incident: $freshIncident,
                    type: 'dispatch',
                    title: 'Tanod Dispatch Alert',
                    message: 'You have been assigned to respond to incident: ' . $freshIncident->display_title . '.'
                );
            }

        });

        return back()->with('success', 'Incident status updated successfully.');
    }

    public function escalate(Request $request, Incident $incident): RedirectResponse
    {
        $this->authorizeIncidentEscalation($request, $incident);

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
                message: 'Incident has been escalated to ' . $validated['agency'] . '.'
            );

            $this->createTanodAlert(
                incident: $freshIncident,
                type: 'escalation',
                title: 'Incident Escalated',
                message: 'Incident has been escalated to ' . $validated['agency'] . '.'
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
public function quickStoreBarangay(Request $request): RedirectResponse
{
    $user = $request->user();

    if (! $user || ! in_array($user->role, ['admin', 'official'], true)) {
        abort(403, 'Only admin or official can add barangays.');
    }

    if (! Schema::hasTable('barangays')) {
        return back()->with('error', 'Barangays table does not exist.');
    }

    $validated = $request->validate([
        'barangay_name' => ['required', 'string', 'max:255'],
    ]);

    $barangayName = trim($validated['barangay_name']);

    $columns = Schema::getColumnListing('barangays');

    $exists = DB::table('barangays')
        ->when(in_array('barangay_name', $columns, true), function ($query) use ($barangayName) {
            $query->whereRaw('LOWER(barangay_name) = ?', [strtolower($barangayName)]);
        })
        ->when(! in_array('barangay_name', $columns, true) && in_array('name', $columns, true), function ($query) use ($barangayName) {
            $query->whereRaw('LOWER(name) = ?', [strtolower($barangayName)]);
        })
        ->exists();

    if ($exists) {
        return back()->with('error', 'Barangay already exists.');
    }

    $data = [];

    if (in_array('barangay_name', $columns, true)) {
        $data['barangay_name'] = $barangayName;
    }

    if (in_array('name', $columns, true)) {
        $data['name'] = $barangayName;
    }

    if (in_array('created_at', $columns, true)) {
        $data['created_at'] = now();
    }

    if (in_array('updated_at', $columns, true)) {
        $data['updated_at'] = now();
    }

    DB::table('barangays')->insert($data);

    return back()->with('success', 'Barangay added successfully.');
}

public function quickDeleteBarangay(Request $request, int $barangayId): RedirectResponse
{
    $user = $request->user();

    if (! $user || ! in_array($user->role, ['admin', 'official'], true)) {
        abort(403, 'Only admin or official can remove barangays.');
    }

    if (! Schema::hasTable('barangays')) {
        return back()->with('error', 'Barangays table does not exist.');
    }

    if (
        Schema::hasTable('incidents')
        && Schema::hasColumn('incidents', 'barangay_id')
        && DB::table('incidents')->where('barangay_id', $barangayId)->exists()
    ) {
        return back()->with('error', 'This barangay cannot be removed because incidents are already linked to it.');
    }

    DB::table('barangays')
        ->where('id', $barangayId)
        ->delete();

    return back()->with('success', 'Barangay removed successfully.');
}

public function showEvidenceFile(Request $request, int $evidenceId)
{
    $evidenceTables = [
        'evidence',
        'incident_evidence',
        'incident_evidences',
        'incident_attachments',
        'attachments',
    ];

    $evidenceRecord = null;

    foreach ($evidenceTables as $table) {
        if (
            ! Schema::hasTable($table)
            || ! Schema::hasColumn($table, 'id')
            || ! Schema::hasColumn($table, 'incident_id')
        ) {
            continue;
        }

        $record = DB::table($table)
            ->where('id', $evidenceId)
            ->first();

        if ($record) {
            $evidenceRecord = $record;
            break;
        }
    }

    if (! $evidenceRecord) {
        abort(404, 'Evidence record not found.');
    }

    $incident = Incident::find((int) $evidenceRecord->incident_id);

    if (! $incident) {
        abort(404, 'Related incident not found.');
    }

    $this->authorizeIncidentAccess($request, $incident);

    $filePath = $evidenceRecord->file_path
        ?? $evidenceRecord->path
        ?? $evidenceRecord->file_url
        ?? $evidenceRecord->url
        ?? null;

    if (! $filePath || str_starts_with((string) $filePath, 'http')) {
        abort(404, 'Evidence file path is invalid.');
    }

    $cleanFilePath = str_replace('\\', '/', trim((string) $filePath));
    $cleanFilePath = preg_replace('#^/?storage/#', '', $cleanFilePath);
    $cleanFilePath = preg_replace('#^/?public/#', '', $cleanFilePath);
    $cleanFilePath = ltrim($cleanFilePath, '/');

    if (
        ! $cleanFilePath
        || str_contains($cleanFilePath, '..')
        || ! Storage::disk('public')->exists($cleanFilePath)
    ) {
        abort(404, 'Evidence file not found in storage.');
    }

    $absolutePath = Storage::disk('public')->path($cleanFilePath);

    if (! is_file($absolutePath)) {
        abort(404, 'Evidence file is missing from disk.');
    }

    $mimeType = $evidenceRecord->mime_type ?? null;

    if (! $mimeType) {
        $detectedMimeType = @mime_content_type($absolutePath);
        $mimeType = $detectedMimeType ?: 'application/octet-stream';
    }

    $fileName = $evidenceRecord->file_name
        ?? $evidenceRecord->name
        ?? basename($cleanFilePath);

    $fileName = str_replace('"', '', (string) $fileName);

    return response()->file($absolutePath, [
        'Content-Type' => (string) $mimeType,
        'Content-Disposition' => 'inline; filename="' . $fileName . '"',
    ]);
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


    private function authorizeIncidentEscalation(Request $request, Incident $incident): void
    {
        $user = $request->user();

        if (in_array($user->role, ['admin', 'official'], true)) {
            return;
        }

        abort(403, 'Unauthorized access.');
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

    $incident->loadMissing(['reporter', 'assignedTanod.user']);

    /*
    |--------------------------------------------------------------------------
    | Incident Update Notification Rule
    |--------------------------------------------------------------------------
    | Every incident update must notify:
    | - admin
    | - official
    | - dao
    | - reporter/resident
    | - assigned tanod
    */
    $managementUserIds = User::query()
        ->whereIn('role', ['admin', 'official', 'dao'])
        ->when(Schema::hasColumn('users', 'is_active'), function ($query) {
            $query->where('is_active', true);
        })
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

        if (Schema::hasColumn('notifications', 'acknowledged_by')) {
            $notificationData['acknowledged_by'] = null;
        }

        if (Schema::hasColumn('notifications', 'acknowledged_at')) {
            $notificationData['acknowledged_at'] = null;
        }

        UserNotification::create($notificationData);
    }
}

    private function createTanodAlert(Incident $incident, string $type, string $title, string $message): void
{
    $incident->loadMissing('assignedTanod.user');

    $assignedTanodUser = $incident->assignedTanod?->user;

    if (! $assignedTanodUser) {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Notification Safety Rule
    |--------------------------------------------------------------------------
    | Do not send a dispatch/escalation alert to the same user who submitted
    | the report. This prevents "you are assigned to your own report" alerts.
    */
    if ((int) $assignedTanodUser->id === (int) $incident->reporter_id) {
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
private function deleteIncidentRelatedFiles(int $incidentId): void
{
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

    foreach ($fileTables as $table) {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'incident_id')) {
            continue;
        }

        $existingFileColumns = array_values(array_filter(
            $fileColumns,
            fn ($column) => Schema::hasColumn($table, $column)
        ));

        if (empty($existingFileColumns)) {
            continue;
        }

        $records = DB::table($table)
            ->where('incident_id', $incidentId)
            ->get($existingFileColumns);

        foreach ($records as $record) {
            foreach ($existingFileColumns as $column) {
                $path = $record->{$column} ?? null;

                if (! $path || str_starts_with((string) $path, 'http')) {
                    continue;
                }

                $path = str_replace('\\', '/', trim((string) $path));
                $path = preg_replace('#^/?storage/#', '', $path);
                $path = preg_replace('#^/?public/#', '', $path);
                $path = ltrim($path, '/');

                if (
                    $path
                    && ! str_contains($path, '..')
                    && Storage::disk('public')->exists($path)
                ) {
                    Storage::disk('public')->delete($path);
                }
            }
        }
    }
}

private function deleteIncidentRelatedRows(int $incidentId): void
{
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
        if (Schema::hasTable($table) && Schema::hasColumn($table, 'incident_id')) {
            DB::table($table)
                ->where('incident_id', $incidentId)
                ->delete();
        }
    }

    if (Schema::hasTable('notifications') && Schema::hasColumn('notifications', 'source_id')) {
    DB::table('notifications')
        ->where('source_id', $incidentId)
        ->when(Schema::hasColumn('notifications', 'type'), function ($query) {
            $query->whereIn('type', [
                'incident',
                'incident_reported',
                'incident_update',
                'dispatch',
                'escalation',
                'emergency',
                'resolved',
                'community_problem',
                'community',
            ]);
        })
        ->delete();
}
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
        $incident->loadMissing(['category', 'reporter']);

        /*
        |--------------------------------------------------------------------------
        | New Incident Notification Rule
        |--------------------------------------------------------------------------
        | Every newly created incident must generate INCIDENT notifications only.
        |
        | Recipients:
        | - All active admin users
        | - All active official/dao users
        | - The reporter, if the reporter is not already included above
        |
        | This prevents Official from showing zero notifications when Admin creates
        | an incident, and it prevents incident reports from appearing as
        | Announcement / Community Problem / Calamity in the bell.
        */

        $receiverIds = User::query()
            ->whereIn('role', ['admin', 'official', 'dao'])
            ->when(Schema::hasColumn('users', 'is_active'), function ($query) {
                $query->where('is_active', true);
            })
            ->pluck('id')
            ->push($incident->reporter_id)
            ->filter()
            ->unique()
            ->values();

        $incidentTitle = $incident->incident_title
            ?? $incident->title
            ?? $incident->display_title
            ?? 'Untitled Incident';

        foreach ($receiverIds as $userId) {
            $notificationData = [
                'user_id' => $userId,
                'type' => 'incident_reported',
                'source_id' => $incident->id,
                'title' => 'New incident report',
                'message' => 'A new incident report was submitted: ' . $incidentTitle . '.',
                'is_read' => false,
                'read_at' => null,
            ];

            if (Schema::hasColumn('notifications', 'acknowledged_by')) {
                $notificationData['acknowledged_by'] = null;
            }

            if (Schema::hasColumn('notifications', 'acknowledged_at')) {
                $notificationData['acknowledged_at'] = null;
            }

            UserNotification::updateOrCreate(
                [
                    'user_id' => $userId,
                    'type' => 'incident_reported',
                    'source_id' => $incident->id,
                ],
                $notificationData
            );
        }
    }

    private function notifyTanodAboutIncidentTask(\App\Models\TanodTask $task, Incident $incident, int $tanodUserId): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $incidentTitle = $incident->incident_title
            ?? $incident->title
            ?? $incident->display_title
            ?? 'Untitled Incident';

        $notificationData = [
            'user_id' => $tanodUserId,
            'type' => 'tanod_task',
            'source_id' => $task->id,
            'title' => 'New incident response task',
            'message' => 'A new incident response task is waiting for your response: ' . $incidentTitle . '.',
            'is_read' => false,
            'read_at' => null,
        ];

        if (Schema::hasColumn('notifications', 'acknowledged_by')) {
            $notificationData['acknowledged_by'] = null;
        }

        if (Schema::hasColumn('notifications', 'acknowledged_at')) {
            $notificationData['acknowledged_at'] = null;
        }

        UserNotification::updateOrCreate(
            [
                'user_id' => $tanodUserId,
                'type' => 'tanod_task',
                'source_id' => $task->id,
            ],
            $notificationData
        );
    }

    private function incidentNotificationType(Incident $incident): string
    {
        return 'incident_reported';
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
