<?php

namespace Tests\Unit;

use App\Models\UserNotification;
use App\Services\FirebasePushService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class NotificationAlertTierPolicyTest extends TestCase
{
    public function test_known_notification_types_have_expected_alert_tiers(): void
    {
        $expected = [
            // CRITICAL: immediate danger / public emergency.
            'mobile_emergency' => 'critical',
            'emergency' => 'critical',
            'calamity' => 'critical',

            // MODERATE: new actionable human-facing work/messages.
            'user_registration' => 'moderate',
            'resident_complaint' => 'moderate',
            'incident_message' => 'moderate',
            'tanod_alert' => 'moderate',
            'community_problem' => 'moderate',
            'account_deactivated' => 'moderate',
            'mobile_emergency_acknowledged' => 'moderate',

            // LOW: routine progress/status/information.
            'incident_update' => 'low',
            'incident_updated' => 'low',
            'incident_status_update' => 'low',
            'status_update' => 'low',
            'resolved' => 'low',
            'mobile_emergency_received' => 'low',
            'mobile_emergency_resolved' => 'low',
            'resident_complaint_update' => 'low',
            'resident_complaint_status_update' => 'low',
            'resident_complaint_proof' => 'low',
            'resident_complaint_deleted' => 'low',
            'account_activated' => 'low',
            'tanod_task_update' => 'low',
            'task_update' => 'low',
            'community' => 'low',

            // SILENT: internal administrative/system activity.
            'case_created' => 'silent',
            'case_updated' => 'silent',
            'case_deleted' => 'silent',
            'system' => 'silent',
        ];

        foreach ($expected as $type => $tier) {
            self::assertSame(
                $tier,
                $this->resolveTier($type),
                "Unexpected TabangNow alert tier for [$type]."
            );
        }
    }

    public function test_source_dependent_notifications_have_safe_defaults(): void
    {
        $expected = [
            // Announcement with no source record is informational.
            'announcement' => 'low',

            // New incident reports normally need attention.
            'incident' => 'moderate',
            'incident_reported' => 'moderate',

            // Assignments/actions normally require attention.
            'assigned_incident' => 'moderate',
            'incident_assigned' => 'moderate',
            'new_assigned_incident' => 'moderate',
            'dispatch' => 'moderate',
            'escalation' => 'moderate',

            // Tanod Task assignments trigger Code Red.
            'tanod_task' => 'critical',
            'tanod_task_assigned' => 'critical',
            'task_assigned' => 'critical',

            // Future unknown informational notifications must never
            // accidentally become Code Red.
            'unknown_future_notification' => 'low',
        ];

        foreach ($expected as $type => $tier) {
            self::assertSame(
                $tier,
                $this->resolveTier($type),
                "Unexpected default TabangNow alert tier for [$type]."
            );
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