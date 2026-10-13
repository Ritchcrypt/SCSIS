<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentFeedbackModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_can_submit_structured_feedback(): void
    {
        $resident = User::factory()->create([
            'role' => 'resident',
        ]);

        $response = $this->actingAs($resident)->post(
            route('resident.resident-complaints.store'),
            [
                'complainant_name' => $resident->name,
                'contact_number' => '09171234567',
                'feedback_category' => 'staff_response',
                'feedback_type' => 'compliment',
                'feedback_subject' => 'Fast response from barangay personnel',
                'service_rating' => 5,
                'complaint_address' => null,
                'complaint_description' => 'Personnel responded quickly and explained the action taken.',
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('resident_complaints', [
            'resident_id' => $resident->id,
            'feedback_category' => 'staff_response',
            'feedback_type' => 'compliment',
            'feedback_subject' => 'Fast response from barangay personnel',
            'service_rating' => 5,
        ]);
    }

    public function test_resident_feedback_page_uses_feedback_language(): void
    {
        $resident = User::factory()->create([
            'role' => 'resident',
        ]);

        $this->actingAs($resident)
            ->get(route('resident.resident-complaints.index'))
            ->assertOk()
            ->assertSee('My Feedback')
            ->assertSee('Send Feedback');
    }
}
