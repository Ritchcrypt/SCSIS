<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReportRecordPreviewNativeScrollbarTest extends TestCase
{
    public function test_reports_preview_hides_native_scrollbar_and_uses_compact_custom_rail(): void
    {
        $source = (string) file_get_contents(
            resource_path(
                'views/reports/index.blade.php'
            )
        );

        foreach ([
            'width: min(92vw, 560px);',
            'height: min(72vh, 620px);',
            'max-height: 72vh;',
            'overflow-y: auto;',
            'scrollbar-width: none;',
            '-ms-overflow-style: none;',
            '#reportRecordPreviewScroller::-webkit-scrollbar',
            'width: 0;',
            'height: 0;',
            'id="reportRecordPreviewScroller"',
            'id="reportPreviewRail"',
            'id="reportPreviewRailThumb"',
        ] as $marker) {
            $this->assertStringContainsString(
                $marker,
                $source
            );
        }

        $this->assertStringNotContainsString(
            'scrollbar-color: #2563eb #dbeafe',
            $source
        );
    }
}
