@extends('layouts.admin')

@section('title', 'Select Report Records | TabangNow')

@section('content')
<style id="reports-selection-v2-style">
    #metricPdfSelectionForm {
        background: #eff6ff !important;
        border-color: #93c5fd !important;
        color: #0f172a !important;
    }

    #metricPdfSelectionForm * {
        border-color: #bfdbfe;
    }

    #metricPdfSelectionForm table {
        background: #ffffff !important;
        color: #0f172a !important;
    }

    #metricPdfSelectionForm thead {
        background: #dbeafe !important;
        color: #0f172a !important;
    }

    #metricPdfSelectionForm tbody,
    #metricPdfSelectionForm tbody tr,
    #metricPdfSelectionForm tbody td {
        background: #ffffff !important;
        color: #0f172a !important;
    }

    #metricPdfSelectionForm tbody tr:hover td {
        background: #eff6ff !important;
    }

    #metricPdfSelectionForm label,
    #metricPdfSelectionForm p,
    #metricPdfSelectionForm span,
    #metricPdfSelectionForm th {
        color: #0f172a !important;
    }

    .reports-download-error {
        background: #fef2f2 !important;
        border: 1px solid #fca5a5 !important;
        color: #991b1b !important;
    }
</style>
<div class="space-y-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ $backUrl }}"
               class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-slate-900">
                ← Back to Reports
            </a>

            <h1 class="mt-3 text-2xl font-bold text-slate-900">
                {{ $selectedMetricLabel }} PDF
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                {{ $periodLabel }} · {{ number_format(count($records ?? [])) }} matching record{{ count($records ?? []) === 1 ? '' : 's' }}
            </p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white px-5 py-3 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">
                Selected Metric
            </p>

            <p class="mt-1 text-lg font-bold text-slate-900">
                {{ $selectedMetricLabel }}
            </p>
        </div>
    </div>

        @if ($errors->any())
        <div class="reports-download-error mb-4 rounded-xl px-4 py-3 text-sm font-semibold">
            {{ $errors->first() }}
        </div>
    @endif
{{-- CAPSTONE18_TOTAL_INCIDENTS_SELECTION_FILTERS_V2 --}}
<div
    id="reportTotalIncidentFilters"
    class="mb-5 hidden rounded-2xl border border-blue-200 bg-blue-50/60 p-4 shadow-sm"
>
    <div class="mb-4">
        <h2 class="text-sm font-black text-slate-900">
            Filter Total Incident Records
        </h2>

        <p class="mt-1 text-xs leading-5 text-slate-600">
            Search or narrow Total Incidents by severity, location, or status before selecting records for PDF.
        </p>
    </div>

    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <div>
            <label
                for="reportTotalIncidentSearch"
                class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-600"
            >
                Search Records
            </label>

            <input
                id="reportTotalIncidentSearch"
                type="search"
                autocomplete="off"
                placeholder="Incident ID, details..."
                class="w-full rounded-xl border border-blue-200 bg-white px-3 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            >
        </div>

        <div>
            <label
                for="reportTotalIncidentSeverity"
                class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-600"
            >
                Severity
            </label>

            <select
                id="reportTotalIncidentSeverity"
                class="w-full rounded-xl border border-blue-200 bg-white px-3 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            >
                <option value="">All Severities</option>
            </select>
        </div>

        <div>
            <label
                for="reportTotalIncidentLocation"
                class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-600"
            >
                Location / Barangay
            </label>

            <select
                id="reportTotalIncidentLocation"
                class="w-full rounded-xl border border-blue-200 bg-white px-3 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            >
                <option value="">All Locations</option>
            </select>
        </div>

        <div>
            <label
                for="reportTotalIncidentStatus"
                class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-600"
            >
                Status
            </label>

            <select
                id="reportTotalIncidentStatus"
                class="w-full rounded-xl border border-blue-200 bg-white px-3 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            >
                <option value="">All Statuses</option>
            </select>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-blue-100 pt-3">
        <p class="text-xs font-semibold text-slate-600">
            Showing
            <span
                id="reportTotalIncidentVisibleCount"
                class="font-black text-blue-800"
            >0</span>
            of
            <span
                id="reportTotalIncidentTotalCount"
                class="font-black text-slate-900"
            >0</span>
            incident records
        </p>

        <button
            id="reportTotalIncidentClearFilters"
            type="button"
            class="inline-flex items-center justify-center rounded-xl border border-blue-300 bg-white px-4 py-2 text-xs font-black text-blue-800 shadow-sm transition hover:bg-blue-100"
        >
            Clear Filters
        </button>
    </div>
</div>
<form method="POST"
          action="{{ route('admin.reports.download.pdf') }}"
          id="metricPdfSelectionForm"
          class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        @csrf

        <input type="hidden"
               name="period"
               value="{{ $period }}">

        <input type="hidden"
               name="metric"
               value="{{ $selectedMetric }}">

        @if (($isCustomPeriod ?? false))
            <input type="hidden"
                   name="date_from"
                   value="{{ $dateFrom }}">

            <input type="hidden"
                   name="date_to"
                   value="{{ $dateTo }}">
        @endif
<div class="sticky top-0 z-20 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white px-5 py-4">
            <div class="flex flex-wrap items-center gap-3">
                <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-700">
                    <input type="checkbox"
                           id="metricSelectAll"
                           class="h-4 w-4 rounded border-slate-300 text-blue-700 focus:ring-blue-500">
                    Select All
                </label>

                <span class="text-xs font-semibold text-slate-500">
                    <span id="metricSelectedCount">0</span>
                    selected
                </span>

                <button type="button"
                        id="metricListToggle"
                        class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50">
                    Expand List
                </button>

                <button type="button"
                        id="metricScrollTop"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-300 bg-white text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50"
                        title="Scroll to top"
                        aria-label="Scroll to top">
                    ↑
                </button>

                <button type="button"
                        id="metricScrollBottom"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-300 bg-white text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50"
                        title="Scroll to bottom"
                        aria-label="Scroll to bottom">
                    ↓
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="submit"
                        name="selection_mode"
                        value="selected"
                        id="downloadSelectedMetricPdf"
                        class="inline-flex h-10 items-center justify-center rounded-xl border border-blue-900 bg-white px-4 text-xs font-bold text-blue-950 shadow-sm hover:bg-blue-50">
                    Download Selected PDF
                </button>

                <button type="submit"
                        name="selection_mode"
                        value="all"
                        class="inline-flex h-10 items-center justify-center rounded-xl bg-blue-950 px-4 text-xs font-bold text-white shadow-sm hover:bg-blue-900">
                    Download All PDF
                </button>
            </div>
        </div>

        <div id="metricRecordScroller"
             style="max-height: 430px; overflow-y: auto;"
             class="relative">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="sticky top-0 z-10 bg-slate-50">
                    <tr>
                        <th class="w-12 px-4 py-3"></th>
                        <th class="px-4 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">
                            Category
                        </th>
                        <th class="px-4 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">
                            Details
                        </th>
                        <th class="px-4 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">
                            Type / Shift
                        </th>
                        <th class="px-4 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">
                            Severity
                        </th>
                        <th class="px-4 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">
                            Barangay / Location
                        </th>
                        <th class="px-4 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">
                            Status
                        </th>
                        <th class="whitespace-nowrap px-4 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">
                            Date / As Of
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse (($records ?? []) as $record)
                        <tr class="transition hover:bg-slate-50">
                            <td class="px-4 py-3 align-top">
                                <input type="checkbox"
                                       name="records[]"
                                       value="{{ $record['record_key'] }}"
                                       class="metric-record-checkbox h-4 w-4 rounded border-slate-300 text-blue-700 focus:ring-blue-500">
                            </td>

                            <td class="px-4 py-3 align-top font-semibold text-slate-700">
                                {{ $record['category'] ?? '—' }}
                            </td>

                            <td class="min-w-[240px] px-4 py-3 align-top text-slate-700">
                                {{ $record['title'] ?? '—' }}
                            </td>

                            <td class="px-4 py-3 align-top text-slate-600">
                                {{ $record['type'] ?? '—' }}
                            </td>

                            <td class="px-4 py-3 align-top text-slate-600">
                                {{ $record['severity'] ?? '—' }}
                            </td>

                            <td class="px-4 py-3 align-top text-slate-600">
                                {{ $record['barangay'] ?? '—' }}
                            </td>

                            <td class="px-4 py-3 align-top text-slate-600">
                                {{ $record['status'] ?? '—' }}
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 align-top text-slate-600">
                                {{ $record['datetime'] ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8"
                                class="px-6 py-12 text-center">
                                <p class="text-sm font-semibold text-slate-700">
                                    No records are available for this metric and timeline.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 bg-slate-50 px-5 py-3 text-xs text-slate-500">
            Scroll inside the list to move through the matching records. Use Expand List for a taller view.
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('metricPdfSelectionForm');
        const selectAll = document.getElementById('metricSelectAll');
        const checkboxes = Array.from(
            document.querySelectorAll('.metric-record-checkbox')
        );
        const selectedCount = document.getElementById('metricSelectedCount');
        const selectedButton = document.getElementById('downloadSelectedMetricPdf');
        const scroller = document.getElementById('metricRecordScroller');
        const toggle = document.getElementById('metricListToggle');
        const topButton = document.getElementById('metricScrollTop');
        const bottomButton = document.getElementById('metricScrollBottom');

        function updateSelectionState() {
            const checked = checkboxes.filter((checkbox) => checkbox.checked).length;

            if (selectedCount) {
                selectedCount.textContent = String(checked);
            }

            if (selectAll) {
                selectAll.checked =
                    checkboxes.length > 0
                    && checked === checkboxes.length;

                selectAll.indeterminate =
                    checked > 0
                    && checked < checkboxes.length;
            }

            if (selectedButton) {
                selectedButton.disabled = checked === 0;
                selectedButton.classList.toggle('opacity-50', checked === 0);
                selectedButton.classList.toggle('cursor-not-allowed', checked === 0);
            }
        }

        selectAll?.addEventListener('change', function () {
            checkboxes.forEach((checkbox) => {
                checkbox.checked = selectAll.checked;
            });

            updateSelectionState();
        });

        checkboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', updateSelectionState);
        });

        toggle?.addEventListener('click', function () {
            const expanded = toggle.dataset.expanded === 'true';

            scroller.style.maxHeight = expanded
                ? '430px'
                : '70vh';

            toggle.dataset.expanded = expanded
                ? 'false'
                : 'true';

            toggle.textContent = expanded
                ? 'Expand List'
                : 'Compact List';
        });

        topButton?.addEventListener('click', function () {
            scroller?.scrollTo({
                top: 0,
                behavior: 'smooth',
            });
        });

        bottomButton?.addEventListener('click', function () {
            if (! scroller) {
                return;
            }

            scroller.scrollTo({
                top: scroller.scrollHeight,
                behavior: 'smooth',
            });
        });

        form?.addEventListener('submit', function (event) {
            const submitter = event.submitter;

            if (
                submitter
                && submitter.value === 'selected'
                && ! checkboxes.some((checkbox) => checkbox.checked)
            ) {
                event.preventDefault();
            }
        });

        updateSelectionState();
    });
</script>



<script>
document.addEventListener('DOMContentLoaded', function () {
    const filterRoot =
        document.getElementById(
            'reportTotalIncidentFilters'
        );

    if (!filterRoot) {
        return;
    }

    function normalize(value) {
        return String(
            value ?? ''
        )
            .replace(/[_-]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase();
    }

    function compact(value) {
        return String(
            value ?? ''
        )
            .replace(/\s+/g, ' ')
            .trim();
    }

    function isTotalIncidentsSelection() {
        const candidates = [];

        const params =
            new URLSearchParams(
                window.location.search
            );

        for (
            const [
                key,
                value
            ]
            of params.entries()
        ) {
            const normalizedKey =
                normalize(key);

            if (
                normalizedKey.includes('metric')
                || normalizedKey.includes('report')
                || normalizedKey.includes('type')
                || normalizedKey.includes('category')
            ) {
                candidates.push(
                    value
                );
            }
        }

        document
            .querySelectorAll(
                'input[type="hidden"]'
            )
            .forEach(
                function (input) {
                    const name =
                        normalize(
                            input.name
                        );

                    if (
                        name.includes('metric')
                        || name.includes('report')
                        || name.includes('type')
                        || name.includes('category')
                    ) {
                        candidates.push(
                            input.value
                        );
                    }
                }
            );

        document
            .querySelectorAll(
                'h1, h2, h3, [data-metric-label], [data-report-title]'
            )
            .forEach(
                function (element) {
                    candidates.push(
                        element.textContent
                    );
                }
            );

        return candidates.some(
            function (candidate) {
                const value =
                    normalize(
                        candidate
                    );

                return value === 'total incidents'
                    || value === 'total incident'
                    || value === 'total incidents records'
                    || value.includes(
                        'total incidents'
                    );
            }
        );
    }

    if (
        !isTotalIncidentsSelection()
    ) {
        filterRoot.remove();

        return;
    }

    filterRoot.classList.remove(
        'hidden'
    );

    const searchInput =
        document.getElementById(
            'reportTotalIncidentSearch'
        );

    const severitySelect =
        document.getElementById(
            'reportTotalIncidentSeverity'
        );

    const locationSelect =
        document.getElementById(
            'reportTotalIncidentLocation'
        );

    const statusSelect =
        document.getElementById(
            'reportTotalIncidentStatus'
        );

    const clearButton =
        document.getElementById(
            'reportTotalIncidentClearFilters'
        );

    const visibleCount =
        document.getElementById(
            'reportTotalIncidentVisibleCount'
        );

    const totalCount =
        document.getElementById(
            'reportTotalIncidentTotalCount'
        );

    function headerIndexes(table) {
        const headers =
            Array.from(
                table.querySelectorAll(
                    'thead th'
                )
            );

        const labels =
            headers.map(
                function (header) {
                    return normalize(
                        header.textContent
                    );
                }
            );

        function find(names) {
            return labels.findIndex(
                function (label) {
                    return names.some(
                        function (name) {
                            return label.includes(
                                name
                            );
                        }
                    );
                }
            );
        }

        return {
            severity:
                find([
                    'severity',
                    'priority',
                ]),

            location:
                find([
                    'barangay',
                    'location',
                    'area',
                ]),

            status:
                find([
                    'status',
                ]),
        };
    }

    function findIncidentTable() {
        const tables =
            Array.from(
                document.querySelectorAll(
                    'table'
                )
            );

        let bestTable =
            null;

        let bestScore =
            -1;

        tables.forEach(
            function (table) {
                const indexes =
                    headerIndexes(
                        table
                    );

                const rowCount =
                    table.querySelectorAll(
                        'tbody tr'
                    ).length;

                let score =
                    0;

                if (
                    indexes.severity >= 0
                ) {
                    score += 4;
                }

                if (
                    indexes.location >= 0
                ) {
                    score += 4;
                }

                if (
                    indexes.status >= 0
                ) {
                    score += 4;
                }

                if (
                    table.querySelector(
                        'input[type="checkbox"]'
                    )
                ) {
                    score += 2;
                }

                if (
                    rowCount > 0
                ) {
                    score += 1;
                }

                if (
                    score > bestScore
                ) {
                    bestScore =
                        score;

                    bestTable =
                        table;
                }
            }
        );

        return bestScore >= 9
            ? bestTable
            : null;
    }

    const table =
        findIncidentTable();

    if (!table) {
        /*
         * Total Incidents should always have the severity/location/status
         * selection table. Hide the filter rather than presenting controls
         * that cannot function against the current DOM.
         */
        filterRoot.classList.add(
            'hidden'
        );

        return;
    }

    const indexes =
        headerIndexes(
            table
        );

    const rows =
        Array.from(
            table.querySelectorAll(
                'tbody tr'
            )
        )
            .filter(
                function (row) {
                    const cells =
                        row.querySelectorAll(
                            'td'
                        );

                    return cells.length > 1;
                }
            );

    function cellText(
        row,
        index
    ) {
        if (index < 0) {
            return '';
        }

        const cells =
            Array.from(
                row.children
            );

        return compact(
            cells[index]?.textContent
            ?? ''
        );
    }

    const records =
        rows.map(
            function (row) {
                const severity =
                    cellText(
                        row,
                        indexes.severity
                    );

                const location =
                    cellText(
                        row,
                        indexes.location
                    );

                const status =
                    cellText(
                        row,
                        indexes.status
                    );

                return {
                    row:
                        row,

                    checkbox:
                        row.querySelector(
                            'input[type="checkbox"]'
                        ),

                    search:
                        normalize(
                            row.textContent
                        ),

                    severity:
                        normalize(
                            severity
                        ),

                    severityLabel:
                        severity,

                    location:
                        normalize(
                            location
                        ),

                    locationLabel:
                        location,

                    status:
                        normalize(
                            status
                        ),

                    statusLabel:
                        status,
                };
            }
        );

    function uniqueOptions(
        valueProperty,
        labelProperty
    ) {
        const values =
            new Map();

        records.forEach(
            function (record) {
                const value =
                    record[
                        valueProperty
                    ];

                const label =
                    record[
                        labelProperty
                    ];

                if (
                    value
                    && label
                    && !values.has(
                        value
                    )
                ) {
                    values.set(
                        value,
                        label
                    );
                }
            }
        );

        return Array.from(
            values.entries()
        );
    }

    function populateFilterSelect(
        select,
        values
    ) {
        values.forEach(
            function (
                [
                    value,
                    label,
                ]
            ) {
                const option =
                    document.createElement(
                        'option'
                    );

                option.value =
                    value;

                option.textContent =
                    label;

                select.appendChild(
                    option
                );
            }
        );
    }

    const severityOrder = [
        'pending assessment',
        'low',
        'moderate',
        'high',
        'critical',
    ];

    const severities =
        uniqueOptions(
            'severity',
            'severityLabel'
        )
            .sort(
                function (
                    left,
                    right
                ) {
                    const leftIndex =
                        severityOrder.indexOf(
                            left[0]
                        );

                    const rightIndex =
                        severityOrder.indexOf(
                            right[0]
                        );

                    const safeLeft =
                        leftIndex >= 0
                            ? leftIndex
                            : 999;

                    const safeRight =
                        rightIndex >= 0
                            ? rightIndex
                            : 999;

                    if (
                        safeLeft
                        !== safeRight
                    ) {
                        return safeLeft
                            - safeRight;
                    }

                    return left[1]
                        .localeCompare(
                            right[1]
                        );
                }
            );

    const locations =
        uniqueOptions(
            'location',
            'locationLabel'
        )
            .sort(
                function (
                    left,
                    right
                ) {
                    return left[1]
                        .localeCompare(
                            right[1],
                            undefined,
                            {
                                sensitivity: 'base',
                            }
                        );
                }
            );

    const statuses =
        uniqueOptions(
            'status',
            'statusLabel'
        )
            .sort(
                function (
                    left,
                    right
                ) {
                    return left[1]
                        .localeCompare(
                            right[1],
                            undefined,
                            {
                                sensitivity: 'base',
                            }
                        );
                }
            );

    populateFilterSelect(
        severitySelect,
        severities
    );

    populateFilterSelect(
        locationSelect,
        locations
    );

    populateFilterSelect(
        statusSelect,
        statuses
    );

    function applyTotalIncidentFilters() {
        const search =
            normalize(
                searchInput.value
            );

        const severity =
            normalize(
                severitySelect.value
            );

        const location =
            normalize(
                locationSelect.value
            );

        const status =
            normalize(
                statusSelect.value
            );

        let visible =
            0;

        records.forEach(
            function (record) {
                const matches =
                    (
                        !search
                        || record.search.includes(
                            search
                        )
                    )
                    && (
                        !severity
                        || record.severity
                            === severity
                    )
                    && (
                        !location
                        || record.location
                            === location
                    )
                    && (
                        !status
                        || record.status
                            === status
                    );

                record.row.hidden =
                    !matches;

                record.row.style.display =
                    matches
                        ? ''
                        : 'none';

                if (matches) {
                    visible +=
                        1;
                }
            }
        );

        visibleCount.textContent =
            String(
                visible
            );

        totalCount.textContent =
            String(
                records.length
            );
    }

    searchInput.addEventListener(
        'input',
        applyTotalIncidentFilters
    );

    [
        severitySelect,
        locationSelect,
        statusSelect,
    ].forEach(
        function (select) {
            select.addEventListener(
                'change',
                applyTotalIncidentFilters
            );
        }
    );

    clearButton.addEventListener(
        'click',
        function () {
            searchInput.value =
                '';

            severitySelect.value =
                '';

            locationSelect.value =
                '';

            statusSelect.value =
                '';

            applyTotalIncidentFilters();

            searchInput.focus();
        }
    );

    /*
     * Existing Select All behavior remains intact for the page, but any rows
     * hidden by these Total Incidents filters are immediately excluded.
     */
    function clearHiddenSelections() {
        records.forEach(
            function (record) {
                if (
                    record.row.hidden
                    && record.checkbox
                ) {
                    record.checkbox.checked =
                        false;
                }
            }
        );
    }

    table.addEventListener(
        'change',
        function (event) {
            if (
                event.target
                    instanceof HTMLInputElement
                && event.target.type
                    === 'checkbox'
            ) {
                window.setTimeout(
                    clearHiddenSelections,
                    0
                );
            }
        }
    );

    document
        .querySelectorAll(
            'button, input[type="button"], input[type="checkbox"]'
        )
        .forEach(
            function (control) {
                const identity =
                    normalize(
                        [
                            control.textContent,
                            control.value,
                            control.id,
                            control.name,
                            control.getAttribute(
                                'aria-label'
                            ),
                        ].join(' ')
                    );

                if (
                    identity.includes(
                        'select all'
                    )
                    || identity.includes(
                        'selectall'
                    )
                    || identity.includes(
                        'select all records'
                    )
                ) {
                    control.addEventListener(
                        'click',
                        function () {
                            window.setTimeout(
                                clearHiddenSelections,
                                0
                            );
                        }
                    );

                    control.addEventListener(
                        'change',
                        function () {
                            window.setTimeout(
                                clearHiddenSelections,
                                0
                            );
                        }
                    );
                }
            }
        );

    totalCount.textContent =
        String(
            records.length
        );

    applyTotalIncidentFilters();
});
</script>
@endsection
