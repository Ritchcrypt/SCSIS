<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ReportController as WebsiteReportController;
use App\Models\CaseRecord;
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

        $data = $websiteReports->apiReportData(
            $request
        );

        return response()
            ->json([
                'data' => [
                    'period' =>
                        (string) (
                            $data['period']
                            ?? 'week'
                        ),
                    'period_label' =>
                        (string) (
                            $data['periodLabel']
                            ?? 'This Week'
                        ),
                    'start_date' =>
                        isset($data['startDate'])
                            ? $data['startDate']
                                ->toIso8601String()
                            : null,
                    'end_date' =>
                        isset($data['endDate'])
                            ? $data['endDate']
                                ->toIso8601String()
                            : null,
                    'generated_at' =>
                        isset($data['generatedAt'])
                            ? $data['generatedAt']
                                ->toIso8601String()
                            : null,
                    'generated_by' =>
                        (string) (
                            $data['generatedBy']
                            ?? 'System'
                        ),

                    'summary' => [
                        'total_incidents' =>
                            (int) (
                                $data[
                                    'totalIncidents'
                                ]
                                ?? 0
                            ),
                        'active_incidents' =>
                            (int) (
                                $data[
                                    'activeIncidents'
                                ]
                                ?? 0
                            ),
                        'resolved_incidents' =>
                            (int) (
                                $data[
                                    'resolvedIncidents'
                                ]
                                ?? 0
                            ),
                        'cases_filed' =>
                            (int) (
                                $data[
                                    'casesFiled'
                                ]
                                ?? 0
                            ),
                        'resident_complaints' =>
                            (int) (
                                $data[
                                    'residentComplaints'
                                ]
                                ?? 0
                            ),
                        'distress_signals' =>
                            (int) (
                                $data[
                                    'distressSignals'
                                ]
                                ?? 0
                            ),
                        'announcements' =>
                            (int) (
                                $data[
                                    'announcements'
                                ]
                                ?? 0
                            ),
                    ],

                    'records' =>
                        array_values(
                            $data['records']
                            ?? []
                        ),

                    'status_summary' =>
                        array_values(
                            $data[
                                'statusSummary'
                            ]
                            ?? []
                        ),

                    'severity_summary' =>
                        array_values(
                            $data[
                                'severitySummary'
                            ]
                            ?? []
                        ),

                    'barangay_summary' =>
                        array_values(
                            $data[
                                'barangaySummary'
                            ]
                            ?? []
                        ),

                    'tanod_summary' =>
                        collect(
                            $data[
                                'tanodSummary'
                            ]
                            ?? []
                        )
                            ->values()
                            ->all(),

                    'report_options' => [
                        'incidents' =>
                            collect(
                                $data[
                                    'incidentReportOptions'
                                ]
                                ?? []
                            )
                                ->values()
                                ->all(),
                        'cases' =>
                            collect(
                                $data[
                                    'caseReportOptions'
                                ]
                                ?? []
                            )
                                ->values()
                                ->all(),
                        'complaints' =>
                            collect(
                                $data[
                                    'complaintReportOptions'
                                ]
                                ?? []
                            )
                                ->values()
                                ->all(),                        'sos' =>
                            collect(
                                $data[
                                    'sosReportOptions'
                                ]
                                ?? []
                            )
                                ->values()
                                ->all(),
                    ],
                ],

                'permissions' => [
                    'can_view_reports' => true,
                    'can_download_pdf' => true,
                    'can_download_incident_pdf' =>
                        true,
                    'can_download_case_pdf' =>
                        true,
                    'can_download_complaint_pdf' =>
                        true,                    'can_download_sos_pdf' =>
                        true,
                ],

                'period_options' => [
                    [
                        'value' => 'today',
                        'label' => 'Today',
                    ],
                    [
                        'value' => 'week',
                        'label' => 'This Week',
                    ],
                    [
                        'value' => 'month',
                        'label' => 'This Month',
                    ],
                    [
                        'value' => 'year',
                        'label' => 'This Year',
                    ],
                ],
            ])
            ->header(
                'Cache-Control',
                'no-store, private'
            );
    }

    public function downloadPdf(
        Request $request,
        WebsiteReportController $websiteReports
    ): Response {
        $this->authorizeRequest($request);

        return $websiteReports
            ->downloadPdf($request);
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

    public function downloadCasePdf(
        Request $request,
        CaseRecord $caseRecord,
        WebsiteReportController $websiteReports
    ): Response {
        $this->authorizeRequest($request);

        return $websiteReports
            ->downloadCasePdf(
                $request,
                $caseRecord
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
