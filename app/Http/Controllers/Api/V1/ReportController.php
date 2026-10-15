<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ReportController as WebsiteReportController;
use App\Models\Incident;
use App\Models\MobileEmergencyAlert;
use App\Models\ResidentComplaint;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function index(
        Request $request,
        WebsiteReportController $websiteReports
    ): JsonResponse {
        $this->authorizeRequest($request);

        $data = $websiteReports->apiReportData($request);

        return response()
            ->json([
                'data' => [
                    'period' => (string) ($data['period'] ?? 'week'),
                    'period_label' => (string) ($data['periodLabel'] ?? 'Last 7 Days'),
                    'date_from' => (string) ($data['dateFrom'] ?? ''),
                    'date_to' => (string) ($data['dateTo'] ?? ''),
                    'is_custom_period' => (bool) ($data['isCustomPeriod'] ?? false),
                    'is_historical_range' => (bool) ($data['isHistoricalRange'] ?? false),
                    'start_date' => isset($data['startDate'])
                        ? $data['startDate']->toIso8601String()
                        : null,
                    'end_date' => isset($data['endDate'])
                        ? $data['endDate']->toIso8601String()
                        : null,
                    'generated_at' => isset($data['generatedAt'])
                        ? $data['generatedAt']->toIso8601String()
                        : null,
                    'generated_by' => (string) ($data['generatedBy'] ?? 'System'),
                    'selected_metric' => (string) ($data['selectedMetric'] ?? 'total'),
                    'selected_metric_label' => (string) ($data['selectedMetricLabel'] ?? 'Total Incidents'),
                    'selected_metric_count' => (int) ($data['selectedMetricCount'] ?? 0),
                    'summary' => [
                        'total_incidents' => (int) ($data['totalIncidents'] ?? 0),
                        'active_incidents' => (int) ($data['activeIncidents'] ?? 0),
                        'critical_incidents' => (int) ($data['criticalIncidents'] ?? 0),
                        'resolved_incidents' => (int) ($data['resolvedIncidents'] ?? 0),
                        'distress_signals' => (int) ($data['distressSignals'] ?? 0),
                        'tanod_on_duty' => (int) ($data['tanodOnDuty'] ?? 0),
                        'resident_feedback' => (int) ($data['residentComplaints'] ?? 0),
                        'announcements' => (int) ($data['announcements'] ?? 0),
                    ],
                    'records' => array_values($data['records'] ?? []),
                    'status_summary' => array_values($data['statusSummary'] ?? []),
                    'severity_summary' => array_values($data['severitySummary'] ?? []),
                    'barangay_summary' => array_values($data['barangaySummary'] ?? []),
                    'report_options' => [
                        'incidents' => collect($data['incidentReportOptions'] ?? [])->values()->all(),
                        'resident_feedback' => collect($data['complaintReportOptions'] ?? [])->values()->all(),
                        'sos' => collect($data['sosReportOptions'] ?? [])->values()->all(),
                    ],
                ],
                'permissions' => [
                    'can_view_reports' => true,
                    'can_download_pdf' => true,
                    'can_download_incident_pdf' => true,
                    'can_download_resident_feedback_pdf' => true,
                    'can_download_sos_pdf' => true,
                    'can_download_selected_pdf' => true,
                ],
                'period_options' => [
                    ['value' => 'today', 'label' => 'Today'],
                    ['value' => 'week', 'label' => 'Last 7 Days'],
                    ['value' => 'month', 'label' => 'Last 30 Days'],
                    ['value' => 'year', 'label' => 'This Year'],
                    ['value' => 'custom', 'label' => 'Custom / Previous Dates'],
                ],
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    public function downloadPdf(
        Request $request,
        WebsiteReportController $websiteReports
    ): Response {
        $this->authorizeRequest($request);

        return $websiteReports
            ->downloadPdf($request);
    }

    public function downloadSelectedPdf(
        Request $request,
        WebsiteReportController $websiteReports
    ): Response {
        $this->authorizeRequest($request);

        return $websiteReports->downloadSelectedPdf($request);
    }

    public function downloadIncidentPdf(
        Request $request,
        Incident $incident,
        WebsiteReportController $websiteReports
    ): Response {
        $this->authorizeRequest($request);

        return $websiteReports
            ->downloadIncidentPdf(
                $request,
                $incident
            );
    }

    public function downloadComplaintPdf(
        Request $request,
        ResidentComplaint $residentComplaint,
        WebsiteReportController $websiteReports
    ): Response {
        $this->authorizeRequest($request);

        return $websiteReports
            ->downloadComplaintPdf(
                $request,
                $residentComplaint
            );
    }


    public function downloadSosPdf(
        Request $request,
        MobileEmergencyAlert $mobileEmergencyAlert,
        WebsiteReportController $websiteReports
    ): Response {
        $this->authorizeRequest($request);

        return $websiteReports
            ->downloadSosPdf(
                $request,
                $mobileEmergencyAlert
            );
    }
    private function authorizeRequest(
        Request $request
    ): User {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        /*
         * The website ReportController uses Auth::user()
         * when writing "Generated By" into PDF/report data.
         * Sanctum already authenticated the request; mirror
         * that exact authenticated user into the default guard
         * for this one API request so the existing website
         * report generator remains the single source of truth.
         */
        Auth::guard()->setUser($user);

        Gate::authorize('viewReports');

        return $user;
    }
}
