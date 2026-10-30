<?php

namespace Tests\Feature;

use Tests\TestCase;

final class DashboardDaoNewsAuditPresentationTest extends TestCase
{
    public function test_website_audit_history_has_human_readable_labels_and_preserves_original_sources(): void
    {
        $view = file_get_contents(resource_path('views/components/dashboard/dao-news-carousel.blade.php'));
        self::assertIsString($view);
        self::assertStringContainsString('External news audit history', $view);
        self::assertStringContainsString('Article updated', $view);
        self::assertStringContainsString('Link checked: available', $view);
        self::assertStringContainsString("timezone('Asia/Manila')", $view);
        self::assertStringContainsString('tn-dao-friendly-audit-item', $view);
        self::assertStringNotContainsString('· Link ID {{', $view);
        self::assertStringNotContainsString("{{ $"."event['link_id'] }}", $view);
    }
}
