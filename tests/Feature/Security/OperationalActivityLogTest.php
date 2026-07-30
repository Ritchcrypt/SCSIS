<?php

namespace Tests\Feature\Security;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\EmergencyHotline;
use App\Models\TanodTask;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_creation_is_logged_without_subject_details(): void
    {
        /** @var User $admin */
        $admin = $this->activeUser('admin');

        $subjectName = 'Do Not Store Subject Name';

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.cases.store'), [
                'case_number' => 'AUTO-GENERATED',
                'case_type' => 'blotter',
                'subject_name' => $subjectName,
                'contact' => '09123456789',
                'address' => 'Sensitive case address',
                'incident_id' => null,
                'incident_title' => null,
                'status' => 'open',
                'hearing_date' => null,
                'handled_by' => null,
                'resolution' => null,
                'notes' => 'Sensitive case notes',
            ]);

        $response->assertRedirect(
            route('admin.cases.index')
        );

        $response->assertSessionHasNoErrors();

        $log = ActivityLog::query()
            ->where('event', 'case.created')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            $admin->id,
            $log->actor_id
        );

        $this->assertSame(
            'case',
            $log->category
        );

        $metadata = json_encode(
            $log->metadata,
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            $subjectName,
            $metadata
        );

        $this->assertStringNotContainsString(
            'Sensitive case address',
            $metadata
        );

        $this->assertStringNotContainsString(
            'Sensitive case notes',
            $metadata
        );
    }

    public function test_resident_complaint_creation_is_logged_without_complaint_content(): void
    {
        /** @var User $resident */
        $resident = $this->activeUser('resident');

        $description = 'Do not place this complaint description in metadata.';

        $response = $this
            ->actingAs($resident)
            ->post(
                route('resident.resident-complaints.store'),
                [
                    'complainant_name' => 'Private Resident Name',
                    'contact_number' => '09987654321',
                    'complaint_address' => 'Private complaint address',
                    'complaint_description' => $description,
                ]
            );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $log = ActivityLog::query()
            ->where('event', 'complaint.created')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            $resident->id,
            $log->actor_id
        );

        $metadata = json_encode(
            $log->metadata,
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            $description,
            $metadata
        );

        $this->assertStringNotContainsString(
            'Private Resident Name',
            $metadata
        );

        $this->assertStringNotContainsString(
            'Private complaint address',
            $metadata
        );
    }

    public function test_announcement_toggle_records_the_state_transition(): void
    {
        /** @var User $admin */
        $admin = $this->activeUser('admin');

        $announcement = Announcement::query()->create([
            'title' => 'Test announcement',
            'content' => 'Test content',
            'category' => 'general',
            'priority' => 'normal',
            'audience' => 'everyone',
            'is_active' => true,
            'activate_calamity_mode' => false,
            'posted_by' => $admin->id,
            'published_at' => now(),
        ]);

        $response = $this
            ->actingAs($admin)
            ->patch(
                route(
                    'admin.announcements.toggle',
                    $announcement
                )
            );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $log = ActivityLog::query()
            ->where('event', 'announcement.toggled')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            $announcement->id,
            $log->metadata['announcement_id']
        );

        $this->assertTrue(
            $log->metadata['previous_active']
        );

        $this->assertFalse(
            $log->metadata['new_active']
        );
    }

    public function test_closing_a_tanod_task_is_logged(): void
    {
        /** @var User $admin */
        $admin = $this->activeUser('admin');

        $task = TanodTask::query()->create([
            'created_by' => $admin->id,
            'title' => 'Test patrol task',
            'description' => null,
            'location' => null,
            'task_datetime' => now(),
            'due_at' => null,
            'priority' => 'normal',
            'status' => 'open',
        ]);

        $response = $this
            ->actingAs($admin)
            ->patch(
                route(
                    'admin.tanod-tasks.close',
                    $task
                )
            );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $admin->id,
            'event' => 'tanod_task.closed',
            'category' => 'tanod_task',
        ]);

        $log = ActivityLog::query()
            ->where('event', 'tanod_task.closed')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            'open',
            $log->metadata['previous_status']
        );

        $this->assertSame(
            'closed',
            $log->metadata['new_status']
        );
    }

    public function test_emergency_hotline_deletion_is_logged(): void
    {
        /** @var User $admin */
        $admin = $this->activeUser('admin');

        $hotline = EmergencyHotline::query()->create([
            'agency_name' => 'Test Emergency Agency',
            'hotline_number' => '911',
            'color' => 'red',
            'is_active' => true,
            'sort_order' => 99,
        ]);

        $hotlineId = (int) $hotline->id;

        $response = $this
            ->actingAs($admin)
            ->delete(
                route(
                    'admin.emergency-mode.destroy',
                    $hotline
                )
            );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseMissing(
            'emergency_hotlines',
            [
                'id' => $hotlineId,
            ]
        );

        $log = ActivityLog::query()
            ->where('event', 'emergency_hotline.deleted')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            $hotlineId,
            $log->metadata['emergency_hotline_id']
        );

        $this->assertSame(
            'Test Emergency Agency',
            $log->metadata['agency_name']
        );
    }

    public function test_tanod_alert_acknowledgement_is_logged(): void
    {
        /** @var User $tanod */
        $tanod = $this->activeUser('tanod');

        $notification = UserNotification::query()->create([
            'user_id' => $tanod->id,
            'type' => 'dispatch',
            'source_id' => 1001,
            'title' => 'Dispatch alert',
            'message' => 'Operational test alert',
            'is_read' => false,
            'read_at' => null,
            'acknowledged_by' => null,
            'acknowledged_at' => null,
        ]);

        $response = $this
            ->actingAs($tanod)
            ->patch(
                route(
                    'tanod.tanod-alerts.acknowledge',
                    $notification
                )
            );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $tanod->id,
            'event' => 'tanod_alert.acknowledged',
            'category' => 'tanod_alert',
        ]);

        $log = ActivityLog::query()
            ->where('event', 'tanod_alert.acknowledged')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            $notification->id,
            $log->metadata['notification_id']
        );

        $this->assertSame(
            'dispatch',
            $log->metadata['notification_type']
        );
    }

    private function activeUser(string $role): User
    {
        /** @var User $user */
        $user = User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'status' => true,
        ]);

        return $user;
    }
}
