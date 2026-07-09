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

    $response->update([
        'response_status' => $validated['response_status'],
        'response_note' => $validated['response_note'] ?? null,
        'responded_at' => now(),
    ]);

    return back()->with('success', 'Task response submitted successfully.');
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