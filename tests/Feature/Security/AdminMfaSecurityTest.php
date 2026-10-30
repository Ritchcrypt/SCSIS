<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\AdminTotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Livewire\Volt\Volt as LivewireVolt;
use Tests\TestCase;

class AdminMfaSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function enrolledAdmin(): array
    {
        $totp = app(AdminTotpService::class);
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $secret = $totp->newSecret();
        $user->forceFill([
            'mfa_secret' => Crypt::encryptString($secret),
            'mfa_last_used_step' => null,
            'mfa_recovery_codes' => $totp->hashRecoveryCodes($totp->createRecoveryCodes()),
            'mfa_enabled_at' => now(),
        ])->save();
        return [$user, $secret];
    }

    public function test_totp_matches_rfc_6238_standard_vector(): void
    {
        // RFC 6238, T=59s, SHA1, reduced to six digits.
        $this->assertSame('287082', app(AdminTotpService::class)
            ->codeAt('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 59));
    }

    public function test_mobile_admin_login_requires_mfa_before_issuing_token(): void
    {
        [$user, $secret] = $this->enrolledAdmin();
        $login = ['email' => $user->email, 'password' => 'password', 'device_name' => 'Android'];
        $this->postJson('/api/v1/auth/login', $login)
            ->assertStatus(202)->assertJson(['mfa_required' => true])->assertJsonMissingPath('access_token');
        $this->assertSame(0, $user->tokens()->count());

        $code = app(AdminTotpService::class)->codeAt($secret, time());
        $this->postJson('/api/v1/auth/login', $login + ['mfa_code' => '000ABC'])
            ->assertStatus(422);
        $this->postJson('/api/v1/auth/login', $login + ['mfa_code' => $code])
            ->assertOk()->assertJsonStructure(['access_token', 'user']);
        $this->assertSame(1, $user->tokens()->count());
        $this->assertNotNull($user->tokens()->first()->expires_at);
        $this->postJson('/api/v1/auth/login', $login + ['mfa_code' => $code])
            ->assertStatus(422);
    }

    public function test_web_admin_login_does_not_authenticate_until_second_factor(): void
    {
        [$user, $secret] = $this->enrolledAdmin();
        LivewireVolt::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertSet('awaitingMfa', true);
        $this->assertGuest();
    }

    public function test_admin_recovery_code_cannot_be_reused(): void
    {
        [$user] = $this->enrolledAdmin();
        $recovery = app(AdminTotpService::class)->createRecoveryCodes();
        $user->forceFill([
            'mfa_recovery_codes' => app(AdminTotpService::class)->hashRecoveryCodes($recovery),
        ])->save();
        $totp = app(AdminTotpService::class);
        $this->assertTrue($totp->verifyAndConsume($user, $recovery[0]));
        $this->assertFalse($totp->verifyAndConsume($user, $recovery[0]));
    }

    public function test_non_admin_login_remains_compatible_and_tokens_expire(): void
    {
        $user = User::factory()->create(['role' => 'resident', 'is_active' => true]);
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'password', 'device_name' => 'Android',
        ])->assertOk()->assertJsonStructure(['access_token', 'user']);
        $this->assertNotNull($user->tokens()->first()->expires_at);
    }
}
