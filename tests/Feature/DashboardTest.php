<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_are_redirected_to_their_role_dashboard(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertRedirect(
            route('resident.dashboard')
        );
    }
}