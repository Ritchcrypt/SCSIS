@extends('layouts.admin')

@section('title', 'Tanod Dashboard | DaoSystem')

@section('content')
@php
    $summary = $summary ?? [];

    $assignedIncidentsCount = $summary['assigned_incidents'] ?? 0;
    $openTasksCount = $summary['open_tasks'] ?? 0;
    $acceptedTasksCount = $summary['accepted_tasks'] ?? 0;
    $unreadAlertsCount = $summary['unread_alerts'] ?? 0;

    $assignedIncidents = $assignedIncidents ?? collect();
    $latestTasks = $latestTasks ?? collect();
    $latestAlerts = $latestAlerts ?? collect();

    $incidentIndexUrl = Route::has('tanod.incidents.index')
        ? route('tanod.incidents.index')
        : '#';

    $taskIndexUrl = Route::has('tanod.tanod-tasks.index')
        ? route('tanod.tanod-tasks.index')
        : '#';

    $alertIndexUrl = Route::has('tanod.tanod-alerts.index')
        ? route('tanod.tanod-alerts.index')
        : '#';

    $statusBadgeClass = function ($status) {
        $status = strtolower((string) $status);

        return match ($status) {
            'pending', 'reported' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
            'active', 'responding', 'in progress', 'in_progress', 'dispatched' => 'bg-blue-100 text-blue-700 border-blue-200',
            'escalated' => 'bg-red-100 text-red-700 border-red-200',
            'resolved', 'closed', 'completed' => 'bg-green-100 text-green-700 border-green-200',
            default => 'bg-slate-100 text-slate-700 border-slate-200',
        };
    };

    $priorityBadgeClass = function ($priority) {
        $priority = strtolower((string) $priority);

        return match ($priority) {
            'critical' => 'bg-red-100 text-red-700 border-red-200',
            'high' => 'bg-orange-100 text-orange-700 border-orange-200',
            'moderate', 'medium' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
            'low' => 'bg-green-100 text-green-700 border-green-200',
            default => 'bg-slate-100 text-slate-700 border-slate-200',
        };
    };

    $taskStatusBadgeClass = function ($status) {
        $status = strtolower((string) $status);

        return match ($status) {
            'pending' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
            'accepted' => 'bg-blue-100 text-blue-700 border-blue-200',
            'declined', 'rejected' => 'bg-red-100 text-red-700 border-red-200',
            'completed', 'done' => 'bg-green-100 text-green-700 border-green-200',
            default => 'bg-slate-100 text-slate-700 border-slate-200',
        };
    };
@endphp

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-medium uppercase tracking-wide text-blue-700">
                    Tanod Dashboard
                </p>

                <h1 class="mt-1 text-2xl font-bold text-slate-900">
                    Field Response Overview
                </h1>

                <p class="mt-2 max-w-3xl text-sm text-slate-600">
                    Track assigned incidents, task responses, and important alerts assigned to your account.
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ $incidentIndexUrl }}"
                   class="inline-flex items-center justify-center rounded-xl bg-blue-700 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">
                    Assigned Incidents
                </a>

                <a href="{{ $taskIndexUrl }}"
                   class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Tanod Tasks
                </a>
            </div>
        </div>
    </div>

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5 shadow-sm">
            <p class="text-sm font-semibold text-blue-700">Assigned Incidents</p>
            <p class="mt-3 text-3xl font-bold text-blue-900">{{ $assignedIncidentsCount }}</p>
            <p class="mt-2 text-xs text-blue-700">Incidents currently assigned to you</p>
        </div>

        <div class="rounded-2xl border border-yellow-200 bg-yellow-50 p-5 shadow-sm">
            <p class="text-sm font-semibold text-yellow-700">Pending Tasks</p>
            <p class="mt-3 text-3xl font-bold text-yellow-900">{{ $openTasksCount }}</p>
            <p class="mt-2 text-xs text-yellow-700">Tasks waiting for your response</p>
        </div>

        <div class="rounded-2xl border border-green-200 bg-green-50 p-5 shadow-sm">
            <p class="text-sm font-semibold text-green-700">Accepted Tasks</p>
            <p class="mt-3 text-3xl font-bold text-green-900">{{ $acceptedTasksCount }}</p>
            <p class="mt-2 text-xs text-green-700">Tasks you accepted for action</p>
        </div>

        <div class="rounded-2xl border border-red-200 bg-red-50 p-5 shadow-sm">
            <p class="text-sm font-semibold text-red-700">Unread Alerts</p>
            <p class="mt-3 text-3xl font-bold text-red-900">{{ $unreadAlertsCount }}</p>
            <p class="mt-2 text-xs text-red-700">Important alerts not yet read</p>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900">
                        Latest Assigned Incidents
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Recent incidents assigned to you for response.
                    </p>
                </div>

                <a href="{{ $incidentIndexUrl }}"
                   class="text-sm font-bold text-blue-700 hover:text-blue-900">
                    Open Assigned Incidents →
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                Incident
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                Type
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                Priority
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                Status
                            </th>

                            <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">
                                Action
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($assignedIncidents as $incident)
                            @php
                                $incidentCode = $incident->incident_code ?? ('INC-' . str_pad((string) $incident->id, 5, '0', STR_PAD_LEFT));
                                $incidentTitle = $incident->incident_title ?? 'Untitled Incident';
                                $categoryName = $incident->category_name ?? 'Uncategorized';
                                $priority = $incident->priority ?? 'low';
                                $statusName = $incident->status_name ?? 'Pending';

                                $showUrl = Route::has('tanod.incidents.show')
                                    ? route('tanod.incidents.show', $incident->id)
                                    : '#';
                            @endphp

                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-4">
                                    <p class="text-xs font-bold uppercase tracking-wide text-blue-700">
                                        {{ $incidentCode }}
                                    </p>

                                    <p class="mt-1 max-w-xs truncate text-sm font-semibold text-slate-900">
                                        {{ $incidentTitle }}
                                    </p>
                                </td>

                                <td class="px-5 py-4 text-sm font-medium text-slate-700">
                                    {{ $categoryName }}
                                </td>

                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full border px-3 py-1 text-xs font-bold {{ $priorityBadgeClass($priority) }}">
                                        {{ ucfirst((string) $priority) }}
                                    </span>
                                </td>

                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full border px-3 py-1 text-xs font-bold {{ $statusBadgeClass($statusName) }}">
                                        {{ ucfirst(str_replace('_', ' ', (string) $statusName)) }}
                                    </span>
                                </td>

                                <td class="px-5 py-4 text-right">
                                    <a href="{{ $showUrl }}"
                                       class="inline-flex rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-14 text-center">
                                    <div class="mx-auto max-w-md">
                                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-2xl">
                                            🛡️
                                        </div>

                                        <h3 class="mt-4 text-base font-bold text-slate-900">
                                            No assigned incidents
                                        </h3>

                                        <p class="mt-2 text-sm text-slate-500">
                                            Assigned incident reports will appear here once dispatch is made.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">
                            Latest Tasks
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Your recent task responses.
                        </p>
                    </div>
                </div>

                <div class="space-y-4 p-6">
                    @forelse ($latestTasks as $task)
                        @php
                            $taskTitle = $task->task_title ?? ('Task #' . $task->id);
                            $taskDescription = $task->task_description ?? 'No task description provided.';
                            $taskPriority = $task->priority ?? 'normal';
                            $taskStatus = $task->response_status ?? $task->status ?? 'pending';

                            try {
                                $taskDate = $task->created_at
                                    ? \Carbon\Carbon::parse($task->created_at)->format('M d, Y h:i A')
                                    : '—';
                            } catch (\Throwable $e) {
                                $taskDate = '—';
                            }
                        @endphp

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="rounded-full border px-3 py-1 text-xs font-bold {{ $taskStatusBadgeClass($taskStatus) }}">
                                    {{ ucfirst(str_replace('_', ' ', (string) $taskStatus)) }}
                                </span>

                                <span class="text-xs font-medium text-slate-500">
                                    {{ $taskDate }}
                                </span>
                            </div>

                            <h3 class="mt-3 text-sm font-bold text-slate-900">
                                {{ $taskTitle }}
                            </h3>

                            <p class="mt-1 line-clamp-2 text-sm leading-6 text-slate-600">
                                {{ $taskDescription }}
                            </p>

                            <p class="mt-2 text-xs font-bold uppercase tracking-wide text-slate-500">
                                Priority: {{ ucfirst((string) $taskPriority) }}
                            </p>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                            <div class="text-3xl">📋</div>

                            <h3 class="mt-3 text-sm font-bold text-slate-900">
                                No tasks yet
                            </h3>

                            <p class="mt-1 text-sm text-slate-500">
                                Tanod tasks will appear here once assigned.
                            </p>
                        </div>
                    @endforelse

                    <a href="{{ $taskIndexUrl }}"
                       class="block rounded-xl bg-blue-700 px-4 py-2.5 text-center text-sm font-bold text-white hover:bg-blue-800">
                        Open Tasks
                    </a>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">
                            Latest Alerts
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Important unread or recent notifications.
                        </p>
                    </div>
                </div>

                <div class="space-y-4 p-6">
                    @forelse ($latestAlerts as $alert)
                        @php
                            $alertType = $alert->type ?? 'incident';
                            $alertTitle = $alert->title ?? 'Untitled alert';
                            $alertMessage = $alert->message ?? 'No alert message.';
                            $isRead = (bool) ($alert->is_read ?? false);

                            try {
                                $alertDate = $alert->created_at
                                    ? \Carbon\Carbon::parse($alert->created_at)->diffForHumans()
                                    : '—';
                            } catch (\Throwable $e) {
                                $alertDate = '—';
                            }
                        @endphp

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-blue-700">
                                    {{ ucfirst((string) $alertType) }}
                                </span>

                                <span class="text-xs font-medium {{ $isRead ? 'text-slate-400' : 'text-red-600' }}">
                                    {{ $isRead ? 'Read' : 'Unread' }}
                                </span>
                            </div>

                            <h3 class="mt-3 text-sm font-bold text-slate-900">
                                {{ $alertTitle }}
                            </h3>

                            <p class="mt-1 line-clamp-2 text-sm leading-6 text-slate-600">
                                {{ $alertMessage }}
                            </p>

                            <p class="mt-2 text-xs text-slate-400">
                                {{ $alertDate }}
                            </p>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                            <div class="text-3xl">🔔</div>

                            <h3 class="mt-3 text-sm font-bold text-slate-900">
                                No alerts yet
                            </h3>

                            <p class="mt-1 text-sm text-slate-500">
                                Dispatch and escalation alerts will appear here.
                            </p>
                        </div>
                    @endforelse

                    <a href="{{ $alertIndexUrl }}"
                       class="block rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-bold text-slate-700 hover:bg-slate-50">
                        Open Alerts
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection