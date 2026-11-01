<?php

namespace Tests\Feature\Auth;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class AuthBackgroundAdminUploadTest extends TestCase
{
    use RefreshDatabase;

    private function picture(): UploadedFile
    {
        // Small real PNG avoids a GD dependency in test fixtures.
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $path = tempnam(sys_get_temp_dir(), 'tn-background-');
        file_put_contents($path, $bytes);
        return new UploadedFile($path, 'background.png', 'image/png', null, true);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    public function test_fallback_background_works_before_any_upload(): void
    {
        foreach (['/login', '/register', '/forgot-password'] as $route) {
            $this->get($route)->assertOk()
                ->assertSee(route('auth-background.image', [], false), false);
        }
        $this->get(route('auth-background.image'))
            ->assertRedirect(asset('images/auth/dao-auth-photo-20261022_181835_038.jpg'));
    }

    public function test_administrator_can_upload_one_shared_auth_background_and_reset_to_default(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->post(route('admin.system-branding.auth-background.update'), [
            'auth_background' => $this->picture(),
        ])->assertRedirect(route('admin.system-branding.edit'));

        $setting = SystemSetting::query()->firstOrFail();
        $path = $setting->auth_background_path;
        $this->assertMatchesRegularExpression('/\Aauth-background\/[a-f0-9-]{36}\.png\z/', $path);
        Storage::disk('local')->assertExists($path);

        // Auth pages use guest middleware; the administrator must sign out
        // before we can test their actual public content instead of a 302.
        auth()->logout();
        $this->assertGuest();

        foreach (['/login', '/register', '/forgot-password'] as $route) {
            // Compare the route's stable path: Laravel test requests can use
            // a different host than APP_URL (localhost vs 127.0.0.1).
            $this->get($route)->assertOk()
                ->assertSee(route('auth-background.image', [], false), false);
        }

        $this->get(route('auth-background.image'))->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        // Sign back in to exercise the admin-only reset endpoint.
        $this->actingAs($admin)->delete(route('admin.system-branding.auth-background.reset'))
            ->assertRedirect(route('admin.system-branding.edit'));
        $this->assertNull($setting->fresh()->auth_background_path);
        Storage::disk('local')->assertMissing($path);

        // Assert guest fallback after the admin performs the reset.
        auth()->logout();
        $this->assertGuest();
        $this->get('/login')->assertOk()
            ->assertSee(route('auth-background.image', [], false), false);
        $this->get(route('auth-background.image'))
            ->assertRedirect(asset('images/auth/dao-auth-photo-20261022_181835_038.jpg'));
    }

    public function test_non_admin_cannot_upload_or_reset_background(): void
    {
        $resident = User::factory()->create(['role' => 'resident', 'is_active' => true]);
        $this->actingAs($resident)->post(route('admin.system-branding.auth-background.update'), [
            'auth_background' => $this->picture(),
        ])->assertForbidden();
        $this->delete(route('admin.system-branding.auth-background.reset'))->assertForbidden();
    }

    public function test_invalid_upload_is_rejected_and_does_not_update_setting(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin())->post(route('admin.system-branding.auth-background.update'), [
            'auth_background' => UploadedFile::fake()->create('background.svg', 1, 'image/svg+xml'),
        ])->assertSessionHasErrors('auth_background');
        $this->assertNull(SystemSetting::query()->firstOrFail()->auth_background_path);
    }
}
