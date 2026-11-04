<?php

namespace Tests\Feature;

use App\Models\MobilePushToken;
use App\Models\UserNotification;
use App\Services\FirebasePushService;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

class FirebaseCodeRedTransportTest extends TestCase
{
    public function test_code_red_events_use_data_only_high_priority_delivery_for_repeating_alarm(): void
    {
        foreach ([
            'announcement', 'calamity', 'mobile_emergency', 'emergency',
            'incident', 'incident_reported', 'incident_message',
            'incident_update', 'incident_updated', 'incident_status_update',
            'assigned_incident', 'incident_assigned', 'new_assigned_incident',
            'dispatch', 'escalation', 'tanod_alert', 'tanod_task',
            'tanod_task_assigned', 'task_assigned', 'tanod_task_update',
            'tanod_task_response', 'task_update',
        ] as $type) {
            // Data-only reaches Flutter's Android background handler; an
            // auto-rendered notification would bypass repeating Code Red.
            $message = $this->sendMessage($type, 'critical');
            self::assertArrayNotHasKey('notification', $message, $type);
            self::assertArrayNotHasKey('notification', $message['android'], $type);
            self::assertSame('high', $message['android']['priority'], $type);
            self::assertSame('900s', $message['android']['ttl'], $type);
            self::assertSame($type, $message['data']['type'], $type);
            self::assertSame('777', $message['data']['notification_id'], $type);
            self::assertSame('Sample Code Red', $message['data']['title'], $type);
        }
    }

    public function test_normal_tiers_keep_android_managed_notification_and_sound(): void
    {
        $moderate = $this->sendMessage('resident_complaint', 'moderate');
        self::assertSame('Sample Code Red', $moderate['notification']['title']);
        self::assertSame('tabangnow_moderate_v2', $moderate['android']['notification']['channel_id']);
        self::assertSame('tabangnow_moderate_v1', $moderate['android']['notification']['sound']);
        self::assertSame('high', $moderate['android']['priority']);

        $low = $this->sendMessage('account_activated', 'low');
        self::assertSame('tabangnow_low_v2', $low['android']['notification']['channel_id']);
        self::assertSame('tabangnow_low_v1', $low['android']['notification']['sound']);
        self::assertSame('normal', $low['android']['priority']);

        $silent = $this->sendMessage('system', 'silent');
        self::assertSame('tabangnow_silent_v1', $silent['android']['notification']['channel_id']);
        self::assertArrayNotHasKey('sound', $silent['android']['notification']);
        self::assertSame('normal', $silent['android']['priority']);
    }

    public function test_legacy_critical_field_does_not_escalate_a_non_red_event(): void
    {
        $message = $this->sendMessage('resident_complaint', 'critical');

        self::assertSame('tabangnow_moderate_v2', $message['android']['notification']['channel_id']);
        self::assertArrayHasKey('notification', $message);
    }

    private function sendMessage(string $type, string $tier): array
    {
        Http::fake([
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/example/messages/1'], 200),
        ]);

        $pushToken = new MobilePushToken();
        $pushToken->forceFill(['fcm_token' => 'fake-local-test-token']);

        $notification = new UserNotification();
        $notification->forceFill([
            'id' => 777,
            'type' => $type,
            'source_id' => 10,
            'title' => 'Sample Code Red',
            'message' => 'Test notification only',
        ]);

        $method = new ReflectionMethod(FirebasePushService::class, 'sendToToken');
        $method->setAccessible(true);

        $response = $method->invoke(
            new FirebasePushService(),
            'tabangnow-mobile-2026',
            'test-access-token',
            $pushToken,
            $notification,
            $tier
        );

        self::assertTrue($response->successful());
        $records = Http::recorded();
        self::assertCount(1, $records);
        [$request] = $records->first();
        self::assertSame(
            'https://fcm.googleapis.com/v1/projects/tabangnow-mobile-2026/messages:send',
            $request->url()
        );

        return $request->data()['message'];
    }
}
