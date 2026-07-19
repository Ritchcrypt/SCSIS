@extends('layouts.admin')

@section('content')
@php
    $totalIncidents = (int) ($summary['total_incidents'] ?? 0);
    $pendingIncidents = (int) ($summary['pending_incidents'] ?? 0);
    $activeIncidents = (int) ($summary['active_incidents'] ?? 0);
    $resolvedIncidents = (int) ($summary['resolved_incidents'] ?? 0);
    $latestRecordsCount = $latestIncidents?->count() ?? 0;

    $watchCount = $pendingIncidents + $activeIncidents;

    if ($activeIncidents > 0) {
        $watchLabel = 'Watch';
        $watchTitle = 'Active Monitoring';
        $watchDetails = $pendingIncidents . ' pending · ' . $activeIncidents . ' active';
        $watchAdvisory = 'Advisory: Barangay officials should monitor active and pending incidents, coordinate updates, and verify response progress throughout the day.';
    } elseif ($pendingIncidents > 0) {
        $watchLabel = 'Observe';
        $watchTitle = 'Pending Review';
        $watchDetails = $pendingIncidents . ' pending · ' . $activeIncidents . ' active';
        $watchAdvisory = 'Advisory: Pending reports need review. Officials should verify details and update case status when action is taken.';
    } else {
        $watchLabel = 'Stable';
        $watchTitle = 'No Active Alerts';
        $watchDetails = $resolvedIncidents . ' resolved records';
        $watchAdvisory = 'Advisory: No active incident load is currently detected. Continue routine monitoring and keep records updated.';
    }
@endphp

<div class="mb-8">
    <h1 class="text-3xl font-bold tracking-tight text-slate-900">
        Official Dashboard
    </h1>

    <p class="mt-1 text-slate-600">
        Dao, Capiz — Community Safety Overview
    </p>
</div>

<section class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-5">
    <div class="rounded-2xl border border-blue-300 bg-white p-6 shadow-sm transition-all duration-200 ease-out hover:-translate-y-1 hover:border-blue-500 hover:shadow-lg">
        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
            📄
        </div>

        <p class="text-4xl font-bold text-slate-900">
            {{ $totalIncidents }}
        </p>

        <p class="mt-2 text-sm font-medium text-slate-600">
            Total Incidents
        </p>
    </div>

    <div class="rounded-2xl border border-amber-300 bg-white p-6 shadow-sm transition-all duration-200 ease-out hover:-translate-y-1 hover:border-amber-500 hover:shadow-lg">
        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-yellow-50 text-yellow-600">
            🕒
        </div>

        <p class="text-4xl font-bold text-slate-900">
            {{ $pendingIncidents }}
        </p>

        <p class="mt-2 text-sm font-medium text-slate-600">
            Pending Incidents
        </p>
    </div>

    <div class="rounded-2xl border border-orange-300 bg-white p-6 shadow-sm transition-all duration-200 ease-out hover:-translate-y-1 hover:border-orange-500 hover:shadow-lg">
        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-orange-50 text-orange-600">
            ⚡
        </div>

        <p class="text-4xl font-bold text-slate-900">
            {{ $activeIncidents }}
        </p>

        <p class="mt-2 text-sm font-medium text-slate-600">
            Active Incidents
        </p>
    </div>

    <div class="rounded-2xl border border-emerald-300 bg-white p-6 shadow-sm transition-all duration-200 ease-out hover:-translate-y-1 hover:border-emerald-500 hover:shadow-lg">
        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-green-50 text-green-600">
            ✅
        </div>

        <p class="text-4xl font-bold text-slate-900">
            {{ $resolvedIncidents }}
        </p>

        <p class="mt-2 text-sm font-medium text-slate-600">
            Resolved
        </p>
    </div>

    <div class="rounded-2xl border border-violet-300 bg-white p-6 shadow-sm transition-all duration-200 ease-out hover:-translate-y-1 hover:border-violet-500 hover:shadow-lg">
        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-blue-950">
            📌
        </div>

        <p class="text-4xl font-bold text-slate-900">
            {{ $latestRecordsCount }}
        </p>

        <p class="mt-2 text-sm font-medium text-slate-600">
            Latest Records
        </p>
    </div>
</section>

<section class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="rounded-2xl border border-sky-300 bg-white p-6 shadow-sm transition-all duration-200 ease-out hover:-translate-y-1 hover:border-sky-500 hover:shadow-lg">
        <div class="mb-6 flex items-start justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900">
                    Weather & Disaster Feed
                </h2>

                <p class="text-sm text-slate-500">
                    Dao, Capiz
                </p>
            </div>

            <span class="rounded-full bg-yellow-100 px-4 py-1 text-sm font-semibold text-yellow-700">
                {{ $watchLabel }}
            </span>
        </div>

        <div class="mb-5 flex items-center gap-6">
            <p class="text-5xl font-bold text-slate-900">
                {{ $watchCount }}
            </p>

            <div>
                <p class="text-lg font-bold text-slate-900">
                    {{ $watchTitle }}
                </p>

                <p class="text-sm text-slate-500">
                    {{ $watchDetails }}
                </p>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm leading-relaxed text-slate-700">
            {{ $watchAdvisory }}
        </div>

        <p class="mt-5 text-sm text-slate-500">
            Last updated: {{ now()->format('h:i:s A') }}
        </p>
    </div>

    <div class="rounded-2xl border border-orange-300 bg-white p-6 shadow-sm transition-all duration-200 ease-out hover:-translate-y-1 hover:border-orange-500 hover:shadow-lg">
        <div class="mb-6 flex items-center justify-between">
            <h2 class="text-xl font-bold text-slate-900">
                Recent Incident Activity
            </h2>

            <span class="text-sm text-slate-400">
                Latest records
            </span>
        </div>

        <div class="max-h-96 space-y-5 overflow-y-auto pr-2">
            @forelse ($latestIncidents as $incident)
                @php
                    $incidentTitle = $incident->incident_title
                        ?? $incident->title
                        ?? $incident->incident_code
                        ?? 'Untitled Incident';

                    $categoryName = $incident->category_name
                        ?? 'Uncategorized';

                    $statusName = $incident->status_name
                        ?? 'Pending';

                    $normalizedStatus = strtolower(str_replace(' ', '_', (string) $statusName));

                    $priority = strtolower((string) (
                        $incident->priority
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
                                Code: {{ $incident->incident_code ?? 'No code' }}
                            </p>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-slate-500">
                    No recent incidents found.
                </p>
            @endforelse
        </div>
    </div>
</section>
@endsection