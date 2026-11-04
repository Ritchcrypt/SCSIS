<?php

namespace Tests\Unit;

use App\Models\UserNotification;
use App\Services\FirebasePushService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class NotificationAlertTierPolicyTest extends TestCase
{
    public function test_code_red_notification_types_always_use_critical_push_tier(): void
    {
        foreach ([
            'announcement',
            'calamity',
            'mobile_emergency',
            'emergency',
            'incident',
            'incident_reported',
            'incident_message',
            'incident_update',
            'incident_updated',
            'incident_status_update',
            'assigned_incident',
            'incident_assigned',
            'new_assigned_incident',
            'dispatch',
            'escalation',
            'tanod_alert',
            'tanod_task',
            'tanod_task_assigned',
            'task_assigned',
            'tanod_task_update',
            'tanod_task_response',
            'task_update',
        ] as $type) {
            self::assertSame('critical', $this->resolveTier($type), "[$type] must trigger Code Red.");
        }
    }

    public function test_routine_updates_and_account_notifications_use_moderate_or_low(): void
    {
        $expected = [
            'user_registration' => 'moderate',
            'resident_complaint' => 'moderate',
            'community_problem' => 'moderate',
            'account_deactivated' => 'moderate',
            'mobile_emergency_acknowledged' => 'moderate',
            'status_update' => 'low',
            'resolved' => 'low',
            'mobile_emergency_received' => 'low',
            'mobile_emergency_resolved' => 'low',
            'resident_complaint_update' => 'low',
            'resident_complaint_status_update' => 'low',
            'resident_complaint_proof' => 'low',
            'resident_complaint_deleted' => 'low',
            'account_activated' => 'low',
            'community' => 'low',
            'unknown_future_notification' => 'low',
            'case_created' => 'silent',
            'case_updated' => 'silent',
            'case_deleted' => 'silent',
            'system' => 'silent',
        ];

        foreach ($expected as $type => $tier) {
            self::assertSame($tier, $this->resolveTier($type), "Unexpected tier for [$type].");
        }
    }

    private function resolveTier(string $type): string
    {
        $notification = new UserNotification();

        $notification->forceFill([
            'type' => $type,
            'source_id' => null,
        ]);

        $service = new FirebasePushService();

        $method = new ReflectionMethod(
            FirebasePushService::class,
            'resolveAlertTier'
        );

        $method->setAccessible(true);

        return $method->invoke($service, $notification);
    }
}