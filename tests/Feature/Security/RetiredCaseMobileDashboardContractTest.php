<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class RetiredCaseMobileDashboardContractTest extends TestCase
{
    public function test_mobile_dashboard_does_not_access_retired_case_records(): void
    {
        $controller = (string) file_get_contents(app_path('Http/Controllers/Api/V1/DashboardController.php'));
        $parity = (string) file_get_contents(app_path('Http/Controllers/Api/V1/DashboardParityController.php'));
        $incident = (string) file_get_contents(app_path('Http/Controllers/Api/V1/IncidentController.php'));

        $this->assertStringNotContainsString('case_records', $controller);
        $this->assertStringNotContainsString('CaseRecord', $controller);
        $this->assertStringContainsString("'active_incidents' =>", $controller);
        $this->assertStringContainsString("'resolved_incidents' =>", $controller);
        $this->assertStringNotContainsString("'active_cases'", $parity);
        $this->assertStringNotContainsString("'resolved_cases'", $parity);
        $this->assertStringNotContainsString('caseRecords', $incident);
        $this->assertStringContainsString("$" . "data['related_cases'] = [];", $incident);
    }
}
