<?php

namespace Tests\Feature;

use App\Mail\RegistrationDecisionMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationDecisionEmailTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'status' => true,
        ]);
    }

    private function applicant(): User
    {
        return User::factory()->create([
            'role' => 'resident',
            'is_active' => false,
            'status' => false,
            'registration_review_status' => 'pending',
        ]);
    }

    public function test_admin_approval_sends_one_email_and_activates_account(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $applicant = $this->applicant();

        $this->actingAs($admin)->patch(route('admin.users.activate', $applicant))->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $applicant->id, 'is_active' => true, 'registration_review_status' => 'approved']);
        Mail::assertSent(RegistrationDecisionMail::class, fn ($mail) => $mail->hasTo($applicant->email) && $mail->decision === 'approved');

        $this->actingAs($admin)->patch(route('admin.users.activate', $applicant))->assertRedirect();
        Mail::assertSentCount(1);
    }

    public function test_admin_decline_sends_one_email_and_keeps_account_inactive(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $applicant = $this->applicant();

        $this->actingAs($admin)->patch(route('admin.users.decline-registration', $applicant))->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $applicant->id, 'is_active' => false, 'registration_review_status' => 'declined']);
        Mail::assertSent(RegistrationDecisionMail::class, fn ($mail) => $mail->hasTo($applicant->email) && $mail->decision === 'declined');

        $this->actingAs($admin)->patch(route('admin.users.decline-registration', $applicant))->assertRedirect();
        Mail::assertSentCount(1);
    }

    public function test_non_admin_cannot_decline_another_resident(): void
    {
        Mail::fake();
        $applicant = $this->applicant();
        $otherResident = User::factory()->create(['role' => 'resident', 'is_active' => true, 'status' => true]);

        $this->actingAs($otherResident)->patch(route('admin.users.decline-registration', $applicant))->assertForbidden();
        Mail::assertNothingSent();
    }

    public function test_admin_can_resend_decline_email_without_changing_account_state(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $applicant = $this->applicant();
        $applicant->update(['registration_review_status' => 'declined']);

        $this->actingAs($admin)
            ->patch(route('admin.users.resend-decline-email', $applicant))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $applicant->id,
            'is_active' => false,
            'registration_review_status' => 'declined',
        ]);
        Mail::assertSent(RegistrationDecisionMail::class, fn ($mail) =>
            $mail->decision === 'declined' && $mail->hasTo($applicant->email)
        );
    }

    public function test_resend_rejected_for_pending_or_approved_accounts(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $pending = $this->applicant();
        $approved = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
            'registration_review_status' => 'approved',
        ]);

        $this->actingAs($admin)->patch(route('admin.users.resend-decline-email', $pending))
            ->assertForbidden();
        $this->actingAs($admin)->patch(route('admin.users.resend-decline-email', $approved))
            ->assertForbidden();
        Mail::assertNothingSent();
    }

    public function test_non_admin_cannot_resend_decline_email(): void
    {
        Mail::fake();
        $resident = User::factory()->create([
            'role' => 'resident', 'is_active' => true, 'status' => true,
        ]);
        $declined = $this->applicant();
        $declined->update(['registration_review_status' => 'declined']);

        $this->actingAs($resident)
            ->patch(route('admin.users.resend-decline-email', $declined))
            ->assertForbidden();
        Mail::assertNothingSent();
    }

    public function test_existing_deactivation_does_not_send_registration_declined_email(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $active = User::factory()->create(['role' => 'resident', 'is_active' => true, 'status' => true, 'registration_review_status' => 'approved']);

        $this->actingAs($admin)->patch(route('admin.users.deactivate', $active))->assertRedirect();
        Mail::assertNothingSent();
    }
}
