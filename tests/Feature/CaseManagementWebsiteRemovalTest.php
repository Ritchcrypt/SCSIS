<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CaseManagementWebsiteRemovalTest extends TestCase
{
    public function test_case_management_website_routes_are_absent(): void
    {
        foreach ([
            'admin.cases.index',
            'admin.cases.store',
            'admin.cases.update',
            'admin.cases.destroy',
            'admin.cases.pdf',
            'admin.reports.case-pdf',
        ] as $routeName) {
            $this->assertFalse(
                Route::has($routeName),
                "Removed Case Management website route remains: {$routeName}"
            );
        }
    }

    public function test_case_management_website_controller_and_views_are_absent(): void
    {
        $this->assertFileDoesNotExist(
            app_path(
                'Http/Controllers/CaseManagementController.php'
            )
        );

        $this->assertDirectoryDoesNotExist(
            resource_path('views/cases')
        );
    }

    public function test_user_facing_pages_do_not_expose_case_management(): void
    {
        $checks = [
            resource_path('views/layouts/admin.blade.php') => [
                'Case Management',
                'admin.cases.',
            ],
            resource_path('views/incidents/show.blade.php') => [
                'Related Cases',
                '+ Create Case',
                '$createCaseUrl',
                '$relatedCases',
            ],
            resource_path('views/reports/index.blade.php') => [
                'Case PDF',
                'Cases Filed',
                'caseReportModal',
                'case_report_id',
            ],
            resource_path('views/admin/dashboard.blade.php') => [
                'Active Cases',
                'admin.cases.index',
            ],
            resource_path('views/dashboards/official.blade.php') => [
                'Active Cases',
            ],
        ];

        foreach ($checks as $path => $needles) {
            if (! is_file($path)) {
                continue;
            }

            $source = (string) file_get_contents($path);

            foreach ($needles as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $source,
                    "{$needle} still exists in {$path}"
                );
            }
        }
    }

    public function test_mobile_incident_contract_has_no_retired_case_permissions(): void
    {
        $source = (string) file_get_contents(
            app_path('Http/Controllers/Api/V1/IncidentController.php')
        );

        $this->assertStringNotContainsString("'can_view_related_cases'", $source);
        $this->assertStringNotContainsString("'can_create_case'", $source);
        // Keep an empty field for older API clients until a future versioned API.
        $this->assertStringContainsString("$" . "data['related_cases'] = [];", $source);
    }

    public function test_historical_case_notifications_route_only_to_dashboard(): void
    {
        $source = (string) file_get_contents(
            app_path('Http/Controllers/Api/V1/NotificationCenterController.php')
        );

        $this->assertStringContainsString("'case_created', 'case_updated', 'case_deleted'", $source);
        $this->assertStringNotContainsString("target('caseManagement'", $source);
    }

    public function test_notification_open_flow_has_no_case_management_destination(): void
    {
        $source = (string) file_get_contents(
            app_path(
                'Http/Controllers/NotificationOpenController.php'
            )
        );

        $this->assertStringNotContainsString(
            'caseManagementUrl',
            $source
        );

        $this->assertStringNotContainsString(
            'admin.cases.index',
            $source
        );
    }
}
