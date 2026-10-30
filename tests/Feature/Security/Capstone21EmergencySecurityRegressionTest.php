<?php

namespace Tests\Feature\Security;

use App\Models\MobileEmergencyAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class Capstone21EmergencySecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function emergency(string $id, string $installation = 'owner-device', string $contact = '09171234567'): array
    {
        return [
            'request_id' => $id,
            'installation_id' => $installation,
            'emergency_details' => 'Need medical assistance at the market',
            'contact_number' => $contact,
            'latitude' => 11.3942,
            'longitude' => 122.6922,
            'location_source' => 'current',
        ];
    }

    public function test_reusing_request_id_cannot_retrieve_the_first_reporters_private_sos_details(): void
    {
        $original = $this->emergency('sos-random-owner-001');
        $this->postJson('/api/v1/emergency/sos', $original)
            ->assertCreated()->assertJsonPath('data.contact_number', '09171234567');

        $attacker = $this->emergency('sos-random-owner-001', 'other-device', '09179999999');
        $attacker['emergency_details'] = 'Different person attempting duplicate';
        $result = $this->postJson('/api/v1/emergency/sos', $attacker)
            ->assertOk()->assertJsonPath('data.received', true);

        foreach (['contact_number', 'latitude', 'longitude', 'emergency_details', 'alert_code', 'display_name'] as $key) {
            $this->assertArrayNotHasKey($key, $result->json('data'));
        }
        $this->assertSame(1, MobileEmergencyAlert::query()->count());
    }

    public function test_reusing_installation_id_with_different_emergency_does_not_suppress_another_report(): void
    {
        $this->postJson('/api/v1/emergency/sos', $this->emergency('one'))
            ->assertCreated();
        $second = $this->emergency('two', 'owner-device', '09178888888');
        $second['emergency_details'] = 'Different accident elsewhere';
        $this->postJson('/api/v1/emergency/sos', $second)->assertCreated();
        $this->assertSame(2, MobileEmergencyAlert::query()->count());
    }

    public function test_repeat_of_same_installation_and_message_returns_private_data_free_receipt(): void
    {
        $this->postJson('/api/v1/emergency/sos', $this->emergency('first'))->assertCreated();
        $again = $this->postJson('/api/v1/emergency/sos', $this->emergency('second'))
            ->assertOk()->assertJsonPath('data.received', true);
        $this->assertArrayNotHasKey('latitude', $again->json('data'));
        $this->assertSame(1, MobileEmergencyAlert::query()->count());
    }

    public function test_another_user_cannot_reenroll_existing_emergency_device(): void
    {
        $owner = User::factory()->create(['role' => 'resident', 'is_active' => true]);
        $other = User::factory()->create(['role' => 'resident', 'is_active' => true]);
        Sanctum::actingAs($owner, ['mobile']);
        $this->postJson('/api/v1/emergency/device/enroll', [
            'installation_id' => 'enrolled-device-001', 'platform' => 'android',
        ])->assertOk();
        Sanctum::actingAs($other, ['mobile']);
        $this->postJson('/api/v1/emergency/device/enroll', [
            'installation_id' => 'enrolled-device-001', 'platform' => 'android',
        ])->assertStatus(409);
    }

    public function test_privileged_development_session_is_disabled_by_default(): void
    {
        config()->set('security.development.admin_bypass_enabled', false);
        $this->postJson('/api/v1/auth/dev-session')->assertNotFound();
    }

    public function test_sanctum_api_tokens_have_a_finite_default_lifetime(): void
    {
        $this->assertGreaterThan(0, (int) config('sanctum.expiration'));
    }
}
