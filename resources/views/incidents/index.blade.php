@extends('layouts.admin')

@section('title', 'Incidents | DaoSystem')

@section('content')
@php
    $authUser = auth()->user();
    $role = $authUser?->role ?? 'admin';

    $routePrefix = match ($role) {
        'admin' => 'admin',
        'official', 'dao' => 'official',
        'tanod' => 'tanod',
        'resident' => 'resident',
        default => 'admin',
    };

    $indexRoute = "{$routePrefix}.incidents.index";
    $createRoute = "{$routePrefix}.incidents.create";
    $showRoute = "{$routePrefix}.incidents.show";

    $canCreateIncident = Route::has($createRoute)
        && in_array($role, ['admin', 'official', 'dao', 'resident'], true);

    $canDeleteIncident = $role === 'admin' && Route::has('admin.incidents.destroy');

    $severityBadgeClass = function ($severity) {
        $severity = strtolower((string) $severity);

        return match ($severity) {
            'critical', 'emergency' => 'border-red-200 bg-red-100 text-red-700',
            'high' => 'border-orange-200 bg-orange-100 text-orange-700',
            'medium', 'moderate' => 'border-yellow-200 bg-yellow-100 text-yellow-700',
            'low' => 'border-green-200 bg-green-100 text-green-700',
            default => 'border-slate-200 bg-slate-100 text-slate-700',
        };
    };

    $statusBadgeClass = function ($status) {
        $status = strtolower((string) $status);

        return match ($status) {
            'resolved', 'completed', 'closed' => 'border-green-200 bg-green-100 text-green-700',
            'escalated', 'critical' => 'border-slate-200 bg-slate-100 text-slate-700',
            'responding', 'dispatched', 'in progress', 'in_progress' => 'border-blue-200 bg-blue-100 text-blue-700',
            'pending', 'reported' => 'border-yellow-200 bg-yellow-100 text-yellow-700',
            'cancelled', 'canceled', 'rejected' => 'border-red-200 bg-red-100 text-red-700',
            default => 'border-slate-200 bg-slate-100 text-slate-700',
        };
    };
@endphp

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Incidents</h1>

                <p class="mt-2 text-sm text-slate-600">
                    Review, filter, and monitor reported community safety incidents in Dao, Capiz.
                </p>
            </div>

            @if ($canCreateIncident)
                <a href="{{ route($createRoute) }}"
                   class="inline-flex items-center justify-center rounded-xl bg-blue-700 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">
                    <span class="mr-2">+</span>
                    Report Incident
                </a>
            @endif
        </div>
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-5 py-4 text-sm font-semibold text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-semibold text-red-700">
            {{ session('error') }}
        </div>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <form method="GET" action="{{ Route::has($indexRoute) ? route($indexRoute) : '#' }}">
            <div class="grid gap-4 lg:grid-cols-[2fr_1fr_1fr_auto_auto]">
                <div>
                    <label for="search" class="mb-2 block text-sm font-semibold text-slate-700">
                        Search
                    </label>

                    <input id="search"
                           type="text"
                           name="search"
                           value="{{ $filters['search'] ?? request('search') }}"
                           placeholder="Search title, description, barangay..."
                           class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                </div>

                <div>
                    <label for="status" class="mb-2 block text-sm font-semibold text-slate-700">
                        Status
                    </label>

                    <select id="status"
                            name="status"
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                        <option value="all">All Statuses</option>

                        @foreach ($statuses as $status)
                            @php
                                $statusId = (string) data_get($status, 'id');
                                $statusName = data_get($status, 'status_name')
                                    ?? data_get($status, 'name')
                                    ?? 'Unnamed Status';
                            @endphp

                            <option value="{{ $statusId }}"
                                @selected((string) ($filters['status'] ?? request('status', 'all')) === $statusId)>
                                {{ $statusName }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="severity" class="mb-2 block text-sm font-semibold text-slate-700">
                        Severity
                    </label>

                    <select id="severity"
                            name="severity"
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                        <option value="all">All Severity</option>

                        @foreach ($severityOptions as $value => $label)
                            <option value="{{ $value }}"
                                @selected((string) ($filters['severity'] ?? request('severity', 'all')) === (string) $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end">
                    <button type="submit"
                            class="w-full rounded-xl bg-blue-700 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">
                        Filter
                    </button>
                </div>

                <div class="flex items-end">
                    <a href="{{ Route::has($indexRoute) ? route($indexRoute) : '#' }}"
                       class="w-full rounded-xl border border-slate-300 bg-white px-5 py-3 text-center text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Incident Reports</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Latest incident reports based on your access level.
                </p>
            </div>

            <div class="flex items-center gap-2">
    <button id="incidentReportsScrollUp"
            type="button"
            class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
        <span>▲</span>
        <span>Up</span>
    </button>

    <button id="incidentReportsScrollDown"
            type="button"
            class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
        <span>▼</span>
        <span>Down</span>
    </button>
</div>
        </div>

        <div id="incidentReportsBody" class="max-h-[560px] overflow-y-auto">
            <table class="min-w-full border-collapse">
                <thead class="sticky top-0 z-10 bg-slate-50">
                    <tr class="border-b border-slate-200">
                        <th class="min-w-[110px] whitespace-nowrap px-6 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-700">
    ID No.
</th>
                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-700">
                            Incident
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-700">
                            Severity
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-700">
                            Location
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-700">
                            Status
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-700">
                            Reported
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-700">
                            Action
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($incidents as $incident)
                        @php
                            $incidentTitle = data_get($incident, 'incident_title')
                                ?? data_get($incident, 'title')
                                ?? 'Untitled Incident';

                            $incidentDescription = data_get($incident, 'incident_description')
                                ?? data_get($incident, 'description')
                                ?? 'No description provided.';

                            $severity = data_get($incident, 'priority')
                                ?? data_get($incident, 'severity')
                                ?? 'low';

                            $severityLabel = ucfirst(str_replace('_', ' ', (string) $severity));

                            $barangayName = data_get($incident, 'barangay.barangay_name')
                                ?? data_get($incident, 'barangay.name')
                                ?? '—';

                            $locationText = data_get($incident, 'location.location_address')
    ?? data_get($incident, 'location.address')
    ?? data_get($incident, 'location.location_name')
    ?? data_get($incident, 'location.name')
    ?? data_get($incident, 'location_address')
    ?? data_get($incident, 'address')
    ?? data_get($incident, 'location')
    ?? data_get($incident, 'map_location_name')
    ?? 'No exact location provided';

                            $statusText = data_get($incident, 'currentStatus.status_name')
                                ?? data_get($incident, 'status.status_name')
                                ?? data_get($incident, 'status.name')
                                ?? data_get($incident, 'status')
                                ?? 'Pending';

                            $reportedRaw = data_get($incident, 'reported_at')
                                ?? data_get($incident, 'incident_datetime')
                                ?? data_get($incident, 'created_at');

                            try {
                                $reportedAt = $reportedRaw
                                    ? \Carbon\Carbon::parse($reportedRaw)
                                    : null;
                            } catch (\Throwable $e) {
                                $reportedAt = null;
                            }

                            $showUrl = Route::has($showRoute)
                                ? route($showRoute, $incident)
                                : '#';

                            $deleteUrl = $canDeleteIncident
                                ? route('admin.incidents.destroy', $incident)
                                : null;
                        @endphp

                        <tr class="border-b border-slate-200 hover:bg-slate-50">
    <td class="min-w-[110px] whitespace-nowrap px-6 py-4 align-top">
    <span class="text-sm font-bold text-slate-700">
        #{{ $incident->id }}
    </span>
</td>

    <td class="px-5 py-4 align-top">
                                <p class="font-semibold text-slate-900">
                                    {{ $incidentTitle }}
                                </p>

                                <p class="mt-1 max-w-md text-sm text-slate-600">
                                    {{ \Illuminate\Support\Str::limit($incidentDescription, 90) }}
                                </p>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <span class="inline-flex rounded-full border px-3 py-1 text-xs font-bold {{ $severityBadgeClass($severity) }}">
                                    {{ $severityLabel }}
                                </span>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <p class="text-sm font-semibold text-slate-900">
                                    {{ $barangayName }}
                                </p>

                                <p class="mt-1 max-w-xs text-sm text-slate-500">
                                    {{ \Illuminate\Support\Str::limit($locationText, 60) }}
                                </p>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <span class="inline-flex rounded-full border px-3 py-1 text-xs font-bold {{ $statusBadgeClass($statusText) }}">
                                    {{ $statusText }}
                                </span>
                            </td>

                            <td class="px-5 py-4 align-top">
                                @if ($reportedAt)
                                    <p class="text-sm font-semibold text-slate-900">
                                        {{ $reportedAt->format('M d, Y') }}
                                    </p>

                                    <p class="mt-1 text-sm text-slate-500">
                                        {{ $reportedAt->format('h:i A') }}
                                    </p>

                                    <p class="reported-relative mt-1 text-xs font-medium text-blue-700"
                                       data-reported-at="{{ $reportedAt->toIso8601String() }}">
                                        {{ $reportedAt->diffForHumans() }}
                                    </p>
                                @else
                                    <p class="text-sm text-slate-500">—</p>
                                @endif
                            </td>

                            <td class="px-5 py-4 align-top">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ $showUrl }}"
                                       title="View incident"
                                       class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100">
                                        👁
                                    </a>

                                    @if ($deleteUrl)
                                        <form method="POST"
      action="{{ $deleteUrl }}">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit"
                                                    title="Delete incident"
                                                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-red-200 bg-red-50 text-red-700 hover:bg-red-100">
                                                🗑
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center">
                                <p class="text-sm font-semibold text-slate-700">
                                    No incidents found.
                                </p>

                                <p class="mt-1 text-sm text-slate-500">
                                    Try changing the filters or submit a new incident report.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (method_exists($incidents, 'links'))
            <div class="border-t border-slate-200 px-5 py-4">
                {{ $incidents->links() }}
            </div>
        @endif
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const scrollUpButton = document.getElementById('incidentReportsScrollUp');
        const scrollDownButton = document.getElementById('incidentReportsScrollDown');
        const reportsBody = document.getElementById('incidentReportsBody');


        if (scrollUpButton && reportsBody) {
    scrollUpButton.addEventListener('click', function () {
        reportsBody.scrollBy({
            top: -260,
            behavior: 'smooth'
        });
    });
}


        if (scrollDownButton && reportsBody) {
    scrollDownButton.addEventListener('click', function () {
        reportsBody.scrollBy({
            top: 260,
            behavior: 'smooth'
        });
    });
}

        function formatRelativeTime(dateString) {
            const date = new Date(dateString);
            const now = new Date();

            if (Number.isNaN(date.getTime())) {
                return '';
            }

            const diffSeconds = Math.round((date.getTime() - now.getTime()) / 1000);
            const absSeconds = Math.abs(diffSeconds);

            const units = [
                { name: 'year', seconds: 31536000 },
                { name: 'month', seconds: 2592000 },
                { name: 'week', seconds: 604800 },
                { name: 'day', seconds: 86400 },
                { name: 'hour', seconds: 3600 },
                { name: 'minute', seconds: 60 },
                { name: 'second', seconds: 1 },
            ];

            const unit = units.find((item) => absSeconds >= item.seconds) || units[units.length - 1];
            const value = Math.round(diffSeconds / unit.seconds);

            if (typeof Intl !== 'undefined' && Intl.RelativeTimeFormat) {
                return new Intl.RelativeTimeFormat('en', { numeric: 'auto' }).format(value, unit.name);
            }

            if (value < 0) {
                return Math.abs(value) + ' ' + unit.name + (Math.abs(value) === 1 ? '' : 's') + ' ago';
            }

            return 'in ' + value + ' ' + unit.name + (value === 1 ? '' : 's');
        }

        function updateReportedTimes() {
            document.querySelectorAll('.reported-relative[data-reported-at]').forEach(function (element) {
                const value = formatRelativeTime(element.dataset.reportedAt);

                if (value) {
                    element.textContent = value;
                }
            });
        }

        updateReportedTimes();
        setInterval(updateReportedTimes, 60000);
    });
</script>
@endsection