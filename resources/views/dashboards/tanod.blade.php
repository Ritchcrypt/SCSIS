@extends('layouts.admin')

@section('title', 'Tanod Dashboard | DaoSystem')

@section('content')
@php
    $summary = $summary ?? [];

    $assignedIncidentsCount = $summary['assigned_incidents'] ?? 0;
    $openTasksCount = $summary['open_tasks'] ?? 0;
    $acceptedTasksCount = $summary['accepted_tasks'] ?? 0;
    $declinedTasksCount = $summary['declined_tasks'] ?? 0;

    $recentIncidents = $recentIncidents ?? collect();
@endphp

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div>
            <p class="text-sm font-medium uppercase tracking-wide text-blue-700">
                Tanod Dashboard
            </p>

            <h1 class="mt-1 text-2xl font-bold text-slate-900">
                Tanod Operations Overview
            </h1>

            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Monitor your assigned incidents, task response status, and barangay field conditions in one place.
            </p>
        </div>
    </div>

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-lg">
            <p class="text-sm font-semibold text-blue-700">Assigned Incidents</p>
            <p class="mt-3 text-3xl font-bold text-blue-900">{{ $assignedIncidentsCount }}</p>
            <p class="mt-2 text-xs text-blue-700">Incidents currently assigned to you</p>
        </div>

        <div class="rounded-2xl border border-yellow-200 bg-yellow-50 p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-lg">
            <p class="text-sm font-semibold text-yellow-700">Pending Tasks</p>
            <p class="mt-3 text-3xl font-bold text-yellow-900">{{ $openTasksCount }}</p>
            <p class="mt-2 text-xs text-yellow-700">Tasks waiting for your response</p>
        </div>

        <div class="rounded-2xl border border-green-200 bg-green-50 p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-lg">
            <p class="text-sm font-semibold text-green-700">Accepted Tasks</p>
            <p class="mt-3 text-3xl font-bold text-green-900">{{ $acceptedTasksCount }}</p>
            <p class="mt-2 text-xs text-green-700">Tasks you accepted for action</p>
        </div>

        <div class="rounded-2xl border border-red-200 bg-red-50 p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-lg">
            <p class="text-sm font-semibold text-red-700">Declined Tasks</p>
            <p class="mt-3 text-3xl font-bold text-red-900">{{ $declinedTasksCount }}</p>
            <p class="mt-2 text-xs text-red-700">Tasks you declined or rejected</p>
        </div>
    </div>

    <section class="mt-8">
        @include('components.dashboard.weather-disaster-feed')
    </section>

    <section class="mt-8">
        <div class="rounded-2xl border border-orange-300 bg-white p-6 shadow-sm transition-all duration-200 ease-out hover:-translate-y-1 hover:border-orange-500 hover:shadow-lg">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-xl font-bold text-slate-900">Recent Incident Activity</h2>
                <span class="text-sm text-slate-400">Latest records</span>
            </div>

            <div class="max-h-96 space-y-5 overflow-y-auto pr-2">
                @forelse ($recentIncidents as $incident)
                    @php
                        $incidentTitle = $incident->title
                            ?? $incident->incident_title
                            ?? 'Untitled Incident';

                        $categoryName = $incident->category?->category_name
                            ?? $incident->category?->name
                            ?? $incident->category_name
                            ?? $incident->incident_type
                            ?? $incident->type
                            ?? 'Uncategorized';

                        $statusName = $incident->currentStatus?->status_name
                            ?? $incident->status?->status_name
                            ?? $incident->status
                            ?? 'Pending';

                        $normalizedStatus = strtolower(str_replace(' ', '_', (string) $statusName));

                        $priority = strtolower((string) (
                            $incident->priority
                            ?? $incident->severity
                            ?? 'low'
                        ));

                        $reportedRaw = $incident->reported_at
                            ?? $incident->incident_datetime
                            ?? $incident->created_at;

                        try {
                            $reportedAgo = $reportedRaw
                                ? \Carbon\Carbon::parse($reportedRaw)->diffForHumans()
                                : 'Unknown time';
                        } catch (\Throwable $e) {
                            $reportedAgo = 'Unknown time';
                        }

                        $reporterName = $incident->reporter?->name ?? 'Unknown';

                        $assignedName = data_get($incident, 'assignedTanod.user.name')
                            ?? data_get($incident, 'assignedTanod.name')
                            ?? null;
                    @endphp

                    <div class="border-b border-slate-100 pb-4 last:border-0">
                        <div class="flex items-start gap-4">
                            <div class="mt-1 h-3 w-3 rounded-full
                                @if ($priority === 'critical') bg-red-500
                                @elseif ($priority === 'high') bg-orange-500
                                @elseif ($priority === 'moderate' || $priority === 'medium') bg-yellow-400
                                @else bg-green-500
                                @endif
                            "></div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="font-semibold text-slate-900">
                                            {{ $incidentTitle }}
                                        </p>

                                        <p class="mt-1 text-sm text-slate-500">
                                            {{ $categoryName }}
                                            ·
                                            <span class="font-semibold
                                                @if ($normalizedStatus === 'escalated') text-red-600
                                                @elseif ($normalizedStatus === 'dispatched' || $normalizedStatus === 'responding') text-orange-600
                                                @elseif ($normalizedStatus === 'resolved' || $normalizedStatus === 'completed' || $normalizedStatus === 'closed') text-green-600
                                                @else text-blue-600
                                                @endif
                                            ">
                                                {{ ucfirst(str_replace('_', ' ', $normalizedStatus)) }}
                                            </span>
                                            ·
                                            {{ $reportedAgo }}
                                        </p>
                                    </div>

                                    <span class="rounded-full px-3 py-1 text-xs font-semibold
                                        @if ($priority === 'critical') bg-red-100 text-red-700
                                        @elseif ($priority === 'high') bg-orange-100 text-orange-700
                                        @elseif ($priority === 'moderate' || $priority === 'medium') bg-yellow-100 text-yellow-700
                                        @else bg-green-100 text-green-700
                                        @endif
                                    ">
                                        {{ ucfirst($priority) }}
                                    </span>
                                </div>

                                <p class="mt-2 text-xs text-slate-400">
                                    Reporter: {{ $reporterName }}

                                    @if ($assignedName)
                                        · Assigned: {{ $assignedName }}
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-slate-500">No recent incidents found.</p>
                @endforelse
            </div>
        </div>
    </section>
</div>
@endsection