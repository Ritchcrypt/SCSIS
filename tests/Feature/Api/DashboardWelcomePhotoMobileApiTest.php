<?php

namespace Tests\Feature\Api;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class DashboardWelcomePhotoMobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_replace_shared_image_via_bearer_api(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $resident = User::factory()->create(['role' => 'resident', 'is_active' => true]);

        $this->getJson(route('api.v1.dashboard-welcome.status'))->assertUnauthorized();
        $this->actingAs($resident, 'sanctum')->getJson(route('api.v1.dashboard-welcome.status'))->assertOk();
        $this->actingAs($resident, 'sanctum')->postJson(route('api.v1.dashboard-welcome.update'), [
            'welcome_image' => UploadedFile::fake()->image('no.png', 400, 100),
        ])->assertForbidden();

        $before = $this->actingAs($admin, 'sanctum')->getJson(route('api.v1.dashboard-welcome.status'))
            ->assertOk()->json('version');
        $this->actingAs($admin, 'sanctum')->post(route('api.v1.dashboard-welcome.update'), [
            'welcome_image' => UploadedFile::fake()->image('banner.jpg', 1200, 350),
        ], ['Accept' => 'application/json'])->assertOk();
        $path = SystemSetting::query()->firstOrFail()->dashboard_welcome_path;
        $this->assertStringStartsWith('dashboard-welcome/', $path);
        Storage::disk('local')->assertExists($path);
        $after = $this->actingAs($resident, 'sanctum')->getJson(route('api.v1.dashboard-welcome.status'))
            ->assertOk()->json('version');
        $this->assertNotSame($before, $after);
        $this->actingAs($resident, 'sanctum')->get(route('api.v1.dashboard-welcome.image'))
            ->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
