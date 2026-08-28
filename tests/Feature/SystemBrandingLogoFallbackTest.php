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

    public function test_public_login_and_signup_use_the_permanent_bundled_shield_logo(): void
    {
        $this->assertFileExists(
            public_path('images/tabangnow-favicon-final.svg')
        );

        $shieldLogoUrl = asset(
            'images/tabangnow-favicon-final.svg'
        );

        $this->get('/login')
            ->assertOk()
            ->assertSee(
                $shieldLogoUrl,
                false
            )
            ->assertDontSee(
                'Secure community access',
                false
            );

        $this->get('/register')
            ->assertOk()
            ->assertSee(
                $shieldLogoUrl,
                false
            )
            ->assertDontSee(
                'Secure community access',
                false
            );
    }
}