<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReportRecordPreviewCompactLayoutTest extends TestCase
{
    public function test_reports_preview_modal_uses_compact_scrollable_layout(): void
    {
        $source = (string) file_get_contents(
            resource_path('views/reports/index.blade.php')
        );

        foreach ([
            'max-w-2xl',
            'max-h-[86vh]',
            'min-h-0 flex-1 overflow-y-auto',
            'flex flex-col-reverse gap-2 sm:flex-row sm:justify-end',
            "document.body.style.overflow = 'hidden';",
        ] as $marker) {
            $this->assertStringContainsString(
                $marker,
                $source
            );
        }
    }
}
