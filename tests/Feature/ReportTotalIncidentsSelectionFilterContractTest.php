<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReportTotalIncidentsSelectionFilterContractTest extends TestCase
{
    public function test_total_incidents_selection_filter_is_scoped_and_dynamic(): void
    {
        $path =
            resource_path(
                'views/reports/download-selection.blade.php'
            );

        $this->assertFileExists(
            $path
        );

        $source =
            (string) file_get_contents(
                $path
            );

        foreach ([
            'reportTotalIncidentFilters',
            'isTotalIncidentsSelection',
            'findIncidentTable',
            'applyTotalIncidentFilters',
            'populateFilterSelect',
            'reportTotalIncidentSearch',
            'reportTotalIncidentSeverity',
            'reportTotalIncidentLocation',
            'reportTotalIncidentStatus',
            'reportTotalIncidentVisibleCount',
            'Filter Total Incident Records',
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
