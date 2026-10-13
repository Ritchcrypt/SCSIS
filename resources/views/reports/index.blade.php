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
            <form id="reportTimelineForm"
      method="GET"
      action="{{ route('admin.reports.index') }}"
      class="relative z-40 m-0 p-0"
      style="width: 330px; height: 64px; flex: 0 0 auto;">
    <input type="hidden"
           name="metric"
           value="{{ $selectedMetric ?? 'total' }}">

    <label for="reportTimelineSelect"
           class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">
        Timeline
    </label>

    <select id="reportTimelineSelect"
            name="period"
            onchange="handleReportsTimelineChange(this)"
            class="h-11 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
        <option value="today" @selected($period === 'today')>Today</option>
        <option value="week" @selected($period === 'week')>Last 7 Days</option>
        <option value="month" @selected($period === 'month')>Last 30 Days</option>
        <option value="year" @selected($period === 'year')>This Year</option>
        <option value="custom" @selected($period === 'custom')>Custom / Previous Dates</option>
    </select>

    <div id="reportCustomDateDropdown"
         class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl"
         style="display: none; position: absolute; top: 68px; right: 0; width: 330px; z-index: 9999;">
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="reportDateFrom"
                       class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-slate-500">
                    From
                </label>

                <input type="date"
                       id="reportDateFrom"
                       name="date_from"
                       value="{{ request('date_from', ($isCustomPeriod ?? false) ? ($dateFrom ?? '') : '') }}"
                       class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
            </div>

            <div>
                <label for="reportDateTo"
                       class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-slate-500">
                    To
                </label>

                <input type="date"
                       id="reportDateTo"
                       name="date_to"
                       value="{{ request('date_to', ($isCustomPeriod ?? false) ? ($dateTo ?? '') : '') }}"
                       class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
            </div>
        </div>

        <div class="mt-3 flex items-center justify-between gap-3">
            <span class="text-[11px] leading-4 text-slate-500">
                Select a previous date range.
            </span>

            <button type="submit"
                    id="reportCustomDateApply"
                    class="inline-flex h-9 shrink-0 items-center justify-center rounded-xl bg-blue-950 px-5 text-xs font-bold text-white shadow-sm hover:bg-blue-900">
                Apply
            </button>
        </div>
    </div>
</form>
@if (! empty($securePdfUrl))
@endif
        </div>
    </div>

    {{-- Summary Cards --}}
    <style>
        .reports-metric-card {
            background: #dbeafe !important;
            border: 1px solid #93c5fd !important;
            color: #0f172a !important;
        }

        .reports-metric-card:hover {
            background: #bfdbfe !important;
            border-color: #60a5fa !important;
        }

        .reports-metric-card.is-selected {
            box-shadow: 0 0 0 3px #2563eb !important;
        }

        .reports-metric-label,
        .reports-metric-arrow {
            color: #1d4ed8 !important;
        }

        .reports-metric-value {
            color: #0f172a !important;
        }

        .reports-metric-helper {
            color: #334155 !important;
        }

        .reports-metric-download {
            background: #eff6ff !important;
            border: 1px solid #93c5fd !important;
            color: #1e3a8a !important;
        }

        .reports-metric-download:hover {
            background: #dbeafe !important;
        }
    </style>

    @php
        $metricBaseQuery = [
            'period' => $period,
        ];

        if (($isCustomPeriod ?? false)) {
            $metricBaseQuery['date_from'] =
                $dateFrom ?? null;

            $metricBaseQuery['date_to'] =
                $dateTo ?? null;
        }

        $metricCards = [
            [
                'key' => 'total',
                'label' => 'Total Incidents',
                'value' => $totalIncidents ?? 0,
                'helper' => 'All incident records in the selected timeline',
            ],
            [
                'key' => 'active',
                'label' => 'Active Incidents',
                'value' => $activeIncidents ?? 0,
                'helper' => 'All current non-terminal incident statuses',
            ],
            [
                'key' => 'critical',
                'label' => 'Critical Incidents',
                'value' => $criticalIncidents ?? 0,
                'helper' => 'Unresolved critical-priority incidents',
            ],
            [
                'key' => 'resolved',
                'label' => 'Resolved Incidents',
                'value' => $resolvedIncidents ?? 0,
                'helper' => 'Resolved / completed / closed incidents',
            ],
            [
                'key' => 'distress',
                'label' => 'Distress Signals / SOS',
                'value' => $distressSignals ?? 0,
                'helper' => 'Live emergency SOS records in the selected timeline',
            ],
            [
                'key' => 'tanod',
                'label' => 'Tanod On Duty',
                'value' => $tanodOnDuty ?? 0,
                'helper' => 'Current Tanod roster duty status',
            ],
        ];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        @foreach ($metricCards as $metricCard)
            @php
                $isSelectedMetric =
                    ($selectedMetric ?? 'total')
                    === $metricCard['key'];

                $metricUrl = route(
                    'admin.reports.index',
                    array_merge(
                        $metricBaseQuery,
                        [
                            'metric' =>
                                $metricCard['key'],
                        ]
                    )
                ) . '#records-breakdown';

                $metricSelectionUrl =
                    $metricSelectionUrls[
                        $metricCard['key']
                    ]
                    ?? null;
            @endphp

            <div class="flex min-w-0 flex-col gap-2">
                <a href="{{ $metricUrl }}"
                   class="reports-metric-card {{ $isSelectedMetric ? 'is-selected' : '' }} group block flex-1 rounded-2xl p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-start justify-between gap-3">
                        <p class="reports-metric-label text-xs font-bold uppercase tracking-wide">
                            {{ $metricCard['label'] }}
                        </p>

                        <span class="reports-metric-arrow text-sm">
                            →
                        </span>
                    </div>

                    <p class="reports-metric-value mt-3 text-3xl font-bold">
                        {{ $metricCard['value'] }}
                    </p>

                    <p class="reports-metric-helper mt-2 text-xs">
                        {{ $metricCard['helper'] }}
                    </p>
                </a>

                @if ($metricSelectionUrl)
                    <a href="{{ $metricSelectionUrl }}"
                       class="reports-metric-download inline-flex h-10 w-full items-center justify-center rounded-xl px-3 text-xs font-bold shadow-sm">
                        Download PDF
                    </a>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Records Breakdown --}}
    <div id="records-breakdown"
         class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 px-6 py-4">
            <div>
                <h2 class="text-base font-bold text-slate-900">
                    {{ $selectedMetricLabel ?? 'Total Incidents' }} Records Breakdown
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    {{ $periodLabel }} · Click any record to preview it without leaving Reports.
                </p>
            </div>

            @if (count($records ?? []) > 5)
                <div class="flex items-center gap-2">
                    <button type="button"
                            onclick="scrollReportsBreakdown('up')"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-300 bg-white text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50"
                            title="Scroll records up">
                        ↑
                    </button>

                    <button type="button"
                            onclick="scrollReportsBreakdown('down')"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-300 bg-white text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50"
                            title="Scroll records down">
                        ↓
                    </button>
                </div>
            @endif
        </div>

        <div id="reportsRecordsScroller"
             class="max-h-[480px] overflow-auto p-6">
            @if (count($records ?? []))
                <table class="w-full min-w-[900px] table-fixed text-sm">
                    <colgroup>
                        <col class="w-[13%]">
                        <col class="w-[26%]">
                        <col class="w-[13%]">
                        <col class="w-[16%]">
                        <col class="w-[14%]">
                        <col class="w-[18%]">
                    </colgroup>

                    <thead class="sticky top-0 z-10 bg-white">
                        <tr class="border-b border-slate-200 text-xs font-bold uppercase tracking-wide text-slate-500">
                            <th class="px-3 py-3 text-center">Category</th>
                            <th class="px-3 py-3 text-center">Details</th>
                            <th class="px-3 py-3 text-center">Severity</th>
                            <th class="px-3 py-3 text-center">Barangay Areas</th>
                            <th class="px-3 py-3 text-center">Status</th>
                            <th class="px-3 py-3 text-center">Date & Time</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        @foreach (($records ?? []) as $index => $record)
                            <tr onclick="openReportRecordPreview({{ $index }})"
                                tabindex="0"
                                onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openReportRecordPreview({{ $index }}); }"
                                class="cursor-pointer transition hover:bg-blue-50 focus:bg-blue-50 focus:outline-none">
                                <td class="px-3 py-3 text-center text-slate-900">
                                    {{ $record['category'] ?? '—' }}
                                </td>

                                <td class="px-3 py-3 text-center text-slate-900">
                                    {{ $record['title'] ?? '—' }}
                                </td>

                                <td class="px-3 py-3 text-center text-slate-900">
                                    {{ $record['severity'] ?? '—' }}
                                </td>

                                <td class="px-3 py-3 text-center text-slate-900">
                                    {{ $record['barangay'] ?? '—' }}
                                </td>

                                <td class="px-3 py-3 text-center text-slate-900">
                                    {{ $record['status'] ?? '—' }}
                                </td>

                                <td class="px-3 py-3 text-center text-slate-900">
                                    {{ $record['datetime'] ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                    <h3 class="text-sm font-bold text-slate-900">
                        No records found
                    </h3>

                    <p class="mt-1 text-sm text-slate-500">
                        No {{ strtolower($selectedMetricLabel ?? 'selected metric') }} records were recorded during {{ strtolower($periodLabel) }}.
                    </p>
                </div>
            @endif
        </div>
    </div>

        <style id="reports-preview-functional-scroll-style">
        #reportRecordPreviewPanel {
            width: min(92vw, 560px);
            height: min(72vh, 620px);
            max-height: 72vh;
        }

        #reportRecordPreviewScroller {
            position: relative;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: none;
            -ms-overflow-style: none;
            min-height: 0;
        }

        #reportRecordPreviewScroller::-webkit-scrollbar {
            width: 0;
            height: 0;
        }

        #reportPreviewRail {
            width: 22px;
            flex: 0 0 22px;
            display: flex;
            flex-direction: column;
            align-items: center;
            background: #eff6ff;
            border-left: 1px solid #bfdbfe;
        }

        #reportPreviewRailTrack {
            position: relative;
            flex: 1 1 auto;
            width: 10px;
            min-height: 80px;
            margin: 6px 0;
            background: #dbeafe;
            border-radius: 999px;
            cursor: pointer;
        }

        #reportPreviewRailThumb {
            position: absolute;
            top: 0;
            left: 0;
            width: 10px;
            min-height: 42px;
            background: #2563eb;
            border-radius: 999px;
            cursor: grab;
            touch-action: none;
        }

        #reportPreviewRailThumb:active {
            cursor: grabbing;
        }

        .report-preview-rail-button {
            width: 22px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 0;
            background: #dbeafe;
            color: #1d4ed8;
            font-weight: 800;
            cursor: pointer;
            user-select: none;
        }

        .report-preview-rail-button:hover {
            background: #bfdbfe;
        }
    </style>

    <div id="reportRecordPreviewModal"
         class="fixed inset-0 hidden items-center justify-center bg-slate-900/60 p-3"
         style="z-index: 9999;"
         onclick="if (event.target === this) closeReportRecordPreview()">
        <div id="reportRecordPreviewPanel"
             class="relative flex flex-col overflow-hidden rounded-2xl border border-blue-200 bg-white shadow-2xl"
             style="z-index: 10000;">
            <div class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3">
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-blue-700">
                        Record Preview
                    </p>

                    <h3 id="reportPreviewTitle"
                        class="mt-1 break-words text-base font-bold leading-tight text-slate-900">
                        —
                    </h3>

                    <p id="reportPreviewReference"
                       class="mt-1 break-words text-[11px] font-semibold text-slate-500">
                        —
                    </p>
                </div>

                <button type="button"
                        onclick="closeReportRecordPreview()"
                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-slate-300 bg-white text-xl leading-none text-slate-700">
                    ×
                </button>
            </div>

            <div class="flex min-h-0 flex-1 overflow-hidden">
                <div id="reportRecordPreviewScroller"
                     class="min-h-0 flex-1 px-4 py-3">
                    <dl class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-xl bg-blue-50 p-3">
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-blue-700">Category</dt>
                            <dd id="reportPreviewCategory" class="mt-1 text-sm text-slate-900">—</dd>
                        </div>

                        <div class="rounded-xl bg-blue-50 p-3">
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-blue-700">Type / Shift</dt>
                            <dd id="reportPreviewType" class="mt-1 text-sm text-slate-900">—</dd>
                        </div>

                        <div class="rounded-xl bg-blue-50 p-3">
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-blue-700">Severity</dt>
                            <dd id="reportPreviewSeverity" class="mt-1 text-sm text-slate-900">—</dd>
                        </div>

                        <div class="rounded-xl bg-blue-50 p-3">
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-blue-700">Status</dt>
                            <dd id="reportPreviewStatus" class="mt-1 text-sm text-slate-900">—</dd>
                        </div>

                        <div class="rounded-xl bg-blue-50 p-3">
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-blue-700">Barangay</dt>
                            <dd id="reportPreviewBarangay" class="mt-1 text-sm text-slate-900">—</dd>
                        </div>

                        <div class="rounded-xl bg-blue-50 p-3">
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-blue-700">Location</dt>
                            <dd id="reportPreviewLocation" class="mt-1 break-words text-sm text-slate-900">—</dd>
                        </div>

                        <div class="rounded-xl bg-blue-50 p-3">
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-blue-700">Reporter / Person</dt>
                            <dd id="reportPreviewReporter" class="mt-1 text-sm text-slate-900">—</dd>
                        </div>

                        <div class="rounded-xl bg-blue-50 p-3 sm:col-span-2">
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-blue-700">Description / Details</dt>
                            <dd id="reportPreviewDescription" class="mt-1 whitespace-pre-line break-words text-sm text-slate-900">—</dd>
                        </div>

                        <div class="rounded-xl bg-blue-50 p-3">
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-blue-700">Reported At</dt>
                            <dd id="reportPreviewReportedAt" class="mt-1 text-sm text-slate-900">—</dd>
                        </div>

                        <div class="rounded-xl bg-blue-50 p-3">
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-blue-700">Recorded Date &amp; Time</dt>
                            <dd id="reportPreviewDatetime" class="mt-1 text-sm text-slate-900">—</dd>
                        </div>

                        <div class="rounded-xl bg-blue-50 p-3 sm:col-span-2">
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-blue-700">Last Updated</dt>
                            <dd id="reportPreviewUpdatedAt" class="mt-1 text-sm text-slate-900">—</dd>
                        </div>
                    </dl>
                </div>

                <div id="reportPreviewRail">
                    <button type="button"
                            id="reportPreviewRailUp"
                            class="report-preview-rail-button"
                            title="Scroll preview up"
                            aria-label="Scroll preview up">
                        ▲
                    </button>

                    <div id="reportPreviewRailTrack"
                         title="Drag the blue bar to scroll">
                        <div id="reportPreviewRailThumb"></div>
                    </div>

                    <button type="button"
                            id="reportPreviewRailDown"
                            class="report-preview-rail-button"
                            title="Scroll preview down"
                            aria-label="Scroll preview down">
                        ▼
                    </button>
                </div>
            </div>

            <div class="shrink-0 border-t border-slate-200 bg-white px-4 py-3">
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button"
                            onclick="closeReportRecordPreview()"
                            class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-xs font-bold text-slate-700">
                        Close
                    </button>

                    @if (($metricSelectionUrls[$selectedMetric ?? 'total'] ?? null))
                        <a href="{{ $metricSelectionUrls[$selectedMetric ?? 'total'] }}"
                           class="inline-flex h-10 items-center justify-center rounded-xl bg-blue-950 px-4 text-xs font-bold text-white">
                            Choose Records for PDF
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        window.tabangNowReportRecords = @json(array_values($records ?? []));

        let reportPreviewDragging = false;
        let reportPreviewDragStartY = 0;
        let reportPreviewDragStartTop = 0;

        function reportPreviewText(value, fallback = '—') {
            if (
                value === null
                || value === undefined
                || String(value).trim() === ''
            ) {
                return fallback;
            }

            return String(value);
        }

        function setReportPreviewText(id, value, fallback = '—') {
            const element = document.getElementById(id);

            if (!element) {
                return;
            }

            element.textContent =
                reportPreviewText(
                    value,
                    fallback
                );
        }

        function updateReportPreviewRail() {
            const scroller =
                document.getElementById('reportRecordPreviewScroller');

            const track =
                document.getElementById('reportPreviewRailTrack');

            const thumb =
                document.getElementById('reportPreviewRailThumb');

            if (!scroller || !track || !thumb) {
                return;
            }

            const visible =
                scroller.clientHeight;

            const total =
                scroller.scrollHeight;

            const trackHeight =
                track.clientHeight;

            if (total <= visible || trackHeight <= 0) {
                thumb.style.height =
                    Math.max(42, trackHeight) + 'px';

                thumb.style.top =
                    '0px';

                thumb.style.opacity =
                    '0.35';

                return;
            }

            const thumbHeight =
                Math.max(
                    42,
                    Math.round(
                        trackHeight
                        * visible
                        / total
                    )
                );

            const maxThumbTop =
                Math.max(
                    0,
                    trackHeight - thumbHeight
                );

            const maxScroll =
                total - visible;

            const thumbTop =
                maxScroll > 0
                    ? Math.round(
                        maxThumbTop
                        * scroller.scrollTop
                        / maxScroll
                    )
                    : 0;

            thumb.style.height =
                thumbHeight + 'px';

            thumb.style.top =
                thumbTop + 'px';

            thumb.style.opacity =
                '1';
        }

        function scrollReportPreviewBy(amount) {
            const scroller =
                document.getElementById('reportRecordPreviewScroller');

            if (!scroller) {
                return;
            }

            scroller.scrollBy({
                top: amount,
                behavior: 'smooth',
            });
        }

        function openReportRecordPreview(index) {
            const records =
                window.tabangNowReportRecords || [];

            const record =
                records[index];

            if (!record) {
                return;
            }

            setReportPreviewText('reportPreviewTitle', record.title);
            setReportPreviewText('reportPreviewReference', record.preview_reference, record.record_key || '—');
            setReportPreviewText('reportPreviewCategory', record.preview_category || record.category);
            setReportPreviewText('reportPreviewType', record.type);
            setReportPreviewText('reportPreviewSeverity', record.severity);
            setReportPreviewText('reportPreviewStatus', record.preview_status || record.status);
            setReportPreviewText('reportPreviewBarangay', record.preview_barangay || record.barangay);
            setReportPreviewText('reportPreviewLocation', record.preview_location || record.barangay);
            setReportPreviewText('reportPreviewReporter', record.preview_reporter, 'Not identified');
setReportPreviewText('reportPreviewDescription', record.preview_description || record.title, 'No description provided.');
setReportPreviewText('reportPreviewReportedAt', record.preview_reported_at || record.datetime);
            setReportPreviewText('reportPreviewDatetime', record.preview_incident_at || record.datetime);
            setReportPreviewText('reportPreviewUpdatedAt', record.preview_updated_at, '—');

            const modal =
                document.getElementById('reportRecordPreviewModal');

            const scroller =
                document.getElementById('reportRecordPreviewScroller');

            if (scroller) {
                scroller.scrollTop = 0;
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');

            document.documentElement.style.overflow =
                'hidden';

            document.body.style.overflow =
                'hidden';

            window.requestAnimationFrame(
                updateReportPreviewRail
            );
        }

        function closeReportRecordPreview() {
            const modal =
                document.getElementById('reportRecordPreviewModal');

            modal.classList.add('hidden');
            modal.classList.remove('flex');

            document.documentElement.style.overflow =
                '';

            document.body.style.overflow =
                '';
        }

        function scrollReportsBreakdown(direction) {
            const scroller =
                document.getElementById('reportsRecordsScroller');

            if (!scroller) {
                return;
            }

            scroller.scrollBy({
                top:
                    direction === 'up'
                        ? -280
                        : 280,
                behavior: 'smooth',
            });
        }

        document.addEventListener(
            'DOMContentLoaded',
            function () {
                const scroller =
                    document.getElementById('reportRecordPreviewScroller');

                const track =
                    document.getElementById('reportPreviewRailTrack');

                const thumb =
                    document.getElementById('reportPreviewRailThumb');

                const up =
                    document.getElementById('reportPreviewRailUp');

                const down =
                    document.getElementById('reportPreviewRailDown');

                scroller?.addEventListener(
                    'scroll',
                    updateReportPreviewRail,
                    { passive: true }
                );

                up?.addEventListener(
                    'click',
                    function () {
                        scrollReportPreviewBy(-220);
                    }
                );

                down?.addEventListener(
                    'click',
                    function () {
                        scrollReportPreviewBy(220);
                    }
                );

                track?.addEventListener(
                    'click',
                    function (event) {
                        if (
                            !scroller
                            || !thumb
                            || event.target === thumb
                        ) {
                            return;
                        }

                        const trackRect =
                            track.getBoundingClientRect();

                        const targetRatio =
                            Math.max(
                                0,
                                Math.min(
                                    1,
                                    (
                                        event.clientY
                                        - trackRect.top
                                    )
                                    / trackRect.height
                                )
                            );

                        scroller.scrollTo({
                            top:
                                (
                                    scroller.scrollHeight
                                    - scroller.clientHeight
                                )
                                * targetRatio,
                            behavior: 'smooth',
                        });
                    }
                );

                thumb?.addEventListener(
                    'pointerdown',
                    function (event) {
                        if (!track || !scroller) {
                            return;
                        }

                        reportPreviewDragging =
                            true;

                        reportPreviewDragStartY =
                            event.clientY;

                        reportPreviewDragStartTop =
                            parseFloat(
                                thumb.style.top
                                || '0'
                            );

                        thumb.setPointerCapture(
                            event.pointerId
                        );

                        event.preventDefault();
                    }
                );

                thumb?.addEventListener(
                    'pointermove',
                    function (event) {
                        if (
                            !reportPreviewDragging
                            || !track
                            || !scroller
                        ) {
                            return;
                        }

                        const trackHeight =
                            track.clientHeight;

                        const thumbHeight =
                            thumb.offsetHeight;

                        const maxThumbTop =
                            Math.max(
                                0,
                                trackHeight - thumbHeight
                            );

                        const nextTop =
                            Math.max(
                                0,
                                Math.min(
                                    maxThumbTop,
                                    reportPreviewDragStartTop
                                    + (
                                        event.clientY
                                        - reportPreviewDragStartY
                                    )
                                )
                            );

                        const ratio =
                            maxThumbTop > 0
                                ? nextTop
                                    / maxThumbTop
                                : 0;

                        scroller.scrollTop =
                            (
                                scroller.scrollHeight
                                - scroller.clientHeight
                            )
                            * ratio;
                    }
                );

                function stopDragging(event) {
                    if (!reportPreviewDragging) {
                        return;
                    }

                    reportPreviewDragging =
                        false;

                    if (
                        thumb
                        && event.pointerId !== undefined
                        && thumb.hasPointerCapture(
                            event.pointerId
                        )
                    ) {
                        thumb.releasePointerCapture(
                            event.pointerId
                        );
                    }
                }

                thumb?.addEventListener(
                    'pointerup',
                    stopDragging
                );

                thumb?.addEventListener(
                    'pointercancel',
                    stopDragging
                );

                window.addEventListener(
                    'resize',
                    updateReportPreviewRail
                );
            }
        );

        document.addEventListener(
            'keydown',
            function (event) {
                if (event.key === 'Escape') {
                    closeReportRecordPreview();
                }
            }
        );
    </script>

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
                        Download PDF
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

{{-- Resident Feedback Report Modal --}}
<div id="specificComplaintReportModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-5 flex items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-slate-900">
                    Resident Feedback Report
                </h2>

                <p class="mt-1 text-sm leading-6 text-slate-500">
                    Generate a separate PDF report for one selected resident feedback only.
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
                            Select Feedback
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
                        No feedback selected
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
                    Select one feedback record first.
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
                    <button type="button"
                            onclick="closeSpecificComplaintReportModal()"
                            class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Cancel
                    </button>

                    <button type="submit"
                            class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Download PDF
                    </button>
                </div>
            </form>
        @else
            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                <h3 class="text-sm font-bold text-slate-900">
                    No feedback records available
                </h3>
            </div>
        @endif
    </div>
</div>


{{-- SOS / Distress Signal Report Modal --}}
<div id="specificSosReportModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-5 flex items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-slate-900">
                    SOS / Distress Signal Report
                </h2>

                <p class="mt-1 text-sm leading-6 text-slate-500">
                    Generate a structured A4 PDF for one selected SOS / Distress Signal record.
                </p>
            </div>

            <button type="button"
                    onclick="closeSpecificSosReportModal()"
                    class="text-2xl leading-none text-slate-500 hover:text-slate-900">
                &times;
            </button>
        </div>

        @if (($sosReportOptions ?? collect())->count())
            <form id="specificSosReportForm"
                  data-report-url-template="{{ url('/admin/reports/sos/__SOS_ID__/pdf') }}"
                  class="space-y-5">
                <input type="hidden" id="specific_sos_id">

                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <label class="block text-sm font-semibold text-slate-700">
                            Select SOS / Distress Signal
                        </label>

                        <div class="flex items-center gap-2">
                            <button type="button"
                                    onclick="scrollSpecificReportList('specificSosReportList', 'up')"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 bg-white text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50">
                                ↑
                            </button>

                            <button type="button"
                                    onclick="scrollSpecificReportList('specificSosReportList', 'down')"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 bg-white text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50">
                                ↓
                            </button>
                        </div>
                    </div>

                    <div id="specificSosSelectedLabel"
                         class="mb-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-600">
                        No SOS / Distress Signal selected
                    </div>

                    <div id="specificSosReportList"
                         data-specific-report-list
                         class="max-h-64 overflow-y-auto rounded-xl border border-slate-300 bg-white p-2">
                        @foreach ($sosReportOptions as $sosOption)
                            <button type="button"
                                    data-specific-report-option
                                    data-value="{{ $sosOption['id'] }}"
                                    onclick="selectSpecificReportOption(this, 'specific_sos_id', 'specificSosSelectedLabel')"
                                    class="mb-2 block w-full rounded-lg px-4 py-3 text-left text-sm font-semibold text-slate-700 hover:bg-blue-50 hover:text-blue-800">
                                {{ $sosOption['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div id="specificSosWarning"
                     class="hidden rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    Select one SOS / Distress Signal first.
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
                    <button type="button"
                            onclick="closeSpecificSosReportModal()"
                            class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Cancel
                    </button>

                    <button type="submit"
                            class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Download PDF
                    </button>
                </div>
            </form>
        @else
            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                <h3 class="text-sm font-bold text-slate-900">
                    No SOS / Distress Signal records available
                </h3>

                <p class="mt-1 text-sm text-slate-500">
                    SOS reports will appear here after a distress signal has been received.
                </p>
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
            row.classList.toggle('hidden', !isHidden);
        });

        button.textContent = isHidden ? '↑' : '↓';

        if (text) {
            text.textContent = isHidden
                ? 'Showing all records'
                : 'Showing 5 records only';
        }
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

    function openSpecificSosReportModal() {
        openSpecificReportModal('specificSosReportModal');
    }

    function closeSpecificSosReportModal() {
        closeSpecificReportModal('specificSosReportModal');
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
                option.classList.remove(
                    'bg-blue-100',
                    'text-blue-900',
                    'ring-2',
                    'ring-blue-200'
                );
            });
        }

        button.classList.add(
            'bg-blue-100',
            'text-blue-900',
            'ring-2',
            'ring-blue-200'
        );

        input.value = button.dataset.value || '';
        label.textContent = button.textContent.trim();

        [
            'specificIncidentWarning',
'specificComplaintWarning',
            'specificSosWarning',
        ].forEach(function (warningId) {
            const warning = document.getElementById(warningId);

            if (warning) {
                warning.classList.add('hidden');
            }
        });
    }

    function bindSpecificReportForm(
        formId,
        inputId,
        warningId,
        placeholder
    ) {
        const form = document.getElementById(formId);
        const input = document.getElementById(inputId);
        const warning = document.getElementById(warningId);

        if (!form || !input) {
            return;
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            if (!input.value) {
                if (warning) {
                    warning.classList.remove('hidden');
                }

                return;
            }

            const template = form.dataset.reportUrlTemplate;

            if (!template) {
                return;
            }

            window.location.href = template.replace(
                placeholder,
                encodeURIComponent(input.value)
            );
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindSpecificReportForm(
            'specificIncidentReportForm',
            'specific_incident_id',
            'specificIncidentWarning',
            '__INCIDENT_ID__'
        );
bindSpecificReportForm(
            'specificComplaintReportForm',
            'specific_complaint_id',
            'specificComplaintWarning',
            '__COMPLAINT_ID__'
        );

        bindSpecificReportForm(
            'specificSosReportForm',
            'specific_sos_id',
            'specificSosWarning',
            '__SOS_ID__'
        );
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



<script>
    function setReportsCustomDateDropdown(open) {
        const dropdown = document.getElementById('reportCustomDateDropdown');

        if (!dropdown) {
            return;
        }

        dropdown.style.display = open ? 'block' : 'none';
    }

    function handleReportsTimelineChange(select) {
        const form = document.getElementById('reportTimelineForm');
        const from = document.getElementById('reportDateFrom');
        const to = document.getElementById('reportDateTo');

        if (select.value === 'custom') {
            setReportsCustomDateDropdown(true);

            window.setTimeout(() => {
                if (from) {
                    from.focus();
                }
            }, 0);

            return;
        }

        setReportsCustomDateDropdown(false);

        if (from) {
            from.value = '';
        }

        if (to) {
            to.value = '';
        }

        if (form) {
            form.requestSubmit();
        }
    }

    document.addEventListener('click', function (event) {
        const form = document.getElementById('reportTimelineForm');
        const select = document.getElementById('reportTimelineSelect');

        if (!form || !select) {
            return;
        }

        if (
            select.value === 'custom'
            && !form.contains(event.target)
        ) {
            setReportsCustomDateDropdown(false);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            setReportsCustomDateDropdown(false);
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        const select = document.getElementById('reportTimelineSelect');

        /*
         * Do not automatically open the dropdown after a page load.
         * It behaves like the notification bell: the user explicitly opens it.
         */
        setReportsCustomDateDropdown(false);

        if (
            select
            && select.value !== 'custom'
        ) {
            const from = document.getElementById('reportDateFrom');
            const to = document.getElementById('reportDateTo');

            if (from) {
                from.value = '';
            }

            if (to) {
                to.value = '';
            }
        }
    });
</script>
@endsection