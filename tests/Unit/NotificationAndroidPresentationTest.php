<?php

namespace Tests\Unit;

use App\Services\FirebasePushService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class NotificationAndroidPresentationTest extends TestCase
{
    public function test_all_existing_flutter_code_red_events_use_exact_critical_channel(): void
    {
        foreach ([
            'announcement', 'calamity', 'mobile_emergency',
            'emergency', 'incident', 'incident_reported',
            'incident_message', 'incident_update', 'incident_updated',
            'incident_status_update', 'assigned_incident', 'incident_assigned',
            'new_assigned_incident', 'dispatch', 'escalation',
            'tanod_alert', 'tanod_task', 'tanod_task_assigned',
            'task_assigned', 'tanod_task_update', 'tanod_task_response',
            'task_update',
        ] as $type) {
            self::assertSame(
                ['channel_id' => 'tabangnow_notifications_v4', 'sound' => 'tabangnow_notification_v4'],
                $this->presentation($type, 'low'),
                "Expected Code Red for {$type}"
            );
        }
    }

    public function test_non_code_red_types_never_trigger_code_red_by_critical_field_alone(): void
    {
        foreach ([
            'mobile_emergency_received', 'mobile_emergency_acknowledged',
            'mobile_emergency_resolved', 'status_update', 'resolved',
            'resident_complaint', 'case_created', 'future_emergency',
        ] as $type) {
            self::assertSame(
                ['channel_id' => 'tabangnow_moderate_v2', 'sound' => 'tabangnow_moderate_v1'],
                $this->presentation($type, 'critical'),
                "Non-Code-Red event {$type} must not ring Code Red"
            );
        }
    }

    public function test_existing_moderate_low_and_silent_channels_are_preserved(): void
    {
        self::assertSame(
            ['channel_id' => 'tabangnow_moderate_v2', 'sound' => 'tabangnow_moderate_v1'],
            $this->presentation('resident_complaint', 'moderate')
        );
        self::assertSame(
            ['channel_id' => 'tabangnow_low_v2', 'sound' => 'tabangnow_low_v1'],
            $this->presentation('account_activated', 'low')
        );
        self::assertSame(
            ['channel_id' => 'tabangnow_silent_v1'],
            $this->presentation('system', 'silent')
        );
    }

    private function presentation(string $type, string $tier): array
    {
        $method = new ReflectionMethod(FirebasePushService::class, 'androidPresentation');
        $method->setAccessible(true);

        return $method->invoke(new FirebasePushService(), $type, $tier);
    }
}
