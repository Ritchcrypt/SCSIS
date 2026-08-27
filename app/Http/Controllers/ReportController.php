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

        return view(
            'reports.index',
            $this->reportData($request)
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

    public function downloadPdf(Request $request)
    {
        Gate::authorize('viewReports');

        $data = $this->reportData($request);

        $fileName = 'barangay-report-'
            . $this->safeFileToken(
                $data['periodLabel'] ?? 'report',
                'report'
            )
            . '-'
            . now()->format('Ymd-His')
            . '.pdf';

        return Pdf::loadView('reports.pdf', $data)
            ->setPaper('a4', 'portrait')
            ->download($fileName);
    }


    public function downloadIncidentPdf(
        Request $request,
        Incident $incident
    ) {
        Gate::authorize('viewReports');
        Gate::authorize('view', $incident);

        $data = $this->specificIncidentReportData($incident);

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

        $fileName = 'complaint-report-'
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
        This report contains only the selected case management record. Other cases, complaints, or incidents are excluded.
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
        $period = $this->validPeriod($request->query('period', 'week'));
        [$startDate, $endDate, $periodLabel] = $this->periodRange($period);

        $incidentBase = $this->incidentBaseQuery($startDate, $endDate);

        $totalIncidents = (clone $incidentBase)->count();

        $activeIncidents = (clone $incidentBase)
            ->where(function ($query) {
                $query->whereDoesntHave('currentStatus')
                    ->orWhereHas('currentStatus', function ($statusQuery) {
                        $statusQuery->whereNotIn(DB::raw('LOWER(status_name)'), [
                            'resolved',
                            'closed',
                            'completed',
                            'cancelled',
                            'canceled',
                            'rejected',
                        ]);
                    });
            })
            ->count();

        $resolvedIncidents = (clone $incidentBase)
            ->whereHas('currentStatus', function ($query) {
                $query->whereIn(DB::raw('LOWER(status_name)'), [
                    'resolved',
                    'closed',
                    'completed',
                ]);
            })
            ->count();

        $currentUser = Auth::user();

        return [
            'period' => $period,
            'periodLabel' => $periodLabel,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'generatedAt' => now(),
            'generatedBy' => $currentUser ? $currentUser->name : 'System',

            'totalIncidents' => $totalIncidents,
            'activeIncidents' => $activeIncidents,
            'resolvedIncidents' => $resolvedIncidents,
            'casesFiled' => $this->countTableByDate('case_records', $startDate, $endDate),
            'residentComplaints' => $this->residentComplaintCount($startDate, $endDate),
            'distressSignals' => $this->distressSignalCount($startDate, $endDate),
            'announcements' => $this->countTableByDate('announcements', $startDate, $endDate),

            'records' => $this->recordsBreakdown($startDate, $endDate),
            'statusSummary' => $this->statusSummary($startDate, $endDate),
            'severitySummary' => $this->severitySummary($startDate, $endDate),
            'barangaySummary' => $this->barangaySummary($startDate, $endDate),
            'tanodSummary' => $this->tanodResponseSummary($startDate, $endDate),

            'incidentReportOptions' => $this->incidentReportOptions(),
'caseReportOptions' => $this->caseReportOptions(),
'complaintReportOptions' => $this->complaintReportOptions(),
'sosReportOptions' => $this->sosReportOptions(),
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
                ], 'Resident Complaint'),

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
                        <td>' . e($proof['file_name'] ?? 'Proof file') . '</td>
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
    <title>Complaint Report</title>

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

        <p class="title">Complaint Report</p>

        <p class="subtitle">
            Resident Complaint Documentation — Dao, Capiz
        </p>

        <div class="meta">
            Generated by: ' . $generatedBy . '<br>
            Generated at: ' . e($generatedAt) . '
        </div>
    </div>

    <div class="notice">
        This report contains only the selected resident complaint and its directly related records. Other complaints or incidents are excluded.
    </div>

    <div class="section">
        <p class="section-title">Complaint Details</p>

        <table>
            <tr>
                <td class="label">Complaint Code</td>
                <td class="value">' . $value('code') . '</td>
            </tr>

            <tr>
                <td class="label">Title / Subject</td>
                <td class="value">' . $value('title') . '</td>
            </tr>

            <tr>
                <td class="label">Complainant</td>
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
                <td class="label">Complaint Date & Time</td>
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
        <p class="section-title">Complaint Narrative / Details</p>

        <table>
            <tr>
                <td class="wide-text">' . nl2br($value('description')) . '</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <p class="section-title">Proofs / Attachments</p>
        ' . $proofSection . '
    </div>

    <div class="footer">
        This report is generated from TabangNow system records. It is intended for barangay complaint documentation and monitoring.
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
                ], 'Resident Complaint');

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
                                ?? basename((string) ($record->file_path ?? $record->path ?? 'Proof file')),
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
    private function incidentBaseQuery(Carbon $startDate, Carbon $endDate)
    {
        return Incident::query()
            ->with(['barangay', 'category', 'currentStatus'])
            ->whereBetween('created_at', [$startDate, $endDate]);
    }

    private function recordsBreakdown(Carbon $startDate, Carbon $endDate): array
    {
        $records = [];

        $incidents = Incident::query()
            ->with(['category', 'barangay', 'currentStatus'])
            ->whereBetween('created_at', [$startDate, $endDate])
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
                    'category' => 'Resident Complaint',
                    'title' => 'Complaint #'
                        . $complaint->id
                        . ' - '
                        . ($complaint->complainant_name
                            ?: 'Unknown complainant'),
                    'type' => 'Resident Complaint',
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

    private function validPeriod(?string $period): string
    {
        return in_array($period, ['today', 'week', 'month', 'year'], true)
            ? $period
            : 'week';
    }

    private function periodRange(string $period): array
    {
        return match ($period) {
            'today' => [
                now()->startOfDay(),
                now()->endOfDay(),
                'Today',
            ],
            'month' => [
                now()->startOfMonth(),
                now()->endOfMonth(),
                'This Month',
            ],
            'year' => [
                now()->startOfYear(),
                now()->endOfYear(),
                'This Year',
            ],
            default => [
                now()->startOfWeek(),
                now()->endOfWeek(),
                'This Week',
            ],
        };
    }
}
