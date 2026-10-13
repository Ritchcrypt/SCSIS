<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReportRecordPreviewDetailsTest extends TestCase
{
    public function test_incident_metric_records_expose_full_preview_fields(): void
    {
        $source = (string) file_get_contents(
            app_path('Http/Controllers/ReportController.php')
        );

        foreach ([
            "'preview_reference'",
            "'preview_description'",
            "'preview_persons_involved'",
            "'preview_reporter'",
            "'preview_assigned_responder'",
            "'preview_location'",
            "'preview_coordinates'",
            "'preview_reported_at'",
            "'preview_incident_at'",
        ] as $field) {
            $this->assertStringContainsString(
                $field,
                $source
            );
        }
    }

    public function test_reports_preview_has_full_fields_and_strong_modal_z_index(): void
    {
        $source = (string) file_get_contents(
            resource_path('views/reports/index.blade.php')
        );

        foreach ([
            'Reference',
            'Reporter / Person',
            'Assigned Responder',
            'Persons Involved',
            'Coordinates',
            'Description / Details',
            'Recorded Date & Time',
            'reportPreviewReporter',
            'reportPreviewAssignedResponder',
            'reportPreviewPersons',
            'reportPreviewCoordinates',
            'z-index: 9999',
            'z-index: 10000',
        ] as $marker) {
            $this->assertStringContainsString(
                $marker,
                $source
            );
        }
    }
}
