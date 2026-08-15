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
            'emergency_details' => 'May taong nahimatay at kailangan ng agarang tulong.',
            'contact_number' => '+63 912 345 6789',
            'latitude' => 11.3935000,
            'longitude' => 122.6851000,
            'accuracy_meters' => 12.5,
            'location_source' => 'current',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.identified', false)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.contact_number', '09123456789')
            ->assertJsonPath('data.location_source', 'current');

        $alert = MobileEmergencyAlert::query()->firstOrFail();

        $this->assertNull($alert->user_id);
        $this->assertSame('test-installation-anonymous-001', $alert->installation_id);
        $this->assertSame('09123456789', $alert->contact_number);
        $this->assertSame('May taong nahimatay at kailangan ng agarang tulong.', $alert->emergency_details);
        $this->assertSame('current', $alert->location_source);
        $this->assertSame(11.3935, $alert->latitude);
        $this->assertSame(122.6851, $alert->longitude);

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
            'emergency_details' => 'May aksidente sa kalsada at may nasugatan.',
            'contact_number' => '09181234567',
            'latitude' => 11.3951000,
            'longitude' => 122.6829000,
            'accuracy_meters' => 35,
            'location_source' => 'last_known',
        ]);

        $sos
            ->assertCreated()
            ->assertJsonPath('data.identified', true)
            ->assertJsonPath('data.display_name', $tanod->name)
            ->assertJsonPath('data.location_source', 'last_known');

        $alert = MobileEmergencyAlert::query()->firstOrFail();

        $this->assertSame($tanod->id, $alert->user_id);
        $this->assertSame('09181234567', $alert->contact_number);
        $this->assertSame('last_known', $alert->location_source);
    }

    public function test_only_admin_and_official_can_manage_emergency_alerts(): void
    {
        $alert = MobileEmergencyAlert::query()->create([
            'alert_code' => 'SOS-TEST-000001',
            'installation_id' => 'test-installation-management',
            'request_id' => 'test-management-sos-001',
            'status' => 'active',
            'emergency_details' => 'Test emergency',
            'contact_number' => '09171234567',
            'latitude' => 11.3900000,
            'longitude' => 122.6800000,
            'location_source' => 'current',
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

    public function test_mobile_sos_module_is_available_only_to_admin_and_official(): void
    {
        $alert = MobileEmergencyAlert::query()->create([
            'alert_code' => 'SOS-TEST-000002',
            'installation_id' => 'test-installation-module',
            'request_id' => 'test-module-sos-001',
            'status' => 'active',
            'emergency_details' => 'Kailangan ng tulong sa bahay dahil may nasugatan.',
            'contact_number' => '09175551234',
            'latitude' => 11.3910000,
            'longitude' => 122.6810000,
            'location_source' => 'current',
            'source' => 'mobile',
            'triggered_at' => now(),
        ]);

        $resident = $this->activeUser('resident');

        $this->actingAs($resident)
            ->get('/admin/mobile-sos')
            ->assertForbidden();

        $admin = $this->activeUser('admin');

        $this->actingAs($admin)
            ->get('/admin/mobile-sos')
            ->assertOk()
            ->assertSee('Mobile SOS')
            ->assertSee($alert->alert_code);

        $official = $this->activeUser('official');

        $this->actingAs($official)
            ->get('/official/mobile-sos')
            ->assertOk()
            ->assertSee('Mobile SOS')
            ->assertSee($alert->alert_code);
    }

    public function test_notification_pulse_keeps_mobile_sos_visible_when_a_newer_ordinary_notification_exists(): void
    {
        $admin = $this->activeUser('admin');

        $emergencyNotification = UserNotification::query()->create([
            'user_id' => $admin->id,
            'type' => 'mobile_emergency',
            'source_id' => 7001,
            'title' => 'Emergency SOS',
            'message' => 'A mobile SOS was triggered.',
            'is_read' => false,
        ]);

        $ordinaryNotification = UserNotification::query()->create([
            'user_id' => $admin->id,
            'type' => 'announcement',
            'source_id' => 7002,
            'title' => 'Barangay announcement',
            'message' => 'This notification arrived after the SOS.',
            'is_read' => false,
        ]);

        $this->assertGreaterThan(
            $emergencyNotification->id,
            $ordinaryNotification->id
        );

        $response = $this
            ->actingAs($admin)
            ->getJson('/notifications/pulse');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.latest_notification_id',
                $ordinaryNotification->id
            )
            ->assertJsonPath(
                'data.notification.type',
                'announcement'
            )
            ->assertJsonPath(
                'data.latest_emergency_notification_id',
                $emergencyNotification->id
            )
            ->assertJsonPath(
                'data.emergency_notification.type',
                'mobile_emergency'
            );
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
