<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReportRecordPreviewRightScrollRailTest extends TestCase
{
    public function test_preview_has_permanent_right_side_scroll_rail(): void
    {
        $source = (string) file_get_contents(
            resource_path('views/reports/index.blade.php')
        );

        foreach ([
            'max-w-lg',
            'h-[72vh]',
            'id="reportPreviewScrollRail"',
            'id="reportPreviewScrollTrack"',
            'id="reportPreviewScrollThumb"',
            'startReportPreviewThumbDrag',
            'jumpReportPreviewScroll',
            'updateReportPreviewScrollThumb',
        ] as $marker) {
            $this->assertStringContainsString($marker, $source);
        }
    }
}
