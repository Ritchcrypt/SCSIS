<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ReportMetricPdfButtonsTest extends TestCase
{
    public function test_each_metric_download_button_opens_the_reports_selection_flow(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(
                route(
                    'admin.reports.index',
                    [
                        'period' => 'week',
                        'metric' => 'total',
                    ]
                )
            );

        $response
            ->assertOk()
            ->assertSee('Download PDF');

        $response->assertViewHas(
            'metricSelectionUrls',
            function (array $urls): bool {
                return count($urls) === 6;
            }
        );
    }
}