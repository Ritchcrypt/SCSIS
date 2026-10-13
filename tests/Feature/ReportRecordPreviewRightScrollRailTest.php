<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReportRecordPreviewRightScrollRailTest extends TestCase
{
    public function test_preview_has_final_permanent_right_side_scroll_rail(): void
    {
        $source = (string) file_get_contents(
            resource_path(
                'views/reports/index.blade.php'
            )
        );

        foreach ([
            'id="reportPreviewRail"',
            'id="reportPreviewRailTrack"',
            'id="reportPreviewRailThumb"',
            'id="reportPreviewRailUp"',
            'id="reportPreviewRailDown"',
            'function updateReportPreviewRail()',
            'pointerdown',
            'pointermove',
            'setPointerCapture',
            'function scrollReportPreviewBy(amount)',
        ] as $marker) {
            $this->assertStringContainsString(
                $marker,
                $source
            );
        }

        foreach ([
            'reportPreviewScrollRail',
            'reportPreviewScrollTrack',
            'reportPreviewScrollThumb',
            'startReportPreviewThumbDrag',
            'jumpReportPreviewScroll',
            'updateReportPreviewScrollThumb',
        ] as $obsoleteMarker) {
            $this->assertStringNotContainsString(
                $obsoleteMarker,
                $source
            );
        }
    }
}
