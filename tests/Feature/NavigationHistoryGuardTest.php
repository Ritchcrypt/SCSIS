<?php

namespace Tests\Feature;

use Tests\TestCase;

class NavigationHistoryGuardTest extends TestCase
{
    public function test_authenticated_history_guard_keeps_bfcache_auth_check_without_duplicate_popstate_reload(): void
    {
        $source = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('window.__tabangNowHistorySecurityV2Installed', $source);
        $this->assertStringContainsString("'pagehide'", $source);
        $this->assertStringContainsString("'pageshow'", $source);
        $this->assertStringContainsString('if (event.persisted)', $source);
        $this->assertStringContainsString('refreshThroughLaravel();', $source);
        $this->assertStringNotContainsString("'popstate'", $source);
    }

    public function test_same_sidebar_destination_does_not_reload_but_other_navigation_is_preserved(): void
    {
        $source = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString("closest('#adminSidebar a[href]')", $source);
        $this->assertStringContainsString('destination.pathname === window.location.pathname', $source);
        $this->assertStringContainsString('destination.search === window.location.search', $source);
        $this->assertStringContainsString('event.preventDefault();', $source);
        $this->assertStringContainsString('route($item[\'route\'])', $source);
    }
}
