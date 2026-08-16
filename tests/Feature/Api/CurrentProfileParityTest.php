<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CurrentProfileParityTest extends TestCase
{
    use RefreshDatabase;

    private const CURRENT_PASSWORD = 'Current!Password123';
    private const NEW_PASSWORD = 'Changed!Password456';

    public function test_resident_can_view_and_update_own_profile(): void
    {
        $user = $this->user('resident');
        $token = $user->createToken('profile-test', ['mobile'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('permissions.can_change_password', true)
            ->assertJsonPath('permissions.can_self_delete', true);

        $this->withToken($token)->post('/api/v1/profile/update', [
            'name' => 'Updated Resident',
            'email' => 'UPDATED@example.test',
            'contact_number' => '09123456789',
            'address' => 'Dao, Capiz',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Resident')
            ->assertJsonPath('data.email', 'updated@example.test');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Resident',
            'email' => 'updated@example.test',
            'contact_number' => '09123456789',
            'address' => 'Dao, Capiz',
        ]);
    }

    public function test_password_change_preserves_current_mobile_token_and_revokes_other_tokens(): void
    {
        $user = $this->user('resident');
        $current = $user->createToken('current-phone', ['mobile']);
        $other = $user->createToken('other-phone', ['mobile']);

        $currentId = (int) $current->accessToken->id;
        $otherId = (int) $other->accessToken->id;

        $this->withToken($current->plainTextToken)->patchJson('/api/v1/profile/password', [
            'current_password' => self::CURRENT_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, (string) $user->password));
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $currentId]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $otherId]);
        $this->withToken($current->plainTextToken)->getJson('/api/v1/profile')->assertOk();
    }

    public function test_sign_out_other_devices_keeps_current_phone_authenticated(): void
    {
        $user = $this->user('tanod');
        $current = $user->createToken('current-phone', ['mobile']);
        $other = $user->createToken('other-phone', ['mobile']);

        $this->withToken($current->plainTextToken)->deleteJson('/api/v1/profile/other-sessions', [
            'password' => self::CURRENT_PASSWORD,
        ])->assertOk();

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $current->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->id]);
        $this->withToken($current->plainTextToken)->getJson('/api/v1/profile')->assertOk();
    }

    public function test_admin_self_profile_hides_self_delete_and_self_password_ui_capabilities(): void
    {
        $user = $this->user('admin');
        $token = $user->createToken('admin-phone', ['mobile'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('permissions.can_change_password', false)
            ->assertJsonPath('permissions.can_self_delete', false)
            ->assertJsonPath('permissions.can_sign_out_other_devices', true);

        $this->withToken($token)->deleteJson('/api/v1/profile/self-delete', [
            'password' => self::CURRENT_PASSWORD,
        ])->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_resident_can_permanently_delete_own_account_with_password(): void
    {
        $user = $this->user('resident');
        $token = $user->createToken('delete-phone', ['mobile'])->plainTextToken;

        $this->withToken($token)->deleteJson('/api/v1/profile/self-delete', [
            'password' => self::CURRENT_PASSWORD,
        ])->assertOk()->assertJsonPath('account_deleted', true);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'name' => 'Profile Test User',
            'email' => $role . '@example.test',
            'password' => Hash::make(self::CURRENT_PASSWORD),
            'role' => $role,
            'is_active' => true,
            'status' => true,
        ]);
    }
}