<?php

namespace Tests\Feature\Security;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountManagementActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_update_records_only_changed_field_names(): void
    {
        /** @var User $user */
        $user = $this->activeResident([
            'name' => 'Original Resident',
            'email' => 'original.resident@example.com',
            'contact_number' => '09123456789',
            'address' => 'Original Address',
        ]);

        $newEmail = 'updated.resident@example.com';
        $newAddress = 'Updated Address';

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Updated Resident',
                'email' => $newEmail,
                'contact_number' => '09987654321',
                'address' => $newAddress,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $log = ActivityLog::query()
            ->where('event', 'account.profile_updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            $user->id,
            $log->actor_id
        );

        $this->assertEqualsCanonicalizing(
            [
                'name',
                'email',
                'contact_number',
                'address',
            ],
            $log->metadata['changed_fields']
        );

        $encodedMetadata = json_encode(
            $log->metadata,
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            $newEmail,
            $encodedMetadata
        );

        $this->assertStringNotContainsString(
            $newAddress,
            $encodedMetadata
        );
    }

    public function test_password_change_and_other_device_revocation_are_logged(): void
    {
        $currentPassword = 'CurrentTabang#2026';
        $newPassword = 'UpdatedTabang#2026';

        /** @var User $user */
        $user = $this->activeResident([
            'password' => Hash::make($currentPassword),
        ]);

        $passwordResponse = $this
            ->actingAs($user)
            ->put(route('profile.password.update'), [
                'current_password' => $currentPassword,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ]);

        $passwordResponse->assertRedirect();
        $passwordResponse->assertSessionHasNoErrors();

        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'target_user_id' => $user->id,
            'event' => 'auth.password_changed',
        ]);

        $sessionResponse = $this
            ->actingAs($user->fresh())
            ->delete(route('profile.other-sessions.destroy'), [
                'password' => $newPassword,
            ]);

        $sessionResponse->assertRedirect();
        $sessionResponse->assertSessionHasNoErrors();

        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'target_user_id' => $user->id,
            'event' => 'auth.other_sessions_revoked',
        ]);
    }

    public function test_admin_user_creation_and_update_are_logged_without_passwords(): void
    {
        /** @var User $admin */
        $admin = $this->activeAdmin();

        $initialPassword = 'CreatedUser#2026';

        $createResponse = $this
            ->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Created Resident',
                'email' => 'created.resident@example.com',
                'role' => 'resident',
                'is_active' => true,
                'password' => $initialPassword,
            ]);

        $createResponse->assertRedirect();
        $createResponse->assertSessionHasNoErrors();

        $createdUser = User::query()
            ->where('email', 'created.resident@example.com')
            ->firstOrFail();

        $createdLog = ActivityLog::query()
            ->where('event', 'user_management.created')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            $admin->id,
            $createdLog->actor_id
        );

        $this->assertSame(
            $createdUser->id,
            $createdLog->target_user_id
        );

        $this->assertStringNotContainsString(
            $initialPassword,
            json_encode(
                $createdLog->metadata,
                JSON_THROW_ON_ERROR
            )
        );

        $updateResponse = $this
            ->actingAs($admin)
            ->patch(
                route('admin.users.update', $createdUser),
                [
                    'name' => 'Updated Official',
                    'email' => $createdUser->email,
                    'contact_number' => null,
                    'barangay_id' => null,
                    'address' => null,
                    'role' => 'official',
                    'is_active' => true,
                    'password' => null,
                ]
            );

        $updateResponse->assertRedirect();
        $updateResponse->assertSessionHasNoErrors();

        $updatedLog = ActivityLog::query()
            ->where('event', 'user_management.updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertContains(
            'name',
            $updatedLog->metadata['changed_fields']
        );

        $this->assertContains(
            'role',
            $updatedLog->metadata['changed_fields']
        );

        $this->assertSame(
            'resident',
            $updatedLog->metadata['old_role']
        );

        $this->assertSame(
            'official',
            $updatedLog->metadata['new_role']
        );
    }

    public function test_admin_activation_deactivation_and_reset_link_are_logged(): void
    {
        Notification::fake();

        /** @var User $admin */
        $admin = $this->activeAdmin();

        /** @var User $target */
        $target = User::factory()->create([
            'role' => 'resident',
            'is_active' => false,
            'status' => false,
        ]);

        $activateResponse = $this
            ->actingAs($admin)
            ->patch(route('admin.users.activate', $target));

        $activateResponse->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $admin->id,
            'target_user_id' => $target->id,
            'event' => 'user_management.activated',
        ]);

        $resetResponse = $this
            ->actingAs($admin)
            ->patch(route('admin.users.reset-password', $target));

        $resetResponse->assertRedirect();

        Notification::assertSentTo(
            $target,
            ResetPassword::class
        );

        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $admin->id,
            'target_user_id' => $target->id,
            'event' => 'user_management.password_reset_link_sent',
        ]);

        $deactivateResponse = $this
            ->actingAs($admin)
            ->patch(route('admin.users.deactivate', $target));

        $deactivateResponse->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $admin->id,
            'target_user_id' => $target->id,
            'event' => 'user_management.deactivated',
        ]);
    }

    public function test_admin_deletion_preserves_audit_snapshots(): void
    {
        /** @var User $admin */
        $admin = $this->activeAdmin();

        /** @var User $target */
        $target = $this->activeResident([
            'name' => 'Resident To Delete',
        ]);

        $targetId = (int) $target->id;

        $response = $this
            ->actingAs($admin)
            ->delete(route('admin.users.destroy', $target));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('users', [
            'id' => $targetId,
        ]);

        $log = ActivityLog::query()
            ->where('event', 'user_management.deleted')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            $admin->id,
            $log->actor_id
        );

        $this->assertSame(
            'Resident To Delete',
            $log->target_name
        );

        $this->assertSame(
            'resident',
            $log->target_role
        );
    }

    public function test_self_account_deletion_is_logged_and_snapshot_is_preserved(): void
    {
        $password = 'DeleteTabang#2026';

        /** @var User $user */
        $user = $this->activeResident([
            'name' => 'Self Delete Resident',
            'password' => Hash::make($password),
        ]);

        $userId = (int) $user->id;

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.self-delete'), [
                'password' => $password,
            ]);

        $response->assertRedirect('/');
        $this->assertGuest();

        $this->assertDatabaseMissing('users', [
            'id' => $userId,
        ]);

        $log = ActivityLog::query()
            ->where('event', 'account.self_deleted')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            $userId,
            $log->actor_id
        );

        $this->assertSame(
            'Self Delete Resident',
            $log->actor_name
        );

        $this->assertSame(
            'Self Delete Resident',
            $log->target_name
        );
    }

    public function test_user_export_is_logged_without_filter_values(): void
    {
        /** @var User $admin */
        $admin = $this->activeAdmin();

        $searchValue = 'private-search-value';

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.users.export', [
                'search' => $searchValue,
                'role' => 'resident',
            ]));

        $response->assertOk();

        $log = ActivityLog::query()
            ->where('event', 'user_management.exported')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            $admin->id,
            $log->actor_id
        );

        $this->assertTrue(
            $log->metadata['filters_applied']
        );

        $this->assertStringNotContainsString(
            $searchValue,
            json_encode(
                $log->metadata,
                JSON_THROW_ON_ERROR
            )
        );
    }

    private function activeAdmin(array $attributes = []): User
    {
        /** @var User $user */
        $user = User::factory()->create(array_merge([
            'role' => 'admin',
            'is_active' => true,
            'status' => true,
        ], $attributes));

        return $user;
    }

    private function activeResident(array $attributes = []): User
    {
        /** @var User $user */
        $user = User::factory()->create(array_merge([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
        ], $attributes));

        return $user;
    }
}
