<?php
namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\DashboardDaoNewsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class DashboardDaoNewsMobileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_bearer_news_is_shared_with_website_and_admin_actions_are_guarded(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $resident = User::factory()->create(['role' => 'resident', 'is_active' => true]);
        $url = 'https://pia.gov.ph/news/visayas/wv/dao-capiz-test';
        $this->getJson(route('api.v1.dao-news.index'))->assertUnauthorized();
        $this->actingAs($admin, 'sanctum');
        app(DashboardDaoNewsService::class)->addCuratedLink($url, 'Local news and updates', gmdate('Y-m-d'));
        $this->getJson(route('api.v1.dao-news.index'))
            ->assertOk()->assertJsonPath('mode', 'curated_links_only')
            ->assertJsonPath('max_slides', 10)
            ->assertJsonPath('data.0.url', $url)
            ->assertJsonPath('data.0.image', null);
        $this->actingAs($resident, 'sanctum')->getJson(route('api.v1.dao-news.index'))
            ->assertOk()->assertJsonPath('data.0.url', $url);
        $this->getJson(route('api.v1.dao-news.manage'))->assertForbidden();
        $this->postJson(route('api.v1.dao-news.update'), [
            'action' => 'remove', 'id' => '00000000-0000-4000-8000-000000000000',
        ])->assertForbidden();
    }

    public function test_mobile_admin_edit_unpublish_reapprove_are_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin, 'sanctum');
        $date = gmdate('Y-m-d');
        $old = 'https://pia.gov.ph/news/visayas/wv/sample-one';
        $new = 'https://pia.gov.ph/news/visayas/wv/sample-two';
        $this->postJson(route('api.v1.dao-news.update'), [
            'action' => 'add', 'url' => $old, 'topic' => 'Local government', 'published' => $date,
            'source_checked' => true, 'dao_relevance_checked' => true,
        ])->assertOk();
        $id = $this->getJson(route('api.v1.dao-news.manage'))->assertOk()->json('entries.0.id');
        $this->postJson(route('api.v1.dao-news.update'), [
            'action' => 'edit', 'id' => $id, 'url' => $new, 'topic' => 'Public safety',
            'published' => $date, 'source_checked' => true, 'dao_relevance_checked' => true,
        ])->assertOk();
        $this->getJson(route('api.v1.dao-news.index'))->assertJsonPath('data.0.url', $new);
        $this->postJson(route('api.v1.dao-news.update'), ['action' => 'unpublish', 'id' => $id])->assertOk();
        $this->getJson(route('api.v1.dao-news.index'))->assertJsonCount(0, 'data');
        $audit = $this->getJson(route('api.v1.dao-news.manage'))->assertOk()->json('audit');
        $this->assertGreaterThanOrEqual(3, count($audit));
        $this->postJson(route('api.v1.dao-news.update'), [
            'action' => 'review', 'id' => $id, 'published' => $date,
            'source_checked' => true, 'dao_relevance_checked' => true,
        ])->assertOk();
        $this->getJson(route('api.v1.dao-news.index'))->assertJsonCount(1, 'data');
    }

    public function test_untrusted_url_and_unverified_submissions_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), 'sanctum');
        $this->postJson(route('api.v1.dao-news.update'), [
            'action' => 'add', 'url' => 'https://evil.example/phishing',
            'topic' => 'Local government', 'published' => gmdate('Y-m-d'),
            'source_checked' => true, 'dao_relevance_checked' => true,
        ])->assertUnprocessable();
        $this->postJson(route('api.v1.dao-news.update'), [
            'action' => 'add', 'url' => 'https://pia.gov.ph/news/sample',
            'topic' => 'Local government', 'published' => gmdate('Y-m-d'),
        ])->assertUnprocessable();
    }
}
