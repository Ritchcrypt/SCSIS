@extends('layouts.admin')

@section('title', 'Reports & Analytics | DaoSystem')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                Reports & Analytics
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Dao, Capiz — {{ $periodLabel }}
            </p>
        </div>

        <div class="print-hidden flex flex-col gap-3 sm:flex-row sm:items-center">
            <form method="GET" action="{{ route('admin.reports.index') }}">
                <select name="period"
                        onchange="this.form.submit()"
                        class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    <option value="today" @selected($period === 'today')>
                        Today
                    </option>

                    <option value="week" @selected($period === 'week')>
                        This Week
                    </option>

                    <option value="month" @selected($period === 'month')>
                        This Month
                    </option>

                    <option value="year" @selected($period === 'year')>
                        This Year
                    </option>
                </select>
            </form>

            <a href="{{ route('admin.reports.pdf', ['period' => $period]) }}"
               class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                Download PDF
            </a>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                Total Incidents
            </p>

            <p class="mt-3 text-3xl font-bold text-blue-950">
                {{ $totalIncidents }}
            </p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                Active / Pending
            </p>

            <p class="mt-3 text-3xl font-bold text-orange-600">
                {{ $activeIncidents }}
            </p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                Resolved
            </p>

            <p class="mt-3 text-3xl font-bold text-green-600">
                {{ $resolvedIncidents }}
            </p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                Cases Filed
            </p>

            <p class="mt-3 text-3xl font-bold text-indigo-600">
                {{ $casesFiled }}
            </p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                Emergency Logs
            </p>

            <p class="mt-3 text-3xl font-bold text-red-600">
                {{ $emergencyLogs }}
            </p>
        </div>
    </div>

    {{-- Records Breakdown --}}
<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-6 py-4">
        <div>
            <h2 class="text-base font-bold text-slate-900">
                {{ $periodLabel }} Records Breakdown
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Incidents, cases, announcements, and emergency logs recorded within the selected period.
            </p>
        </div>

        @if (count($records) > 5)
            <button type="button"
                    id="recordsToggleButton"
                    onclick="toggleRecordsBreakdown()"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-300 bg-white text-lg font-bold text-slate-700 shadow-sm hover:bg-slate-50"
                    title="Show more or less records">
                ↓
            </button>
        @endif
    </div>

    <div class="overflow-x-auto p-6">
        @if (count($records))
            <table class="min-w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-xs font-bold uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-3">Category</th>
                        <th class="px-3 py-3">Title / Details</th>
                        <th class="px-3 py-3">Type</th>
                        <th class="px-3 py-3">Date & Time</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @foreach ($records as $index => $record)
                        <tr class="{{ $index >= 5 ? 'extra-record-row hidden' : '' }}">
                            <td class="px-3 py-3">
                                <span class="inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">
                                    {{ $record['category'] }}
                                </span>
                            </td>

                            <td class="px-3 py-3 font-semibold text-slate-900">
                                {{ $record['title'] }}
                            </td>

                            <td class="px-3 py-3 text-slate-600">
                                {{ $record['type'] }}
                            </td>

                            <td class="px-3 py-3 text-slate-600">
                                {{ $record['datetime'] }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if (count($records) > 5)
                <div id="recordsToggleText" class="mt-4 text-center text-xs font-semibold text-slate-500">
                    Showing 5 of {{ count($records) }} records
                </div>
            @endif
        @else
            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                <h3 class="text-sm font-bold text-slate-900">
                    No records found
                </h3>

                <p class="mt-1 text-sm text-slate-500">
                    There are no recorded activities for {{ strtolower($periodLabel) }}.
                </p>
            </div>
        @endif
    </div>
</div>

    {{-- Simple Analytics --}}
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-bold text-slate-900">
                    Incident Status
                </h2>
            </div>

            <div class="p-6">
                @if (count($statusSummary))
                    <div class="space-y-3">
                        @foreach ($statusSummary as $item)
                            <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <span class="text-sm font-semibold text-slate-700">
                                    {{ $item['label'] }}
                                </span>

                                <span class="text-sm font-bold text-slate-900">
                                    {{ $item['total'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-slate-500">
                        No status data available.
                    </p>
                @endif
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-bold text-slate-900">
                    Severity
                </h2>
            </div>

            <div class="p-6">
                @if (count($severitySummary))
                    <div class="space-y-3">
                        @foreach ($severitySummary as $item)
                            <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <span class="text-sm font-semibold text-slate-700">
                                    {{ $item['label'] }}
                                </span>

                                <span class="text-sm font-bold text-slate-900">
                                    {{ $item['total'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-slate-500">
                        No severity data available.
                    </p>
                @endif
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-bold text-slate-900">
                    Barangay Areas
                </h2>
            </div>

            <div class="p-6">
                @if (count($barangaySummary))
                    <div class="space-y-3">
                        @foreach ($barangaySummary as $item)
                            <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <span class="text-sm font-semibold text-slate-700">
                                    {{ $item['label'] }}
                                </span>

                                <span class="text-sm font-bold text-slate-900">
                                    {{ $item['total'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-slate-500">
                        No barangay data available.
                    </p>
                @endif
            </div>
        </div>
    </div>

    {{-- Tanod Response Summary --}}
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h2 class="text-base font-bold text-slate-900">
                Tanod Response Summary
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Workload and response status based on assigned incident records.
            </p>
        </div>

        <div class="overflow-x-auto p-6">
            @if (count($tanodSummary))
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-xs font-bold uppercase tracking-wide text-slate-500">
                            <th class="px-3 py-3">Tanod</th>
                            <th class="px-3 py-3">Badge</th>
                            <th class="px-3 py-3">Assigned</th>
                            <th class="px-3 py-3">Resolved</th>
                            <th class="px-3 py-3">Pending</th>
                            <th class="px-3 py-3">Last Update</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        @foreach ($tanodSummary as $tanod)
                            <tr>
                                <td class="px-3 py-3 font-semibold text-slate-900">
                                    {{ $tanod['name'] }}
                                </td>

                                <td class="px-3 py-3 text-slate-600">
                                    {{ $tanod['badge'] }}
                                </td>

                                <td class="px-3 py-3 text-slate-600">
                                    {{ $tanod['assigned'] }}
                                </td>

                                <td class="px-3 py-3 text-slate-600">
                                    {{ $tanod['resolved'] }}
                                </td>

                                <td class="px-3 py-3 text-slate-600">
                                    {{ $tanod['pending'] }}
                                </td>

                                <td class="px-3 py-3 text-slate-600">
                                    {{ $tanod['last_update'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                    <h3 class="text-sm font-bold text-slate-900">
                        No tanod assignments in this period
                    </h3>

                    <p class="mt-1 text-sm text-slate-500">
                        Tanod response data will appear once incidents are assigned.
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
    function toggleRecordsBreakdown() {
        const extraRows = document.querySelectorAll('.extra-record-row');
        const button = document.getElementById('recordsToggleButton');
        const text = document.getElementById('recordsToggleText');

        if (!extraRows.length || !button) {
            return;
        }

        const isHidden = extraRows[0].classList.contains('hidden');

        extraRows.forEach(function (row) {
            if (isHidden) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        });

        if (isHidden) {
            button.textContent = '↑';

            if (text) {
                text.textContent = 'Showing all records';
            }
        } else {
            button.textContent = '↓';

            if (text) {
                text.textContent = 'Showing 5 records only';
            }
        }
    }
</script>

<style>
    @media print {
        aside,
        header,
        nav,
        .print-hidden {
            display: none !important;
        }

        body {
            background: white !important;
        }

        .shadow-sm {
            box-shadow: none !important;
        }
    }
</style>
@endsection