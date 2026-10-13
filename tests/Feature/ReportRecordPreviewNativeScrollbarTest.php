<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReportRecordPreviewNativeScrollbarTest extends TestCase
{
    public function test_reports_preview_uses_small_modal_with_native_right_scrollbar(): void
    {
        $source = (string) file_get_contents(
            resource_path(
                'views/reports/index.blade.php'
            )
        );

        foreach ([
            'max-w-md',
            'h-[70vh]',
            'overflow-y: scroll !important',
            'scrollbar-color: #2563eb #dbeafe',
            '::-webkit-scrollbar',
            'width: 18px',
            'id="reportRecordPreviewScroller"',
        ] as $marker) {
            $this->assertStringContainsString(
                $marker,
                $source
            );
        }
    }
}
