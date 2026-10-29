<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DashboardDaoNewsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class DashboardDaoNewsCarouselTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_curated_link_only_does_not_fetch_or_embed_external_content(): void
    {
        Http::preventStrayRequests();
        $news = new DashboardDaoNewsService();
        $news->addCuratedLink('https://pia.gov.ph/capiz-update', 'Public safety', gmdate('Y-m-d'));
        $result = $news->forUser(new User());
        $this->assertCount(1, $result);
        $this->assertSame('Dao, Capiz · Public safety', $result[0]['title']);
        $this->assertSame('pia.gov.ph', $result[0]['source']);
        $this->assertNull($result[0]['image']);
        $this->assertStringNotContainsString('capiz-update', $result[0]['summary']);
        Http::assertNothingSent();
    }

    public function test_total_carousel_limit_remains_ten_slides(): void
    {
        $news = new DashboardDaoNewsService();
        for ($i = 1; $i <= 15; $i++) {
            $news->addCuratedLink('https://pia.gov.ph/capiz-update-'.$i, 'Local government', gmdate('Y-m-d'));
        }
        $this->assertCount(15, $news->entries());
        $this->assertCount(9, $news->forUser(new User()));
    }

    public function test_unapproved_links_and_url_bypass_attempts_are_rejected(): void
    {
        $news = new DashboardDaoNewsService();
        foreach ([
            'http://pia.gov.ph/story',
            'https://pia.gov.ph.attacker.example/story',
            'https://pia.gov.ph@attacker.example/story',
            'https://attacker.example/story',
            'https://subdomain.pia.gov.ph/story',
            'https://pia.gov.ph:8443/story',
            'https://pia.gov.ph/story?redirect=https://evil.example',
            'https://pia.gov.ph/redirect/evil.example',
            'https://127.0.0.1/story',
            'https://localhost/story',
        ] as $bad) {
            $this->assertFalse($news->isTrustedUrl($bad), $bad);
            try {
                $news->addCuratedLink($bad, 'Education', gmdate('Y-m-d'));
                $this->fail('Unsafe URL accepted: '.$bad);
            } catch (ValidationException $expected) {
                $this->assertTrue(true);
            }
        }
        $this->assertTrue($news->isTrustedUrl('https://www.pna.gov.ph/articles/12345'));
        $this->assertSame([], $news->entries());
    }

    public function test_legacy_entries_are_hidden_until_reverified_and_untrusted_are_blocked(): void
    {
        $news = new DashboardDaoNewsService();
        Storage::disk('local')->put('dao-external-links/entries.json', json_encode([
            'version' => 1, 'entries' => [
                ['id' => '11111111-1111-4111-8111-111111111111', 'url' => 'https://pia.gov.ph/old', 'topic' => 'Education', 'published' => ''],
                ['id' => '22222222-2222-4222-8222-222222222222', 'url' => 'https://evil.example/old', 'topic' => 'Education', 'published' => ''],
            ],
        ]));
        $this->assertCount(2, $news->entries());
        $this->assertCount(0, $news->forUser(new User()));
        $news->reviewCuratedLink('11111111-1111-4111-8111-111111111111', gmdate('Y-m-d'));
        $this->assertCount(1, $news->forUser(new User()));
        $this->assertTrue(Storage::disk('local')->exists('dao-external-links/entries.json.bak'));
        try {
            $news->reviewCuratedLink('22222222-2222-4222-8222-222222222222', gmdate('Y-m-d'));
            $this->fail('Untrusted legacy domain approved');
        } catch (ValidationException $expected) { $this->assertTrue(true); }
        $this->assertCount(1, $news->forUser(new User()));
    }

    public function test_outdated_reviews_are_hidden_without_deleting_links(): void
    {
        $news = new DashboardDaoNewsService();
        Storage::disk('local')->put('dao-external-links/entries.json', json_encode([
            'version' => 2, 'entries' => [[
                'id' => '11111111-1111-4111-8111-111111111111',
                'url' => 'https://pia.gov.ph/old', 'topic' => 'Education',
                'published' => gmdate('Y-m-d'), 'reviewed_at' => '2020-01-01',
            ]],
        ]));
        $this->assertCount(1, $news->entries());
        $this->assertCount(0, $news->forUser(new User()));
        $news->reviewCuratedLink('11111111-1111-4111-8111-111111111111', gmdate('Y-m-d'));
        $this->assertCount(1, $news->forUser(new User()));
    }

    public function test_corrupted_storage_is_not_silently_overwritten(): void
    {
        Storage::disk('local')->put('dao-external-links/entries.json', '{bad-json');
        $news = new DashboardDaoNewsService();
        try {
            $news->addCuratedLink('https://pia.gov.ph/story', 'Education', gmdate('Y-m-d'));
            $this->fail('Corrupt data was silently discarded');
        } catch (\RuntimeException $expected) { $this->assertTrue(true); }
        $this->assertSame('{bad-json', Storage::disk('local')->get('dao-external-links/entries.json'));
    }

    public function test_external_news_opens_without_popup_while_preserving_link_security_and_admin_confirmations(): void
    {
        $view = file_get_contents(resource_path('views/components/dashboard/dao-news-carousel.blade.php'));
        $php = file_get_contents(app_path('Services/DashboardDaoNewsService.php'));
        $this->assertStringNotContainsString('window.confirm(warning)', $view);
        $this->assertStringContainsString("window.open(link.href, '_blank', 'noopener,noreferrer')", $view);
        $this->assertStringContainsString("link.protocol !== 'https:'", $view);
        $this->assertStringContainsString('if (active === 0) return;', $view);
        $this->assertStringContainsString("label.textContent = 'DAO, CAPIZ';", $view);
        $this->assertStringContainsString('Tap edges to browse · Tap center to open', $view);
        $this->assertStringContainsString("source.textContent = [story.source,story.published].filter(Boolean).join(' · ');", $view);
        $this->assertStringNotContainsString('DAO, CAPIZ · EXTERNAL NEWS', $view);
        $this->assertStringNotContainsString('Opens publisher website in new tab', $view);
        $this->assertStringNotContainsString('data-summary=', $view);
        $this->assertStringNotContainsString('external update.', $view);
        $this->assertStringContainsString('tn-dao-ext-navigation--left', $view);
        $this->assertStringContainsString('Review and approve', $view);
        // Admin-only destructive operations still ask for explicit confirmation.
        $this->assertStringContainsString("confirm('Unpublish this article", $view);
        $this->assertStringContainsString("confirm('Remove this external link?')", $view);
        $this->assertStringNotContainsString('Http::', $php);
        $this->assertStringNotContainsString('Announcement::', $php);
    }

    public function test_publication_age_and_dao_relevance_gate(): void
    {
        $news = new DashboardDaoNewsService();
        $today = gmdate('Y-m-d');
        $old = gmdate('Y-m-d', strtotime('-100 days'));
        try {
            $news->addCuratedLink('https://pia.gov.ph/old-story', 'Local government', $old);
            $this->fail('Outdated publication added');
        } catch (ValidationException $expected) { $this->assertTrue(true); }
        try {
            $news->addCuratedLink('https://pia.gov.ph/no-date', 'Local government', null);
            $this->fail('Missing publication date accepted');
        } catch (ValidationException $expected) { $this->assertTrue(true); }
        $news->addCuratedLink('https://pia.gov.ph/current-dao', 'Local government', $today);
        $this->assertCount(1, $news->forUser(new User()));
        $row = $news->entries()[0];
        $this->assertSame($today, $row['dao_verified_at']);
        $this->assertSame($today, $row['published']);
        Storage::disk('local')->put('dao-external-links/entries.json', json_encode([
            'version' => 2, 'entries' => [[
                'id' => $row['id'], 'url' => $row['url'], 'topic' => $row['topic'],
                'published' => $today, 'reviewed_at' => $today,
            ]],
        ]));
        $this->assertCount(0, $news->forUser(new User()));
        $news->reviewCuratedLink($row['id'], $today);
        $this->assertCount(1, $news->forUser(new User()));
    }

    public function test_malformed_saved_record_stops_write_without_data_loss(): void
    {
        $broken = json_encode(['version' => 2, 'entries' => [[
            'id' => 'not-a-uuid', 'url' => 'https://pia.gov.ph/story', 'topic' => 'Education',
        ]]]);
        Storage::disk('local')->put('dao-external-links/entries.json', $broken);
        $news = new DashboardDaoNewsService();
        try {
            $news->addCuratedLink('https://pia.gov.ph/new-story', 'Education', gmdate('Y-m-d'));
            $this->fail('Malformed saved record overwritten');
        } catch (\RuntimeException $expected) { $this->assertTrue(true); }
        $this->assertSame($broken, Storage::disk('local')->get('dao-external-links/entries.json'));
    }

    public function test_ambiguous_encoded_urls_rejected(): void
    {
        $news = new DashboardDaoNewsService();
        foreach (['https://pia.gov.ph/a/%2F/b', 'https://pia.gov.ph/a/%5C/b',
            'https://pia.gov.ph/a/../story', 'https://pia.gov.ph/a/%0d%0a',
            'https://pia.gov.ph/a/%252f'] as $url) {
            $this->assertFalse($news->isTrustedUrl($url), $url);
        }
    }

    public function test_admin_form_requires_local_relevance_and_publication_date(): void
    {
        $form = file_get_contents(resource_path('views/components/dashboard/dao-news-carousel.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/DashboardDaoNewsLinksController.php'));
        $this->assertStringContainsString('name="dao_relevance_checked"', $form);
        $this->assertStringContainsString("'dao_relevance_checked' => ['accepted']", $controller);
        $this->assertStringContainsString("'published' => ['required'", $controller);
        $this->assertStringContainsString('Reviewer user #', $form);
    }
    public function test_edit_remove_and_audit_keep_old_and_new_details(): void
    {
        $news = new DashboardDaoNewsService();
        $today = gmdate('Y-m-d');
        $news->addCuratedLink('https://pia.gov.ph/dao-first', 'Education', $today);
        $id = $news->entries()[0]['id'];
        $news->editCuratedLink($id, 'https://pna.gov.ph/dao-corrected', 'Local government', $today);
        $this->assertSame($id, $news->entries()[0]['id']);
        $this->assertSame('https://pna.gov.ph/dao-corrected', $news->entries()[0]['url']);
        $news->flagCuratedLink($id);
        $this->assertCount(0, $news->forUser(new User()));
        $news->reviewCuratedLink($id, $today);
        $this->assertCount(1, $news->forUser(new User()));
        $news->removeCuratedLink($id);
        $this->assertCount(0, $news->entries());
        $history = $news->auditHistory();
        $this->assertSame(['removed','reviewed','unpublished','edited','added'], array_column($history, 'action'));
        $this->assertSame('https://pia.gov.ph/dao-first', $history[3]['before']['url']);
        $this->assertSame('https://pna.gov.ph/dao-corrected', $history[3]['after']['url']);
        $this->assertSame('https://pna.gov.ph/dao-corrected', $history[0]['before']['url']);
    }

    public function test_tampered_audit_blocks_changes_without_overwriting_data(): void
    {
        $news = new DashboardDaoNewsService();
        $news->addCuratedLink('https://pia.gov.ph/dao', 'Education', gmdate('Y-m-d'));
        $path = 'dao-external-links/entries.json';
        $data = json_decode(Storage::disk('local')->get($path), true);
        $data['audit'][0]['action'] = 'removed';
        $tampered = json_encode($data);
        Storage::disk('local')->put($path, $tampered);
        try {
            $news->editCuratedLink($data['entries'][0]['id'], 'https://pia.gov.ph/change', 'Education', gmdate('Y-m-d'));
            $this->fail('Audit tampering was not detected.');
        } catch (\RuntimeException $expected) { $this->assertTrue(true); }
        $this->assertSame($tampered, Storage::disk('local')->get($path));
    }

    public function test_head_checks_do_not_follow_redirects_and_failed_checks_hide_link(): void
    {
        $news = new DashboardDaoNewsService();
        $news->addCuratedLink('https://pia.gov.ph/dao', 'Education', gmdate('Y-m-d'));
        $id = $news->entries()[0]['id'];
        $this->assertSame('redirected', $news->checkCuratedLink($id, fn () => ['state'=>'redirected','code'=>301]));
        $this->assertCount(0, $news->forUser(new User()));
        $row = $news->entries()[0];
        $this->assertSame(301, $row['last_http_status']);
        $news->reviewCuratedLink($id, gmdate('Y-m-d'));
        $this->assertCount(1, $news->forUser(new User()));
        $this->assertSame('ok', $news->checkCuratedLink($id, fn () => ['state'=>'ok','code'=>200]));
        $this->assertCount(1, $news->forUser(new User()));
        $this->assertSame('link_checked', $news->auditHistory()[0]['action']);
    }

    public function test_edit_and_check_forms_are_admin_only_under_existing_routes(): void
    {
        $form = file_get_contents(resource_path('views/components/dashboard/dao-news-carousel.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/DashboardDaoNewsLinksController.php'));
        $this->assertStringContainsString('name="edit_id"', $form);
        $this->assertStringContainsString('name="check_id"', $form);
        $this->assertStringContainsString('Unpublish / flag changed', $form);
        $this->assertStringContainsString('External news audit history', $form);
        $this->assertStringContainsString('editCuratedLink', $controller);
    }


    public function test_dedicated_audit_key_survives_app_key_rotation_and_creates_recovery_snapshots(): void
    {
        $news = new DashboardDaoNewsService();
        $news->addCuratedLink('https://pia.gov.ph/dao', 'Education', gmdate('Y-m-d'));
        $disk = Storage::disk('local');
        $this->assertTrue($disk->exists('dao-external-links/audit-key.json'));
        $this->assertGreaterThanOrEqual(1, count($news->availableSnapshots()));
        $snapshotFiles = $news->availableSnapshots();
        $this->assertTrue($disk->exists($snapshotFiles[0].'/entries.json'));
        $this->assertTrue($disk->exists($snapshotFiles[0].'/audit-key.json'));
        $original = config('app.key');
        try {
            config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
            $this->assertCount(1, $news->entries());
            $news->editCuratedLink($news->entries()[0]['id'], 'https://pna.gov.ph/dao', 'Education', gmdate('Y-m-d'));
            $this->assertSame(['edited', 'added'], array_column($news->auditHistory(), 'action'));
        } finally {
            config(['app.key' => $original]);
        }
    }

    public function test_existing_legacy_v3_audit_migrates_without_losing_event_details(): void
    {
        $event = ['action' => 'added', 'link_id' => '11111111-1111-4111-8111-111111111111',
            'actor_id' => 44, 'at' => '2026-10-01T12:00:00+00:00',
            'before' => null, 'after' => null, 'previous_hash' => ''];
        $event['hash'] = hash_hmac('sha256', json_encode($event, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), config('app.key'));
        Storage::disk('local')->put('dao-external-links/entries.json', json_encode(['version' => 3, 'entries' => [], 'audit' => [$event]]));
        $news = new DashboardDaoNewsService();
        $this->assertSame('added', $news->auditHistory()[0]['action']);
        $news->addCuratedLink('https://pia.gov.ph/new-dao', 'Education', gmdate('Y-m-d'));
        $stored = json_decode(Storage::disk('local')->get('dao-external-links/entries.json'), true);
        $this->assertSame(4, $stored['version']);
        $this->assertSame(44, $stored['audit'][0]['actor_id']);
        $this->assertSame('dao-local-v1', $stored['audit'][0]['key_id']);
        $this->assertCount(2, $news->auditHistory());
    }

    public function test_archive_retains_deleted_article_history_and_detects_missing_archive(): void
    {
        config(['dao_news.audit_archive_threshold' => 10]);
        $news = new DashboardDaoNewsService();
        for ($i = 0; $i < 12; $i++) {
            $news->addCuratedLink('https://pia.gov.ph/dao-archive-'.$i, 'Education', gmdate('Y-m-d'));
        }
        $doc = json_decode(Storage::disk('local')->get('dao-external-links/entries.json'), true);
        $this->assertNotEmpty($doc['archives']);
        $this->assertCount(12, $news->auditHistory());
        $snapshots = $news->availableSnapshots();
        $latestSnapshot = $snapshots[0];
        $this->assertTrue(Storage::disk('local')->exists($latestSnapshot.'/manifest.json'));
        $archiveName = $doc['archives'][0];
        Storage::disk('local')->delete('dao-external-links/archives/'.$archiveName);
        $this->expectException(\RuntimeException::class);
        $news->entries();
    }

    public function test_head_not_supported_preserves_manual_review_and_confirmed_gone_unpublishes(): void
    {
        $news = new DashboardDaoNewsService();
        $news->addCuratedLink('https://pia.gov.ph/dao-manual', 'Education', gmdate('Y-m-d'));
        $id = $news->entries()[0]['id'];
        $this->assertSame('manual_check', $news->checkCuratedLink($id, fn () => ['state' => 'manual_check', 'code' => 405]));
        $this->assertCount(1, $news->forUser(new User()));
        $this->assertSame('broken', $news->checkCuratedLink($id, fn () => ['state' => 'broken', 'code' => 404]));
        $this->assertCount(0, $news->forUser(new User()));
    }

    public function test_admin_changes_have_an_additional_rate_limit_and_role_check(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/DashboardDaoNewsLinksController.php'));
        $this->assertStringContainsString('RateLimiter::tooManyAttempts', $controller);
        $this->assertStringContainsString("$"."user->role === 'admin'", $controller);
    }

}
