<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ReportsUiCleanupTest extends TestCase
{
    public function test_reports_hide_pdf_button_and_only_reveal_custom_dates_conditionally(): void
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
            ->assertDontSee('Generate PDF')
            ->assertSee('Download PDF')
            ->assertSee('Custom / Previous Dates')
            ->assertSee('reportCustomDateDropdown', false)
            ->assertSee('display: none', false);
    }

    public function test_website_tanod_code_does_not_use_badge_number(): void
    {
        $dutySource = (string) file_get_contents(
            app_path(
                'Services/TanodDutyCounter.php'
            )
        );

        $userManagementSource = (string) file_get_contents(
            app_path(
                'Http/Controllers/UserManagementController.php'
            )
        );

        $rosterView = (string) file_get_contents(
            resource_path(
                'views/tanods/index.blade.php'
            )
        );

        $reportController = (string) file_get_contents(
            app_path(
                'Http/Controllers/ReportController.php'
            )
        );

        $this->assertStringNotContainsString(
            "'badge_number'",
            $dutySource
        );

        $this->assertStringNotContainsString(
            "\$profileData['badge_number']",
            $userManagementSource
        );

        $this->assertStringNotContainsString(
            'name="badge_number"',
            $rosterView
        );

        $this->assertStringNotContainsString(
            "Badge '",
            $reportController
        );
    }
}
