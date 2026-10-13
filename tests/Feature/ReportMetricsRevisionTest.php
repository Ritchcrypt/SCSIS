<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ReportMetricsRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_page_exposes_current_metrics(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(
                route(
                    'admin.reports.index',
                    [
                        'period' => 'week',
                    ]
                )
            )
            ->assertOk()
            ->assertSee('Total Incidents')
            ->assertSee('Active Incidents')
            ->assertSee('Critical Incidents')
            ->assertSee('Resolved Incidents')
            ->assertSee('Distress Signals / SOS')
            ->assertSee('Tanod On Duty');
    }

    public function test_report_download_selection_routes_remain_available(): void
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

    public function test_historical_snapshot_route_is_removed(): void
    {
        $this->assertFalse(
            Route::has(
                'admin.reports.snapshots.pdf'
            )
        );
    }
}