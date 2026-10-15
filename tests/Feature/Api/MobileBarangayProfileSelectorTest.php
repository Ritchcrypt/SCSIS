<?php

namespace Tests\Feature\Api;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileBarangayProfileSelectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_mobile_barangay_options_are_available(): void
    {
        $barangay = $this->makeBarangay('Public');

        $this->getJson('/api/v1/auth/barangays')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $barangay->id,
                'name' => $barangay->barangay_name,
            ]);
    }

    public function test_mobile_registration_stores_selected_barangay(): void
    {
        $barangay = $this->makeBarangay('Registration');

        $email =
            'patch09.registration.'
            . Str::lower(Str::random(16))
            . '@example.test';

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Patch 09 Resident',
            'email' => $email,
            'contact_number' => '09123456789',
            'address' => 'Dao, Capiz',
            'barangay_id' => $barangay->id,
            'password' => 'TabangNow#2026Secure',
            'password_confirmation' =>
                'TabangNow#2026Secure',
        ])
            ->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'barangay_id' => $barangay->id,
        ]);
    }

    public function test_admin_can_change_own_residential_barangay(): void
    {
        $this->assertRoleCanChangeBarangay('admin');
    }

    public function test_official_can_change_own_residential_barangay(): void
    {
        $this->assertRoleCanChangeBarangay('official');
    }

    public function test_tanod_can_change_own_residential_barangay(): void
    {
        $this->assertRoleCanChangeBarangay('tanod');
    }

    public function test_resident_can_change_own_residential_barangay(): void
    {
        $this->assertRoleCanChangeBarangay('resident');
    }

    private function assertRoleCanChangeBarangay(string $role): void
    {
        $oldBarangay =
            $this->makeBarangay("Old {$role}");

        $newBarangay =
            $this->makeBarangay("New {$role}");

        $user = User::factory()->create([
            'name' => ucfirst($role) . ' User',
            'email' =>
                'patch09.'
                . $role
                . '.'
                . Str::lower(Str::random(16))
                . '@example.test',
            'role' => $role,
            'is_active' => true,
            'status' => true,
            'barangay_id' => $oldBarangay->id,
        ]);

        $user->refresh();

        $this->assertSame(
            (int) $oldBarangay->id,
            (int) $user->barangay_id
        );

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'barangay_id' => $oldBarangay->id,
        ]);

        $token = $user
            ->createToken(
                "patch09-{$role}",
                ['mobile']
            )
            ->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $user->id
            )
            ->assertJsonPath(
                'data.role',
                $role
            )
            ->assertJsonPath(
                'data.barangay_id',
                $oldBarangay->id
            )
            ->assertJsonFragment([
                'id' => $newBarangay->id,
                'name' => $newBarangay->barangay_name,
            ]);

        $this->withToken($token)
            ->postJson(
                '/api/v1/profile/update',
                [
                    'name' => $user->name,
                    'email' => $user->email,
                    'contact_number' =>
                        '09123456789',
                    'address' => 'Dao, Capiz',
                    'barangay_id' =>
                        $newBarangay->id,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $user->id
            )
            ->assertJsonPath(
                'data.barangay_id',
                $newBarangay->id
            );

        $user->refresh();

        $this->assertSame(
            (int) $newBarangay->id,
            (int) $user->barangay_id
        );

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'barangay_id' => $newBarangay->id,
        ]);
    }

    private function makeBarangay(string $label): Barangay
    {
        return Barangay::query()->create([
            'barangay_name' =>
                'Patch09 '
                . $label
                . ' '
                . Str::uuid(),
            'location' => 'Dao, Capiz',
        ]);
    }
}