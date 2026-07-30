<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SessionRevocationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_change_revokes_other_database_sessions(): void
    {
        $currentPassword = 'CurrentTabang#2026';
        $newPassword = 'UpdatedTabang#2026';

        /** @var User $user */
        $user = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
            'password' => Hash::make($currentPassword),
            'remember_token' => 'old-remember-token',
        ]);

        $this->insertSession(
            'other-device-session',
            (int) $user->id
        );

        $response = $this
            ->actingAs($user)
            ->put(route('profile.password.update'), [
                'current_password' => $currentPassword,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', [
            'id' => 'other-device-session',
        ]);

        $user->refresh();

        $this->assertTrue(
            Hash::check($newPassword, $user->password)
        );

        $this->assertNotSame(
            'old-remember-token',
            $user->remember_token
        );

        $this->assertAuthenticatedAs($user);
    }

    public function test_completed_password_reset_revokes_all_database_sessions(): void
    {
        Notification::fake();

        /** @var User $user */
        $user = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
            'last_seen_at' => now(),
            'remember_token' => 'old-reset-remember-token',
        ]);

        $this->insertSession(
            'reset-session-one',
            (int) $user->id
        );

        $this->insertSession(
            'reset-session-two',
            (int) $user->id
        );

        Volt::test('auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use ($user): bool {
                $newPassword = 'ResetTabang#2026';

                Volt::test('auth.reset-password', [
                    'token' => $notification->token,
                ])
                    ->set('email', $user->email)
                    ->set('password', $newPassword)
                    ->set('password_confirmation', $newPassword)
                    ->call('resetPassword')
                    ->assertHasNoErrors()
                    ->assertRedirect(
                        route('login', absolute: false)
                    );

                return true;
            }
        );

        $this->assertDatabaseMissing('sessions', [
            'user_id' => $user->id,
        ]);

        $user->refresh();

        $this->assertNull($user->last_seen_at);

        $this->assertNotSame(
            'old-reset-remember-token',
            $user->remember_token
        );
    }

    public function test_admin_deactivation_revokes_target_sessions_and_reset_tokens(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'status' => true,
        ]);

        /** @var User $target */
        $target = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
            'last_seen_at' => now(),
            'remember_token' => 'target-remember-token',
        ]);

        $this->insertSession(
            'target-active-session',
            (int) $target->id
        );

        DB::table('password_reset_tokens')->updateOrInsert(
            [
                'email' => $target->email,
            ],
            [
                'token' => Hash::make('temporary-reset-token'),
                'created_at' => now(),
            ]
        );

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.deactivate', $target));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $target->refresh();

        $this->assertFalse((bool) $target->is_active);
        $this->assertFalse((bool) $target->status);
        $this->assertNull($target->last_seen_at);
        $this->assertNull($target->remember_token);

        $this->assertDatabaseMissing('sessions', [
            'user_id' => $target->id,
        ]);

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $target->email,
        ]);
    }

    public function test_self_account_deletion_removes_authentication_artifacts(): void
    {
        $password = 'DeleteTabang#2026';

        /** @var User $user */
        $user = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
            'password' => Hash::make($password),
        ]);

        $userId = (int) $user->id;
        $email = (string) $user->email;

        $this->insertSession(
            'self-delete-other-session',
            $userId
        );

        DB::table('password_reset_tokens')->updateOrInsert(
            [
                'email' => $email,
            ],
            [
                'token' => Hash::make('self-delete-reset-token'),
                'created_at' => now(),
            ]
        );

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

        $this->assertDatabaseMissing('sessions', [
            'user_id' => $userId,
        ]);

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $email,
        ]);
    }

    private function insertSession(
        string $sessionId,
        int $userId
    ): void {
        $this->assertTrue(
            Schema::hasTable('sessions'),
            'The sessions table is required for session revocation tests.'
        );

        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit security test',
            'payload' => 'test-session-payload',
            'last_activity' => time(),
        ]);
    }
}
