<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SidebarModuleBadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SidebarModuleBadgeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_activity_badge_counts_new_events_and_clears_only_on_module_visit(): void
    {
        Carbon::setTestNow('2026-11-05 08:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(SidebarModuleBadgeService::class);
        $menu = [['route' => 'admin.activity-logs.index']];

        $this->assertSame(0, $service->counts($admin, $menu)['activity-logs']);

        Carbon::setTestNow('2026-11-05 08:01:00');
        DB::table('activity_logs')->insert([
            'actor_id' => $admin->id,
            'event' => 'sidebar.test',
            'category' => 'test',
            'description' => 'A new activity record.',
            'created_at' => now(),
        ]);

        $this->assertSame(1, $service->counts($admin, $menu)['activity-logs']);
        Carbon::setTestNow('2026-11-05 08:02:00');
        $this->assertSame(0, $service->counts($admin, $menu, 'admin.activity-logs.index', true)['activity-logs']);
    }

    public function test_tanod_alert_badge_is_independent_of_notification_read_state(): void
    {
        Carbon::setTestNow('2026-11-05 08:00:00');
        $tanod = User::factory()->create(['role' => 'tanod']);
        $service = app(SidebarModuleBadgeService::class);
        $menu = [['route' => 'tanod.tanod-alerts.index']];
        $service->counts($tanod, $menu);

        Carbon::setTestNow('2026-11-05 08:01:00');
        $id = DB::table('notifications')->insertGetId([
            'user_id' => $tanod->id,
            'type' => 'incident',
            'title' => 'Test alert',
            'message' => 'New incident',
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(1, $service->counts($tanod, $menu)['tanod-alerts']);
        Carbon::setTestNow('2026-11-05 08:02:00');
        $this->assertSame(0, $service->counts($tanod, $menu, 'tanod.tanod-alerts.index', true)['tanod-alerts']);
        $this->assertSame(0, (int) DB::table('notifications')->where('id', $id)->value('is_read'));
    }

    public function test_role_limited_json_does_not_expose_admin_counts_to_resident(): void
    {
        $resident = User::factory()->create(['role' => 'resident']);
        $notificationsBefore = DB::table('notifications')->orderBy('id')->get()->toArray();

        $response = $this->actingAs($resident)
            ->get(route('sidebar-module-badges.index'));

        $response->assertOk()
            ->assertJsonMissingPath('counts.users')
            ->assertJsonMissingPath('counts.activity-logs');

        // Symfony may reorder cache directives while preserving the meaning.
        $cacheControl = strtolower((string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);

        // Prove this website-only badge request never alters system notices.
        $notificationsAfter = DB::table('notifications')->orderBy('id')->get()->toArray();
        $this->assertEquals($notificationsBefore, $notificationsAfter);
    }

    public function test_livewire_hover_prefetch_does_not_acknowledge_a_module(): void
    {
        Carbon::setTestNow('2026-11-05 08:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(SidebarModuleBadgeService::class);
        $menu = [['route' => 'admin.activity-logs.index']];
        $this->assertSame(0, $service->counts($admin, $menu)['activity-logs']);

        Carbon::setTestNow('2026-11-05 08:01:00');
        DB::table('activity_logs')->insert([
            'actor_id' => $admin->id,
            'event' => 'sidebar.prefetch',
            'category' => 'test',
            'description' => 'Unreviewed event',
            'created_at' => now(),
        ]);

        // Livewire hover-prefetch and actual SPA navigation use this same header.
        request()->headers->set('X-Livewire-Navigate', '');
        $this->assertSame(1, $service->counts($admin, $menu, 'admin.activity-logs.index', true)['activity-logs']);
        request()->headers->remove('X-Livewire-Navigate');

        Carbon::setTestNow('2026-11-05 08:02:00');
        $this->actingAs($admin)
            ->postJson(route('sidebar-module-badges.review'), ['module' => 'activity-logs'])
            ->assertOk()
            ->assertJsonPath('reviewed', true);
        $this->assertSame(0, $service->counts($admin, $menu)['activity-logs']);
    }

    public function test_resident_cannot_review_admin_only_modules(): void
    {
        $resident = User::factory()->create(['role' => 'resident']);
        $this->actingAs($resident)
            ->postJson(route('sidebar-module-badges.review'), ['module' => 'activity-logs'])
            ->assertForbidden();
    }

    public function test_sidebar_badge_json_is_protected_from_guests(): void
    {
        $this->get(route('sidebar-module-badges.index'))
            ->assertRedirect(route('login'));
    }
}
