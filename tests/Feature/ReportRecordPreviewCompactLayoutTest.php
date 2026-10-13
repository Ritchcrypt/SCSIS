<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReportRecordPreviewCompactLayoutTest extends TestCase
{
    public function test_reports_preview_modal_uses_final_compact_scrollable_layout(): void
    {
        $source = (string) file_get_contents(
            resource_path(
                'views/reports/index.blade.php'
            )
        );

        foreach ([
            '#reportRecordPreviewPanel',
            'width: min(92vw, 560px);',
            'height: min(72vh, 620px);',
            'max-height: 72vh;',
            '#reportRecordPreviewScroller',
            'overflow-y: auto;',
            'min-height: 0;',
            'flex flex-col-reverse gap-2 sm:flex-row sm:justify-end',
            'document.documentElement.style.overflow =',
            'document.body.style.overflow =',
            "'hidden';",
        ] as $marker) {
            $this->assertStringContainsString(
                $marker,
                $source
            );
        }
    }
}
