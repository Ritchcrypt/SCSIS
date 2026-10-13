<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReportRecordPreviewScrollToggleTest extends TestCase
{
    public function test_reports_preview_modal_has_scroll_toggles_and_compact_size(): void
    {
        $source = (string) file_get_contents(
            resource_path('views/reports/index.blade.php')
        );

        foreach ([
            'max-w-lg',
            'h-[78vh]',
            'id="reportRecordPreviewScroller"',
            'scrollReportPreviewContent',
            '↑ Up',
            '↓ Down',
        ] as $marker) {
            $this->assertStringContainsString(
                $marker,
                $source
            );
        }
    }
}
