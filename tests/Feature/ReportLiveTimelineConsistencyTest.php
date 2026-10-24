<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportLiveTimelineConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_last_seven_days_includes_recent_records_across_calendar_week_boundary(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $status = Status::query()->firstOrCreate(
            ['status_name' => 'Reported'],
            [
                'description' => 'Reported incident',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $category = IncidentCategory::query()->firstOrCreate(
            ['category_name' => 'Test Incident'],
            ['is_active' => true]
        );

        $incident = Incident::query()->create([
            'category_id' => $category->id,
            'status_id' => $status->id,
            'title' => 'Recent boundary incident',
            'incident_title' => 'Recent boundary incident',
            'incident_description' => 'Timeline consistency test',
            'priority' => 'high',
            'incident_datetime' => now()->subDays(2),
            'reported_at' => now()->subDays(2),
        ]);

        DB::table('incidents')
            ->where('id', $incident->id)
            ->update([
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ]);

        $this->actingAs($admin)
            ->get(
                route(
                    'admin.reports.index',
                    [
                        'period' => 'week',
                        'metric' => 'total',
                    ]
                )
            )
            ->assertOk()
            ->assertSee('Recent boundary incident')
            ->assertSee('Last 7 Days');
    }

    public function test_last_thirty_days_includes_recent_incidents(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $status = Status::query()->firstOrCreate(
            ['status_name' => 'Reported'],
            [
                'description' => 'Reported incident',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $category = IncidentCategory::query()->firstOrCreate(
            ['category_name' => 'Monthly Test'],
            ['is_active' => true]
        );

        Incident::query()->create([
            'category_id' => $category->id,
            'status_id' => $status->id,
            'title' => 'Recent monthly incident',
            'incident_title' => 'Recent monthly incident',
            'incident_description' => 'Live report test',
            'priority' => 'moderate',
            'incident_datetime' => now()->subDays(12),
            'reported_at' => now()->subDays(12),
        ]);

        $this->actingAs($admin)
            ->get(
                route(
                    'admin.reports.index',
                    [
                        'period' => 'month',
                        'metric' => 'total',
                    ]
                )
            )
            ->assertOk()
            ->assertSee('Recent monthly incident')
            ->assertSee('Last 30 Days');
    }

    public function test_reports_page_has_no_historical_archive_and_has_record_preview(): void
    {
        $source = (string) file_get_contents(
            resource_path('views/reports/index.blade.php')
        );

        $this->assertStringNotContainsString(
            'Historical Report Archive',
            $source
        );

        $this->assertStringNotContainsString(
            'Historical snapshot:',
            $source
        );

        $this->assertStringContainsString(
            'openReportRecordPreview',
            $source
        );

        $this->assertStringContainsString(
            'Record Preview',
            $source
        );
    }

    public function test_report_controller_no_longer_archives_or_reads_snapshots(): void
    {
        $source = (string) file_get_contents(
            app_path('Http/Controllers/ReportController.php')
        );

        $this->assertDoesNotMatchRegularExpression(
            '/function\\s+archiveHistoricalReport\\s*\\(|\\$this\\s*->\\s*archiveHistoricalReport\\s*\\(/',
            $source
        );

        $this->assertDoesNotMatchRegularExpression(
            '/function\\s+snapshotViewData\\s*\\(|\\$this\\s*->\\s*snapshotViewData\\s*\\(/',
            $source
        );

        $this->assertDoesNotMatchRegularExpression(
            '/function\\s+reportSnapshotList\\s*\\(|\\$this\\s*->\\s*reportSnapshotList\\s*\\(/',
            $source
        );

        $this->assertStringContainsString(
            'COALESCE(incident_datetime, reported_at, created_at)',
            $source
        );
    }
}
