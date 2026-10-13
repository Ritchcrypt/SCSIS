<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReportRecordPreviewScrollToggleTest extends TestCase
{
    public function test_reports_preview_uses_final_rail_buttons_for_scrolling(): void
    {
        $source = (string) file_get_contents(
            resource_path(
                'views/reports/index.blade.php'
            )
        );

        foreach ([
            'id="reportRecordPreviewScroller"',
            'id="reportPreviewRailUp"',
            'id="reportPreviewRailDown"',
            'scrollReportPreviewBy(-220);',
            'scrollReportPreviewBy(220);',
            '▲',
            '▼',
        ] as $marker) {
            $this->assertStringContainsString(
                $marker,
                $source
            );
        }

        $this->assertStringNotContainsString(
            'scrollReportPreviewContent',
            $source
        );
    }
}
