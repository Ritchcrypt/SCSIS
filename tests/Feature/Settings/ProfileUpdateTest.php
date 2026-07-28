<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = $this->activeResident();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = $this->activeResident();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => 'Updated Resident',
                'email' => 'updated.resident@example.com',
                'contact_number' => '09987654321',
                'address' => 'Updated Address, Dao, Capiz',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame(
            'Updated Resident',
            $user->name
        );

        $this->assertSame(
            'updated.resident@example.com',
            $user->email
        );

        $this->assertSame(
            '09987654321',
            $user->contact_number
        );

        $this->assertSame(
            'Updated Address, Dao, Capiz',
            $user->address
        );
    }

    public function test_email_verification_status_is_unchanged_when_email_address_is_unchanged(): void
    {
        $verifiedAt = now()->subDay()->startOfSecond();

        /** @var User $user */
        $user = User::factory()->create([
            'name' => 'Verified Resident',
            'email' => 'verified.resident@example.com',
            'email_verified_at' => $verifiedAt,
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
            'contact_number' => '09123456789',
            'address' => 'Dao, Capiz',
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => 'Updated Verified Resident',
                'email' => $user->email,
                'contact_number' => $user->contact_number,
                'address' => $user->address,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertNotNull($user->email_verified_at);

        $this->assertSame(
            $verifiedAt->toDateTimeString(),
            $user->email_verified_at->toDateTimeString()
        );
    }

    public function test_user_can_delete_their_account(): void
    {
        $password = 'DeleteTabang#2026';

        /** @var User $user */
        $user = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
            'password' => Hash::make($password),
        ]);

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.self-delete'), [
                'password' => $password,
            ]);

        $response->assertRedirect();

        $this->assertGuest();

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $password = 'DeleteTabang#2026';

        /** @var User $user */
        $user = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
            'password' => Hash::make($password),
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.self-delete'), [
                'password' => 'IncorrectPassword#2026',
            ]);

        $response->assertRedirect();

        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
        ]);
    }

    private function activeResident(): User
    {
        /** @var User $user */
        $user = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
            'contact_number' => '09123456789',
            'address' => 'Dao, Capiz',
        ]);

        return $user;
    }
}