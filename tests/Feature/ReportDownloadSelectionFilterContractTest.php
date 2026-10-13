<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class ReportDownloadSelectionFilterContractTest extends TestCase
{
    public function test_report_selection_view_contains_record_filters(): void
    {
        $directory =
            resource_path(
                'views/reports'
            );

        $iterator =
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $directory
                )
            );

        $matches = [];

        foreach ($iterator as $file) {
            if (
                ! $file->isFile()
                || ! str_ends_with(
                    $file->getFilename(),
                    '.blade.php'
                )
            ) {
                continue;
            }

            $source =
                (string) file_get_contents(
                    $file->getPathname()
                );

            if (
                str_contains(
                    $source,
                    'CAPSTONE18_REPORT_SELECTION_FILTERS'
                )
            ) {
                $matches[] =
                    $source;
            }
        }

        $this->assertCount(
            1,
            $matches
        );

        $source =
            $matches[0];

        foreach ([
            'reportSelectionSearch',
            'reportSelectionSeverity',
            'reportSelectionLocation',
            'reportSelectionStatus',
            'reportSelectionClearFilters',
            'reportSelectionVisibleCount',
            'Pending Assessment',
            'applyFilters',
            'inferSeverity',
            'inferLocation',
            'inferStatus',
        ] as $marker) {
            $this->assertStringContainsString(
                $marker,
                $source
            );
        }
    }
}
