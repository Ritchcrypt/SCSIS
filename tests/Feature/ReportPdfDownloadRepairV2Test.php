<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportPdfDownloadRepairV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_tanod_download_all_returns_pdf_using_posted_metric(): void
    {
        [$admin, $profileId] = $this->makeOnDutyTanod();

        $response = $this
            ->actingAs($admin)
            ->post(
                route('admin.reports.download.pdf'),
                [
                    'period' => 'week',
                    'metric' => 'tanod',
                    'selection_mode' => 'all',
                ]
            );

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringContainsString(
            'attachment;',
            strtolower(
                (string) $response->headers->get('content-disposition')
            )
        );
    }

    public function test_tanod_selected_row_returns_pdf(): void
    {
        [$admin, $profileId] = $this->makeOnDutyTanod();

        $response = $this
            ->actingAs($admin)
            ->post(
                route('admin.reports.download.pdf'),
                [
                    'period' => 'week',
                    'metric' => 'tanod',
                    'selection_mode' => 'selected',
                    'records' => [
                        'tanod:' . $profileId,
                    ],
                ]
            );

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_custom_date_post_values_are_kept_for_pdf(): void
    {
        [$admin] = $this->makeOnDutyTanod();

        $today = now()->toDateString();

        $response = $this
            ->actingAs($admin)
            ->post(
                route('admin.reports.download.pdf'),
                [
                    'period' => 'custom',
                    'metric' => 'tanod',
                    'date_from' => $today,
                    'date_to' => $today,
                    'selection_mode' => 'all',
                ]
            );

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_report_cards_use_uniform_blue_classes(): void
    {
        $source = (string) file_get_contents(
            resource_path('views/reports/index.blade.php')
        );

        $this->assertStringContainsString(
            'reports-metric-card',
            $source
        );

        $this->assertStringContainsString(
            '#dbeafe',
            $source
        );

        $this->assertStringContainsString(
            'reports-metric-download',
            $source
        );
    }

    /**
     * @return array{0:User,1:int}
     */
    private function makeOnDutyTanod(): array
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $tanod = User::factory()->create([
            'role' => 'tanod',
        ]);

        $row = [
            'user_id' => $tanod->id,
            'employee_id' => null,
            'contact_number' => null,
            'purok_assignment' => null,
            'date_appointed' => null,
            'shift' => 'day',
            'status' => 'on_duty',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        /*
         * The website no longer uses badges, but old installations may still
         * have a NOT NULL legacy compatibility column.
         */
        if (\Illuminate\Support\Facades\Schema::hasColumn(
            'tanod_profiles',
            'badge_number'
        )) {
            $row['badge_number'] =
                'TEST-LEGACY-'
                . $tanod->id;
        }

        $profileId = (int) DB::table('tanod_profiles')
            ->insertGetId($row);

        return [
            $admin,
            $profileId,
        ];
    }
}
