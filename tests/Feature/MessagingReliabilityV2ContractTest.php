<?php

namespace Tests\Feature;

use Tests\TestCase;

class MessagingReliabilityV2ContractTest extends TestCase
{
    public function test_tanod_response_notifications_use_task_semantics(): void
    {
        foreach ([
            app_path('Http/Controllers/TanodTaskController.php'),
            app_path('Http/Controllers/Api/V1/TanodTaskController.php'),
        ] as $path) {
            $source = (string) file_get_contents($path);

            $start = strpos(
                $source,
                'private function notifyAdminsAboutTanodTaskResponse('
            );

            $this->assertNotFalse($start);

            $method = substr(
                $source,
                $start,
                5000
            );

            $this->assertStringContainsString(
                "'type' => 'tanod_task_response'",
                $method
            );

            $this->assertStringContainsString(
                "'source_id' => \$task->id",
                $method
            );
        }
    }

    public function test_incident_message_handlers_notify_other_participants(): void
    {
        foreach ([
            app_path('Http/Controllers/IncidentController.php'),
            app_path('Http/Controllers/Api/V1/IncidentController.php'),
        ] as $path) {
            $source = (string) file_get_contents($path);

            $this->assertStringContainsString(
                'notifyIncidentMessageRecipients(',
                $source
            );

            $this->assertStringContainsString(
                "'type' => 'incident_message'",
                $source
            );
        }
    }

    public function test_notification_routing_knows_tanod_task_response(): void
    {
        $website = (string) file_get_contents(
            app_path('Http/Controllers/NotificationOpenController.php')
        );

        $api = (string) file_get_contents(
            app_path('Http/Controllers/Api/V1/NotificationCenterController.php')
        );

        $bell = (string) file_get_contents(
            app_path('Services/NotificationBellService.php')
        );

        $push = (string) file_get_contents(
            app_path('Services/FirebasePushService.php')
        );

        $this->assertStringContainsString(
            'tanod_task_response',
            $website
        );

        $this->assertStringContainsString(
            'tanod_task_response',
            $api
        );

        $this->assertStringContainsString(
            "'tanod_task_response' => 'Tanod Task Response'",
            $bell
        );

        $this->assertStringContainsString(
            'tanod_task_response',
            $push
        );
    }
}
