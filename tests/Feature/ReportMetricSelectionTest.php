<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportMetricSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_metric_cards_filter_records_inside_reports_module(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(
                route(
                    'admin.reports.index',
                    [
                        'period' => 'today',
                        'metric' => 'active',
                    ]
                )
            )
            ->assertOk()
            ->assertSee('Active Incidents Records Breakdown')
            ->assertSee('Download PDF')
            ->assertDontSee('Secure PDF')
            ->assertDontSee('Generate PDF')
            ->assertDontSee('Incident PDF')
            ->assertDontSee('Feedback PDF')
            ->assertDontSee('SOS PDF')
            ->assertDontSee('Tanod Response Summary');
    }

    public function test_each_supported_metric_can_be_selected(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        foreach ([
            'total',
            'active',
            'critical',
            'resolved',
            'distress',
            'tanod',
        ] as $metric) {
            $this->actingAs($admin)
                ->get(
                    route(
                        'admin.reports.index',
                        [
                            'period' => 'today',
                            'metric' => $metric,
                        ]
                    )
                )
                ->assertOk();
        }
    }
}
