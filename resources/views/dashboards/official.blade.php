@extends('layouts.admin')

@section('title', 'Official Dashboard | DaoSystem')

@section('content')
@php
    $summary = $summary ?? [];

    $totalIncidents = $summary['total_incidents'] ?? 0;
    $pendingIncidents = $summary['pending_incidents'] ?? 0;
    $activeIncidents = $summary['active_incidents'] ?? 0;
    $resolvedIncidents = $summary['resolved_incidents'] ?? 0;

    $latestIncidents = $latestIncidents ?? collect();

    $incidentIndexUrl = Route::has('official.incidents.index')
        ? route('official.incidents.index')
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
@endphp

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-medium uppercase tracking-wide text-blue-700">
                    Official Dashboard
                </p>

                <h1 class="mt-1 text-2xl font-bold text-slate-900">
                    Incident Monitoring Overview
                </h1>

                <p class="mt-2 max-w-3xl text-sm text-slate-600">
                    Monitor submitted incident reports, track active cases, and review latest barangay safety updates.
                </p>
            </div>

            <a href="{{ $incidentIndexUrl }}"
               class="inline-flex items-center justify-center rounded-xl bg-blue-700 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">
                View Incidents
            </a>
        </div>
    </div>

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-semibold text-slate-500">Total Incidents</p>
            <p class="mt-3 text-3xl font-bold text-slate-900">{{ $totalIncidents }}</p>
            <p class="mt-2 text-xs text-slate-500">All incident reports in the system</p>
        </div>

        <div class="rounded-2xl border border-yellow-200 bg-yellow-50 p-5 shadow-sm">
            <p class="text-sm font-semibold text-yellow-700">Pending</p>
            <p class="mt-3 text-3xl font-bold text-yellow-900">{{ $pendingIncidents }}</p>
            <p class="mt-2 text-xs text-yellow-700">Reports waiting for action</p>
        </div>

        <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5 shadow-sm">
            <p class="text-sm font-semibold text-blue-700">Active</p>
            <p class="mt-3 text-3xl font-bold text-blue-900">{{ $activeIncidents }}</p>
            <p class="mt-2 text-xs text-blue-700">Ongoing, dispatched, or escalated incidents</p>
        </div>

        <div class="rounded-2xl border border-green-200 bg-green-50 p-5 shadow-sm">
            <p class="text-sm font-semibold text-green-700">Resolved</p>
            <p class="mt-3 text-3xl font-bold text-green-900">{{ $resolvedIncidents }}</p>
            <p class="mt-2 text-xs text-green-700">Closed or completed incident reports</p>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-bold text-slate-900">
                    Latest Incidents
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Recent incident reports submitted to the system.
                </p>
            </div>

            <a href="{{ $incidentIndexUrl }}"
               class="text-sm font-bold text-blue-700 hover:text-blue-900">
                Open Full List →
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

                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                            Date
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">
                            Action
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($latestIncidents as $incident)
                        @php
                            $incidentCode = $incident->incident_code ?? ('INC-' . str_pad((string) $incident->id, 5, '0', STR_PAD_LEFT));
                            $incidentTitle = $incident->incident_title ?? 'Untitled Incident';
                            $categoryName = $incident->category_name ?? 'Uncategorized';
                            $priority = $incident->priority ?? 'low';
                            $statusName = $incident->status_name ?? 'Pending';

                            $reportedRaw = $incident->incident_datetime
                                ?? $incident->reported_at
                                ?? $incident->created_at
                                ?? null;

                            try {
                                $reportedDate = $reportedRaw
                                    ? \Carbon\Carbon::parse($reportedRaw)->format('M d, Y h:i A')
                                    : '—';
                            } catch (\Throwable $e) {
                                $reportedDate = '—';
                            }

                            $showUrl = Route::has('official.incidents.show')
                                ? route('official.incidents.show', $incident->id)
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

                            <td class="px-5 py-4 text-sm text-slate-600">
                                {{ $reportedDate }}
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
                            <td colspan="6" class="px-5 py-14 text-center">
                                <div class="mx-auto max-w-md">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-2xl">
                                        📄
                                    </div>

                                    <h3 class="mt-4 text-base font-bold text-slate-900">
                                        No incidents yet
                                    </h3>

                                    <p class="mt-2 text-sm text-slate-500">
                                        New incident reports will appear here once submitted.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection