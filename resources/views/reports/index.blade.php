@extends('layouts.admin')

@section('title', 'Reports | DaoSystem')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                Reports
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Dao, Capiz — {{ $periodLabel }}
            </p>
        </div>

        <div class="print-hidden flex flex-col gap-3 sm:flex-row sm:items-center">
            <form method="GET" action="{{ route('admin.reports.index') }}">
                <select name="period"
                        onchange="this.form.submit()"
                        class="h-11 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
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

            <button type="button"
        onclick="openSpecificIncidentReportModal()"
        class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
    Incident PDF
</button>

<button type="button"
        onclick="openCaseReportModal()"
        class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
    Case PDF
</button>

<button type="button"
        onclick="openSpecificComplaintReportModal()"
        class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
    Complaint PDF
</button>

            <a href="{{ route('admin.reports.pdf', ['period' => $period]) }}"
               class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                Download PDF
            </a>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                Total Incidents
            </p>

            <p class="tn-report-total-number mt-3 text-3xl font-bold">
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
    </div>

    {{-- Records Breakdown --}}
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-6 py-4">
            <div>
                <h2 class="text-base font-bold text-slate-900">
                    {{ $periodLabel }} Records Breakdown
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Incidents, cases, and announcements recorded within the selected period.
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
                <table class="w-full table-fixed text-sm">
                    <colgroup>
                        <col class="w-[13%]">
                        <col class="w-[25%]">
                        <col class="w-[12%]">
                        <col class="w-[18%]">
                        <col class="w-[15%]">
                        <col class="w-[17%]">
                    </colgroup>

                    <thead>
                        <tr class="border-b border-slate-200 text-xs font-bold uppercase tracking-wide text-slate-500">
                            <th class="px-3 py-3 text-center">Category</th>
                            <th class="px-3 py-3 text-center">Details</th>
                            <th class="px-3 py-3 text-center">Severity</th>
                            <th class="px-3 py-3 text-center">Barangay Areas</th>
                            <th class="px-3 py-3 text-center">Incident Status</th>
                            <th class="px-3 py-3 text-center">Date & Time</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        @foreach ($records as $index => $record)
                            <tr class="{{ $index >= 5 ? 'extra-record-row hidden' : '' }}">
                                <td class="px-3 py-3 text-center font-normal text-slate-900">
                                    {{ $record['category'] }}
                                </td>

                                <td class="px-3 py-3 text-center font-normal text-slate-900">
                                    {{ $record['title'] }}
                                </td>

                                <td class="px-3 py-3 text-center font-normal text-slate-900">
                                    {{ $record['severity'] ?? '—' }}
                                </td>

                                <td class="px-3 py-3 text-center font-normal text-slate-900">
                                    {{ $record['barangay'] ?? '—' }}
                                </td>

                                <td class="px-3 py-3 text-center font-normal text-slate-900">
                                    {{ $record['status'] ?? '—' }}
                                </td>

                                <td class="px-3 py-3 text-center font-normal text-slate-900">
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

    {{-- Tanod Response Summary --}}
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h2 class="text-base font-bold text-slate-900">
                Tanod Response Summary
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Acceptance and decline activity from Tanod Tasks within the selected period.
            </p>
        </div>

        <div class="overflow-x-auto p-6">
            @if (count($tanodSummary))
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-xs font-bold uppercase tracking-wide text-slate-500">
                            <th class="px-3 py-3">Tanod</th>
                            <th class="px-3 py-3 text-center">Total Tasks</th>
                            <th class="px-3 py-3 text-center">Accepted</th>
                            <th class="px-3 py-3 text-center">Declined</th>
                            <th class="px-3 py-3 text-center">Pending</th>
                            <th class="px-3 py-3 text-center">Response Rate</th>
                            <th class="px-3 py-3">Last Response</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        @foreach ($tanodSummary as $tanod)
                            <tr>
                                <td class="px-3 py-3 font-normal text-slate-900">
                                    {{ $tanod['name'] }}
                                </td>

                                <td class="px-3 py-3 text-center text-slate-900">
                                    {{ $tanod['total_tasks'] }}
                                </td>

                                <td class="px-3 py-3 text-center font-normal text-slate-900">
                                    {{ $tanod['accepted'] }}
                                </td>

                                <td class="px-3 py-3 text-center font-normal text-slate-900">
                                    {{ $tanod['declined'] }}
                                </td>

                                <td class="px-3 py-3 text-center font-normal text-slate-900">
                                    {{ $tanod['pending'] }}
                                </td>

                                <td class="px-3 py-3 text-center text-slate-900">
                                    {{ $tanod['response_rate'] }}%
                                </td>

                                <td class="px-3 py-3 font-normal text-slate-900">
                                    {{ $tanod['last_response'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                    <h3 class="text-sm font-bold text-slate-900">
                        No tanod task responses in this period
                    </h3>

                    <p class="mt-1 text-sm text-slate-500">
                        Response data will appear when tanods accept or decline tasks.
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Incident Report Modal --}}
<div id="specificIncidentReportModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-5 flex items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-slate-900">
                    Incident Report
                </h2>

                <p class="mt-1 text-sm leading-6 text-slate-500">
                    Generate a separate PDF report for one selected incident only.
                </p>
            </div>

            <button type="button"
                    onclick="closeSpecificIncidentReportModal()"
                    class="text-2xl leading-none text-slate-500 hover:text-slate-900">
                &times;
            </button>
        </div>

        @if (($incidentReportOptions ?? collect())->count())
            <form id="specificIncidentReportForm"
                  data-report-url-template="{{ url('/admin/reports/incidents/__INCIDENT_ID__/pdf') }}"
                  class="space-y-5">
                <input type="hidden" id="specific_incident_id">

                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <label class="block text-sm font-semibold text-slate-700">
                            Select Incident
                        </label>

                        <div class="flex items-center gap-2">
                            <button type="button"
                                    onclick="scrollSpecificReportList('specificIncidentReportList', 'up')"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 bg-white text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50">
                                ↑
                            </button>

                            <button type="button"
                                    onclick="scrollSpecificReportList('specificIncidentReportList', 'down')"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 bg-white text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50">
                                ↓
                            </button>
                        </div>
                    </div>

                    <div id="specificIncidentSelectedLabel"
                         class="mb-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-600">
                        No incident selected
                    </div>

                    <div id="specificIncidentReportList"
                         data-specific-report-list
                         class="max-h-64 overflow-y-auto rounded-xl border border-slate-300 bg-white p-2">
                        @foreach ($incidentReportOptions as $incidentOption)
                            <button type="button"
                                    data-specific-report-option
                                    data-value="{{ $incidentOption['id'] }}"
                                    onclick="selectSpecificReportOption(this, 'specific_incident_id', 'specificIncidentSelectedLabel')"
                                    class="mb-2 block w-full rounded-lg px-4 py-3 text-left text-sm font-semibold text-slate-700 hover:bg-blue-50 hover:text-blue-800">

                                    const caseWarning = document.getElementById('caseReportWarning');

if (caseWarning) {
    caseWarning.classList.add('hidden');
}
                                {{ $incidentOption['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div id="specificIncidentWarning"
                     class="hidden rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    Select one incident first.
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
                    <button type="button"
                            onclick="closeSpecificIncidentReportModal()"
                            class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Cancel
                    </button>

                    <button type="submit"
                            class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Generate PDF
                    </button>
                </div>
            </form>
        @else
            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                <h3 class="text-sm font-bold text-slate-900">
                    No incidents available
                </h3>
            </div>
        @endif
    </div>
</div>

{{-- Case Report Modal --}}
<div id="caseReportModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-5 flex items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-slate-900">
                    Case Report
                </h2>

                <p class="mt-1 text-sm leading-6 text-slate-500">
                    Generate a separate PDF report for one selected case management record only.
                </p>
            </div>

            <button type="button"
                    onclick="closeCaseReportModal()"
                    class="text-2xl leading-none text-slate-500 hover:text-slate-900">
                &times;
            </button>
        </div>

        @if (($caseReportOptions ?? collect())->count())
            <form id="caseReportForm"
                  data-report-url-template="{{ url('/admin/reports/cases/__CASE_ID__/pdf') }}"
                  class="space-y-5">
                <input type="hidden" id="case_report_id">

                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <label class="block text-sm font-semibold text-slate-700">
                            Select Case
                        </label>

                        <div class="flex items-center gap-2">
                            <button type="button"
                                    onclick="scrollSpecificReportList('caseReportList', 'up')"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 bg-white text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50">
                                ↑
                            </button>

                            <button type="button"
                                    onclick="scrollSpecificReportList('caseReportList', 'down')"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 bg-white text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50">
                                ↓
                            </button>
                        </div>
                    </div>

                    <div id="caseReportSelectedLabel"
                         class="mb-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-600">
                        No case selected
                    </div>

                    <div id="caseReportList"
                         data-specific-report-list
                         class="max-h-64 overflow-y-auto rounded-xl border border-slate-300 bg-white p-2">
                        @foreach ($caseReportOptions as $caseOption)
                            <button type="button"
                                    data-specific-report-option
                                    data-value="{{ $caseOption['id'] }}"
                                    onclick="selectSpecificReportOption(this, 'case_report_id', 'caseReportSelectedLabel')"
                                    class="mb-2 block w-full rounded-lg px-4 py-3 text-left text-sm font-semibold text-slate-700 hover:bg-blue-50 hover:text-blue-800">
                                {{ $caseOption['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div id="caseReportWarning"
                     class="hidden rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    Select one case first.
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
                    <button type="button"
                            onclick="closeCaseReportModal()"
                            class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Cancel
                    </button>

                    <button type="submit"
                            class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Generate PDF
                    </button>
                </div>
            </form>
        @else
            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                <h3 class="text-sm font-bold text-slate-900">
                    No cases available
                </h3>
            </div>
        @endif
    </div>
</div>

{{-- Complaint Report Modal --}}
<div id="specificComplaintReportModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-5 flex items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-slate-900">
                    Complaint Report
                </h2>

                <p class="mt-1 text-sm leading-6 text-slate-500">
                    Generate a separate PDF report for one selected resident complaint only.
                </p>
            </div>

            <button type="button"
                    onclick="closeSpecificComplaintReportModal()"
                    class="text-2xl leading-none text-slate-500 hover:text-slate-900">
                &times;
            </button>
        </div>

        @if (($complaintReportOptions ?? collect())->count())
            <form id="specificComplaintReportForm"
                  data-report-url-template="{{ url('/admin/reports/complaints/__COMPLAINT_ID__/pdf') }}"
                  class="space-y-5">
                <input type="hidden" id="specific_complaint_id">

                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <label class="block text-sm font-semibold text-slate-700">
                            Select Complaint
                        </label>

                        <div class="flex items-center gap-2">
                            <button type="button"
                                    onclick="scrollSpecificReportList('specificComplaintReportList', 'up')"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 bg-white text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50">
                                ↑
                            </button>

                            <button type="button"
                                    onclick="scrollSpecificReportList('specificComplaintReportList', 'down')"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 bg-white text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50">
                                ↓
                            </button>
                        </div>
                    </div>

                    <div id="specificComplaintSelectedLabel"
                         class="mb-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-600">
                        No complaint selected
                    </div>

                    <div id="specificComplaintReportList"
                         data-specific-report-list
                         class="max-h-64 overflow-y-auto rounded-xl border border-slate-300 bg-white p-2">
                        @foreach ($complaintReportOptions as $complaintOption)
                            <button type="button"
                                    data-specific-report-option
                                    data-value="{{ $complaintOption['id'] }}"
                                    onclick="selectSpecificReportOption(this, 'specific_complaint_id', 'specificComplaintSelectedLabel')"
                                    class="mb-2 block w-full rounded-lg px-4 py-3 text-left text-sm font-semibold text-slate-700 hover:bg-blue-50 hover:text-blue-800">
                                {{ $complaintOption['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div id="specificComplaintWarning"
                     class="hidden rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    Select one complaint first.
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
                    <button type="button"
                            onclick="closeSpecificComplaintReportModal()"
                            class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Cancel
                    </button>

                    <button type="submit"
                            class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Generate PDF
                    </button>
                </div>
            </form>
        @else
            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                <h3 class="text-sm font-bold text-slate-900">
                    No complaints available
                </h3>
            </div>
        @endif
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

    function openSpecificIncidentReportModal() {
        openSpecificReportModal('specificIncidentReportModal');
    }

    function closeSpecificIncidentReportModal() {
        closeSpecificReportModal('specificIncidentReportModal');
    }

    function openSpecificComplaintReportModal() {
        openSpecificReportModal('specificComplaintReportModal');
    }

    function closeSpecificComplaintReportModal() {
        closeSpecificReportModal('specificComplaintReportModal');
    }

    function openSpecificReportModal(modalId) {
        const modal = document.getElementById(modalId);

        if (!modal) {
            return;
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeSpecificReportModal(modalId) {
        const modal = document.getElementById(modalId);

        if (!modal) {
            return;
        }

        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function openCaseReportModal() {
    openSpecificReportModal('caseReportModal');
}

function closeCaseReportModal() {
    closeSpecificReportModal('caseReportModal');
}

    function scrollSpecificReportList(listId, direction) {
        const list = document.getElementById(listId);

        if (!list) {
            return;
        }

        list.scrollBy({
            top: direction === 'up' ? -180 : 180,
            behavior: 'smooth',
        });
    }

    function selectSpecificReportOption(button, inputId, labelId) {
        const input = document.getElementById(inputId);
        const label = document.getElementById(labelId);

        if (!button || !input || !label) {
            return;
        }

        const list = button.closest('[data-specific-report-list]');

        if (list) {
            list.querySelectorAll('[data-specific-report-option]').forEach(function (option) {
                option.classList.remove('bg-blue-100', 'text-blue-900', 'ring-2', 'ring-blue-200');
            });
        }

        button.classList.add('bg-blue-100', 'text-blue-900', 'ring-2', 'ring-blue-200');

        input.value = button.dataset.value || '';
        label.textContent = button.textContent.trim();

        const incidentWarning = document.getElementById('specificIncidentWarning');
        const complaintWarning = document.getElementById('specificComplaintWarning');

        if (incidentWarning) {
            incidentWarning.classList.add('hidden');
        }

        if (complaintWarning) {
            complaintWarning.classList.add('hidden');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const incidentForm = document.getElementById('specificIncidentReportForm');
        const incidentInput = document.getElementById('specific_incident_id');
        const incidentWarning = document.getElementById('specificIncidentWarning');

        if (incidentForm && incidentInput) {
            incidentForm.addEventListener('submit', function (event) {
                event.preventDefault();

                if (!incidentInput.value) {
                    if (incidentWarning) {
                        incidentWarning.classList.remove('hidden');
                    }

                    return;
                }

                const template = incidentForm.dataset.reportUrlTemplate;
                const caseForm = document.getElementById('caseReportForm');
const caseInput = document.getElementById('case_report_id');
const caseWarning = document.getElementById('caseReportWarning');

if (caseForm && caseInput) {
    caseForm.addEventListener('submit', function (event) {
        event.preventDefault();

        if (!caseInput.value) {
            if (caseWarning) {
                caseWarning.classList.remove('hidden');
            }

            return;
        }

        const template = caseForm.dataset.reportUrlTemplate;

        if (!template) {
            return;
        }

        window.location.href = template.replace('__CASE_ID__', encodeURIComponent(caseInput.value));
    });
}

                if (!template) {
                    return;
                }

                window.location.href = template.replace('__INCIDENT_ID__', encodeURIComponent(incidentInput.value));
            });
        }

        const complaintForm = document.getElementById('specificComplaintReportForm');
        const complaintInput = document.getElementById('specific_complaint_id');
        const complaintWarning = document.getElementById('specificComplaintWarning');

        if (complaintForm && complaintInput) {
            complaintForm.addEventListener('submit', function (event) {
                event.preventDefault();

                if (!complaintInput.value) {
                    if (complaintWarning) {
                        complaintWarning.classList.remove('hidden');
                    }

                    return;
                }

                const template = complaintForm.dataset.reportUrlTemplate;

                if (!template) {
                    return;
                }

                window.location.href = template.replace('__COMPLAINT_ID__', encodeURIComponent(complaintInput.value));
            });
        }
    });
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