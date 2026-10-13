<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ReportPdfSelectionFlowTest extends TestCase
{
    public function test_metric_download_buttons_open_selection_inside_reports(): void
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
                foreach ([
                    'total',
                    'active',
                    'critical',
                    'resolved',
                    'distress',
                    'tanod',
                ] as $metric) {
                    $url = $urls[$metric] ?? '';

                    if (
                        ! is_string($url)
                        || ! str_contains(
                            $url,
                            '/reports/download'
                        )
                        || ! str_contains(
                            $url,
                            'metric=' . $metric
                        )
                    ) {
                        return false;
                    }
                }

                return true;
            }
        );
    }

    public function test_selection_page_has_select_all_scroll_toggle_and_download_choices(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(
                route(
                    'admin.reports.download.select',
                    [
                        'period' => 'week',
                        'metric' => 'active',
                    ]
                )
            )
            ->assertOk()
            ->assertSee('Active Incidents PDF')
            ->assertSee('Select All')
            ->assertSee('Expand List')
            ->assertSee('Download Selected PDF')
            ->assertSee('Download All PDF')
            ->assertSee('metricRecordScroller', false);
    }

    public function test_selection_routes_exist(): void
    {
        $this->assertTrue(
            Route::has(
                'admin.reports.download.select'
            )
        );

        $this->assertTrue(
            Route::has(
                'admin.reports.download.pdf'
            )
        );
    }
}
