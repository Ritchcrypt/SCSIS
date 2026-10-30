<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\AdminTotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountMfaMobileEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private function mobileToken(User $user): string
    {
        return $user->createToken('Android', ['mobile'], now()->addDays(30))->plainTextToken;
    }

    public function test_all_roles_can_start_mobile_enrollment_and_must_confirm_totp(): void
    {
        $totp = app(AdminTotpService::class);
        foreach (['admin', 'official', 'tanod', 'resident'] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);
            $token = $this->mobileToken($user);
            $this->withToken($token)->getJson('/api/v1/profile/mfa/status')
                ->assertOk()->assertJson(['enabled' => false]);
            $this->withToken($token)->postJson('/api/v1/profile/mfa/begin', ['password' => 'incorrect'])
                ->assertUnprocessable();
            $start = $this->withToken($token)->postJson('/api/v1/profile/mfa/begin', ['password' => 'password'])
                ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
                ->json();
            $secret = $start['secret'];
            $this->assertStringStartsWith('otpauth://totp/', $start['provisioning_uri']);
            $this->withToken($token)->postJson('/api/v1/profile/mfa/confirm', ['code' => '000ABC'])
                ->assertUnprocessable();
            $result = $this->withToken($token)->postJson('/api/v1/profile/mfa/confirm', [
                'code' => $totp->codeAt($secret, time()),
            ])->assertOk()->assertJson(['enabled' => true])->json();
            $this->assertCount(8, $result['recovery_codes']);
            $this->assertNotEmpty($result['access_token']);
            $this->withToken($token)->getJson('/api/v1/profile/mfa/status')
                ->assertUnauthorized(); // Pre-MFA tokens must be revoked.
            $this->withToken($result['access_token'])->getJson('/api/v1/profile/mfa/status')
                ->assertOk()->assertJson(['enabled' => true, 'recovery_codes_remaining' => 8]);
        }
    }

    public function test_mobile_recovery_rotation_and_disable_need_password_and_second_factor(): void
    {
        $user = User::factory()->create(['role' => 'resident', 'is_active' => true]);
        $token = $this->mobileToken($user);
        $totp = app(AdminTotpService::class);
        $start = $this->withToken($token)->postJson('/api/v1/profile/mfa/begin', [
            'password' => 'password',
        ])->assertOk()->json();
        $confirmed = $this->withToken($token)->postJson('/api/v1/profile/mfa/confirm', [
            'code' => $totp->codeAt($start['secret'], time()),
        ])->assertOk()->json();
        $token = $confirmed['access_token'];
        $oldCodes = $confirmed['recovery_codes'];
        $this->withToken($token)->postJson('/api/v1/profile/mfa/recovery', [
            'password' => 'bad-password', 'code' => $oldCodes[0],
        ])->assertUnprocessable();
        $fresh = $this->withToken($token)->postJson('/api/v1/profile/mfa/recovery', [
            'password' => 'password', 'code' => $oldCodes[0],
        ])->assertOk()->json();
        $this->assertCount(8, $fresh['recovery_codes']);
        $this->withToken($token)->postJson('/api/v1/profile/mfa/disable', [
            'password' => 'password', 'code' => $oldCodes[1],
        ])->assertUnprocessable();
        $off = $this->withToken($token)->postJson('/api/v1/profile/mfa/disable', [
            'password' => 'password', 'code' => $fresh['recovery_codes'][0],
        ])->assertOk()->assertJson(['enabled' => false])->json();
        $this->assertNotEmpty($off['access_token']);
        $this->withToken($token)->getJson('/api/v1/profile/mfa/status')->assertUnauthorized();
        $this->withToken($off['access_token'])->getJson('/api/v1/profile/mfa/status')
            ->assertOk()->assertJson(['enabled' => false]);
    }

    public function test_every_role_has_mfa_entry_point_in_their_website_profile(): void
    {
        foreach (['admin', 'official', 'tanod', 'resident'] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);
            $this->actingAs($user)->get('/profile')->assertOk()
                ->assertSee('Account Security')
                ->assertSee('Enable Two-Factor Authentication');
        }
    }

    public function test_another_mobile_device_cannot_complete_the_first_devices_enrollment(): void
    {
        $user = User::factory()->create(['role' => 'resident', 'is_active' => true]);
        $firstDevice = $this->mobileToken($user);
        $secondDevice = $this->mobileToken($user);
        $begin = $this->withToken($firstDevice)->postJson('/api/v1/profile/mfa/begin', [
            'password' => 'password',
        ])->assertOk()->json();

        $this->withToken($secondDevice)->postJson('/api/v1/profile/mfa/confirm', [
            'code' => app(AdminTotpService::class)->codeAt($begin['secret'], time()),
        ])->assertUnprocessable();

        $this->withToken($firstDevice)->getJson('/api/v1/profile/mfa/status')
            ->assertOk()->assertJson(['enabled' => false]);
    }

    public function test_unauthenticated_devices_cannot_manage_mfa(): void
    {
        $this->postJson('/api/v1/profile/mfa/begin', ['password' => 'password'])
            ->assertUnauthorized();
    }
}
