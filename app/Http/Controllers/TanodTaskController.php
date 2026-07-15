<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\TanodTask;
use App\Models\TanodTaskResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Models\Incident;
use App\Models\Status;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Schema;

class TanodTaskController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        $tasks = TanodTask::query()
            ->withCount([
                'responses',
                'acceptedResponses',
                'declinedResponses',
                'pendingResponses',
            ])
            ->with('createdBy')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('tanod-tasks.index', [
            'tasks' => $tasks,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeAdmin($request);

        $tanods = Employee::query()
            ->with('user')
            ->tanods()
            ->active()
            ->orderBy('id')
            ->get();

        return view('tanod-tasks.create', [
            'tanods' => $tanods,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'location' => ['nullable', 'string', 'max:500'],
            'task_datetime' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:task_datetime'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
        ]);

        DB::transaction(function () use ($request, $validated) {
            $task = TanodTask::create([
                'created_by' => $request->user()->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'location' => $validated['location'] ?? null,
                'task_datetime' => $validated['task_datetime'] ?? null,
                'due_at' => $validated['due_at'] ?? null,
                'priority' => $validated['priority'],
                'status' => 'open',
            ]);

            $tanods = Employee::query()
                ->with('user')
                ->tanods()
                ->active()
                ->get();

            foreach ($tanods as $tanod) {
                TanodTaskResponse::create([
                    'tanod_task_id' => $task->id,
                    'employee_id' => $tanod->id,
                    'user_id' => $tanod->user_id,
                    'response_status' => 'pending',
                    'response_note' => null,
                    'responded_at' => null,
                ]);
            }
        });

        return redirect()
            ->route('admin.tanod-tasks.index')
            ->with('success', 'Tanod task created and assigned to all active tanods.');
    }

    public function show(Request $request, TanodTask $tanodTask): View
    {
        $this->authorizeAdmin($request);

        $tanodTask->load([
            'createdBy',
            'responses.employee.user',
            'responses.user',
        ]);

        return view('tanod-tasks.show', [
            'task' => $tanodTask,
        ]);
    }

public function destroy(TanodTask $tanodTask): RedirectResponse
{
    DB::transaction(function () use ($tanodTask) {
        if (method_exists($tanodTask, 'responses')) {
            $tanodTask->responses()->delete();
        } else {
            DB::table('tanod_task_responses')
                ->where('tanod_task_id', $tanodTask->id)
                ->delete();
        }

        $tanodTask->delete();
    });

    return redirect()
        ->route('admin.tanod-tasks.index')
        ->with('success', 'Tanod task deleted successfully.');
}

    public function close(Request $request, TanodTask $tanodTask): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $tanodTask->update([
            'status' => 'closed',
        ]);

        return back()->with('success', 'Tanod task closed successfully.');
    }

    public function cancel(Request $request, TanodTask $tanodTask): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $tanodTask->update([
            'status' => 'cancelled',
        ]);

        return back()->with('success', 'Tanod task cancelled successfully.');
    }

    private function authorizeAdmin(Request $request): void
    {
        if ($request->user()?->role !== 'admin') {
            abort(403, 'Unauthorized access.');
        }
    }

    public function tanodIndex(Request $request): View
{
    $employee = $this->getTanodEmployee($request);

    $responses = TanodTaskResponse::query()
        ->with(['task.createdBy'])
        ->where('employee_id', $employee->id)
        ->whereHas('task', function ($query) {
            $query->whereIn('status', ['open', 'closed', 'cancelled']);
        })
        ->latest()
        ->paginate(10)
        ->withQueryString();

    return view('tanod-tasks.tanod-index', [
        'responses' => $responses,
        'employee' => $employee,
    ]);
}

public function respond(Request $request, TanodTaskResponse $response): RedirectResponse
{
    $employee = $this->getTanodEmployee($request);

    if ((int) $response->employee_id !== (int) $employee->id) {
        abort(403, 'Unauthorized access.');
    }

    $response->load('task');

    if (! $response->task || $response->task->status !== 'open') {
        return back()->with('error', 'This task is no longer open for response.');
    }

    $validated = $request->validate([
        'response_status' => ['required', Rule::in(['accepted', 'declined'])],
        'response_note' => ['nullable', 'string', 'max:1000'],
    ]);

    DB::transaction(function () use ($request, $response, $employee, $validated) {
        $oldResponseStatus = $response->response_status;

        $response->update([
            'response_status' => $validated['response_status'],
            'response_note' => $validated['response_note'] ?? null,
            'responded_at' => now(),
        ]);

        $response->refresh();
        $response->load('task');

        /*
        |--------------------------------------------------------------------------
        | Phase D / E
        |--------------------------------------------------------------------------
        | Only run notification and status-history logic when the response status
        | actually changes. This prevents duplicate notifications if the tanod
        | submits the same response again.
        */

        if ($oldResponseStatus !== $validated['response_status']) {
            $this->syncIncidentAfterTanodTaskResponse(
                request: $request,
                task: $response->task,
                employee: $employee,
                responseStatus: $validated['response_status'],
                responseNote: $validated['response_note'] ?? null
            );

            $this->notifyAdminsAboutTanodTaskResponse(
                task: $response->task,
                employee: $employee,
                responseStatus: $validated['response_status']
            );
        }
    });

    return back()->with('success', 'Task response submitted successfully.');
}

private function syncIncidentAfterTanodTaskResponse(
    Request $request,
    TanodTask $task,
    Employee $employee,
    string $responseStatus,
    ?string $responseNote = null
): void {
    $incident = $this->findIncidentFromTanodTask($task);

    if (! $incident) {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Accepted Response
    |--------------------------------------------------------------------------
    | When a tanod accepts, the backend assignment is updated here.
    | The Incidents View no longer manually assigns responders.
    */

    if ($responseStatus === 'accepted') {
        $updates = [];

        if (Schema::hasColumn('incidents', 'assigned_to')) {
            $updates['assigned_to'] = $employee->id;
        }

        $respondingStatus = Status::query()
            ->whereRaw('LOWER(status_name) IN (?, ?, ?)', [
                'responding',
                'in progress',
                'in_progress',
            ])
            ->first();

        if ($respondingStatus && Schema::hasColumn('incidents', 'status_id')) {
            $updates['status_id'] = $respondingStatus->id;
        }

        if (! empty($updates)) {
            $incident->forceFill($updates)->save();
        }

        $this->createIncidentStatusHistoryRecord(
            incident: $incident,
            updatedBy: $request->user()->id,
            statusId: $respondingStatus?->id ?? $incident->status_id,
            remarks: 'Tanod accepted the response task.'
                . ($responseNote ? ' Note: ' . $responseNote : '')
        );

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Declined Response
    |--------------------------------------------------------------------------
    | Decline does not clear assigned_to. It only logs the decision.
    */

    if ($responseStatus === 'declined') {
        $this->createIncidentStatusHistoryRecord(
            incident: $incident,
            updatedBy: $request->user()->id,
            statusId: $incident->status_id,
            remarks: 'Tanod declined the response task.'
                . ($responseNote ? ' Note: ' . $responseNote : '')
        );
    }
}

private function notifyAdminsAboutTanodTaskResponse(
    TanodTask $task,
    Employee $employee,
    string $responseStatus
): void {
    $incident = $this->findIncidentFromTanodTask($task);

    $tanodName = $employee->user?->name
        ?? $employee->full_name
        ?? $employee->name
        ?? 'A tanod';

    $statusText = $responseStatus === 'accepted'
        ? 'accepted'
        : 'declined';

    $title = $responseStatus === 'accepted'
        ? 'Tanod task accepted'
        : 'Tanod task declined';

    $message = $tanodName . ' ' . $statusText . ' the response task: ' . $task->title . '.';

    $receiverIds = User::query()
        ->whereIn('role', ['admin', 'official', 'dao'])
        ->pluck('id');

    foreach ($receiverIds as $userId) {
        UserNotification::create([
            'user_id' => $userId,
            'type' => 'dispatch',
            'source_id' => $incident?->id,
            'title' => $title,
            'message' => $message,
            'is_read' => false,
            'read_at' => null,
        ]);
    }
}

private function findIncidentFromTanodTask(TanodTask $task): ?Incident
{
    if (! Schema::hasColumn('tanod_tasks', 'incident_id')) {
        return null;
    }

    $incidentId = data_get($task, 'incident_id');

    if (! $incidentId) {
        return null;
    }

    return Incident::find((int) $incidentId);
}

private function createIncidentStatusHistoryRecord(
    Incident $incident,
    int $updatedBy,
    ?int $statusId,
    string $remarks
): void {
    if (! Schema::hasTable('incident_status_histories')) {
        return;
    }

    $columns = Schema::getColumnListing('incident_status_histories');

    $historyData = [];

    if (in_array('incident_id', $columns, true)) {
        $historyData['incident_id'] = $incident->id;
    }

    if (in_array('status_id', $columns, true) && $statusId) {
        $historyData['status_id'] = $statusId;
    }

    if (in_array('updated_by', $columns, true)) {
        $historyData['updated_by'] = $updatedBy;
    }

    if (in_array('remarks', $columns, true)) {
        $historyData['remarks'] = $remarks;
    }

    if (in_array('status_changed_at', $columns, true)) {
        $historyData['status_changed_at'] = now();
    }

    if (in_array('created_at', $columns, true)) {
        $historyData['created_at'] = now();
    }

    if (in_array('updated_at', $columns, true)) {
        $historyData['updated_at'] = now();
    }

    if (! empty($historyData)) {
        DB::table('incident_status_histories')->insert($historyData);
    }
}
private function getTanodEmployee(Request $request): Employee
{
    $user = $request->user();

    if (! $user || $user->role !== 'tanod') {
        abort(403, 'Unauthorized access.');
    }

    $employee = Employee::query()
        ->where('user_id', $user->id)
        ->where(function ($query) {
            $query->where('employee_type', 'tanod')
                ->orWhere('position', 'tanod');
        })
        ->first();

    if (! $employee) {
        abort(403, 'No tanod employee profile found for this account.');
    }

    return $employee;
}
}