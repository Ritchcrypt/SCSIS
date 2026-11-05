<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementBarangayBulkSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_official_can_select_or_clear_all_target_barangays(): void
    {
        foreach (['admin', 'official'] as $role) {
            $user = User::factory()->create([
                'role' => $role,
                'is_active' => true,
                'status' => true,
            ]);

            $route = $role === 'admin'
                ? 'admin.announcements.index'
                : 'official.announcements.index';

            $this->actingAs($user)
                ->get(route($route))
                ->assertOk()
                ->assertSee('Select All')
                ->assertSee('Clear')
                ->assertSee('id="announcementTargetBarangays"', false)
                ->assertSee('onclick="setAnnouncementBarangaySelection(true)"', false)
                ->assertSee('onclick="setAnnouncementBarangaySelection(false)"', false)
                ->assertSee('name="barangay_ids[]"', false);
        }
    }

    public function test_bulk_selection_is_only_for_barangays_and_never_submits_form(): void
    {
        $view = file_get_contents(
            resource_path('views/announcements/index.blade.php')
        );

        $this->assertIsString($view);
        $this->assertStringContainsString(
            'function setAnnouncementBarangaySelection(checked)',
            $view
        );
        $this->assertStringContainsString(
            'list.querySelectorAll(\'input[type="checkbox"][name="barangay_ids[]"]\')',
            $view
        );
        $this->assertStringContainsString('box.checked = checked', $view);
        $this->assertStringContainsString('type="button"', $view);
        $this->assertStringContainsString('name="activate_calamity_mode"', $view);
        $this->assertStringContainsString('name="show_in_weather_feed"', $view);
    }
}
