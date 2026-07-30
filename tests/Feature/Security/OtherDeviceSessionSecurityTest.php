<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OtherDeviceSessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_revoke_authenticated_sessions(): void
    {
        $response = $this->delete(
            route('profile.other-sessions.destroy'),
            [
                'password' => 'AnyPassword#2026',
            ]
        );

        $response->assertRedirect(route('login'));
    }

    public function test_correct_password_revokes_only_the_current_users_other_sessions(): void
    {
        $password = 'CurrentTabang#2026';

        /** @var User $user */
        $user = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
            'password' => Hash::make($password),
            'remember_token' => 'old-user-remember-token',
        ]);

        /** @var User $otherUser */
        $otherUser = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
        ]);

        $this->insertSession(
            'user-other-device-one',
            (int) $user->id
        );

        $this->insertSession(
            'user-other-device-two',
            (int) $user->id
        );

        $this->insertSession(
            'unrelated-user-session',
            (int) $otherUser->id
        );

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(
                route('profile.other-sessions.destroy'),
                [
                    'password' => $password,
                ]
            );

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', [
            'id' => 'user-other-device-one',
        ]);

        $this->assertDatabaseMissing('sessions', [
            'id' => 'user-other-device-two',
        ]);

        $this->assertDatabaseHas('sessions', [
            'id' => 'unrelated-user-session',
            'user_id' => $otherUser->id,
        ]);

        $user->refresh();

        $this->assertNotSame(
            'old-user-remember-token',
            $user->remember_token
        );

        $this->assertAuthenticatedAs($user);
    }

    public function test_incorrect_password_does_not_revoke_other_sessions(): void
    {
        $password = 'CurrentTabang#2026';

        /** @var User $user */
        $user = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
            'password' => Hash::make($password),
            'remember_token' => 'unchanged-remember-token',
        ]);

        $this->insertSession(
            'preserved-other-device-session',
            (int) $user->id
        );

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(
                route('profile.other-sessions.destroy'),
                [
                    'password' => 'IncorrectPassword#2026',
                ]
            );

        $response->assertRedirect(route('profile.edit'));

        $response->assertSessionHasErrors(
            ['password'],
            null,
            'logoutOtherSessions'
        );

        $this->assertDatabaseHas('sessions', [
            'id' => 'preserved-other-device-session',
            'user_id' => $user->id,
        ]);

        $user->refresh();

        $this->assertSame(
            'unchanged-remember-token',
            $user->remember_token
        );

        $this->assertAuthenticatedAs($user);
    }

    private function insertSession(
        string $sessionId,
        int $userId
    ): void {
        $this->assertTrue(
            Schema::hasTable('sessions'),
            'The sessions table is required for session security tests.'
        );

        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit other-device test',
            'payload' => 'test-session-payload',
            'last_activity' => time(),
        ]);
    }
}
