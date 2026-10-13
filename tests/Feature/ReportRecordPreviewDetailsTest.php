<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReportRecordPreviewDetailsTest extends TestCase
{
    public function test_incident_metric_records_keep_full_internal_preview_metadata(): void
    {
        $source = (string) file_get_contents(
            app_path(
                'Http/Controllers/ReportController.php'
            )
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

    public function test_reports_preview_shows_only_the_accepted_visible_fields(): void
    {
        $source = (string) file_get_contents(
            resource_path(
                'views/reports/index.blade.php'
            )
        );

        foreach ([
            'Record Preview',
            'Reporter / Person',
            'Description / Details',
            'Recorded Date &amp; Time',
            'Last Updated',
            'reportPreviewReporter',
            'reportPreviewDescription',
            'reportPreviewDatetime',
            'reportPreviewUpdatedAt',
            'z-index: 9999',
            'z-index: 10000',
        ] as $marker) {
            $this->assertStringContainsString(
                $marker,
                $source
            );
        }

        foreach ([
            '>Assigned Responder<',
            '>Persons Involved<',
            '>Coordinates<',
            'id="reportPreviewAssignedResponder"',
            'id="reportPreviewPersons"',
            'id="reportPreviewCoordinates"',
        ] as $removedMarker) {
            $this->assertStringNotContainsString(
                $removedMarker,
                $source
            );
        }
    }
}
