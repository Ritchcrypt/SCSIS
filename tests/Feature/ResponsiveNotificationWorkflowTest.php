<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\IncidentMessage;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResponsiveNotificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_resident_registration_notification_is_guaranteed_without_duplicates(): void
    {
        $admin = $this->activeUser('admin');

        $resident = User::factory()->create([
            'role' => 'resident',
            'is_active' => false,
            'status' => false,
        ]);

        event(new Registered($resident));
        event(new Registered($resident));

        $notifications = UserNotification::query()
            ->where('user_id', $admin->id)
            ->where('type', 'user_registration')
            ->where('source_id', $resident->id)
            ->get();

        $this->assertCount(1, $notifications);
        $this->assertFalse((bool) $notifications->first()->is_read);
    }

    public function test_account_activation_and_deactivation_create_notifications_for_the_affected_user(): void
    {
        $resident = $this->activeUser('resident');

        $resident->forceFill([
            'is_active' => false,
            'status' => false,
        ])->save();

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $resident->id)
                ->where('type', 'account_deactivated')
                ->where('source_id', $resident->id)
                ->where('is_read', false)
                ->exists()
        );

        $resident->forceFill([
            'is_active' => true,
            'status' => true,
        ])->save();

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $resident->id)
                ->where('type', 'account_activated')
                ->where('source_id', $resident->id)
                ->where('is_read', false)
                ->exists()
        );
    }

    public function test_new_incident_message_notifies_other_participants_but_not_the_sender(): void
    {
        $admin = $this->activeUser('admin');
        $official = $this->activeUser('official');
        $resident = $this->activeUser('resident');

        $incident = Incident::query()->create([
            'reporter_id' => $resident->id,
            'title' => 'Road obstruction report',
            'incident_title' => 'Road obstruction report',
            'description' => 'A fallen tree is blocking the road.',
            'incident_description' => 'A fallen tree is blocking the road.',
            'priority' => 'high',
        ]);

        IncidentMessage::query()->create([
            'incident_id' => $incident->id,
            'user_id' => $admin->id,
            'message' => 'Responder coordination has started.',
        ]);

        foreach ([$official, $resident] as $recipient) {
            $this->assertTrue(
                UserNotification::query()
                    ->where('user_id', $recipient->id)
                    ->where('type', 'incident_message')
                    ->where('source_id', $incident->id)
                    ->exists()
            );
        }

        $this->assertFalse(
            UserNotification::query()
                ->where('user_id', $admin->id)
                ->where('type', 'incident_message')
                ->where('source_id', $incident->id)
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
}
