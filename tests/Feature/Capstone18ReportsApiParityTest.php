<?php

namespace Tests\Feature;

use Tests\TestCase;

class Capstone18ReportsApiParityTest extends TestCase
{
    public function test_reports_api_exposes_metric_timeline_and_selection_contract(): void
    {
        $api = file_get_contents(
            app_path('Http/Controllers/Api/V1/ReportController.php')
        );
        $website = file_get_contents(
            app_path('Http/Controllers/ReportController.php')
        );
        $routes = file_get_contents(base_path('routes/api.php'));

        $this->assertIsString($api);
        $this->assertIsString($website);
        $this->assertIsString($routes);

        foreach ([
            "'critical_incidents'",
            "'tanod_on_duty'",
            "'selected_metric'",
            "'selected_metric_label'",
            "'selected_metric_count'",
            "'is_custom_period'",
            "'custom'",
            'downloadSelectedPdf',
        ] as $marker) {
            $this->assertStringContainsString($marker, $api);
        }

        $this->assertStringContainsString(
            "Route::post('/reports/selection/pdf'",
            $routes
        );

        $this->assertStringContainsString(
            "\$this->recordsWithSelectionKeys(",
            $website
        );
    }

    public function test_reports_api_no_longer_advertises_case_pdf_in_index_contract(): void
    {
        $api = file_get_contents(
            app_path('Http/Controllers/Api/V1/ReportController.php')
        );

        $this->assertIsString($api);
        $this->assertStringNotContainsString("'cases_filed'", $api);
        $this->assertStringNotContainsString("'can_download_case_pdf'", $api);
    }
}
