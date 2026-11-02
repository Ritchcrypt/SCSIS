<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemBrandingLogoFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_branding_logo_returns_not_found_without_a_custom_upload(): void
    {
        SystemSetting::query()->delete();

        $this->assertFileDoesNotExist(
            public_path('images/tabangnow-default-logo.svg')
        );

        $this->get(route('system-branding.logo'))
            ->assertNotFound();
    }

    public function test_public_branding_logo_keeps_serving_an_existing_custom_logo(): void
    {
        Storage::fake('public');

        $logoBytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        );

        Storage::disk('public')->put(
            'system-branding/logo-test.png',
            $logoBytes
        );

        $setting = SystemSetting::query()->firstOrFail();

        $setting->update([
            'system_name' => 'TabangNow',
            'system_subtitle' => 'Dao, Capiz',
            'system_logo_path' => 'system-branding/logo-test.png',
        ]);

        $setting->refresh();

        $this->assertSame(
            'system-branding/logo-test.png',
            $setting->system_logo_path
        );

        $this->assertSame(
            1,
            SystemSetting::query()->count(),
            'System branding must remain a singleton row.'
        );

        Storage::disk('public')->assertExists(
            'system-branding/logo-test.png'
        );

        $this->get(route('system-branding.logo'))
            ->assertOk()
            ->assertHeader(
                'X-Content-Type-Options',
                'nosniff'
            );
    }

    public function test_public_login_and_signup_follow_the_admin_managed_global_logo(): void
    {
        Storage::fake('public');

        $logoBytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        );

        Storage::disk('public')->put(
            'system-branding/login-global-logo.png',
            $logoBytes
        );

        $setting = SystemSetting::query()->firstOrFail();

        $setting->update([
            'system_name' => 'SCSISystem',
            'system_subtitle' => 'Dao, Capiz',
            'system_logo_path' =>
                'system-branding/login-global-logo.png',
        ]);

        $dynamicLogoUrlPrefix = route(
            'system-branding.logo'
        ) . '?v=';

        foreach (['/login', '/register'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee(
                    $dynamicLogoUrlPrefix,
                    false
                )
                ->assertDontSee(
                    'images/tabangnow-favicon-final.svg',
                    false
                )
                ->assertDontSee(
                    'tabangnow-tab-icon-v7.png?v=12',
                    false
                )
                ->assertDontSee(
                    'tabangnow-tab-icon-v7.png?v=13',
                    false
                )
                ->assertSee(
                    '<link rel="icon" href="' . route('system-branding.logo') . '?v=',
                    false
                )
                ->assertDontSee(
                    'Secure community access',
                    false
                );
        }
    }

    public function test_authenticated_profile_and_mfa_use_the_same_uploaded_system_logo_as_public_auth(): void
    {
        Storage::fake('public');

        $logoBytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        );

        Storage::disk('public')->put('system-branding/parity.png', $logoBytes);

        $setting = SystemSetting::query()->firstOrFail();
        $setting->update(['system_logo_path' => 'system-branding/parity.png']);

        $logoUrl = route('system-branding.logo') . '?v='
            . optional($setting->updated_at)->timestamp;

        $user = \App\Models\User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        foreach (['/profile', '/security/account-mfa'] as $path) {
            $this->actingAs($user)
                ->get($path)
                ->assertOk()
                ->assertSee('<link rel="icon" href="' . $logoUrl . '">', false)
                ->assertSee('<link rel="shortcut icon" href="' . $logoUrl . '">', false)
                ->assertDontSee('tabangnow-tab-icon-v7.png?v=13', false);
        }
    }

    public function test_public_auth_pages_do_not_restore_a_legacy_logo_when_admin_has_no_logo(): void
    {
        $setting = SystemSetting::query()->firstOrFail();

        $setting->update([
            'system_logo_path' => null,
        ]);

        foreach (['/login', '/register'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertDontSee(
                    'images/tabangnow-favicon-final.svg',
                    false
                )
                ->assertSee(
                    'tabangnow-tab-icon-v7.png?v=13',
                    false
                )
                ->assertDontSee(
                    route('system-branding.logo') . '?v=',
                    false
                )
                ->assertDontSee(
                    'Secure community access',
                    false
                );
        }
    }
}