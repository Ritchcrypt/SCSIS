@extends('layouts.admin')

@section('content')
@php
    $summary = $summary ?? [];
    $recentIncidents = $recentIncidents ?? ($latestIncidents ?? collect());

    $totalIncidents = (int) data_get($summary, 'total_incidents', 0);

    $pendingIncidents = (int) data_get($summary, 'pending_incidents', 0);
    $activeIncidents = (int) data_get($summary, 'active_incidents', 0);
    $activeCases = (int) data_get($summary, 'active_cases', $pendingIncidents + $activeIncidents);

    $resolvedCases = (int) data_get($summary, 'resolved_cases', data_get($summary, 'resolved_incidents', 0));

    $criticalIncidents = data_get($summary, 'critical_incidents');

    if (
        $criticalIncidents === null
        && class_exists(\App\Models\Incident::class)
        && \Illuminate\Support\Facades\Schema::hasTable('incidents')
    ) {
        $hasPriorityColumn = \Illuminate\Support\Facades\Schema::hasColumn('incidents', 'priority');
        $hasSeverityColumn = \Illuminate\Support\Facades\Schema::hasColumn('incidents', 'severity');

        if ($hasPriorityColumn || $hasSeverityColumn) {
            $criticalIncidents = \App\Models\Incident::query()
                ->where(function ($query) use ($hasPriorityColumn, $hasSeverityColumn) {
                    if ($hasPriorityColumn) {
                        $query->where('priority', 'critical');
                    }

                    if ($hasSeverityColumn) {
                        $query->orWhere('severity', 'critical');
                    }
                })
                ->count();
        } else {
            $criticalIncidents = 0;
        }
    }

    $criticalIncidents = (int) $criticalIncidents;

    $tanodOnDuty = data_get($summary, 'tanod_on_duty');

    if (
        $tanodOnDuty === null
        && \Illuminate\Support\Facades\Schema::hasTable('tanod_profiles')
        && \Illuminate\Support\Facades\Schema::hasColumn('tanod_profiles', 'duty_status')
    ) {
        $tanodOnDuty = \Illuminate\Support\Facades\DB::table('tanod_profiles')
            ->where('duty_status', 'on_duty')
            ->count();
    }

    $tanodOnDuty = (int) $tanodOnDuty;
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
            {{ $activeCases }}
        </p>

        <p class="mt-2 text-sm font-medium text-slate-600">
            Active Cases
        </p>
    </div>

    <div class="rounded-2xl border border-red-300 bg-white p-6 shadow-sm transition-all duration-200 ease-out hover:-translate-y-1 hover:border-red-500 hover:shadow-lg">
        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-red-50 text-red-600">
            ⚠️
        </div>

        <p class="text-4xl font-bold text-slate-900">
            {{ $criticalIncidents }}
        </p>

        <p class="mt-2 text-sm font-medium text-slate-600">
            Critical
        </p>
    </div>

    <div class="rounded-2xl border border-emerald-300 bg-white p-6 shadow-sm transition-all duration-200 ease-out hover:-translate-y-1 hover:border-emerald-500 hover:shadow-lg">
        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-green-50 text-green-600">
            ✅
        </div>

        <p class="text-4xl font-bold text-slate-900">
            {{ $resolvedCases }}
        </p>

        <p class="mt-2 text-sm font-medium text-slate-600">
            Resolved
        </p>
    </div>

    <div class="rounded-2xl border border-violet-300 bg-white p-6 shadow-sm transition-all duration-200 ease-out hover:-translate-y-1 hover:border-violet-500 hover:shadow-lg">
        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-blue-950">
            👥
        </div>

        <p class="text-4xl font-bold text-slate-900">
            {{ $tanodOnDuty }}
        </p>

        <p class="mt-2 text-sm font-medium text-slate-600">
            Tanod On Duty
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
                Watch
            </span>
        </div>

        <div class="mb-5 flex items-center gap-6">
            <p class="text-5xl font-bold text-slate-900">
                31°
            </p>

            <div>
                <p class="text-lg font-bold text-slate-900">
                    Cloudy
                </p>

                <p class="text-sm text-slate-500">
                    72% humidity · 10 km/h wind
                </p>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm leading-relaxed text-slate-700">
            Advisory: Tanod patrols should exercise caution and stay alert for possible sudden heavy rainfall or thunderstorms throughout the day.
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
            @forelse ($recentIncidents as $incident)
                @php
                    $incidentTitle = data_get($incident, 'title')
                        ?? data_get($incident, 'incident_title')
                        ?? 'Untitled Incident';

                    $statusName = data_get($incident, 'currentStatus.status_name')
                        ?? data_get($incident, 'status.status_name')
                        ?? data_get($incident, 'status')
                        ?? 'Pending';

                    $normalizedStatus = strtolower(str_replace(' ', '_', (string) $statusName));

                    $priority = strtolower((string) (
                        data_get($incident, 'priority')
                        ?? data_get($incident, 'severity')
                        ?? 'low'
                    ));

                    $reportedRaw = data_get($incident, 'reported_at')
                        ?? data_get($incident, 'incident_datetime')
                        ?? data_get($incident, 'created_at');

                    try {
                        $reportedAgo = $reportedRaw
                            ? \Carbon\Carbon::parse($reportedRaw)->diffForHumans()
                            : 'Unknown time';
                    } catch (\Throwable $e) {
                        $reportedAgo = 'Unknown time';
                    }

                    $reporterName = data_get($incident, 'reporter.name')
    ?? data_get($incident, 'reporter_name')
    ?? data_get($incident, 'resident_name')
    ?? data_get($incident, 'reported_by_name')
    ?? 'Unknown';

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
                <p class="text-slate-500">
                    No recent incidents found.
                </p>
            @endforelse
        </div>
    </div>
</section>
@endsection