<?php

namespace Tests\Feature;

use Tests\TestCase;

class CustomThemeAccentCoverageTest extends TestCase
{
    public function test_custom_mode_covers_navy_table_headers_profile_panels_and_pagination(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString("html[data-theme='custom'] .bg-blue-950", $css);
        $this->assertStringContainsString("html[data-theme='custom'] .bg-blue-900", $css);
        $this->assertStringContainsString("html[data-theme='custom'] .bg-blue-800", $css);
        $this->assertStringContainsString('background-color: var(--tn-shell-deep) !important;', $css);
        $this->assertStringContainsString('background-color: var(--tn-shell-hover) !important;', $css);
        $this->assertStringContainsString("html[data-theme='custom'] .text-indigo-600", $css);
    }

    public function test_custom_mode_covers_buttons_focus_and_existing_gradient(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString("html[data-theme='custom'] .hover\\:bg-blue-900:hover", $css);
        $this->assertStringContainsString("html[data-theme='custom'] .focus\\:ring-blue-500:focus", $css);
        $this->assertStringContainsString("html[data-theme='custom'] .from-blue-950", $css);
        $this->assertStringContainsString("html[data-theme='custom'] .via-blue-900", $css);
        $this->assertStringContainsString("html[data-theme='custom'] .to-blue-700", $css);
    }

    public function test_user_management_blue_utilities_are_handled_only_under_custom_mode(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $users = file_get_contents(resource_path('views/admin/users/index.blade.php'));
        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        $this->assertIsString($css);
        $this->assertIsString($users);
        $this->assertIsString($layout);
        $this->assertStringContainsString('bg-blue-950 shadow-sm', $users);
        $this->assertStringContainsString('text-indigo-600', $users);
        $this->assertStringContainsString('bg-blue-950', $layout);

        // New color declarations belong inside custom selectors and custom tokens.
        $this->assertStringContainsString("html[data-theme='custom'] .bg-blue-950", $css);
        $this->assertStringContainsString("html[data-theme='custom'] .bg-indigo-600", $css);
    }
}
