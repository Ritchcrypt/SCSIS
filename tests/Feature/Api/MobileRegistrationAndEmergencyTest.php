<?php

namespace Tests\Feature\Api;

use App\Models\MobileEmergencyAlert;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileRegistrationAndEmergencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_registration_creates_pending_resident_and_notifies_admin(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'status' => true,
        ]);

        RateLimiter::clear('registration|127.0.0.1');

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Mobile Resident',
            'email' => 'mobile.resident@example.com',
            'contact_number' => '+63 912 345 6789',
            'address' => 'Poblacion, Dao, Capiz',
            'password' => 'TabangNow#2026Secure',
            'password_confirmation' => 'TabangNow#2026Secure',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('approval_required', true)
            ->assertJsonPath('user.role', 'resident');

        $resident = User::query()
            ->where('email', 'mobile.resident@example.com')
            ->firstOrFail();

        $this->assertFalse((bool) $resident->is_active);
        $this->assertFalse((bool) $resident->status);
        $this->assertSame('09123456789', $resident->contact_number);

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $admin->id)
                ->where('type', 'user_registration')
                ->where('source_id', $resident->id)
                ->exists()
        );
    }

    public function test_anonymous_sos_works_without_login_and_notifies_admin_and_official_only(): void
    {
        $admin = $this->activeUser('admin');
        $official = $this->activeUser('official');
        $tanod = $this->activeUser('tanod');
        $resident = $this->activeUser('resident');

        $response = $this->postJson('/api/v1/emergency/sos', [
            'request_id' => 'test-anonymous-sos-001',
            'installation_id' => 'test-installation-anonymous-001',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.identified', false)
            ->assertJsonPath('data.status', 'active');

        $alert = MobileEmergencyAlert::query()->firstOrFail();

        $this->assertNull($alert->user_id);
        $this->assertSame('test-installation-anonymous-001', $alert->installation_id);

        foreach ([$admin, $official] as $recipient) {
            $this->assertTrue(
                UserNotification::query()
                    ->where('user_id', $recipient->id)
                    ->where('type', 'mobile_emergency')
                    ->where('source_id', $alert->id)
                    ->exists()
            );
        }

        foreach ([$tanod, $resident] as $nonRecipient) {
            $this->assertFalse(
                UserNotification::query()
                    ->where('user_id', $nonRecipient->id)
                    ->where('type', 'mobile_emergency')
                    ->where('source_id', $alert->id)
                    ->exists()
            );
        }
    }

    public function test_any_authenticated_role_can_enroll_device_and_sos_can_identify_that_user_without_login(): void
    {
        $this->activeUser('admin');
        $this->activeUser('official');
        $tanod = $this->activeUser('tanod');

        Sanctum::actingAs($tanod, ['mobile']);

        $enrollment = $this->postJson('/api/v1/emergency/device/enroll', [
            'installation_id' => 'test-tanod-installation-001',
            'device_name' => 'Test Android',
            'platform' => 'android',
        ]);

        $enrollment
            ->assertOk()
            ->assertJsonPath('data.linked_user.id', $tanod->id)
            ->assertJsonPath('data.linked_user.role', 'tanod');

        $emergencyToken = (string) $enrollment->json('data.emergency_token');

        $this->assertNotSame('', $emergencyToken);

        /*
         * Clear authentication to prove the actual SOS call does not depend on
         * a normal account session or Sanctum bearer token.
         */
        app('auth')->forgetGuards();

        $sos = $this->postJson('/api/v1/emergency/sos', [
            'request_id' => 'test-linked-sos-001',
            'installation_id' => 'test-tanod-installation-001',
            'emergency_token' => $emergencyToken,
        ]);

        $sos
            ->assertCreated()
            ->assertJsonPath('data.identified', true)
            ->assertJsonPath('data.display_name', $tanod->name);

        $alert = MobileEmergencyAlert::query()->firstOrFail();

        $this->assertSame($tanod->id, $alert->user_id);
    }

    public function test_only_admin_and_official_can_manage_emergency_alerts(): void
    {
        $alert = MobileEmergencyAlert::query()->create([
            'alert_code' => 'SOS-TEST-000001',
            'installation_id' => 'test-installation-management',
            'request_id' => 'test-management-sos-001',
            'status' => 'active',
            'source' => 'mobile',
            'triggered_at' => now(),
        ]);

        $resident = $this->activeUser('resident');
        Sanctum::actingAs($resident, ['mobile']);

        $this->patchJson("/api/v1/emergency-alerts/{$alert->id}/acknowledge")
            ->assertForbidden();

        $official = $this->activeUser('official');
        Sanctum::actingAs($official, ['mobile']);

        $this->patchJson("/api/v1/emergency-alerts/{$alert->id}/acknowledge")
            ->assertOk()
            ->assertJsonPath('data.status', 'acknowledged');

        $admin = $this->activeUser('admin');
        Sanctum::actingAs($admin, ['mobile']);

        $this->patchJson("/api/v1/emergency-alerts/{$alert->id}/resolve")
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');
    }

    private function activeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'status' => true,
        ]);
    }
}
