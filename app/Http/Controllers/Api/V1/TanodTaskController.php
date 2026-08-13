<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\RecordsOperationalActivity;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Incident;
use App\Models\Status;
use App\Models\TanodTask;
use App\Models\TanodTaskResponse;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TanodTaskController extends Controller
{
    use RecordsOperationalActivity;

    public function index(Request $request): JsonResponse
    {
        return match ($this->role($request)) {
            'admin' => $this->adminIndex($request),
            'tanod' => $this->tanodIndex($request),
            default => abort(403, 'Unauthorized access.'),
        };
    }

    public function store(Request $request): JsonResponse
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

        $task = null;
        $assignedTanodCount = 0;

        DB::transaction(function () use ($request, $validated, &$task, &$assignedTanodCount): void {
            $task = TanodTask::create([
                'created_by' => $request->user()->id,
                'title' => trim($validated['title']),
                'description' => $this->nullableText($validated['description'] ?? null),
                'location' => $this->nullableText($validated['location'] ?? null),
                'task_datetime' => $validated['task_datetime'] ?? null,
                'due_at' => $validated['due_at'] ?? null,
                'priority' => $validated['priority'],
                'status' => 'open',
            ]);

            $tanods = Employee::query()->with('user')->tanods()->active()->get();
            $assignedTanodCount = $tanods->count();

            foreach ($tanods as $tanod) {
                TanodTaskResponse::create([
                    'tanod_task_id' => $task->id,
                    'employee_id' => $tanod->id,
                    'user_id' => $tanod->user_id,
                    'response_status' => 'pending',
                    'response_note' => null,
                    'responded_at' => null,
                ]);

                if ($tanod->user_id) {
                    $this->notifyTanodAboutNewTask($task, (int) $tanod->user_id);
                }
            }
        });

        if (! $task instanceof TanodTask || ! $task->exists) {
            throw new \RuntimeException('Tanod task creation completed without a persisted task record.');
        }

        $task->load('createdBy')->loadCount([
            'responses',
            'acceptedResponses',
            'declinedResponses',
            'pendingResponses',
        ]);

        $this->recordOperationalActivity(
            event: 'tanod_task.created',
            category: 'tanod_task',
            description: 'A tanod task was created.',
            metadata: [
                'tanod_task_id' => (int) $task->id,
                'incident_id' => $task->incident_id,
                'priority' => $task->priority,
                'status' => $task->status,
                'assigned_tanod_count' => $assignedTanodCount,
            ],
            request: $request,
        );

        return $this->json([
            'message' => 'Tanod task created and assigned to all active tanods.',
            'data' => $this->serializeAdminTask($task),
        ], 201);
    }

    public function show(Request $request, TanodTask $tanodTask): JsonResponse
    {
        $this->authorizeAdmin($request);

        $tanodTask->load([
            'createdBy',
            'responses.employee.user',
            'responses.user',
        ])->loadCount([
            'responses',
            'acceptedResponses',
            'declinedResponses',
            'pendingResponses',
        ]);

        return $this->json([
            'data' => $this->serializeAdminTask($tanodTask, true),
        ]);
    }

    public function close(Request $request, TanodTask $tanodTask): JsonResponse
    {
        $this->authorizeAdmin($request);
        $previous = $this->transitionTaskStatus($tanodTask, 'closed');
        $tanodTask->refresh();

        $this->recordTransition($request, $tanodTask, $previous, 'closed');

        return $this->json([
            'message' => 'Tanod task closed successfully.',
            'data' => ['id' => (int) $tanodTask->id, 'status' => $tanodTask->status],
        ]);
    }

    public function cancel(Request $request, TanodTask $tanodTask): JsonResponse
    {
        $this->authorizeAdmin($request);
        $previous = $this->transitionTaskStatus($tanodTask, 'cancelled');
        $tanodTask->refresh();

        $this->recordTransition($request, $tanodTask, $previous, 'cancelled');

        return $this->json([
            'message' => 'Tanod task cancelled successfully.',
            'data' => ['id' => (int) $tanodTask->id, 'status' => $tanodTask->status],
        ]);
    }

    public function destroy(Request $request, TanodTask $tanodTask): JsonResponse
    {
        $this->authorizeAdmin($request);

        $metadata = [
            'tanod_task_id' => (int) $tanodTask->id,
            'incident_id' => $tanodTask->incident_id,
            'priority' => $tanodTask->priority,
            'status' => $tanodTask->status,
        ];

        DB::transaction(function () use ($tanodTask): void {
            if (Schema::hasTable('notifications')) {
                UserNotification::query()
                    ->where('source_id', $tanodTask->id)
                    ->where('type', 'tanod_task')
                    ->delete();
            }

            $tanodTask->responses()->delete();
            $tanodTask->delete();
        });

        $this->recordOperationalActivity(
            event: 'tanod_task.deleted',
            category: 'tanod_task',
            description: 'A tanod task was deleted.',
            metadata: $metadata,
            request: $request,
        );

        return $this->json(['message' => 'Tanod task deleted successfully.']);
    }

    public function respond(Request $request, TanodTaskResponse $response): JsonResponse
    {
        $employee = $this->getTanodEmployee($request);
        $user = $request->user();

        $this->assertResponseOwnership($response, $employee, (int) $user->id);

        $validated = $request->validate([
            'response_status' => ['required', Rule::in(['accepted', 'declined'])],
            'response_note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $response, $employee, $user, $validated): void {
            $lockedResponse = TanodTaskResponse::query()
                ->whereKey($response->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertResponseOwnership($lockedResponse, $employee, (int) $user->id);

            $current = strtolower(trim((string) $lockedResponse->response_status));

            if ($current !== '' && $current !== 'pending') {
                throw ValidationException::withMessages([
                    'response_status' => 'You have already responded to this task. Your response is final.',
                ]);
            }

            $task = TanodTask::query()
                ->whereKey($lockedResponse->tanod_task_id)
                ->lockForUpdate()
                ->first();

            if (! $task) {
                throw ValidationException::withMessages([
                    'response_status' => 'The related task no longer exists.',
                ]);
            }

            if (strtolower(trim((string) $task->status)) !== 'open') {
                throw ValidationException::withMessages([
                    'response_status' => 'This task is no longer open for responses.',
                ]);
            }

            if ($validated['response_status'] === 'accepted') {
                $incident = $this->findIncidentFromTanodTask($task);

                if ($incident) {
                    $lockedIncident = Incident::query()
                        ->whereKey($incident->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $lockedIncident) {
                        throw ValidationException::withMessages([
                            'response_status' => 'The related incident no longer exists.',
                        ]);
                    }

                    $assignedEmployeeId = $lockedIncident->assigned_to !== null
                        ? (int) $lockedIncident->assigned_to
                        : null;

                    if ($assignedEmployeeId !== null && $assignedEmployeeId !== (int) $employee->id) {
                        throw ValidationException::withMessages([
                            'response_status' => 'Another tanod has already accepted this incident response task.',
                        ]);
                    }
                }
            }

            $note = $this->nullableText($validated['response_note'] ?? null);

            $lockedResponse->forceFill([
                'response_status' => $validated['response_status'],
                'response_note' => $note,
                'responded_at' => now(),
            ])->save();

            $this->syncIncidentAfterTanodTaskResponse(
                $request,
                $task,
                $employee,
                $validated['response_status'],
                $note,
            );

            $this->notifyAdminsAboutTanodTaskResponse(
                $task,
                $employee,
                $validated['response_status'],
            );
        });

        $response->refresh()->load('task.createdBy');

        $this->recordOperationalActivity(
            event: 'tanod_task.response_submitted',
            category: 'tanod_task',
            description: 'A tanod submitted a task response.',
            metadata: [
                'response_id' => (int) $response->id,
                'tanod_task_id' => (int) $response->tanod_task_id,
                'employee_id' => (int) $employee->id,
                'response_status' => $validated['response_status'],
                'response_note_provided' => ! empty($validated['response_note']),
            ],
            request: $request,
        );

        return $this->json([
            'message' => 'Task response submitted successfully.',
            'data' => $this->serializeTanodResponse($response),
        ]);
    }

    private function adminIndex(Request $request): JsonResponse
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

        return $this->json([
            'mode' => 'admin',
            'data' => $tasks->getCollection()
                ->map(fn (TanodTask $task): array => $this->serializeAdminTask($task))
                ->values()
                ->all(),
            'summary' => [
                'active_tanod_count' => Employee::query()->tanods()->active()->count(),
            ],
            'permissions' => [
                'can_create' => true,
                'can_view_details' => true,
                'can_close' => true,
                'can_cancel' => true,
                'can_delete' => true,
                'can_respond' => false,
            ],
            'pagination' => $this->pagination($tasks),
        ]);
    }

    private function tanodIndex(Request $request): JsonResponse
    {
        $employee = $this->getTanodEmployee($request);

        $responses = TanodTaskResponse::query()
            ->with('task.createdBy')
            ->where('employee_id', $employee->id)
            ->whereHas('task', fn ($query) => $query->whereIn('status', ['open', 'closed', 'cancelled']))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return $this->json([
            'mode' => 'tanod',
            'data' => $responses->getCollection()
                ->map(fn (TanodTaskResponse $response): array => $this->serializeTanodResponse($response))
                ->values()
                ->all(),
            'employee' => [
                'id' => (int) $employee->id,
                'name' => $employee->user?->name,
            ],
            'permissions' => [
                'can_create' => false,
                'can_view_details' => false,
                'can_close' => false,
                'can_cancel' => false,
                'can_delete' => false,
                'can_respond' => true,
            ],
            'pagination' => $this->pagination($responses),
        ]);
    }

    private function transitionTaskStatus(TanodTask $task, string $newStatus): string
    {
        return DB::transaction(function () use ($task, $newStatus): string {
            $locked = TanodTask::query()
                ->whereKey($task->id)
                ->lockForUpdate()
                ->firstOrFail();

            $current = strtolower(trim((string) $locked->status));

            if ($current !== 'open') {
                throw ValidationException::withMessages([
                    'status' => 'Only an open task may be closed or cancelled.',
                ]);
            }

            $locked->update(['status' => $newStatus]);

            return $current;
        });
    }

    private function recordTransition(
        Request $request,
        TanodTask $task,
        string $previous,
        string $newStatus
    ): void {
        $event = $newStatus === 'closed'
            ? 'tanod_task.closed'
            : 'tanod_task.cancelled';

        $this->recordOperationalActivity(
            event: $event,
            category: 'tanod_task',
            description: $newStatus === 'closed'
                ? 'A tanod task was closed.'
                : 'A tanod task was cancelled.',
            metadata: [
                'tanod_task_id' => (int) $task->id,
                'incident_id' => $task->incident_id,
                'previous_status' => $previous,
                'new_status' => $task->status,
            ],
            request: $request,
        );
    }

    private function assertResponseOwnership(
        TanodTaskResponse $response,
        Employee $employee,
        int $userId
    ): void {
        if ((int) $response->employee_id !== (int) $employee->id) {
            abort(403, 'Unauthorized task response.');
        }

        if (
            Schema::hasColumn('tanod_task_responses', 'user_id')
            && $response->user_id !== null
            && (int) $response->user_id !== $userId
        ) {
            abort(403, 'Unauthorized task response.');
        }
    }

    private function syncIncidentAfterTanodTaskResponse(
        Request $request,
        TanodTask $task,
        Employee $employee,
        string $responseStatus,
        ?string $responseNote
    ): void {
        $incident = $this->findIncidentFromTanodTask($task);

        if (! $incident) {
            return;
        }

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

            if ($updates !== []) {
                $incident->forceFill($updates)->save();
            }

            $this->createIncidentStatusHistoryRecord(
                $incident,
                (int) $request->user()->id,
                $respondingStatus?->id ?? $incident->status_id,
                'Tanod accepted the response task.'
                    . ($responseNote ? ' Note: ' . $responseNote : ''),
            );

            return;
        }

        if ($responseStatus === 'declined') {
            $this->createIncidentStatusHistoryRecord(
                $incident,
                (int) $request->user()->id,
                $incident->status_id,
                'Tanod declined the response task.'
                    . ($responseNote ? ' Note: ' . $responseNote : ''),
            );
        }
    }

    private function notifyAdminsAboutTanodTaskResponse(
        TanodTask $task,
        Employee $employee,
        string $responseStatus
    ): void {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $incident = $this->findIncidentFromTanodTask($task);
        $tanodName = $employee->user?->name
            ?? data_get($employee, 'full_name')
            ?? data_get($employee, 'name')
            ?? 'A tanod';

        $accepted = $responseStatus === 'accepted';

        foreach (
            User::query()->whereIn('role', ['admin', 'official', 'dao'])->pluck('id')
            as $userId
        ) {
            UserNotification::create([
                'user_id' => $userId,
                'type' => 'dispatch',
                'source_id' => $incident?->id,
                'title' => $accepted ? 'Tanod task accepted' : 'Tanod task declined',
                'message' => $tanodName
                    . ($accepted ? ' accepted' : ' declined')
                    . ' the response task: '
                    . $task->title
                    . '.',
                'is_read' => false,
                'read_at' => null,
            ]);
        }
    }

    private function notifyTanodAboutNewTask(TanodTask $task, int $tanodUserId): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $data = [
            'user_id' => $tanodUserId,
            'type' => 'tanod_task',
            'source_id' => $task->id,
            'title' => 'New tanod task',
            'message' => 'A new tanod task was assigned to you: ' . $task->title . '.',
            'is_read' => false,
            'read_at' => null,
        ];

        if (Schema::hasColumn('notifications', 'acknowledged_by')) {
            $data['acknowledged_by'] = null;
        }

        if (Schema::hasColumn('notifications', 'acknowledged_at')) {
            $data['acknowledged_at'] = null;
        }

        UserNotification::updateOrCreate(
            [
                'user_id' => $tanodUserId,
                'type' => 'tanod_task',
                'source_id' => $task->id,
            ],
            $data,
        );
    }

    private function findIncidentFromTanodTask(TanodTask $task): ?Incident
    {
        if (! Schema::hasColumn('tanod_tasks', 'incident_id')) {
            return null;
        }

        $incidentId = data_get($task, 'incident_id');

        return $incidentId ? Incident::find((int) $incidentId) : null;
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
        $data = [];

        foreach ([
            'incident_id' => $incident->id,
            'status_id' => $statusId,
            'updated_by' => $updatedBy,
            'remarks' => $remarks,
            'status_changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ] as $column => $value) {
            if (
                in_array($column, $columns, true)
                && ! ($column === 'status_id' && ! $value)
            ) {
                $data[$column] = $value;
            }
        }

        if ($data !== []) {
            DB::table('incident_status_histories')->insert($data);
        }
    }

    private function serializeAdminTask(TanodTask $task, bool $includeResponses = false): array
    {
        $task->loadMissing('createdBy');

        $data = [
            'id' => (int) $task->id,
            'incident_id' => $task->incident_id !== null ? (int) $task->incident_id : null,
            'title' => (string) $task->title,
            'description' => $task->description,
            'location' => $task->location,
            'task_datetime' => $task->task_datetime?->toIso8601String(),
            'due_at' => $task->due_at?->toIso8601String(),
            'priority' => strtolower((string) $task->priority),
            'status' => strtolower((string) $task->status),
            'created_by' => [
                'id' => $task->createdBy?->id,
                'name' => $task->createdBy?->name,
            ],
            'responses_count' => $this->taskCount($task, 'responses_count', null),
            'accepted_responses_count' => $this->taskCount($task, 'accepted_responses_count', 'accepted'),
            'declined_responses_count' => $this->taskCount($task, 'declined_responses_count', 'declined'),
            'pending_responses_count' => $this->taskCount($task, 'pending_responses_count', 'pending'),
        ];

        if ($includeResponses) {
            $task->loadMissing(['responses.employee.user', 'responses.user']);

            $data['responses'] = $task->responses
                ->map(fn (TanodTaskResponse $response): array => [
                    'id' => (int) $response->id,
                    'employee_id' => (int) $response->employee_id,
                    'user_id' => $response->user_id !== null ? (int) $response->user_id : null,
                    'tanod_name' => $response->employee?->user?->name
                        ?? $response->user?->name
                        ?? 'Tanod #' . $response->employee_id,
                    'response_status' => strtolower((string) ($response->response_status ?? 'pending')),
                    'response_note' => $response->response_note,
                    'responded_at' => $response->responded_at?->toIso8601String(),
                ])
                ->values()
                ->all();
        }

        return $data;
    }

    private function taskCount(TanodTask $task, string $attribute, ?string $status): int
    {
        if ($task->getAttribute($attribute) !== null) {
            return (int) $task->getAttribute($attribute);
        }

        if (! $task->relationLoaded('responses')) {
            return 0;
        }

        return $status === null
            ? $task->responses->count()
            : $task->responses->where('response_status', $status)->count();
    }

    private function serializeTanodResponse(TanodTaskResponse $response): array
    {
        $response->loadMissing('task.createdBy');
        $task = $response->task;
        $responseStatus = strtolower((string) ($response->response_status ?? 'pending'));

        return [
            'id' => (int) $response->id,
            'employee_id' => (int) $response->employee_id,
            'user_id' => $response->user_id !== null ? (int) $response->user_id : null,
            'response_status' => $responseStatus,
            'response_note' => $response->response_note,
            'responded_at' => $response->responded_at?->toIso8601String(),
            'can_respond' => $task !== null
                && strtolower((string) $task->status) === 'open'
                && $responseStatus === 'pending',
            'task' => $task ? [
                'id' => (int) $task->id,
                'incident_id' => $task->incident_id !== null ? (int) $task->incident_id : null,
                'title' => (string) $task->title,
                'description' => $task->description,
                'location' => $task->location,
                'task_datetime' => $task->task_datetime?->toIso8601String(),
                'due_at' => $task->due_at?->toIso8601String(),
                'priority' => strtolower((string) $task->priority),
                'status' => strtolower((string) $task->status),
                'created_by' => [
                    'id' => $task->createdBy?->id,
                    'name' => $task->createdBy?->name,
                ],
            ] : null,
        ];
    }

    private function getTanodEmployee(Request $request): Employee
    {
        $user = $request->user();

        if (! $user || strtolower(trim((string) $user->role)) !== 'tanod') {
            abort(403, 'Unauthorized access.');
        }

        $query = Employee::query()->with('user')->where('user_id', $user->id);
        $hasEmployeeType = Schema::hasColumn('employees', 'employee_type');
        $hasPosition = Schema::hasColumn('employees', 'position');

        $query->where(function ($roleQuery) use ($hasEmployeeType, $hasPosition): void {
            if ($hasEmployeeType && $hasPosition) {
                $roleQuery
                    ->whereRaw('LOWER(TRIM(employee_type)) = ?', ['tanod'])
                    ->orWhereRaw('LOWER(TRIM(position)) = ?', ['tanod']);
                return;
            }

            if ($hasEmployeeType) {
                $roleQuery->whereRaw('LOWER(TRIM(employee_type)) = ?', ['tanod']);
                return;
            }

            if ($hasPosition) {
                $roleQuery->whereRaw('LOWER(TRIM(position)) = ?', ['tanod']);
                return;
            }

            $roleQuery->whereRaw('1 = 0');
        });

        if (Schema::hasColumn('employees', 'is_active')) {
            $query->where('is_active', true);
        }

        $employee = $query->first();

        if (! $employee) {
            abort(403, 'No active tanod employee profile is linked to this account.');
        }

        return $employee;
    }

    private function authorizeAdmin(Request $request): void
    {
        if ($this->role($request) !== 'admin') {
            abort(403, 'Unauthorized access.');
        }
    }

    private function role(Request $request): string
    {
        return strtolower(trim((string) ($request->user()?->role ?? '')));
    }

    private function pagination($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    private function nullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function json(array $payload, int $status = 200): JsonResponse
    {
        return response()
            ->json($payload, $status)
            ->header('Cache-Control', 'no-store, private');
    }
}
