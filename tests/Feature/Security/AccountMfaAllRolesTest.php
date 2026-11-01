<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\AdminTotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Livewire\Volt\Volt as LivewireVolt;
use Tests\TestCase;

class AccountMfaAllRolesTest extends TestCase
{
    use RefreshDatabase;

    private const ROLES = ['admin', 'official', 'tanod', 'resident'];

    public function test_all_active_roles_can_open_the_same_account_enrollment_page(): void
    {
        foreach (self::ROLES as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);
            $this->actingAs($user)->get('/security/account-mfa')
                ->assertOk()->assertSee('Account security');
        }
    }

    public function test_signed_out_users_cannot_enter_mfa_enrollment(): void
    {
        auth()->logout();
        $this->get('/security/account-mfa')->assertRedirect('/login');
    }

    public function test_each_enrolled_role_must_complete_mobile_mfa_before_getting_a_token(): void
    {
        $totp = app(AdminTotpService::class);
        foreach (self::ROLES as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);
            $secret = $totp->newSecret();
            $user->forceFill([
                'mfa_secret' => Crypt::encryptString($secret),
                'mfa_enabled_at' => now(),
                'mfa_last_used_step' => null,
                'mfa_recovery_codes' => $totp->hashRecoveryCodes($totp->createRecoveryCodes()),
            ])->save();

            $login = ['email' => $user->email, 'password' => 'password', 'device_name' => 'Android'];
            $this->postJson('/api/v1/auth/login', $login)
                ->assertStatus(202)->assertJson(['mfa_required' => true])
                ->assertJsonMissingPath('access_token');
            $this->assertSame(0, $user->tokens()->count(), $role);

            $code = $totp->codeAt($secret, time());
            $this->postJson('/api/v1/auth/login', $login + ['mfa_code' => $code])
                ->assertOk()->assertJsonStructure(['access_token']);
            $this->assertSame(1, $user->tokens()->count(), $role);
            $this->assertNotNull($user->tokens()->first()->expires_at, $role);
            $this->postJson('/api/v1/auth/login', $login + ['mfa_code' => $code])
                ->assertStatus(422);
        }
    }

    public function test_non_admin_enrolled_roles_still_require_web_mfa(): void
    {
        $totp = app(AdminTotpService::class);
        foreach (['official', 'tanod', 'resident'] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);
            $user->forceFill([
                'mfa_secret' => Crypt::encryptString($totp->newSecret()),
                'mfa_enabled_at' => now(),
            ])->save();
            LivewireVolt::test('auth.login')
                ->set('email', $user->email)
                ->set('password', 'password')
                ->call('login')
                ->assertSet('awaitingMfa', true);
            $this->assertGuest();
        }
    }

    public function test_each_unenrolled_role_preserves_ordinary_mobile_login(): void
    {
        foreach (self::ROLES as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'password',
                'device_name' => 'Android',
            ])->assertOk()->assertJsonStructure(['access_token']);
        }
    }
}
