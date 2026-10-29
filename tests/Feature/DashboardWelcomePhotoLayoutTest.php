<?php

namespace Tests\Feature;

use Tests\TestCase;

/** UI-only contract: admin button opens the file picker, then uploads directly. */
final class DashboardWelcomePhotoLayoutTest extends TestCase
{
    public function test_admin_photo_picker_uses_one_button_and_no_popup_or_preview(): void
    {
        $view = file_get_contents(resource_path('views/components/dashboard/welcome-banner.blade.php'));
        $this->assertNotFalse($view);

        $this->assertStringContainsString('tn-c21-dashboard-welcome-shell', $view);
        $this->assertStringContainsString('tn-c21-dashboard-welcome__photo-button', $view);
        $this->assertStringContainsString('data-welcome-photo-trigger', $view);
        $this->assertStringContainsString('name="welcome_image"', $view);
        $this->assertStringContainsString('uploadInput?.click()', $view);
        $this->assertStringContainsString('uploadForm.requestSubmit()', $view);
        $this->assertStringContainsString('event.stopImmediatePropagation()', $view);
        $this->assertStringContainsString("@csrf", $view);
        $this->assertStringContainsString('if ($dashboardWelcomeIsAdmin)', $view);
        $this->assertStringContainsString("route('admin.dashboard-welcome-image.update')", $view);
        $this->assertStringContainsString('data-welcome-version', $view);

        $this->assertStringNotContainsString('<details', $view);
        $this->assertStringNotContainsString('tn-c21-dashboard-welcome__photo-panel', $view);
        $this->assertStringNotContainsString('Selected image preview', $view);
        $this->assertStringNotContainsString('Save photo for all roles', $view);
    }
}
