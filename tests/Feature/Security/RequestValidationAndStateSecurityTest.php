<?php

namespace Tests\Feature\Security;

use App\Models\ResidentComplaint;
use App\Models\TanodTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestValidationAndStateSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_email_change_is_normalized_and_invalidates_verification(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'name' => 'Verified Resident',
            'email' => 'verified.resident@example.com',
            'email_verified_at' => now()->subDay(),
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
            'contact_number' => '09123456789',
            'address' => 'Dao, Capiz',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => '  NEW.RESIDENT@EXAMPLE.COM  ',
                'contact_number' => $user->contact_number,
                'address' => $user->address,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame(
            'new.resident@example.com',
            $user->email
        );

        $this->assertNull(
            $user->email_verified_at
        );
    }

    public function test_admin_email_change_is_normalized_and_invalidates_verification(): void
    {
        /** @var User $admin */
        $admin = $this->activeUser(
            'admin'
        );

        /** @var User $target */
        $target = User::factory()->create([
            'name' => 'Verified Target',
            'email' => 'verified.target@example.com',
            'email_verified_at' => now()->subDay(),
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->patch(
                route(
                    'admin.users.update',
                    $target
                ),
                [
                    'name' => $target->name,
                    'email' => '  UPDATED.TARGET@EXAMPLE.COM  ',
                    'contact_number' => null,
                    'barangay_id' => null,
                    'address' => null,
                    'role' => 'resident',
                    'is_active' => true,
                    'password' => null,
                ]
            );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $target->refresh();

        $this->assertSame(
            'updated.target@example.com',
            $target->email
        );

        $this->assertNull(
            $target->email_verified_at
        );
    }

    public function test_complaint_rejects_malformed_contact_numbers(): void
    {
        /** @var User $resident */
        $resident = $this->activeUser(
            'resident'
        );

        $response = $this
            ->actingAs($resident)
            ->from(
                route(
                    'resident.resident-complaints.create'
                )
            )
            ->post(
                route(
                    'resident.resident-complaints.store'
                ),
                [
                    'complainant_name' => 'Resident User',
                    'contact_number' => '<script>alert(1)</script>',
                    'complaint_address' => 'Dao, Capiz',
                    'complaint_description' =>
                        'Validation security test complaint.',
                ]
            );

        $response->assertRedirect();
        $response->assertSessionHasErrors(
            'contact_number'
        );

        $this->assertDatabaseCount(
            'resident_complaints',
            0
        );
    }

    public function test_complaint_ownership_and_initial_status_are_server_controlled(): void
    {
        /** @var User $resident */
        $resident = $this->activeUser(
            'resident'
        );

        /** @var User $otherResident */
        $otherResident = $this->activeUser(
            'resident'
        );

        $response = $this
            ->actingAs($resident)
            ->post(
                route(
                    'resident.resident-complaints.store'
                ),
                [
                    'complainant_name' => 'Resident User',
                    'contact_number' => '09123456789',
                    'complaint_address' => 'Dao, Capiz',
                    'complaint_description' =>
                        'Ownership security test complaint.',
                    'resident_id' => $otherResident->id,
                    'status' => 'resolved',
                    'submitted_at' => now()->subYear(),
                ]
            );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $complaint = ResidentComplaint::query()
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            $resident->id,
            $complaint->resident_id
        );

        $this->assertSame(
            'submitted',
            $complaint->status
        );

        $this->assertTrue(
            $complaint->submitted_at->isToday()
        );
    }

    public function test_terminal_tanod_task_cannot_be_changed_to_another_terminal_status(): void
    {
        /** @var User $admin */
        $admin = $this->activeUser(
            'admin'
        );

        $task = TanodTask::query()->create([
            'created_by' => $admin->id,
            'title' => 'Closed patrol task',
            'description' => null,
            'location' => null,
            'task_datetime' => now(),
            'due_at' => null,
            'priority' => 'normal',
            'status' => 'closed',
        ]);

        $response = $this
            ->actingAs($admin)
            ->from(
                route(
                    'admin.tanod-tasks.index'
                )
            )
            ->patch(
                route(
                    'admin.tanod-tasks.cancel',
                    $task
                )
            );

        $response->assertRedirect(
            route(
                'admin.tanod-tasks.index'
            )
        );

        $response->assertSessionHasErrors(
            'status'
        );

        $this->assertDatabaseHas(
            'tanod_tasks',
            [
                'id' => $task->id,
                'status' => 'closed',
            ]
        );
    }

    private function activeUser(
        string $role
    ): User {
        /** @var User $user */
        $user = User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'status' => true,
        ]);

        return $user;
    }
}
