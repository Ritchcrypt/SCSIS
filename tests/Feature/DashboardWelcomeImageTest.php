<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class DashboardWelcomeImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_upload_and_every_role_receives_the_same_shared_version(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $official = User::factory()->create(['role' => 'official', 'is_active' => true]);
        $resident = User::factory()->create(['role' => 'resident', 'is_active' => true]);

        $original = $this->actingAs($resident)
            ->get(route('dashboard-welcome.status'))
            ->assertOk()
            ->json('version');

        $this->actingAs($resident)->post(route('admin.dashboard-welcome-image.update'), [
            'welcome_image' => UploadedFile::fake()->image('nope.png', 300, 120),
        ])->assertForbidden();

        $this->actingAs($admin)->post(route('admin.dashboard-welcome-image.update'), [
            'welcome_image' => UploadedFile::fake()->image('banner.jpg', 1600, 600),
        ])->assertRedirect(route('admin.dashboard'));

        $path = SystemSetting::query()->firstOrFail()->dashboard_welcome_path;
        $this->assertStringStartsWith('dashboard-welcome/', $path);
        Storage::disk('local')->assertExists($path);

        $adminVersion = $this->actingAs($admin)->get(route('dashboard-welcome.status'))->assertOk()->json('version');
        $officialVersion = $this->actingAs($official)->get(route('dashboard-welcome.status'))->assertOk()->json('version');
        $residentVersion = $this->actingAs($resident)->get(route('dashboard-welcome.status'))->assertOk()->json('version');

        $this->assertNotSame($original, $adminVersion);
        $this->assertSame($adminVersion, $officialVersion);
        $this->assertSame($officialVersion, $residentVersion);
        $this->actingAs($resident)->get(route('dashboard-welcome.image'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_invalid_upload_does_not_replace_existing_image(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->post(route('admin.dashboard-welcome-image.update'), [
            'welcome_image' => UploadedFile::fake()->create('script.php', 1, 'application/x-php'),
        ])->assertSessionHasErrors('welcome_image');
        $this->assertNull(SystemSetting::query()->first()?->dashboard_welcome_path);
    }
}
