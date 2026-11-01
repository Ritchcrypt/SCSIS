<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpaNavigationPilotTest extends TestCase
{
    public function test_spa_navigation_is_limited_to_low_risk_sidebar_destinations(): void
    {
        $view = file_get_contents(resource_path('views/layouts/admin.blade.php'));
        $this->assertIsString($view);
        $this->assertStringContainsString('wire:navigate.hover', $view);
        $this->assertStringContainsString('request()->route()?->getName()', $view);
        $this->assertStringContainsString("'admin.announcements.index'", $view);
        $this->assertStringContainsString("'official.emergency-mode.index'", $view);
        $this->assertStringContainsString("'tanod.tanod-tasks.index'", $view);
        $this->assertStringContainsString("'resident.announcements.index'", $view);
        $this->assertMatchesRegularExpression('/\$isSpaPilotLink\s*=\s*in_array\(/', $view);
        $routesMatch = [];
        $this->assertSame(1, preg_match('/\$spaPilotRoutes\s*=\s*\[(.*?)\];/s', $view, $routesMatch));
        foreach (['map.index', 'incidents.index', 'reports.index', 'users.index', 'dashboard', 'profile.edit'] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $routesMatch[1]);
        }
    }

    public function test_scripts_support_body_swaps_without_duplicating_sidebar_handlers(): void
    {
        $view = file_get_contents(resource_path('views/layouts/admin.blade.php'));
        $js = file_get_contents(resource_path('js/app.js'));
        $this->assertIsString($view);
        $this->assertIsString($js);
        $this->assertStringContainsString('livewire:navigated', $view);
        $this->assertStringContainsString('tnSpaSidebarReady', $view);
        $this->assertStringContainsString('document.addEventListener(\'livewire:navigated\', initializeImageUploadPreviews)', $js);
    }

    public function test_spa_history_revalidates_authentication_on_back_forward(): void
    {
        $view = file_get_contents(resource_path('views/layouts/admin.blade.php'));
        $this->assertStringContainsString('event.detail?.history', $view);
        $this->assertStringContainsString('window.location.assign(event.detail.url.href)', $view);
        $this->assertStringContainsString('if (event.persisted)', $view);
        $this->assertStringContainsString("'pagehide'", $view);
        $this->assertStringContainsString("'pageshow'", $view);
    }
}
