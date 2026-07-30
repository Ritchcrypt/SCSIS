<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $currentPassword = 'CurrentTabang#2026';
        $newPassword = 'UpdatedTabang#2026';

        /** @var User $user */
        $user = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
            'password' => Hash::make($currentPassword),
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.password.update'), [
                'current_password' => $currentPassword,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertTrue(
            Hash::check($newPassword, $user->password)
        );

        $this->assertFalse(
            Hash::check($currentPassword, $user->password)
        );
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $currentPassword = 'CurrentTabang#2026';
        $newPassword = 'UpdatedTabang#2026';

        /** @var User $user */
        $user = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
            'password' => Hash::make($currentPassword),
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.password.update'), [
                'current_password' => 'IncorrectPassword#2026',
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ]);

        $response->assertRedirect();

        $user->refresh();

        $this->assertTrue(
            Hash::check($currentPassword, $user->password)
        );

        $this->assertFalse(
            Hash::check($newPassword, $user->password)
        );
    }
}