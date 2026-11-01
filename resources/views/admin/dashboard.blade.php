@extends('layouts.admin')

@section('content')
    <div class="mb-8">
        <h1 class="text-3xl font-bold tracking-tight text-slate-900">Admin Dashboard</h1>
        <p class="mt-1 text-slate-600">Dao, Capiz — Community Safety Overview</p>
    </div>

    <section class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-5">
        <a href="{{ route('admin.incidents.index') }}" class="group block rounded-2xl border border-blue-300 bg-blue-50 p-6 shadow-sm transition-all duration-200 ease-out hover:-translate-y-1 hover:border-blue-500 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300">
            <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-blue-100 text-blue-700">📄</div>
            <p class="text-4xl font-bold text-slate-900">{{ $totalIncidents }}</p>
            <div class="mt-2 flex items-center justify-between gap-3"><p class="text-sm font-semibold text-blue-800">Total Incidents</p></div>
        </a>

        <a href="{{ route('admin.incidents.index') }}" class="group block rounded-2xl border border-amber-300 bg-amber-50 p-6 shadow-sm transition-all duration-200 ease-out hover:-translate-y-1 hover:border-amber-500 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-amber-300">
            <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-amber-100 text-amber-700">🕒</div>
            <p class="text-4xl font-bold text-slate-900">{{ $activeIncidents }}</p>
            <div class="mt-2 flex items-center justify-between gap-3"><p class="text-sm font-semibold text-amber-800">Active Incidents</p></div>
        </a>

        <a href="{{ route('admin.incidents.index') }}" class="group block rounded-2xl border border-red-300 bg-red-50 p-6 shadow-sm transition-all duration-200 ease-out hover:-translate-y-1 hover:border-red-500 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-red-300">
            <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-red-100 text-red-700">⚠️</div>
            <p class="text-4xl font-bold text-slate-900">{{ $criticalIncidents }}</p>
            <div class="mt-2 flex items-center justify-between gap-3"><p class="text-sm font-semibold text-red-800">Critical</p></div>
        </a>

        <a href="{{ route('admin.incidents.index') }}" class="group block rounded-2xl border border-emerald-300 bg-emerald-50 p-6 shadow-sm transition-all duration-200 ease-out hover:-translate-y-1 hover:border-emerald-500 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-emerald-300">
            <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">✅</div>
            <p class="text-4xl font-bold text-slate-900">{{ $resolvedIncidents }}</p>
            <div class="mt-2 flex items-center justify-between gap-3"><p class="text-sm font-semibold text-emerald-800">Resolved</p></div>
        </a>

        <a href="{{ route('admin.tanods.index') }}" class="group block rounded-2xl border border-violet-300 bg-violet-50 p-6 shadow-sm transition-all duration-200 ease-out hover:-translate-y-1 hover:border-violet-500 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-violet-300">
            <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-violet-100 text-violet-700">👥</div>
            <p class="text-4xl font-bold text-slate-900">{{ $tanodOnDuty }}</p>
            <div class="mt-2 flex items-center justify-between gap-3"><p class="text-sm font-semibold text-violet-800">Tanod On Duty</p></div>
        </a>
    </section>

    <section class="mt-8">
        @include('components.dashboard.weather-disaster-feed')
    </section>

    <section class="mt-8">
        <div class="rounded-2xl border border-orange-300 bg-white p-6 shadow-sm transition-all duration-200 ease-out hover:-translate-y-1 hover:border-orange-500 hover:shadow-lg">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-bold text-slate-900">Recent Incident Activity</h2>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-sm text-slate-400">Latest records</span>
                    <a href="{{ route('admin.incidents.index') }}"
                       class="inline-flex items-center rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 transition hover:border-blue-400 hover:bg-blue-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                        View All Incidents
                    </a>
                </div>
            </div>

            <div class="max-h-96 space-y-5 overflow-y-auto pr-2">
                @forelse ($recentIncidents as $incident)
                    @php
                        $incidentTitle = $incident->title
                            ?? $incident->incident_title
                            ?? 'Untitled Incident';

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

                    @can('view', $incident)
                        <a href="{{ route('admin.incidents.show', $incident) }}"
                           aria-label="Open incident: {{ $incidentTitle }}"
                           class="group block rounded-lg border-b border-slate-100 px-2 py-3 transition hover:bg-blue-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 last:border-b-0">
                    @else
                        <div class="border-b border-slate-100 px-2 py-3 last:border-b-0">
                    @endcan
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
                    @can('view', $incident)
                        </a>
                    @else
                        </div>
                    @endcan
                @empty
                    <p class="text-slate-500">No recent incidents found.</p>
                @endforelse
            </div>
        </div>
    </section>
@endsection