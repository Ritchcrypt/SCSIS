<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReportRecordPreviewRemovedFieldsTest extends TestCase
{
    public function test_requested_fields_are_removed_from_reports_preview_only(): void
    {
        $source = (string) file_get_contents(
            resource_path(
                'views/reports/index.blade.php'
            )
        );

        foreach ([
            'Assigned Responder',
            'Persons Involved',
            'Coordinates',
            'reportPreviewAssignedResponder',
            'reportPreviewPersons',
            'reportPreviewCoordinates',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }

        foreach ([
            'Reporter / Person',
            'Description / Details',
            'Reported At',
            'Recorded Date &amp; Time',
            'Last Updated',
            'id="reportPreviewRail"',
            'Choose Records for PDF',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }
}
