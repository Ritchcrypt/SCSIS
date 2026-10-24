<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Database-free contract checks for the shared dashboard banner.
 * PHPUnit class style is required; this project does not use Pest's test().
 */
final class DashboardWelcomeBannerContractTest extends TestCase
{
    public function test_banner_is_inserted_below_hamburger_header_on_four_dashboards_only(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));
        $this->assertNotFalse($layout);

        $this->assertStringContainsString(
            "request()->routeIs('admin.dashboard', 'official.dashboard', 'tanod.dashboard', 'resident.dashboard')",
            $layout
        );
        $this->assertStringContainsString(
            "@include('components.dashboard.welcome-banner')",
            $layout
        );

        $headerEnd = strpos($layout, '</header>');
        $bannerInclude = strpos($layout, "@include('components.dashboard.welcome-banner')");
        $contentSlot = strpos($layout, "@yield('content')");

        $this->assertNotFalse($headerEnd);
        $this->assertNotFalse($bannerInclude);
        $this->assertNotFalse($contentSlot);
        $this->assertTrue($headerEnd < $bannerInclude && $bannerInclude < $contentSlot);
    }

    public function test_banner_uses_escaped_authenticated_name_and_effective_photo(): void
    {
        $view = file_get_contents(resource_path('views/components/dashboard/welcome-banner.blade.php'));
        $this->assertNotFalse($view);

        $this->assertStringContainsString('auth()->user()?->name', $view);
        $this->assertStringContainsString('{{ $dashboardWelcomeName }}', $view);
        $this->assertStringContainsString("asset('images/dashboard/dao-welcome-scene-20261022_173013_735.jpg')", $view);
        $this->assertStringContainsString('Welcome back,', $view);
        $this->assertSame(1, substr_count($view, "asset('images/dashboard/dao-welcome-scene-20261022_173013_735.jpg')"));

        $image = public_path('images/dashboard/dao-welcome-scene-20261022_173013_735.jpg');
        $this->assertFileExists($image);
        $this->assertSame(
            '8f8e628b9966db494e0b2723030cd243f4c35236f18601c9891c0460cabc1dc4',
            hash_file('sha256', $image)
        );
    }
}
