<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProfileBarangayThemeStabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_website_profile_shows_and_updates_residential_barangay(): void
    {
        $oldBarangay = $this->barangay('Old');
        $newBarangay = $this->barangay('New');

        $user = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
            'email_verified_at' => now(),
            'barangay_id' => $oldBarangay->id,
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Barangay of Residence')
            ->assertSee($oldBarangay->barangay_name)
            ->assertSee($newBarangay->barangay_name);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'contact_number' => '09123456789',
                'address' => 'Dao, Capiz',
                'barangay_id' => $newBarangay->id,
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'barangay_id' => $newBarangay->id,
        ]);
    }

    public function test_mobile_login_payload_contains_saved_theme_before_home_render(): void
    {
        $password = 'TabangNow#2026Theme';

        $user = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
            'email_verified_at' => now(),
            'password' => Hash::make($password),
            'theme_mode' => 'custom',
            'theme_custom_color' => '#7c3aed',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $password,
            'device_name' => 'Patch 10 Test',
        ])
            ->assertOk()
            ->assertJsonPath(
                'user.theme_mode',
                'custom'
            )
            ->assertJsonPath(
                'user.theme_custom_color',
                '#7c3aed'
            );
    }

    public function test_public_website_authentication_forces_light_prepaint(): void
    {
        $source = file_get_contents(
            resource_path(
                'views/components/layouts/auth.blade.php'
            )
        );

        $this->assertIsString($source);

        $this->assertStringContainsString(
            'TABANGNOW_PUBLIC_AUTH_LIGHT_PREPAINT',
            $source
        );

        $this->assertStringContainsString(
            "root.dataset.theme = 'light';",
            $source
        );
    }

    public function test_authenticated_layout_starts_with_saved_theme(): void
    {
        $user = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
            'email_verified_at' => now(),
            'theme_mode' => 'custom',
            'theme_custom_color' => '#7c3aed',
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee(
                'data-theme="custom"',
                false
            )
            ->assertSee(
                '--tn-accent: #7c3aed',
                false
            );
    }

    private function barangay(string $label): Barangay
    {
        return Barangay::query()->create([
            'barangay_name' =>
                'Patch10 '
                . $label
                . ' '
                . Str::uuid(),
            'location' => 'Dao, Capiz',
        ]);
    }
}