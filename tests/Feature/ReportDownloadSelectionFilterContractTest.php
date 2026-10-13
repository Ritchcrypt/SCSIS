<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReportDownloadSelectionFilterContractTest extends TestCase
{
    public function test_download_selection_filters_are_scoped_to_total_incidents_only(): void
    {
        $source = (string) file_get_contents(
            resource_path(
                'views/reports/download-selection.blade.php'
            )
        );

        foreach ([
            'CAPSTONE18_TOTAL_INCIDENTS_SELECTION_FILTERS_V2',
            'reportTotalIncidentFilters',
            'reportTotalIncidentSearch',
            'reportTotalIncidentSeverity',
            'reportTotalIncidentLocation',
            'reportTotalIncidentStatus',
            'reportTotalIncidentClearFilters',
            'reportTotalIncidentVisibleCount',
            'isTotalIncidentsSelection',
            'findIncidentTable',
            'applyTotalIncidentFilters',
        ] as $marker) {
            $this->assertStringContainsString(
                $marker,
                $source
            );
        }

        $this->assertStringNotContainsString(
            'CAPSTONE18_REPORT_SELECTION_FILTERS',
            $source
        );
    }
}
