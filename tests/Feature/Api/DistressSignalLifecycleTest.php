<?php

namespace Tests\Feature\Api;

use App\Models\MobileEmergencyAlert;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DistressSignalLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_linked_sender_receives_received_acknowledged_and_resolved_notifications(): void
    {
        $sender = $this->activeUser('resident');
        $official = $this->activeUser('official');

        $alert = $this->alertFor($sender, 'SOS-LIFECYCLE-001');

        $this->assertNotification($sender, $alert, 'mobile_emergency_received');

        Sanctum::actingAs($official, ['mobile']);

        $this->patchJson(
            "/api/v1/emergency-alerts/{$alert->id}/acknowledge"
        )->assertOk()->assertJsonPath('data.status', 'acknowledged');

        $this->assertNotification(
            $sender,
            $alert,
            'mobile_emergency_acknowledged'
        );

        $this->patchJson(
            "/api/v1/emergency-alerts/{$alert->id}/resolve"
        )->assertOk()->assertJsonPath('data.status', 'resolved');

        $this->assertNotification(
            $sender,
            $alert,
            'mobile_emergency_resolved'
        );
    }

    public function test_direct_resolve_from_active_notifies_sender_of_acknowledgement_and_resolution(): void
    {
        $sender = $this->activeUser('tanod');
        $admin = $this->activeUser('admin');

        $alert = $this->alertFor($sender, 'SOS-LIFECYCLE-002');

        Sanctum::actingAs($admin, ['mobile']);

        $this->patchJson(
            "/api/v1/emergency-alerts/{$alert->id}/resolve"
        )->assertOk()->assertJsonPath('data.status', 'resolved');

        $this->assertNotification(
            $sender,
            $alert,
            'mobile_emergency_acknowledged'
        );
        $this->assertNotification(
            $sender,
            $alert,
            'mobile_emergency_resolved'
        );
    }

    public function test_admin_and_official_can_delete_reports_and_resident_cannot(): void
    {
        $resident = $this->activeUser('resident');
        $admin = $this->activeUser('admin');
        $official = $this->activeUser('official');

        $first = $this->alertFor($resident, 'SOS-DELETE-001');

        Sanctum::actingAs($resident, ['mobile']);
        $this->deleteJson("/api/v1/emergency-alerts/{$first->id}")
            ->assertForbidden();

        Sanctum::actingAs($official, ['mobile']);
        $this->deleteJson("/api/v1/emergency-alerts/{$first->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted_count', 1);

        $this->assertDatabaseMissing(
            'mobile_emergency_alerts',
            ['id' => $first->id]
        );

        $this->alertFor($resident, 'SOS-DELETE-002');
        $this->alertFor($resident, 'SOS-DELETE-003');

        Sanctum::actingAs($admin, ['mobile']);
        $this->deleteJson('/api/v1/emergency-alerts')
            ->assertOk()
            ->assertJsonPath('data.deleted_count', 2);

        $this->assertDatabaseCount('mobile_emergency_alerts', 0);
    }

    public function test_deleting_alert_removes_stale_responder_notification_but_preserves_sender_history(): void
    {
        $sender = $this->activeUser('resident');
        $admin = $this->activeUser('admin');

        $alert = $this->alertFor($sender, 'SOS-DELETE-004');

        UserNotification::query()->create([
            'user_id' => $admin->id,
            'type' => 'mobile_emergency',
            'source_id' => $alert->id,
            'title' => 'URGENT: Distress Signal',
            'message' => 'Responder notification.',
            'is_read' => false,
        ]);

        Sanctum::actingAs($admin, ['mobile']);
        $this->deleteJson("/api/v1/emergency-alerts/{$alert->id}")
            ->assertOk();

        $this->assertFalse(
            UserNotification::query()
                ->where('user_id', $admin->id)
                ->where('type', 'mobile_emergency')
                ->where('source_id', $alert->id)
                ->exists()
        );

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $sender->id)
                ->where('type', 'mobile_emergency_received')
                ->where('source_id', $alert->id)
                ->exists()
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

    private function alertFor(User $sender, string $code): MobileEmergencyAlert
    {
        return MobileEmergencyAlert::query()->create([
            'alert_code' => $code,
            'user_id' => $sender->id,
            'installation_id' => 'install-' . strtolower($code),
            'request_id' => 'request-' . strtolower($code),
            'status' => 'active',
            'emergency_details' => 'Lifecycle test emergency.',
            'contact_number' => '09171234567',
            'latitude' => 11.3900000,
            'longitude' => 122.6800000,
            'location_source' => 'current',
            'source' => 'mobile',
            'triggered_at' => now(),
        ]);
    }

    private function assertNotification(
        User $sender,
        MobileEmergencyAlert $alert,
        string $type
    ): void {
        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $sender->id)
                ->where('type', $type)
                ->where('source_id', $alert->id)
                ->exists(),
            "Expected notification type {$type} for sender."
        );
    }
}