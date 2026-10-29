<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IncidentMessageNotificationSingleDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_website_message_creates_one_alert_per_other_participant(): void
    {
        $admin = $this->user('admin');
        $official = $this->user('official');
        $resident = $this->user('resident');
        $unrelatedResident = $this->user('resident');
        $incident = $this->incident($resident);

        $this->actingAs($resident)
            ->post(route('resident.incidents.messages.store', $incident), [
                'message' => 'Website message: please respond.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertRecipients($incident, [$admin, $official], [$resident, $unrelatedResident], 1);

        // A genuinely new message must still produce a fresh alert, not
        // suppress notifications due to shared incident source_id.
        $this->actingAs($resident)
            ->post(route('resident.incidents.messages.store', $incident), [
                'message' => 'Second website message.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertRecipients($incident, [$admin, $official], [$resident, $unrelatedResident], 2);
    }

    public function test_mobile_api_message_creates_one_alert_per_other_participant(): void
    {
        $admin = $this->user('admin');
        $official = $this->user('official');
        $resident = $this->user('resident');
        $unrelatedResident = $this->user('resident');
        $incident = $this->incident($resident);

        Sanctum::actingAs($admin, ['mobile']);

        $this->postJson(route('api.v1.incidents.messages.store', $incident), [
            'message' => 'Mobile message from administrator.',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Message added successfully.');

        $this->assertRecipients($incident, [$official, $resident], [$admin, $unrelatedResident], 1);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'status' => true,
        ]);
    }

    private function incident(User $reporter): Incident
    {
        return Incident::query()->create([
            'reporter_id' => $reporter->id,
            'title' => 'Incident message delivery regression',
            'incident_title' => 'Incident message delivery regression',
            'description' => 'Test message recipients.',
            'incident_description' => 'Test message recipients.',
            'priority' => 'high',
        ]);
    }

    private function assertRecipients(
        Incident $incident,
        array $recipients,
        array $excluded,
        int $expectedPerRecipient
    ): void {
        foreach ($recipients as $recipient) {
            $this->assertSame(
                $expectedPerRecipient,
                UserNotification::query()
                    ->where('user_id', $recipient->id)
                    ->where('type', 'incident_message')
                    ->where('source_id', $incident->id)
                    ->count(),
                'Every newly posted message should produce one alert per recipient.'
            );
        }

        foreach ($excluded as $user) {
            $this->assertSame(
                0,
                UserNotification::query()
                    ->where('user_id', $user->id)
                    ->where('type', 'incident_message')
                    ->where('source_id', $incident->id)
                    ->count(),
                'Sender and unrelated users must not receive incident messages.'
            );
        }
    }
}
