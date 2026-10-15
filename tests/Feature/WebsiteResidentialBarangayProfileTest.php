<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebsiteResidentialBarangayProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_authenticated_user_profile_can_select_residential_barangay(): void
    {
        foreach (
            [
                'admin',
                'official',
                'tanod',
                'resident',
            ] as $role
        ) {
            $oldBarangay = $this->barangay(
                "Old {$role}"
            );

            $newBarangay = $this->barangay(
                "New {$role}"
            );

            $user = User::factory()->create([
                'role' => $role,
                'is_active' => true,
                'status' => true,
                'email_verified_at' => now(),
                'barangay_id' => $oldBarangay->id,
            ]);

            $this->actingAs($user);

            $this->get(route('profile.edit'))
                ->assertOk()
                ->assertSee(
                    'Barangay of Residence'
                )
                ->assertSee(
                    $oldBarangay->barangay_name
                )
                ->assertSee(
                    $newBarangay->barangay_name
                );

            $this->patch(
                route('profile.update'),
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
                ->assertRedirect(
                    route('profile.edit')
                );

            $this->assertDatabaseHas(
                'users',
                [
                    'id' => $user->id,
                    'barangay_id' =>
                        $newBarangay->id,
                ]
            );

            auth()->logout();
        }
    }

    public function test_profile_view_uses_barangay_row_objects_correctly(): void
    {
        $source = file_get_contents(
            resource_path(
                'views/profile/edit.blade.php'
            )
        );

        $this->assertIsString($source);

        $this->assertStringContainsString(
            '@foreach (($barangays ?? []) as $barangay)',
            $source
        );

        $this->assertStringContainsString(
            '$barangay->id',
            $source
        );

        $this->assertStringContainsString(
            '$barangay->barangay_name',
            $source
        );
    }

    private function barangay(
        string $label
    ): Barangay {
        return Barangay::query()->create([
            'barangay_name' =>
                'WebResidence '
                . $label
                . ' '
                . Str::uuid(),
            'location' => 'Dao, Capiz',
        ]);
    }
}