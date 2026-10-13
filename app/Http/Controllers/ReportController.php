<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\CaseRecord;
use App\Models\Employee;
use App\Models\Incident;
use App\Models\MobileEmergencyAlert;
use App\Models\ResidentComplaint;
use App\Models\TanodTask;
use App\Support\SafeDatabaseIdentifier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ReportController extends Controller
{
    private const COUNTABLE_REPORT_TABLES = [
        'case_records',
        'announcements',
    ];

    private const INCIDENT_EVIDENCE_TABLES = [
        'evidence',
        'incident_evidence',
        'incident_evidences',
        'incident_attachments',
    ];

    private const COMPLAINT_PROOF_TABLES = [
        'resident_complaint_proofs',
        'resident_complaint_evidence',
        'resident_complaint_evidences',
        'complaint_proofs',
        'complaint_evidence',
        'complaint_evidences',
    ];

    private const COMPLAINT_FOREIGN_KEYS = [
        'resident_complaint_id',
        'complaint_id',
    ];


    public function index(Request $request): View
    {
        Gate::authorize('viewReports');

        $data =
            $this->websiteReportData(
                $request
            );

        $data[
            'metricSelectionUrls'
        ] =
            $this->metricSelectionUrls(
                $data
            );

        return view(
            'reports.index',
            $data
        );
    }







    /**
     * Mobile API bridge.
     *
     * The website reportData() implementation remains the single source of
     * truth for calculations. This wrapper only exposes that existing data to
     * the authenticated /api/v1 Reports controller.
     */
    public function apiReportData(Request $request): array
    {
        Gate::authorize('viewReports');

        return $this->reportData($request);
    }


    private function websiteReportData(
        Request $request
    ): array {
        $data =
            $this->reportData(
                $request
            );

        unset(
            $data['casesFiled'],
            $data['caseReportOptions']
        );

        return $data;
    }



    public function downloadSelection(
        Request $request
    ): View {
        Gate::authorize('viewReports');

        $data =
            $this->websiteReportData(
                $request
            );

        $data['records'] =
            $this->recordsWithSelectionKeys(
                $data['records']
                    ?? []
            );

        $data[
            'selectedMetricCount'
        ] = count(
            $data['records']
        );

        $data[
            'backUrl'
        ] =
            route(
                'admin.reports.index',
                $this->reportNavigationParameters(
                    $data
                )
            )
            . '#records-breakdown';

        return view(
            'reports.download-selection',
            $data
        );
    }



    public function downloadSelectedPdf(
        Request $request
    ) {
        Gate::authorize('viewReports');

        $validated =
            $request->validate([
                'period' => [
                    'required',
                    'in:today,week,month,year,custom',
                ],
                'metric' => [
                    'required',
                    'in:total,active,critical,resolved,distress,tanod',
                ],
                'selection_mode' => [
                    'required',
                    'in:selected,all',
                ],
                'date_from' => [
                    'nullable',
                    'required_if:period,custom',
                    'date_format:Y-m-d',
                ],
                'date_to' => [
                    'nullable',
                    'required_if:period,custom',
                    'date_format:Y-m-d',
                ],
                'records' => [
                    'nullable',
                    'array',
                ],
                'records.*' => [
                    'string',
                    'max:160',
                ],
            ]);

        $reportQuery = [
            'period' =>
                $validated[
                    'period'
                ],
            'metric' =>
                $validated[
                    'metric'
                ],
        ];

        if (
            $validated[
                'period'
            ] === 'custom'
        ) {
            $reportQuery[
                'date_from'
            ] =
                $validated[
                    'date_from'
                ];

            $reportQuery[
                'date_to'
            ] =
                $validated[
                    'date_to'
                ];
        }

        /*
         * The Reports engine uses query parameters for all normal page
         * requests. Bridge the validated POST controls into that same source
         * so PDF results are identical to the live Reports screen.
         */
        $request
            ->query
            ->replace(
                $reportQuery
            );

        $data =
            $this->websiteReportData(
                $request
            );

        $available =
            collect(
                $this->recordsWithSelectionKeys(
                    $data[
                        'records'
                    ]
                    ?? []
                )
            )
                ->keyBy(
                    'record_key'
                );

        if (
            $available->isEmpty()
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'records' =>
                    'There are no records to download for this metric and timeline.',
            ]);
        }

        if (
            $validated[
                'selection_mode'
            ] === 'all'
        ) {
            $records =
                $available
                    ->values()
                    ->all();

            $selectionLabel =
                'All Records';
        } else {
            $selectedKeys =
                array_values(
                    array_unique(
                        $validated[
                            'records'
                        ]
                        ?? []
                    )
                );

            if (
                $selectedKeys === []
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'records' =>
                        'Select at least one record to download.',
                ]);
            }

            $selected =
                $available
                    ->only(
                        $selectedKeys
                    )
                    ->values();

            if (
                $selected->count()
                !== count(
                    $selectedKeys
                )
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'records' =>
                        'One or more selected records do not belong to this metric and timeline.',
                ]);
            }

            $records =
                $selected->all();

            $selectionLabel =
                'Selected Records';
        }

        $data[
            'records'
        ] = $records;

        $data[
            'selectedMetric'
        ] =
            $validated[
                'metric'
            ];

        $data[
            'selectedMetricLabel'
        ] =
            $this->reportMetricLabel(
                $validated[
                    'metric'
                ]
            );

        $data[
            'selectedMetricCount'
        ] =
            count(
                $records
            );

        $data[
            'selectionLabel'
        ] =
            $selectionLabel;

        $fileName =
            'report-'
            . $this->safeFileToken(
                $data[
                    'selectedMetricLabel'
                ],
                $validated[
                    'metric'
                ]
            )
            . '-'
            . (
                $validated[
                    'selection_mode'
                ] === 'all'
                    ? 'all'
                    : 'selected'
            )
            . '-'
            . now()->format(
                'Ymd-His'
            )
            . '.pdf';

        return Pdf::loadView(
            'reports.pdf',
            $data
        )
            ->setPaper(
                'a4',
                'portrait'
            )
            ->download(
                $fileName
            );
    }



    public function downloadPdf(
        Request $request
    ) {
        Gate::authorize('viewReports');

        $data =
            $this->websiteReportData(
                $request
            );

        $metric =
            $this->validReportMetric(
                (string) (
                    $data[
                        'selectedMetric'
                    ]
                    ?? $request->query(
                        'metric',
                        'total'
                    )
                )
            );

        $metricLabel =
            $this->reportMetricLabel(
                $metric
            );

        $fileName =
            'report-'
            . $this->safeFileToken(
                $metricLabel,
                $metric
            )
            . '-'
            . now()->format(
                'Ymd-His'
            )
            . '.pdf';

        return Pdf::loadView(
            'reports.pdf',
            $data
        )
            ->setPaper(
                'a4',
                'portrait'
            )
            ->download(
                $fileName
            );
    }

public function downloadIncidentPdf(
        Request $request,
        Incident $incident
    ) {
        Gate::authorize('viewReports');
        Gate::authorize('view', $incident);

        $data = $this->specificIncidentReportData($incident);

        $data['showCaseRecords'] =
            $request->is('api/*');

        $fileName = 'incident-report-'
            . $this->safeFileToken(
                $data['incidentReport']['code']
                    ?? ('incident-' . $incident->id),
                'incident-' . $incident->id
            )
            . '-'
            . now()->format('Ymd-His')
            . '.pdf';

        return Pdf::loadView('reports.incident-pdf', $data)
            ->setPaper('a4', 'portrait')
            ->download($fileName);
    }


    public function downloadCasePdf(
        Request $request,
        CaseRecord $caseRecord
    ) {
        Gate::authorize('viewReports');
        Gate::authorize('view', $caseRecord);

        $data = $this->specificCaseReportData($caseRecord);

        $code = $data['caseReport']['case_number']
            ?? ('case-' . $caseRecord->id);

        $fileName = 'case-report-'
            . $this->safeFileToken(
                $code,
                'case-' . $caseRecord->id
            )
            . '-'
            . now()->format('Ymd-His')
            . '.pdf';

        return Pdf::loadHTML($this->casePdfHtml($data))
            ->setPaper('a4', 'portrait')
            ->download($fileName);
    }


    public function downloadComplaintPdf(
        Request $request,
        ResidentComplaint $residentComplaint
    ) {
        Gate::authorize('viewReports');
        Gate::authorize('view', $residentComplaint);

        $data = $this->specificComplaintReportData(
            $residentComplaint
        );

        $code = $data['complaintReport']['code']
            ?? ('complaint-' . $residentComplaint->id);

        $fileName = 'resident-feedback-report-'
            . $this->safeFileToken(
                $code,
                'complaint-' . $residentComplaint->id
            )
            . '-'
            . now()->format('Ymd-His')
            . '.pdf';

        return Pdf::loadHTML(
            $this->complaintPdfHtml($data)
        )
            ->setPaper('a4', 'portrait')
            ->download($fileName);
    }



    public function downloadSosPdf(
        Request $request,
        MobileEmergencyAlert $mobileEmergencyAlert
    ) {
        Gate::authorize('viewReports');

        $data = $this->specificSosReportData($mobileEmergencyAlert);

        $code = $data['sosReport']['alert_code']
            ?? ('sos-' . $mobileEmergencyAlert->id);

        $fileName = 'sos-report-'
            . $this->safeFileToken(
                $code,
                'sos-' . $mobileEmergencyAlert->id
            )
            . '-'
            . now()->format('Ymd-His')
            . '.pdf';

        return Pdf::loadView('reports.sos-pdf', $data)
            ->setPaper('a4', 'portrait')
            ->download($fileName);
    }
    private function casePdfHtml(array $data): string
{
    $caseReport = $data['caseReport'] ?? [];

    $generatedBy = e($data['generatedBy'] ?? 'System');

    try {
        $generatedAt = Carbon::parse($data['generatedAt'] ?? now())->format('M d, Y h:i A');
    } catch (\Throwable $e) {
        $generatedAt = now()->format('M d, Y h:i A');
    }

    $value = function (string $key, string $default = '—') use ($caseReport): string {
        $rawValue = $caseReport[$key] ?? null;

        if ($rawValue === null) {
            return e($default);
        }

        $text = trim((string) $rawValue);

        return e($text !== '' ? $text : $default);
    };

    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Case Report</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            font-size: 12px;
            line-height: 1.5;
        }

        .header {
            border-bottom: 3px solid #1e3a8a;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }

        .system-title {
            margin: 0;
            color: #1e3a8a;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .title {
            margin: 4px 0 0;
            color: #0f172a;
            font-size: 22px;
            font-weight: bold;
        }

        .subtitle {
            margin-top: 4px;
            color: #475569;
            font-size: 11px;
        }

        .meta {
            margin-top: 10px;
            font-size: 10px;
            color: #475569;
        }

        .notice {
            margin-bottom: 16px;
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #1e3a8a;
            padding: 9px 10px;
            font-size: 10px;
            font-weight: bold;
        }

        .section {
            margin-top: 16px;
        }

        .section-title {
            background: #1e3a8a;
            color: #ffffff;
            font-weight: bold;
            padding: 8px 10px;
            margin: 0;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
        }

        tr {
            page-break-inside: avoid;
        }

        td,
        th {
            border: 1px solid #cbd5e1;
            padding: 8px;
            vertical-align: top;
        }

        .label {
            width: 30%;
            background: #f8fafc;
            font-weight: bold;
            color: #334155;
        }

        .value {
            color: #0f172a;
        }

        .wide-text {
            min-height: 42px;
            white-space: pre-line;
        }

        .footer {
            margin-top: 28px;
            border-top: 1px solid #cbd5e1;
            padding-top: 10px;
            font-size: 10px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="header">
        <p class="system-title">TabangNow / DaoSystem</p>

        <p class="title">Case Report</p>

        <p class="subtitle">
            Case Management Documentation — Dao, Capiz
        </p>

        <div class="meta">
            Generated by: ' . $generatedBy . '<br>
            Generated at: ' . e($generatedAt) . '
        </div>
    </div>

    <div class="notice">
        This report contains only the selected case management record. Other cases, resident feedback records, or incidents are excluded.
    </div>

    <div class="section">
        <p class="section-title">Case Details</p>

        <table>
            <tr>
                <td class="label">Case Number</td>
                <td class="value">' . $value('case_number') . '</td>
            </tr>

            <tr>
                <td class="label">Title / Subject</td>
                <td class="value">' . $value('title') . '</td>
            </tr>

            <tr>
                <td class="label">Case Type</td>
                <td class="value">' . $value('case_type') . '</td>
            </tr>

            <tr>
                <td class="label">Subject / Party Name</td>
                <td class="value">' . $value('subject_name') . '</td>
            </tr>

            <tr>
                <td class="label">Contact Number</td>
                <td class="value">' . $value('contact') . '</td>
            </tr>

            <tr>
                <td class="label">Address</td>
                <td class="value">' . $value('address') . '</td>
            </tr>

            <tr>
                <td class="label">Linked Incident</td>
                <td class="value">' . $value('linked_incident') . '</td>
            </tr>

            <tr>
                <td class="label">Status</td>
                <td class="value">' . $value('status') . '</td>
            </tr>

            <tr>
                <td class="label">Handled By</td>
                <td class="value">' . $value('handled_by') . '</td>
            </tr>

            <tr>
                <td class="label">Hearing Date</td>
                <td class="value">' . $value('hearing_date') . '</td>
            </tr>

            <tr>
                <td class="label">Created By</td>
                <td class="value">' . $value('created_by') . '</td>
            </tr>

            <tr>
                <td class="label">Created At</td>
                <td class="value">' . $value('created_at') . '</td>
            </tr>

            <tr>
                <td class="label">Last Updated By</td>
                <td class="value">' . $value('updated_by') . '</td>
            </tr>

            <tr>
                <td class="label">Last Updated</td>
                <td class="value">' . $value('updated_at') . '</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <p class="section-title">Resolution / Action Taken</p>

        <table>
            <tr>
                <td class="wide-text">' . nl2br($value('resolution')) . '</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <p class="section-title">Notes / Details</p>

        <table>
            <tr>
                <td class="wide-text">' . nl2br($value('notes')) . '</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        This report is generated from TabangNow / DaoSystem records. It is intended for barangay case management documentation and monitoring.
    </div>
</body>
</html>';
}

    private function reportData(Request $request): array
    {
        [
            $period,
            $startDate,
            $endDate,
            $periodLabel,
            $isCustomPeriod,
        ] = $this->resolveReportRange(
            $request
        );

        $selectedMetric =
            $this->validReportMetric(
                (string) $request->query(
                    'metric',
                    'total'
                )
            );

        $metricRecordSets = [
            'total' =>
                $this->incidentMetricRecords(
                    $startDate,
                    $endDate,
                    'total'
                ),
            'active' =>
                $this->incidentMetricRecords(
                    $startDate,
                    $endDate,
                    'active'
                ),
            'critical' =>
                $this->incidentMetricRecords(
                    $startDate,
                    $endDate,
                    'critical'
                ),
            'resolved' =>
                $this->incidentMetricRecords(
                    $startDate,
                    $endDate,
                    'resolved'
                ),
            'distress' =>
                $this->distressMetricRecords(
                    $startDate,
                    $endDate
                ),
            'tanod' =>
                $this->tanodDutyMetricRecords(
                    $endDate
                ),
        ];

        $tanodDuty =
            app(
                \App\Services\TanodDutyCounter::class
            )->countAt($endDate);

        $currentUser = Auth::user();

        return [
            'period' => $period,
            'periodLabel' => $periodLabel,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dateFrom' =>
                $startDate->toDateString(),
            'dateTo' =>
                $endDate->toDateString(),
            'isCustomPeriod' =>
                $isCustomPeriod,
            'isHistoricalRange' =>
                $endDate->lt(
                    now()->startOfDay()
                ),
            'generatedAt' => now(),
            'generatedBy' =>
                $currentUser
                    ? $currentUser->name
                    : 'System',

            'selectedMetric' =>
                $selectedMetric,
            'selectedMetricLabel' =>
                $this->reportMetricLabel(
                    $selectedMetric
                ),
            'selectedMetricCount' =>
                count(
                    $metricRecordSets[
                        $selectedMetric
                    ]
                ),
            'metricRecordSets' =>
                $metricRecordSets,

            'totalIncidents' =>
                count(
                    $metricRecordSets[
                        'total'
                    ]
                ),
            'activeIncidents' =>
                count(
                    $metricRecordSets[
                        'active'
                    ]
                ),
            'criticalIncidents' =>
                count(
                    $metricRecordSets[
                        'critical'
                    ]
                ),
            'resolvedIncidents' =>
                count(
                    $metricRecordSets[
                        'resolved'
                    ]
                ),
            'distressSignals' =>
                count(
                    $metricRecordSets[
                        'distress'
                    ]
                ),
            'tanodOnDuty' =>
                count(
                    $metricRecordSets[
                        'tanod'
                    ]
                ),
            'tanodDutyBasis' =>
                (string) (
                    $tanodDuty['basis']
                    ?? 'unavailable'
                ),

            'residentComplaints' =>
                $this->residentComplaintCount(
                    $startDate,
                    $endDate
                ),
            'announcements' =>
                $this->countTableByDate(
                    'announcements',
                    $startDate,
                    $endDate
                ),

            'records' =>
                $metricRecordSets[
                    $selectedMetric
                ],
            'statusSummary' =>
                $this->statusSummary(
                    $startDate,
                    $endDate
                ),
            'severitySummary' =>
                $this->severitySummary(
                    $startDate,
                    $endDate
                ),
            'barangaySummary' =>
                $this->barangaySummary(
                    $startDate,
                    $endDate
                ),

            /*
             * Keep backend/API compatibility even though the website Tanod
             * Response Summary is removed.
             */
            'tanodSummary' =>
                $this->tanodResponseSummary(
                    $startDate,
                    $endDate
                ),

            'incidentReportOptions' =>
                $this->incidentReportOptions(),
            'complaintReportOptions' =>
                $this->complaintReportOptions(),
            'sosReportOptions' =>
                $this->sosReportOptions(),
        ];
    }



    private function specificIncidentReportData(Incident $incident): array
    {
        $incident->loadMissing([
            'barangay',
            'category',
            'currentStatus',
            'reporter:id,name',
            'resident.user:id,name',
            'assignedResponder.user:id,name',
        ]);

        $currentUser = Auth::user();

        $incidentDateTime = $incident->incident_datetime
            ?? $incident->created_at
            ?? null;

        return [
            'generatedAt' => now(),
            'generatedBy' => $currentUser ? $currentUser->name : 'System',

            'incident' => $incident,

            'incidentReport' => [
                'id' => $incident->id,
                'code' => $incident->incident_code ?? 'INC-' . str_pad((string) $incident->id, 6, '0', STR_PAD_LEFT),
                'title' => $incident->incident_title ?? $incident->title ?? 'Untitled Incident',
                'category' => $this->incidentCategoryLabel($incident),
                'description' => $incident->incident_description ?? $incident->description ?? '—',
                'persons_involved' => $incident->persons_involved ?? '—',
                'barangay' => $incident->barangay?->barangay_name
                    ?? $incident->barangay?->name
                    ?? 'Unknown barangay',
                'status' => $incident->currentStatus?->status_name
                    ?? $incident->currentStatus?->name
                    ?? 'Pending',
                'priority' => ucfirst((string) ($incident->priority ?? $incident->severity ?? 'Low')),
                'reporter' => $incident->reporter?->name
                    ?? $incident->resident?->full_name
                    ?? $incident->resident?->user?->name
                    ?? 'Not identified',
                'assigned_responder' => $incident->assignedResponder?->user?->name
                    ?? 'Not assigned',
                'reported_at' => $this->formatDateTime(
                    $incident->reported_at
                    ?? $incident->created_at
                    ?? null
                ),
                'location_name' => $incident->map_location_name
                    ?: ($incident->barangay?->barangay_name
                        ?? $incident->barangay?->name
                        ?? 'Not specified'),
                'latitude' => $incident->latitude !== null
                    ? number_format((float) $incident->latitude, 7, '.', '')
                    : 'Unavailable',
                'longitude' => $incident->longitude !== null
                    ? number_format((float) $incident->longitude, 7, '.', '')
                    : 'Unavailable',
                'date_time' => $this->formatDateTime($incidentDateTime),
                'created_at' => $this->formatDateTime($incident->created_at),
                'updated_at' => $this->formatDateTime($incident->updated_at),
            ],

            'relatedCases' => $this->specificIncidentCaseRecords($incident),
            'evidenceRecords' => $this->specificIncidentEvidenceRecords($incident),
            'tanodTaskRecords' => $this->specificIncidentTanodTaskRecords($incident),
        ];
    }

    private function specificCaseReportData(CaseRecord $caseRecord): array
{
    $caseRecord->loadMissing([
        'incident',
        'creator:id,name',
        'updater:id,name',
    ]);

    $currentUser = Auth::user();

    $linkedIncident = $caseRecord->incident
        ? $caseRecord->incident->display_code
            . ' - '
            . $caseRecord->incident->display_title
        : ($caseRecord->incident_title ?: 'No linked incident');

    $caseReport = [
        'id' => $caseRecord->id,

        'case_number' => $this->firstTextValue($caseRecord, [
            'case_number',
            'reference_number',
            'ticket_number',
        ], 'CASE-' . str_pad((string) $caseRecord->id, 6, '0', STR_PAD_LEFT)),

        'title' => $this->firstTextValue($caseRecord, [
            'title',
            'case_title',
            'subject',
            'complaint_title',
            'incident_title',
        ], 'Barangay case record'),

        'case_type' => ucfirst(str_replace('_', ' ', $this->firstTextValue($caseRecord, [
            'case_type',
            'type',
            'category',
        ], 'Case'))),

        'subject_name' => $this->firstTextValue($caseRecord, [
            'subject_name',
            'respondent_name',
            'complainant_name',
            'party_name',
            'name',
        ], 'Not specified'),

        'status' => ucfirst(str_replace('_', ' ', $this->firstTextValue($caseRecord, [
            'status',
            'case_status',
        ], 'Pending'))),

        'handled_by' => $this->firstTextValue($caseRecord, [
            'handled_by',
            'assigned_to',
            'mediator',
            'officer_in_charge',
        ], 'Not specified'),

        'contact' => $this->firstTextValue($caseRecord, [
            'contact',
            'contact_number',
            'phone',
        ], 'Not specified'),

        'address' => $this->firstTextValue($caseRecord, [
            'address',
            'location',
        ], 'Not specified'),

        'linked_incident' => $linkedIncident,
        'created_by' => $caseRecord->creator?->name ?: 'Not recorded',
        'updated_by' => $caseRecord->updater?->name ?: 'Not recorded',

        'hearing_date' => $this->formatDateOnly($caseRecord->hearing_date ?? null),

        'resolution' => $this->firstTextValue($caseRecord, [
            'resolution',
            'case_resolution',
            'settlement',
            'action_taken',
        ], '—'),

        'notes' => $this->firstTextValue($caseRecord, [
            'notes',
            'remarks',
            'description',
            'details',
            'narrative',
        ], '—'),

        'created_at' => $this->formatDateTime($caseRecord->created_at ?? null),
        'updated_at' => $this->formatDateTime($caseRecord->updated_at ?? null),
    ];

    return [
        'generatedAt' => now(),
        'generatedBy' => $currentUser ? $currentUser->name : 'System',

        'caseRecord' => $caseRecord,
        'caseReport' => $caseReport,
    ];
}


    private function specificSosReportData(
        MobileEmergencyAlert $alert
    ): array {
        $alert->loadMissing([
            'user:id,name,role,address,contact_number',
            'acknowledgedBy:id,name',
            'resolvedBy:id,name',
        ]);

        $currentUser = Auth::user();

        $status = trim((string) $alert->status);
        $locationSource = trim((string) $alert->location_source);

        return [
            'generatedAt' => now(),
            'generatedBy' => $currentUser ? $currentUser->name : 'System',
            'alert' => $alert,

            'sosReport' => [
                'id' => (int) $alert->id,
                'alert_code' => $alert->alert_code
                    ?: 'SOS-' . str_pad((string) $alert->id, 6, '0', STR_PAD_LEFT),
                'status' => $status !== ''
                    ? ucwords(str_replace('_', ' ', $status))
                    : 'Active',
                'source' => ucfirst((string) ($alert->source ?: 'mobile')),
                'reporter_name' => $alert->display_name,
                'reporter_role' => $alert->user
                    ? ucfirst((string) $alert->user->role)
                    : 'Unidentified / Public SOS',
                'registered_address' => $alert->user?->address ?: 'Not available',
                'contact_number' => $alert->contact_number ?: 'Not provided',
                'emergency_details' => $alert->emergency_details
                    ?: 'No emergency description available.',
                'location_source' => $locationSource === 'last_known'
                    ? 'Last known device location'
                    : 'Current device GPS',
                'latitude' => $alert->latitude !== null
                    ? number_format((float) $alert->latitude, 7, '.', '')
                    : 'Unavailable',
                'longitude' => $alert->longitude !== null
                    ? number_format((float) $alert->longitude, 7, '.', '')
                    : 'Unavailable',
                'accuracy' => $alert->accuracy_meters !== null
                    ? number_format((float) $alert->accuracy_meters, 1) . ' meters'
                    : 'Not reported',
                'triggered_at' => $this->formatDateTime($alert->triggered_at),
                'acknowledged_at' => $this->formatDateTime($alert->acknowledged_at),
                'acknowledged_by' => $alert->acknowledgedBy?->name ?: 'Not yet acknowledged',
                'resolved_at' => $this->formatDateTime($alert->resolved_at),
                'resolved_by' => $alert->resolvedBy?->name ?: 'Not yet resolved',
                'created_at' => $this->formatDateTime($alert->created_at),
                'updated_at' => $this->formatDateTime($alert->updated_at),
            ],
        ];
    }
    private function specificComplaintReportData(ResidentComplaint $complaint): array
    {
        $currentUser = Auth::user();

        return [
            'generatedAt' => now(),
            'generatedBy' => $currentUser ? $currentUser->name : 'System',

            'complaint' => $complaint,

            'complaintReport' => [
                'id' => $complaint->id,

                'code' => $this->firstTextValue($complaint, [
                    'complaint_code',
                    'reference_number',
                    'ticket_number',
                    'case_number',
                ], 'COMP-' . str_pad((string) $complaint->id, 6, '0', STR_PAD_LEFT)),

                'title' => $this->firstTextValue($complaint, [
                    'subject',
                    'title',
                    'complaint_title',
                    'concern',
                ], 'Resident Feedback'),

                'complainant' => $this->firstTextValue($complaint, [
                    'complainant_name',
                    'complainant_full_name',
                    'full_name',
                    'resident_name',
                    'name',
                ], 'Unknown complainant'),

                'address' => $this->firstTextValue($complaint, [
                    'address',
                    'complainant_address',
                    'complaint_address',
                    'address_of_complaint',
                    'location',
                ], 'Not specified'),

                'contact_number' => $this->firstTextValue($complaint, [
                    'contact_number',
                    'contact',
                    'phone',
                ], 'Not provided'),

                'status' => ucfirst(str_replace('_', ' ', $this->firstTextValue($complaint, [
                    'status',
                    'complaint_status',
                ], 'Pending'))),

                'description' => $this->firstTextValue($complaint, [
                    'complaint_description',
                    'description',
                    'complaint',
                    'complaint_body',
                    'details',
                    'narrative',
                    'message',
                    'body',
                ], '—'),

                'date_time' => $this->formatDateTime(
                    $complaint->submitted_at
                    ?? $complaint->complaint_datetime
                    ?? $complaint->reported_at
                    ?? $complaint->created_at
                    ?? null
                ),

                'created_at' => $this->formatDateTime($complaint->created_at ?? null),
                'updated_at' => $this->formatDateTime($complaint->updated_at ?? null),
            ],

            'proofRecords' => $this->specificComplaintProofRecords($complaint),
        ];
    }

    private function complaintPdfHtml(array $data): string
    {
        $complaintReport = $data['complaintReport'] ?? [];
        $proofRecords = collect($data['proofRecords'] ?? []);

        $generatedBy = e($data['generatedBy'] ?? 'System');

        try {
            $generatedAt = Carbon::parse($data['generatedAt'] ?? now())->format('M d, Y h:i A');
        } catch (\Throwable $e) {
            $generatedAt = now()->format('M d, Y h:i A');
        }

        $value = function (string $key, string $default = '—') use ($complaintReport): string {
            $rawValue = $complaintReport[$key] ?? null;

            if ($rawValue === null) {
                return e($default);
            }

            $text = trim((string) $rawValue);

            return e($text !== '' ? $text : $default);
        };

        $proofSection = '<table><tr><td class="muted">No proof or attachment records found for this complaint.</td></tr></table>';

        if ($proofRecords->count()) {
            $proofRows = '';

            foreach ($proofRecords as $proof) {
                $proofRows .= '
                    <tr>
                        <td>' . e($proof['file_name'] ?? 'Evidence file') . '</td>
                        <td>' . e($proof['file_type'] ?? '—') . '</td>
                        <td>' . e($proof['file_size'] ?? '—') . '</td>
                        <td>' . e($proof['uploaded_at'] ?? '—') . '</td>
                    </tr>';
            }

            $proofSection = '
                <table>
                    <thead>
                        <tr>
                            <th>File Name</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>Uploaded At</th>
                        </tr>
                    </thead>
                    <tbody>' . $proofRows . '</tbody>
                </table>';
        }

        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Resident Feedback Report</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            font-size: 12px;
            line-height: 1.5;
        }

        .header {
            border-bottom: 3px solid #1e3a8a;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }

        .system-title {
            margin: 0;
            color: #1e3a8a;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .title {
            margin: 4px 0 0;
            color: #0f172a;
            font-size: 22px;
            font-weight: bold;
        }

        .subtitle {
            margin-top: 4px;
            color: #475569;
            font-size: 11px;
        }

        .meta {
            margin-top: 10px;
            font-size: 10px;
            color: #475569;
        }

        .notice {
            margin-bottom: 16px;
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #1e3a8a;
            padding: 9px 10px;
            font-size: 10px;
            font-weight: bold;
        }

        .section {
            margin-top: 16px;
        }

        .section-title {
            background: #1e3a8a;
            color: #ffffff;
            font-weight: bold;
            padding: 8px 10px;
            margin: 0;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
        }

        tr {
            page-break-inside: avoid;
        }

        td,
        th {
            border: 1px solid #cbd5e1;
            padding: 8px;
            vertical-align: top;
        }

        th {
            background: #f8fafc;
            color: #334155;
            text-align: left;
            font-weight: bold;
            font-size: 10px;
        }

        .label {
            width: 30%;
            background: #f8fafc;
            font-weight: bold;
            color: #334155;
        }

        .value {
            color: #0f172a;
        }

        .wide-text {
            min-height: 42px;
            white-space: pre-line;
        }

        .muted {
            color: #64748b;
        }

        .footer {
            margin-top: 28px;
            border-top: 1px solid #cbd5e1;
            padding-top: 10px;
            font-size: 10px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="header">
        <p class="system-title">TabangNow System</p>

        <p class="title">Resident Feedback Report</p>

        <p class="subtitle">
            Resident Feedback Documentation — Dao, Capiz
        </p>

        <div class="meta">
            Generated by: ' . $generatedBy . '<br>
            Generated at: ' . e($generatedAt) . '
        </div>
    </div>

    <div class="notice">
        This report contains only the selected resident feedback and its directly related records. Other complaints or incidents are excluded.
    </div>

    <div class="section">
        <p class="section-title">Feedback Details</p>

        <table>
            <tr>
                <td class="label">Feedback Reference</td>
                <td class="value">' . $value('code') . '</td>
            </tr>

            <tr>
                <td class="label">Title / Subject</td>
                <td class="value">' . $value('title') . '</td>
            </tr>

            <tr>
                <td class="label">Resident</td>
                <td class="value">' . $value('complainant') . '</td>
            </tr>

            <tr>
                <td class="label">Contact Number</td>
                <td class="value">' . $value('contact_number') . '</td>
            </tr>

            <tr>
                <td class="label">Address / Location</td>
                <td class="value">' . $value('address') . '</td>
            </tr>

            <tr>
                <td class="label">Status</td>
                <td class="value">' . $value('status') . '</td>
            </tr>

            <tr>
                <td class="label">Feedback Date & Time</td>
                <td class="value">' . $value('date_time') . '</td>
            </tr>

            <tr>
                <td class="label">Created At</td>
                <td class="value">' . $value('created_at') . '</td>
            </tr>

            <tr>
                <td class="label">Last Updated</td>
                <td class="value">' . $value('updated_at') . '</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <p class="section-title">Feedback Narrative / Details</p>

        <table>
            <tr>
                <td class="wide-text">' . nl2br($value('description')) . '</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <p class="section-title">Supporting / Action Evidence</p>
        ' . $proofSection . '
    </div>

    <div class="footer">
        This report is generated from TabangNow system records. It is intended for barangay resident feedback documentation and service monitoring.
    </div>
</body>
</html>';
    }

    private function incidentReportOptions()
    {
        return Incident::query()
            ->with(['barangay'])
            ->latest()
            ->limit(100)
            ->get()
            ->map(function ($incident) {
                $dateValue = $incident->incident_datetime
                    ?? $incident->created_at
                    ?? null;

                $code = $incident->incident_code
                    ?? 'INC-' . str_pad((string) $incident->id, 6, '0', STR_PAD_LEFT);

                $title = $incident->incident_title
                    ?? $incident->title
                    ?? 'Untitled Incident';

                $barangay = $incident->barangay?->barangay_name
                    ?? $incident->barangay?->name
                    ?? 'Unknown barangay';

                return [
                    'id' => $incident->id,
                    'label' => $code
                        . ' - '
                        . $title
                        . ' - '
                        . $barangay
                        . ' - '
                        . $this->formatDateTime($dateValue),
                ];
            });
    }

    private function caseReportOptions()
{
    if (
        ! class_exists(CaseRecord::class)
        || ! Schema::hasTable('case_records')
    ) {
        return collect();
    }

    $query = CaseRecord::query();

    if (Schema::hasColumn('case_records', 'created_at')) {
        $query->orderByDesc('created_at');
    } else {
        $query->orderByDesc('id');
    }

    return $query
        ->limit(100)
        ->get()
        ->map(function ($caseRecord) {
            $caseNumber = $this->firstTextValue($caseRecord, [
                'case_number',
                'reference_number',
                'ticket_number',
            ], 'CASE-' . str_pad((string) $caseRecord->id, 6, '0', STR_PAD_LEFT));

            $title = $this->firstTextValue($caseRecord, [
                'title',
                'case_title',
                'subject',
                'complaint_title',
            ], 'Barangay case record');

            $subjectName = $this->firstTextValue($caseRecord, [
                'subject_name',
                'respondent_name',
                'complainant_name',
                'party_name',
                'name',
            ], 'Not specified');

            return [
                'id' => $caseRecord->id,
                'label' => $caseNumber
                    . ' - '
                    . $title
                    . ' - '
                    . $subjectName
                    . ' - '
                    . $this->formatDateTime($caseRecord->created_at ?? null),
            ];
        });
}

    private function complaintReportOptions()
    {
        if (
            ! class_exists(ResidentComplaint::class)
            || ! Schema::hasTable('resident_complaints')
        ) {
            return collect();
        }

        $query = ResidentComplaint::query();

        if (Schema::hasColumn('resident_complaints', 'created_at')) {
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('id');
        }

        return $query
            ->limit(100)
            ->get()
            ->map(function ($complaint) {
                $code = $this->firstTextValue($complaint, [
                    'complaint_code',
                    'reference_number',
                    'ticket_number',
                    'case_number',
                ], 'COMP-' . str_pad((string) $complaint->id, 6, '0', STR_PAD_LEFT));

                $complainant = $this->firstTextValue($complaint, [
                    'complainant_name',
                    'complainant_full_name',
                    'full_name',
                    'resident_name',
                    'name',
                ], 'Unknown complainant');

                $title = $this->firstTextValue($complaint, [
                    'subject',
                    'title',
                    'complaint_title',
                    'concern',
                ], 'Resident Feedback');

                return [
                    'id' => $complaint->id,
                    'label' => $code
                        . ' - '
                        . $title
                        . ' - '
                        . $complainant
                        . ' - '
                        . $this->formatDateTime($complaint->created_at ?? null),
                ];
            });
    }


    private function sosReportOptions()
    {
        if (
            ! class_exists(MobileEmergencyAlert::class)
            || ! Schema::hasTable('mobile_emergency_alerts')
        ) {
            return collect();
        }

        return MobileEmergencyAlert::query()
            ->with('user:id,name,role')
            ->latest('triggered_at')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(function (MobileEmergencyAlert $alert) {
                $code = $alert->alert_code
                    ?: 'SOS-' . str_pad((string) $alert->id, 6, '0', STR_PAD_LEFT);

                $reporter = $alert->display_name;
                $status = ucwords(str_replace(
                    '_',
                    ' ',
                    trim((string) ($alert->status ?: 'active'))
                ));

                return [
                    'id' => (int) $alert->id,
                    'label' => $code
                        . ' - '
                        . $reporter
                        . ' - '
                        . $status
                        . ' - '
                        . $this->formatDateTime(
                            $alert->triggered_at
                            ?? $alert->created_at
                        ),
                ];
            });
    }
    private function incidentCategoryLabel(Incident $incident): string
    {
        $relatedCategory = $incident->category;

        if ($relatedCategory) {
            foreach ([
                'category_name',
                'name',
                'title',
                'label',
                'type',
                'incident_type',
                'description',
            ] as $column) {
                $value = $relatedCategory->{$column} ?? null;

                if ($this->hasTextValue($value)) {
                    return $this->displayText($value);
                }
            }
        }

        foreach ([
            'category_name',
            'incident_category',
            'category',
            'type',
            'incident_type',
            'incident_type_name',
            'nature',
            'nature_of_incident',
        ] as $column) {
            $value = $incident->{$column} ?? null;

            if ($this->hasTextValue($value)) {
                return $this->displayText($value);
            }
        }

        return 'Not specified';
    }

    private function specificIncidentCaseRecords(Incident $incident)
    {
        if (
            ! class_exists(CaseRecord::class)
            || ! Schema::hasTable('case_records')
            || ! Schema::hasColumn('case_records', 'incident_id')
        ) {
            return collect();
        }

        return CaseRecord::query()
            ->where('incident_id', $incident->id)
            ->latest()
            ->get()
            ->map(function ($case) {
                return [
                    'case_number' => $case->case_number ?? 'Case #' . $case->id,
                    'subject_name' => $case->subject_name ?? $case->title ?? '—',
                    'case_type' => ucfirst(str_replace('_', ' ', (string) ($case->case_type ?? 'case'))),
                    'status' => ucfirst(str_replace('_', ' ', (string) ($case->status ?? '—'))),
                    'handled_by' => $case->handled_by ?? '—',
                    'hearing_date' => $this->formatDateOnly($case->hearing_date ?? null),
                    'resolution' => $case->resolution ?? '—',
                    'notes' => $case->notes ?? '—',
                    'created_at' => $this->formatDateTime($case->created_at ?? null),
                ];
            });
    }

    private function specificIncidentEvidenceRecords(Incident $incident)
    {
        $candidateTables = self::INCIDENT_EVIDENCE_TABLES;

        foreach ($candidateTables as $table) {
            $table = SafeDatabaseIdentifier::approved(
                $table,
                self::INCIDENT_EVIDENCE_TABLES
            );

            if (
                ! Schema::hasTable($table)
                || ! Schema::hasColumn($table, 'incident_id')
            ) {
                continue;
            }

            $orderColumn = Schema::hasColumn(
                $table,
                'created_at'
            )
                ? 'created_at'
                : 'id';

            $orderColumn = SafeDatabaseIdentifier::approved(
                $orderColumn,
                [
                    'created_at',
                    'id',
                ]
            );

            return DB::table($table)
                ->where('incident_id', $incident->id)
                ->orderByDesc($orderColumn)
                ->get()
                ->map(function ($record) use ($table) {
                    return [
                        'table' => $table,
                        'file_name' => $record->file_name
                            ?? $record->name
                            ?? basename((string) ($record->file_path ?? $record->path ?? 'Evidence file')),
                        'file_type' => $record->file_type
                            ?? $record->mime_type
                            ?? '—',
                        'file_size' => isset($record->file_size)
                            ? $this->formatFileSize((int) $record->file_size)
                            : '—',
                        'uploaded_at' => isset($record->created_at)
                            ? $this->formatDateTime($record->created_at)
                            : '—',
                    ];
                });
        }

        return collect();
    }

    private function specificComplaintProofRecords(ResidentComplaint $complaint)
    {
        $candidateTables = self::COMPLAINT_PROOF_TABLES;
        $candidateForeignKeys = self::COMPLAINT_FOREIGN_KEYS;

        foreach ($candidateTables as $table) {
            $table = SafeDatabaseIdentifier::approved(
                $table,
                self::COMPLAINT_PROOF_TABLES
            );

            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($candidateForeignKeys as $foreignKey) {
                $foreignKey = SafeDatabaseIdentifier::approved(
                    $foreignKey,
                    self::COMPLAINT_FOREIGN_KEYS
                );

                if (! Schema::hasColumn($table, $foreignKey)) {
                    continue;
                }

                $orderColumn = Schema::hasColumn(
                    $table,
                    'created_at'
                )
                    ? 'created_at'
                    : 'id';

                $orderColumn = SafeDatabaseIdentifier::approved(
                    $orderColumn,
                    [
                        'created_at',
                        'id',
                    ]
                );

                return DB::table($table)
                    ->where($foreignKey, $complaint->id)
                    ->orderByDesc($orderColumn)
                    ->get()
                    ->map(function ($record) {
                        return [
                            'file_name' => $record->file_name
                                ?? $record->name
                                ?? basename((string) ($record->file_path ?? $record->path ?? 'Evidence file')),
                            'file_type' => $record->file_type
                                ?? $record->mime_type
                                ?? '—',
                            'file_size' => isset($record->file_size)
                                ? $this->formatFileSize((int) $record->file_size)
                                : '—',
                            'uploaded_at' => isset($record->created_at)
                                ? $this->formatDateTime($record->created_at)
                                : '—',
                        ];
                    });
            }
        }

        return collect();
    }

    private function specificIncidentTanodTaskRecords(Incident $incident)
    {
        if (
            ! Schema::hasTable('tanod_tasks')
            || ! Schema::hasColumn('tanod_tasks', 'incident_id')
        ) {
            return collect();
        }

        return TanodTask::query()
            ->with([
                'responses.user:id,name',
                'responses.employee.user:id,name',
            ])
            ->where('incident_id', $incident->id)
            ->latest()
            ->get()
            ->map(function (TanodTask $task) {
                return [
                    'task_id' => $task->id,
                    'title' => $task->title
                        ?: 'Tanod Task #' . $task->id,
                    'status' => ucfirst(str_replace(
                        '_',
                        ' ',
                        (string) ($task->status ?: '—')
                    )),
                    'created_at' => $this->formatDateTime(
                        $task->created_at
                    ),
                    'responses' => $task->responses
                        ->map(function ($response) {
                            return [
                                'responder_name' => $response->user?->name
                                    ?? $response->employee?->user?->name
                                    ?? 'Tanod responder',
                                'response_status' => ucfirst(str_replace(
                                    '_',
                                    ' ',
                                    (string) ($response->response_status ?: 'pending')
                                )),
                                'response_note' => trim(
                                    (string) ($response->response_note ?? '')
                                ) ?: '—',
                                'responded_at' => $this->formatDateTime(
                                    $response->responded_at
                                    ?? $response->updated_at
                                    ?? null
                                ),
                            ];
                        })
                        ->values(),
                ];
            });
    }
    private function incidentBaseQuery(
        Carbon $startDate,
        Carbon $endDate
    ) {
        return Incident::query()
            ->with([
                'barangay',
                'category',
                'currentStatus',
                'reporter:id,name',
                'resident.user:id,name',
                'assignedResponder.user:id,name',
                'location',
            ])
            ->whereRaw(
                'COALESCE(incident_datetime, reported_at, created_at) BETWEEN ? AND ?',
                [
                    $startDate,
                    $endDate,
                ]
            );
    }




    private function recordsBreakdown(Carbon $startDate, Carbon $endDate): array
    {
        $records = [];

        $incidents = $this->incidentBaseQuery(
            $startDate,
            $endDate
        )
            ->latest()
            ->limit(50)
            ->get();

        foreach ($incidents as $incident) {
            $records[] = [
                'category' => 'Incident',
                'title' => ($incident->incident_code ?? 'INC-' . $incident->id) . ' - ' . ($incident->incident_title ?? $incident->title ?? 'Untitled Incident'),
                'type' => $this->incidentCategoryLabel($incident),
                'status' => $incident->currentStatus?->status_name
                    ?? $incident->currentStatus?->name
                    ?? 'Pending',
                'severity' => ucfirst((string) ($incident->priority ?? $incident->severity ?? 'Low')),
                'barangay' => $incident->barangay?->barangay_name
                    ?? $incident->barangay?->name
                    ?? 'Unknown barangay',
                'datetime' => optional($incident->created_at)->format('M d, Y h:i A') ?? '-',
                'sort_date' => optional($incident->created_at)->timestamp ?? 0,
            ];
        }

        if (class_exists(CaseRecord::class) && Schema::hasTable('case_records')) {
            $cases = CaseRecord::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->latest()
                ->limit(50)
                ->get();

            foreach ($cases as $case) {
                $records[] = [
                    'category' => 'Case',
                    'title' => 'Case No. '
                        . ($case->case_number ?? $case->id)
                        . ' - '
                        . ($case->incident_title
                            ?? $case->subject_name
                            ?? 'Barangay case record'),
                    'type' => ucfirst(str_replace('_', ' ', (string) ($case->case_type ?? 'case'))),
                    'status' => ucfirst(str_replace('_', ' ', (string) ($case->status ?? '—'))),
                    'severity' => '—',
                    'barangay' => '—',
                    'datetime' => optional($case->created_at)->format('M d, Y h:i A') ?? '-',
                    'sort_date' => optional($case->created_at)->timestamp ?? 0,
                ];
            }
        }

        if (class_exists(Announcement::class) && Schema::hasTable('announcements')) {
            $announcementRecords = Announcement::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->latest()
                ->limit(50)
                ->get();

            foreach ($announcementRecords as $announcement) {
                $records[] = [
                    'category' => 'Announcement',
                    'title' => $announcement->title ?? 'Announcement',
                    'type' => ucfirst(str_replace('_', ' ', (string) ($announcement->category ?? 'announcement'))),
                    'status' => '—',
                    'severity' => '—',
                    'barangay' => '—',
                    'datetime' => optional($announcement->created_at)->format('M d, Y h:i A') ?? '-',
                    'sort_date' => optional($announcement->created_at)->timestamp ?? 0,
                ];
            }
        }

        if (
            class_exists(ResidentComplaint::class)
            && Schema::hasTable('resident_complaints')
        ) {
            $complaintDateColumn = Schema::hasColumn(
                'resident_complaints',
                'submitted_at'
            )
                ? 'submitted_at'
                : 'created_at';

            $complaints = ResidentComplaint::query()
                ->whereBetween(
                    $complaintDateColumn,
                    [$startDate, $endDate]
                )
                ->latest($complaintDateColumn)
                ->limit(50)
                ->get();

            foreach ($complaints as $complaint) {
                $date = $complaint->{$complaintDateColumn};

                $records[] = [
                    'category' => 'Resident Feedback',
                    'title' => 'Complaint #'
                        . $complaint->id
                        . ' - '
                        . ($complaint->complainant_name
                            ?: 'Unknown complainant'),
                    'type' => 'Resident Feedback',
                    'status' => $complaint->statusLabel(),
                    'severity' => '—',
                    'barangay' => $complaint->complaint_address
                        ?: 'Not specified',
                    'datetime' => $this->formatDateTime($date),
                    'sort_date' => optional($date)->timestamp ?? 0,
                ];
            }
        }

        if (
            class_exists(MobileEmergencyAlert::class)
            && Schema::hasTable('mobile_emergency_alerts')
        ) {
            $sosDateColumn = Schema::hasColumn(
                'mobile_emergency_alerts',
                'triggered_at'
            )
                ? 'triggered_at'
                : 'created_at';

            $alerts = MobileEmergencyAlert::query()
                ->with('user:id,name')
                ->whereBetween(
                    $sosDateColumn,
                    [$startDate, $endDate]
                )
                ->latest($sosDateColumn)
                ->limit(50)
                ->get();

            foreach ($alerts as $alert) {
                $date = $alert->{$sosDateColumn};

                $records[] = [
                    'category' => 'SOS / Distress Signal',
                    'title' => ($alert->alert_code
                            ?: 'SOS-' . $alert->id)
                        . ' - '
                        . $alert->display_name,
                    'type' => 'Emergency',
                    'status' => ucfirst(str_replace(
                        '_',
                        ' ',
                        (string) ($alert->status ?: 'active')
                    )),
                    'severity' => 'Emergency',
                    'barangay' => 'GPS-based location',
                    'datetime' => $this->formatDateTime($date),
                    'sort_date' => optional($date)->timestamp ?? 0,
                ];
            }
        }
        usort($records, function ($a, $b) {
            return $b['sort_date'] <=> $a['sort_date'];
        });

        return array_slice($records, 0, 50);
    }


    private function residentComplaintCount(
        Carbon $startDate,
        Carbon $endDate
    ): int {
        if (! Schema::hasTable('resident_complaints')) {
            return 0;
        }

        $column = Schema::hasColumn(
            'resident_complaints',
            'submitted_at'
        )
            ? 'submitted_at'
            : 'created_at';

        return ResidentComplaint::query()
            ->whereBetween($column, [$startDate, $endDate])
            ->count();
    }

    private function distressSignalCount(
        Carbon $startDate,
        Carbon $endDate
    ): int {
        if (! Schema::hasTable('mobile_emergency_alerts')) {
            return 0;
        }

        $column = Schema::hasColumn(
            'mobile_emergency_alerts',
            'triggered_at'
        )
            ? 'triggered_at'
            : 'created_at';

        return MobileEmergencyAlert::query()
            ->whereBetween($column, [$startDate, $endDate])
            ->count();
    }
    private function statusSummary(Carbon $startDate, Carbon $endDate): array
    {
        return Incident::query()
            ->select('status_id')
            ->selectRaw('COUNT(*) as total')
            ->with('currentStatus')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('status_id')
            ->get()
            ->map(function ($row) {
                return [
                    'label' => $row->currentStatus?->status_name ?? 'Unknown',
                    'total' => $row->total,
                ];
            })
            ->values()
            ->all();
    }

    private function severitySummary(Carbon $startDate, Carbon $endDate): array
    {
        if (! Schema::hasColumn('incidents', 'priority')) {
            return [];
        }

        return Incident::query()
            ->select('priority')
            ->selectRaw('COUNT(*) as total')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('priority')
            ->orderByRaw(
                'CASE priority '
                    . 'WHEN ? THEN 1 '
                    . 'WHEN ? THEN 2 '
                    . 'WHEN ? THEN 3 '
                    . 'WHEN ? THEN 4 '
                    . 'ELSE 5 END',
                [
                    'critical',
                    'high',
                    'moderate',
                    'low',
                ]
            )
            ->get()
            ->map(function ($row) {
                return [
                    'label' => ucfirst((string) $row->priority),
                    'total' => $row->total,
                ];
            })
            ->values()
            ->all();
    }

    private function barangaySummary(Carbon $startDate, Carbon $endDate): array
    {
        return Incident::query()
            ->select('barangay_id')
            ->selectRaw('COUNT(*) as total')
            ->with('barangay')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('barangay_id')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                return [
                    'label' => $row->barangay?->barangay_name ?? $row->barangay?->name ?? 'Unknown barangay',
                    'total' => $row->total,
                ];
            })
            ->values()
            ->all();
    }

    private function tanodResponseSummary(Carbon $startDate, Carbon $endDate)
    {
        if (
            ! Schema::hasTable('employees')
            || ! Schema::hasTable('tanod_tasks')
            || ! Schema::hasTable('tanod_task_responses')
            || ! Schema::hasColumn('tanod_task_responses', 'employee_id')
            || ! Schema::hasColumn('tanod_task_responses', 'tanod_task_id')
            || ! Schema::hasColumn('tanod_task_responses', 'response_status')
        ) {
            return collect();
        }

        return Employee::query()
            ->with('user')
            ->when(Schema::hasColumn('employees', 'employee_type'), function ($query) {
                $query->where('employee_type', 'tanod');
            })
            ->orderBy('id')
            ->get()
            ->map(function ($employee) use ($startDate, $endDate) {
                $responsesQuery = DB::table('tanod_task_responses as responses')
                    ->join('tanod_tasks as tasks', 'tasks.id', '=', 'responses.tanod_task_id')
                    ->where('responses.employee_id', $employee->id);

                if (Schema::hasColumn('tanod_tasks', 'created_at')) {
                    $responsesQuery->whereBetween('tasks.created_at', [$startDate, $endDate]);
                }

                $totalTasks = (clone $responsesQuery)->count();

                $accepted = (clone $responsesQuery)
                    ->whereRaw("LOWER(COALESCE(responses.response_status, 'pending')) = ?", ['accepted'])
                    ->count();

                $declined = (clone $responsesQuery)
                    ->whereRaw("LOWER(COALESCE(responses.response_status, 'pending')) = ?", ['declined'])
                    ->count();

                $pending = (clone $responsesQuery)
                    ->where(function ($query) {
                        $query->whereNull('responses.response_status')
                            ->orWhereRaw("LOWER(responses.response_status) = ?", ['pending']);
                    })
                    ->count();

                $responded = $accepted + $declined;

                return [
                    'name' => $employee->user?->name ?? 'Tanod #' . $employee->id,
                    'total_tasks' => $totalTasks,
                    'accepted' => $accepted,
                    'declined' => $declined,
                    'pending' => $pending,
                    'response_rate' => $totalTasks > 0 ? round(($responded / $totalTasks) * 100) : 0,
                    'last_response' => 'No response yet',
                ];
            })
            ->filter(function ($row) {
                return $row['total_tasks'] > 0;
            })
            ->values();
    }

    private function countTableByDate(
        string $table,
        Carbon $startDate,
        Carbon $endDate
    ): int {
        $table = SafeDatabaseIdentifier::approved(
            $table,
            self::COUNTABLE_REPORT_TABLES
        );

        if (! Schema::hasTable($table)) {
            return 0;
        }

        if (! Schema::hasColumn($table, 'created_at')) {
            return DB::table($table)->count();
        }

        return DB::table($table)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
    }

    private function firstTextValue(object $record, array $columns, string $default = '—'): string
    {
        foreach ($columns as $column) {
            $value = $record->{$column} ?? null;

            if ($this->hasTextValue($value)) {
                return trim((string) $value);
            }
        }

        return $default;
    }

    private function hasTextValue($value): bool
    {
        if ($value === null) {
            return false;
        }

        $text = trim((string) $value);
        $lowerText = strtolower($text);

        return $text !== ''
            && $lowerText !== 'null'
            && $lowerText !== 'undefined'
            && $lowerText !== 'uncategorized';
    }

    private function displayText($value): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return 'Not specified';
        }

        return ucwords(str_replace('_', ' ', $text));
    }

    private function formatDateTime($value): string
    {
        if (! $value) {
            return '—';
        }

        try {
            return Carbon::parse($value)->format('M d, Y h:i A');
        } catch (\Throwable $e) {
            return '—';
        }
    }

    private function formatDateOnly($value): string
    {
        if (! $value) {
            return '—';
        }

        try {
            return Carbon::parse($value)->format('M d, Y');
        } catch (\Throwable $e) {
            return '—';
        }
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $bytes;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }


    private function safeFileToken(
        mixed $value,
        string $fallback
    ): string {
        $token = strtolower(trim((string) $value));

        $token = preg_replace(
            '/[^a-z0-9]+/',
            '-',
            $token
        );

        $token = trim((string) $token, '-');

        if ($token === '') {
            $token = strtolower(trim($fallback));
            $token = preg_replace(
                '/[^a-z0-9]+/',
                '-',
                $token
            );
            $token = trim((string) $token, '-');
        }

        return mb_substr(
            $token !== '' ? $token : 'report',
            0,
            80
        );
    }


    private function reportNavigationParameters(
        array $data
    ): array {
        $parameters = [
            'period' =>
                $data['period']
                ?? 'week',
            'metric' =>
                $this->validReportMetric(
                    (string) (
                        $data[
                            'selectedMetric'
                        ]
                        ?? 'total'
                    )
                ),
        ];

        if (
            $data[
                'isCustomPeriod'
            ]
            ?? false
        ) {
            $parameters[
                'date_from'
            ] =
                $data['dateFrom'];

            $parameters[
                'date_to'
            ] =
                $data['dateTo'];
        }

        return $parameters;
    }


    private function recordsWithSelectionKeys(
        array $records
    ): array {
        return collect(
            $records
        )
            ->values()
            ->map(
                function (
                    array $record,
                    int $index
                ): array {
                    if (
                        ! empty(
                            $record[
                                'record_key'
                            ]
                        )
                    ) {
                        return $record;
                    }

                    $fingerprint =
                        json_encode(
                            $record,
                            JSON_UNESCAPED_UNICODE
                            | JSON_UNESCAPED_SLASHES
                        );

                    $record[
                        'record_key'
                    ] =
                        'legacy:'
                        . hash(
                            'sha256',
                            (string) $fingerprint
                            . '|'
                            . $index
                        );

                    return $record;
                }
            )
            ->all();
    }

    private function validReportMetric(
        ?string $metric
    ): string {
        $metric = strtolower(
            trim(
                (string) $metric
            )
        );

        return in_array(
            $metric,
            [
                'total',
                'active',
                'critical',
                'resolved',
                'distress',
                'tanod',
            ],
            true
        )
            ? $metric
            : 'total';
    }


    private function reportMetricLabel(
        string $metric
    ): string {
        return match (
            $this->validReportMetric(
                $metric
            )
        ) {
            'active' =>
                'Active Incidents',
            'critical' =>
                'Critical Incidents',
            'resolved' =>
                'Resolved Incidents',
            'distress' =>
                'Distress Signals / SOS',
            'tanod' =>
                'Tanod On Duty',
            default =>
                'Total Incidents',
        };
    }


    private function incidentMetricRecords(
        Carbon $startDate,
        Carbon $endDate,
        string $metric
    ): array {
        $metric =
            $this->validReportMetric(
                $metric
            );

        $query =
            $this->incidentBaseQuery(
                $startDate,
                $endDate
            );

        $terminal = [
            'resolved',
            'closed',
            'completed',
        ];

        $inactive = [
            ...$terminal,
            'cancelled',
            'canceled',
            'rejected',
        ];

        if ($metric === 'active') {
            $query->where(
                function ($query) use ($inactive): void {
                    $query
                        ->whereDoesntHave('currentStatus')
                        ->orWhereHas(
                            'currentStatus',
                            function ($statusQuery) use ($inactive): void {
                                $statusQuery->whereNotIn(
                                    DB::raw('LOWER(status_name)'),
                                    $inactive
                                );
                            }
                        );
                }
            );
        }

        if ($metric === 'critical') {
            $query
                ->whereRaw(
                    "LOWER(COALESCE(priority, '')) = ?",
                    ['critical']
                )
                ->where(
                    function ($query) use ($inactive): void {
                        $query
                            ->whereDoesntHave('currentStatus')
                            ->orWhereHas(
                                'currentStatus',
                                function ($statusQuery) use ($inactive): void {
                                    $statusQuery->whereNotIn(
                                        DB::raw('LOWER(status_name)'),
                                        $inactive
                                    );
                                }
                            );
                    }
                );
        }

        if ($metric === 'resolved') {
            $query->whereHas(
                'currentStatus',
                function ($statusQuery) use ($terminal): void {
                    $statusQuery->whereIn(
                        DB::raw('LOWER(status_name)'),
                        $terminal
                    );
                }
            );
        }

        return $query
            ->orderByRaw(
                'COALESCE(incident_datetime, reported_at, created_at) DESC'
            )
            ->orderByDesc('id')
            ->get()
            ->map(
                function (Incident $incident): array {
                    $incidentAt =
                        $incident->incident_datetime
                        ?? $incident->reported_at
                        ?? $incident->created_at;

                    $reportedAt =
                        $incident->reported_at
                        ?? $incident->created_at
                        ?? $incidentAt;

                    $reference =
                        $incident->incident_code
                        ?: 'INC-'
                            . str_pad(
                                (string) $incident->id,
                                6,
                                '0',
                                STR_PAD_LEFT
                            );

                    $title =
                        $incident->display_title
                        ?? $incident->incident_title
                        ?? $incident->title
                        ?? 'Untitled Incident';

                    $barangay =
                        $incident
                            ->barangay
                            ?->barangay_name
                        ?? $incident
                            ->barangay
                            ?->name
                        ?? '—';

                    $mapLocation =
                        trim(
                            (string) (
                                $incident->map_location_name
                                ?? ''
                            )
                        );

                    if (
                        $mapLocation === ''
                        && $incident->location
                    ) {
                        $mapLocation =
                            trim(
                                (string) (
                                    $incident
                                        ->location
                                        ->location_address
                                    ?? $incident
                                        ->location
                                        ->address
                                    ?? ''
                                )
                            );
                    }

                    if ($mapLocation === '') {
                        $mapLocation =
                            $barangay;
                    }

                    $reporter =
                        $incident
                            ->reporter
                            ?->name
                        ?? $incident
                            ->resident
                            ?->user
                            ?->name
                        ?? 'Not identified';

                    $assignedResponder =
                        $incident
                            ->assignedResponder
                            ?->user
                            ?->name
                        ?? 'Not assigned';

                    $coordinates =
                        $incident->latitude !== null
                        && $incident->longitude !== null
                            ? number_format(
                                (float) $incident->latitude,
                                6,
                                '.',
                                ''
                            )
                                . ', '
                                . number_format(
                                    (float) $incident->longitude,
                                    6,
                                    '.',
                                    ''
                                )
                            : 'Unavailable';

                    $status =
                        $incident
                            ->currentStatus
                            ?->status_name
                        ?? 'Reported';

                    $type =
                        $this
                            ->incidentCategoryLabel(
                                $incident
                            );

                    $description =
                        trim(
                            (string) (
                                $incident->incident_description
                                ?? $incident->description
                                ?? ''
                            )
                        );

                    return [
                        'record_key' =>
                            'incident:'
                            . $incident->id,

                        'category' =>
                            'Incident',

                        'title' =>
                            $reference
                            . ' - '
                            . $title,

                        'type' =>
                            $type,

                        'severity' =>
                            ucfirst(
                                (string) (
                                    $incident->priority
                                    ?? 'low'
                                )
                            ),

                        'barangay' =>
                            $barangay,

                        'status' =>
                            $status,

                        'datetime' =>
                            $incidentAt
                                ? Carbon::parse(
                                    $incidentAt
                                )->format(
                                    'M d, Y h:i A'
                                )
                                : '—',

                        'preview_reference' =>
                            $reference,

                        'preview_category' =>
                            $type,

                        'preview_description' =>
                            $description !== ''
                                ? $description
                                : 'No description provided.',

                        'preview_persons_involved' =>
                            trim(
                                (string) (
                                    $incident
                                        ->persons_involved
                                    ?? ''
                                )
                            ) ?: 'Not specified',

                        'preview_reporter' =>
                            $reporter,

                        'preview_assigned_responder' =>
                            $assignedResponder,

                        'preview_barangay' =>
                            $barangay,

                        'preview_location' =>
                            $mapLocation,

                        'preview_coordinates' =>
                            $coordinates,

                        'preview_status' =>
                            $status,

                        'preview_reported_at' =>
                            $reportedAt
                                ? Carbon::parse(
                                    $reportedAt
                                )->format(
                                    'M d, Y h:i A'
                                )
                                : '—',

                        'preview_incident_at' =>
                            $incidentAt
                                ? Carbon::parse(
                                    $incidentAt
                                )->format(
                                    'M d, Y h:i A'
                                )
                                : '—',

                        'preview_updated_at' =>
                            $incident->updated_at
                                ? Carbon::parse(
                                    $incident->updated_at
                                )->format(
                                    'M d, Y h:i A'
                                )
                                : '—',
                    ];
                }
            )
            ->values()
            ->all();
    }




    private function distressMetricRecords(
        Carbon $startDate,
        Carbon $endDate
    ): array {
        if (
            ! class_exists(
                MobileEmergencyAlert::class
            )
            || ! Schema::hasTable(
                'mobile_emergency_alerts'
            )
        ) {
            return [];
        }

        $hasTriggeredAt =
            Schema::hasColumn(
                'mobile_emergency_alerts',
                'triggered_at'
            );

        $dateExpression =
            $hasTriggeredAt
                ? 'COALESCE(triggered_at, created_at)'
                : 'created_at';

        return MobileEmergencyAlert::query()
            ->with('user:id,name')
            ->whereRaw(
                "{$dateExpression} BETWEEN ? AND ?",
                [
                    $startDate,
                    $endDate,
                ]
            )
            ->orderByRaw(
                "{$dateExpression} DESC"
            )
            ->orderByDesc('id')
            ->get()
            ->map(
                function (
                    MobileEmergencyAlert $alert
                ) use (
                    $hasTriggeredAt
                ): array {
                    $date =
                        (
                            $hasTriggeredAt
                                ? $alert->triggered_at
                                : null
                        )
                        ?? $alert->created_at;

                    $reference =
                        $alert->alert_code
                        ?: 'SOS-'
                            . str_pad(
                                (string) $alert->id,
                                6,
                                '0',
                                STR_PAD_LEFT
                            );

                    $coordinates =
                        $alert->latitude !== null
                        && $alert->longitude !== null
                            ? number_format(
                                (float) $alert->latitude,
                                6,
                                '.',
                                ''
                            )
                                . ', '
                                . number_format(
                                    (float) $alert->longitude,
                                    6,
                                    '.',
                                    ''
                                )
                            : 'Unavailable';

                    $person =
                        $alert->user?->name
                        ?? $alert->display_name
                        ?? 'Not identified';

                    $status =
                        ucfirst(
                            str_replace(
                                '_',
                                ' ',
                                (string) (
                                    $alert->status
                                    ?: 'active'
                                )
                            )
                        );

                    return [
                        'record_key' =>
                            'distress:'
                            . $alert->id,

                        'category' =>
                            'SOS / Distress Signal',

                        'title' =>
                            $reference
                            . ' - '
                            . $person,

                        'type' =>
                            'Emergency',

                        'severity' =>
                            'Critical',

                        'barangay' =>
                            $coordinates,

                        'status' =>
                            $status,

                        'datetime' =>
                            $date
                                ? Carbon::parse(
                                    $date
                                )->format(
                                    'M d, Y h:i A'
                                )
                                : '—',

                        'preview_reference' =>
                            $reference,

                        'preview_category' =>
                            'Emergency SOS',

                        'preview_description' =>
                            'Emergency distress signal submitted through TabangNow.',

                        'preview_persons_involved' =>
                            $person,

                        'preview_reporter' =>
                            $person,

                        'preview_assigned_responder' =>
                            'Not assigned',

                        'preview_barangay' =>
                            '—',

                        'preview_location' =>
                            $coordinates,

                        'preview_coordinates' =>
                            $coordinates,

                        'preview_status' =>
                            $status,

                        'preview_reported_at' =>
                            $date
                                ? Carbon::parse(
                                    $date
                                )->format(
                                    'M d, Y h:i A'
                                )
                                : '—',

                        'preview_incident_at' =>
                            $date
                                ? Carbon::parse(
                                    $date
                                )->format(
                                    'M d, Y h:i A'
                                )
                                : '—',

                        'preview_updated_at' =>
                            $alert->updated_at
                                ? Carbon::parse(
                                    $alert->updated_at
                                )->format(
                                    'M d, Y h:i A'
                                )
                                : '—',
                    ];
                }
            )
            ->values()
            ->all();
    }




    private function tanodDutyMetricRecords(
        Carbon $endDate
    ): array {
        $duty =
            app(
                \App\Services\TanodDutyCounter::class
            )->recordsAt(
                $endDate
            );

        return collect(
            $duty['records']
            ?? []
        )
            ->map(
                function (
                    array $tanod
                ) use (
                    $endDate
                ): array {
                    $name =
                        $tanod['name']
                        ?? 'Tanod';

                    $shift =
                        trim(
                            (string) (
                                $tanod['shift']
                                ?? ''
                            )
                        );

                    $barangay =
                        trim(
                            (string) (
                                $tanod['barangay']
                                ?? ''
                            )
                        );

                    $status =
                        $tanod['status']
                        ?? 'On Duty';

                    $asOf =
                        'As of '
                        . $endDate->format(
                            'M d, Y h:i A'
                        );

                    return [
                        'record_key' =>
                            'tanod:'
                            . (
                                $tanod['id']
                                ?? md5($name)
                            ),

                        'category' =>
                            'Tanod On Duty',

                        'title' =>
                            $name,

                        'type' =>
                            $shift !== ''
                                ? $shift
                                : '—',

                        'severity' =>
                            '—',

                        'barangay' =>
                            $barangay !== ''
                                ? $barangay
                                : '—',

                        'status' =>
                            $status,

                        'datetime' =>
                            $asOf,

                        'preview_reference' =>
                            'Tanod roster record',

                        'preview_category' =>
                            'Tanod Duty',

                        'preview_description' =>
                            'Current Tanod roster duty status recorded by TabangNow.',

                        'preview_persons_involved' =>
                            $name,

                        'preview_reporter' =>
                            $name,

                        'preview_assigned_responder' =>
                            'Not applicable',

                        'preview_barangay' =>
                            $barangay !== ''
                                ? $barangay
                                : '—',

                        'preview_location' =>
                            $barangay !== ''
                                ? $barangay
                                : '—',

                        'preview_coordinates' =>
                            'Not applicable',

                        'preview_status' =>
                            $status,

                        'preview_reported_at' =>
                            $asOf,

                        'preview_incident_at' =>
                            $asOf,

                        'preview_updated_at' =>
                            $asOf,
                    ];
                }
            )
            ->values()
            ->all();
    }




    private function resolveReportRange(
        Request $request
    ): array {
        $period = strtolower(
            trim(
                (string) $request->query(
                    'period',
                    'week'
                )
            )
        );

        $dateFrom = trim(
            (string) $request->query(
                'date_from',
                ''
            )
        );

        $dateTo = trim(
            (string) $request->query(
                'date_to',
                ''
            )
        );

        $customRequested =
            $period === 'custom'
            || $dateFrom !== ''
            || $dateTo !== '';

        if (! $customRequested) {
            $period =
                $this->validPeriod(
                    $period
                );

            [
                $startDate,
                $endDate,
                $periodLabel,
            ] = $this->periodRange(
                $period
            );

            return [
                $period,
                $startDate,
                $endDate,
                $periodLabel,
                false,
            ];
        }

        if (
            $dateFrom === ''
            || $dateTo === ''
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'date_from' =>
                    'Both start and end dates are required for a custom report.',
                'date_to' =>
                    'Both start and end dates are required for a custom report.',
            ]);
        }

        try {
            $startDate =
                Carbon::createFromFormat(
                    'Y-m-d',
                    $dateFrom
                )
                    ->startOfDay();

            $endDate =
                Carbon::createFromFormat(
                    'Y-m-d',
                    $dateTo
                )
                    ->endOfDay();
        } catch (\Throwable) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'date_from' =>
                    'Use a valid report start date.',
                'date_to' =>
                    'Use a valid report end date.',
            ]);
        }

        if (
            $startDate->toDateString()
                !== $dateFrom
            || $endDate->toDateString()
                !== $dateTo
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'date_from' =>
                    'Use dates in YYYY-MM-DD format.',
                'date_to' =>
                    'Use dates in YYYY-MM-DD format.',
            ]);
        }

        if ($startDate->gt($endDate)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'date_to' =>
                    'The report end date must be on or after the start date.',
            ]);
        }

        if (
            $startDate->diffInDays(
                $endDate
            ) > 366
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'date_to' =>
                    'A custom report can cover at most 366 days.',
            ]);
        }

        if (
            $endDate->gt(
                now()->endOfDay()
            )
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'date_to' =>
                    'A report cannot end in the future.',
            ]);
        }

        $periodLabel =
            $startDate->format(
                'M d, Y'
            )
            . ' – '
            . $endDate->format(
                'M d, Y'
            );

        return [
            'custom',
            $startDate,
            $endDate,
            $periodLabel,
            true,
        ];
    }

private function metricSelectionUrls(
        array $data
    ): array {
        $urls = [];

        foreach ([
            'total',
            'active',
            'critical',
            'resolved',
            'distress',
            'tanod',
        ] as $metric) {
            $parameters =
                $this->reportNavigationParameters(
                    array_merge(
                        $data,
                        [
                            'selectedMetric' =>
                                $metric,
                        ]
                    )
                );

            $urls[$metric] =
                route(
                    'admin.reports.download.select',
                    $parameters
                );
        }

        return $urls;
    }

private function validPeriod(?string $period): string
    {
        return in_array($period, ['today', 'week', 'month', 'year'], true)
            ? $period
            : 'week';
    }

    private function periodRange(
        string $period
    ): array {
        $now = now();

        return match (
            $period
        ) {
            'today' => [
                $now
                    ->copy()
                    ->startOfDay(),
                $now
                    ->copy()
                    ->endOfDay(),
                'Today',
            ],

            /*
             * Rolling windows avoid calendar-boundary gaps. A Sunday record
             * remains visible on Monday/Tuesday instead of disappearing just
             * because startOfWeek() moved forward.
             */
            'week' => [
                $now
                    ->copy()
                    ->subDays(6)
                    ->startOfDay(),
                $now
                    ->copy()
                    ->endOfDay(),
                'Last 7 Days',
            ],

            'month' => [
                $now
                    ->copy()
                    ->subDays(29)
                    ->startOfDay(),
                $now
                    ->copy()
                    ->endOfDay(),
                'Last 30 Days',
            ],

            'year' => [
                $now
                    ->copy()
                    ->startOfYear(),
                $now
                    ->copy()
                    ->endOfDay(),
                'This Year',
            ],

            default => [
                $now
                    ->copy()
                    ->subDays(6)
                    ->startOfDay(),
                $now
                    ->copy()
                    ->endOfDay(),
                'Last 7 Days',
            ],
        };
    }

}
