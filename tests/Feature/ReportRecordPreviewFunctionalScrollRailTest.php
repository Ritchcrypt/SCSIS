<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReportRecordPreviewFunctionalScrollRailTest extends TestCase
{
    public function test_preview_uses_enforced_height_and_functional_scroll_rail(): void
    {
        $source = (string) file_get_contents(
            resource_path(
                'views/reports/index.blade.php'
            )
        );

        foreach ([
            'height: min(72vh, 620px)',
            'id="reportPreviewRail"',
            'id="reportPreviewRailTrack"',
            'id="reportPreviewRailThumb"',
            'updateReportPreviewRail',
            'scrollReportPreviewBy',
            'pointerdown',
            'pointermove',
            'reportPreviewRailUp',
            'reportPreviewRailDown',
        ] as $marker) {
            $this->assertStringContainsString(
                $marker,
                $source
            );
        }
    }
}
