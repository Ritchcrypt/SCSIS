@extends('layouts.admin')

@section('title', 'Incidents | DaoSystem')

@section('content')
@php
    /*
    |--------------------------------------------------------------------------
    | Route Prefix Resolver
    |--------------------------------------------------------------------------
    | This keeps the incident page usable for admin, official, tanod, and resident
    | without hardcoding only one route group.
    */

    $rawRole = data_get(auth()->user(), 'role', 'admin');

    if (is_object($rawRole)) {
        $role = strtolower((string) data_get($rawRole, 'value', 'admin'));
    } else {
        $role = strtolower((string) $rawRole);
    }

    $allowedRoles = ['admin', 'official', 'tanod', 'resident'];
    $routePrefix = in_array($role, $allowedRoles, true) ? $role . '.' : 'admin.';

    $indexRouteName = Route::has($routePrefix . 'incidents.index')
        ? $routePrefix . 'incidents.index'
        : (Route::has('incidents.index') ? 'incidents.index' : null);

    $createRouteName = Route::has($routePrefix . 'incidents.create')
        ? $routePrefix . 'incidents.create'
        : (Route::has('incidents.create') ? 'incidents.create' : null);

    $showRouteName = Route::has($routePrefix . 'incidents.show')
        ? $routePrefix . 'incidents.show'
        : (Route::has('incidents.show') ? 'incidents.show' : null);

    $indexUrl = $indexRouteName ? route($indexRouteName) : url()->current();
    $createUrl = $createRouteName ? route($createRouteName) : null;

    /*
    |--------------------------------------------------------------------------
    | Safe Collections
    |--------------------------------------------------------------------------
    | These prevent errors if the controller has not passed filter collections yet.
    */

    $categories = $categories ?? collect();
    $statuses = $statuses ?? collect();
    $barangays = $barangays ?? collect();

    /*
    |--------------------------------------------------------------------------
    | Badge Helpers
    |--------------------------------------------------------------------------
    */

    function incidentSeverityBadgeClass($severity)
    {
        $severity = strtolower((string) $severity);

        return match ($severity) {
            'critical', 'emergency' => 'bg-red-100 text-red-700 border-red-200',
            'high' => 'bg-orange-100 text-orange-700 border-orange-200',
            'medium', 'moderate' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
            'low' => 'bg-green-100 text-green-700 border-green-200',
            default => 'bg-slate-100 text-slate-700 border-slate-200',
        };
    }

    function incidentStatusBadgeClass($status)
    {
        $status = strtolower((string) $status);

        return match ($status) {
            'pending', 'reported' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
            'verified', 'validated' => 'bg-blue-100 text-blue-700 border-blue-200',
            'responding', 'in progress', 'in_progress' => 'bg-indigo-100 text-indigo-700 border-indigo-200',
            'resolved', 'completed', 'closed' => 'bg-green-100 text-green-700 border-green-200',
            'rejected', 'invalid', 'cancelled', 'canceled' => 'bg-red-100 text-red-700 border-red-200',
            default => 'bg-slate-100 text-slate-700 border-slate-200',
        };
    }
@endphp

<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-wide text-blue-700">
                Incident Management
            </p>

            <h1 class="mt-1 text-2xl font-bold text-slate-900">
                Incidents
            </h1>

            <p class="mt-2 max-w-3xl text-sm text-slate-600">
                Review, filter, and monitor reported community safety incidents in Dao, Capiz.
            </p>
        </div>

        @if ($createUrl)
            <a href="{{ $createUrl }}"
               class="inline-flex items-center justify-center rounded-xl bg-blue-700 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                + Report Incident
            </a>
        @endif
    </div>

    {{-- Flash Messages --}}
    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-5 py-4 text-sm font-medium text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-700">
            {{ session('error') }}
        </div>
    @endif

    {{-- Filters --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <form method="GET" action="{{ $indexUrl }}" class="grid gap-4 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <label for="search" class="mb-2 block text-sm font-semibold text-slate-700">
                    Search
                </label>

                <input
                    id="search"
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Search code, title, description, barangay..."
                    class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                >
            </div>

            <div class="lg:col-span-2">
                <label for="type" class="mb-2 block text-sm font-semibold text-slate-700">
                    Type
                </label>

                <select
                    id="type"
                    name="type"
                    class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                >
                    <option value="">All Types</option>

                    @foreach ($categories as $category)
                        @php
                            $categoryValue = data_get($category, 'id') ?? data_get($category, 'category_name');
                            $categoryLabel = data_get($category, 'category_name') ?? data_get($category, 'name') ?? $categoryValue;
                        @endphp

                        <option value="{{ $categoryValue }}" @selected((string) request('type') === (string) $categoryValue)>
                            {{ $categoryLabel }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="lg:col-span-2">
                <label for="status" class="mb-2 block text-sm font-semibold text-slate-700">
                    Status
                </label>

                <select
                    id="status"
                    name="status"
                    class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                >
                    <option value="">All Statuses</option>

                    @foreach ($statuses as $status)
                        @php
                            $statusValue = data_get($status, 'id') ?? data_get($status, 'status_name') ?? data_get($status, 'name');
                            $statusLabel = data_get($status, 'status_name') ?? data_get($status, 'name') ?? $statusValue;
                        @endphp

                        <option value="{{ $statusValue }}" @selected((string) request('status') === (string) $statusValue)>
                            {{ $statusLabel }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="lg:col-span-2">
                <label for="severity" class="mb-2 block text-sm font-semibold text-slate-700">
                    Severity
                </label>

                <select
                    id="severity"
                    name="severity"
                    class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                >
                    <option value="">All Severity</option>
                    <option value="low" @selected(request('severity') === 'low')>Low</option>
                    <option value="medium" @selected(request('severity') === 'medium')>Medium</option>
                    <option value="high" @selected(request('severity') === 'high')>High</option>
                    <option value="critical" @selected(request('severity') === 'critical')>Critical</option>
                </select>
            </div>

            <div class="flex items-end gap-2 lg:col-span-2">
                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    Filter
                </button>

                <a href="{{ $indexUrl }}"
                   class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                    Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Incidents Table --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-base font-bold text-slate-900">
                Incident Reports
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Latest incident reports based on your access level.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                            Incident
                        </th>

                        <th scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                            Type
                        </th>

                        <th scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                            Severity
                        </th>

                        <th scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                            Location
                        </th>

                        <th scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                            Status
                        </th>

                        <th scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                            Reported
                        </th>

                        <th scope="col" class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">
                            View
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($incidents as $incident)
                        @php
                            $incidentTitle = data_get($incident, 'display_title')
                                ?? data_get($incident, 'incident_title')
                                ?? data_get($incident, 'title')
                                ?? 'Untitled Incident';

                            $incidentCode = data_get($incident, 'display_code')
                                ?? data_get($incident, 'incident_code')
                                ?? ('INC-' . str_pad((string) data_get($incident, 'id'), 5, '0', STR_PAD_LEFT));

                            $incidentDescription = data_get($incident, 'incident_description')
                                ?? data_get($incident, 'description')
                                ?? 'No description provided.';

                            $categoryName = data_get($incident, 'category.category_name')
                                ?? data_get($incident, 'category.name')
                                ?? data_get($incident, 'type')
                                ?? 'Uncategorized';

                            $severityLabel = data_get($incident, 'severity_label')
                                ?? data_get($incident, 'severity')
                                ?? data_get($incident, 'priority')
                                ?? 'Low';

                            $barangayName = data_get($incident, 'barangay.barangay_name')
                                ?? data_get($incident, 'barangay.name')
                                ?? data_get($incident, 'barangay')
                                ?? '—';

                            $locationAddress = data_get($incident, 'location.location_address')
                                ?? data_get($incident, 'location.address')
                                ?? data_get($incident, 'location')
                                ?? 'No exact location';

                            $statusName = data_get($incident, 'currentStatus.status_name')
                                ?? data_get($incident, 'status.status_name')
                                ?? data_get($incident, 'status.name')
                                ?? data_get($incident, 'status')
                                ?? 'Pending';

                            $reportedRaw = data_get($incident, 'incident_datetime')
                                ?? data_get($incident, 'reported_at')
                                ?? data_get($incident, 'created_at');

                            try {
                                $reportedDate = $reportedRaw
                                    ? \Carbon\Carbon::parse($reportedRaw)->format('M d, Y')
                                    : '—';

                                $reportedTime = $reportedRaw
                                    ? \Carbon\Carbon::parse($reportedRaw)->format('h:i A')
                                    : '';
                            } catch (\Throwable $e) {
                                $reportedDate = '—';
                                $reportedTime = '';
                            }

                            $showUrl = $showRouteName
                                ? route($showRouteName, $incident)
                                : '#';
                        @endphp

                        <tr class="transition hover:bg-slate-50">
                            <td class="px-5 py-4 align-top">
                                <div class="max-w-md">
                                    <div class="flex flex-col gap-1">
                                        <span class="text-xs font-bold uppercase tracking-wide text-blue-700">
                                            {{ $incidentCode }}
                                        </span>

                                        <span class="font-semibold text-slate-900">
                                            {{ $incidentTitle }}
                                        </span>
                                    </div>

                                    <p class="mt-1 line-clamp-2 text-sm text-slate-500">
                                        {{ $incidentDescription }}
                                    </p>
                                </div>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <span class="text-sm font-medium text-slate-700">
                                    {{ $categoryName }}
                                </span>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <span class="inline-flex rounded-full border px-3 py-1 text-xs font-bold {{ incidentSeverityBadgeClass($severityLabel) }}">
                                    {{ ucfirst((string) $severityLabel) }}
                                </span>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <div class="max-w-xs">
                                    <p class="text-sm font-semibold text-slate-800">
                                        {{ $barangayName }}
                                    </p>

                                    <p class="mt-1 line-clamp-2 text-sm text-slate-500">
                                        {{ $locationAddress }}
                                    </p>
                                </div>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <span class="inline-flex rounded-full border px-3 py-1 text-xs font-bold {{ incidentStatusBadgeClass($statusName) }}">
                                    {{ ucfirst(str_replace('_', ' ', (string) $statusName)) }}
                                </span>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <div class="text-sm">
                                    <p class="font-semibold text-slate-800">
                                        {{ $reportedDate }}
                                    </p>

                                    @if ($reportedTime)
                                        <p class="mt-1 text-slate-500">
                                            {{ $reportedTime }}
                                        </p>
                                    @endif
                                </div>
                            </td>

                            <td class="px-5 py-4 text-right align-top">
                                @if ($showRouteName)
                                    <a href="{{ $showUrl }}"
                                       class="inline-flex items-center justify-center rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-100">
                                        View
                                    </a>
                                @else
                                    <span class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-400">
                                        View
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-14 text-center">
                                <div class="mx-auto max-w-md">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-2xl">
                                        🛡️
                                    </div>

                                    <h3 class="mt-4 text-base font-bold text-slate-900">
                                        No incidents found
                                    </h3>

                                    <p class="mt-2 text-sm text-slate-500">
                                        No incident reports matched the current filters.
                                    </p>

                                    <a href="{{ $indexUrl }}"
                                       class="mt-5 inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                                        Clear Filters
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (method_exists($incidents, 'links') && $incidents->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">
                {{ $incidents->links() }}
            </div>
        @endif
    </div>
</div>
@endsection