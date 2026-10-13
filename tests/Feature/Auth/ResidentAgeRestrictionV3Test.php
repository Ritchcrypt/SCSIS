<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ResidentAgeRestrictionV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_resident_registration_screen_has_18_plus_gate(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Resident age requirement')
            ->assertSee('Date of birth')
            ->assertSee('18 years old or older')
            ->assertSee('I certify that the date of birth I entered is accurate');
    }

    public function test_adult_resident_can_register_with_attestation(): void
    {
        $dateOfBirth =
            now()
                ->subYears(20)
                ->subDay()
                ->toDateString();

        Volt::test('auth.register')
            ->set('name', 'Adult Resident')
            ->set(
                'email',
                'adult.age.gate@example.com'
            )
            ->set(
                'contact_number',
                '09123456789'
            )
            ->set(
                'address',
                'Dao, Capiz'
            )
            ->set(
                'date_of_birth',
                $dateOfBirth
            )
            ->set(
                'age_confirmation',
                true
            )
            ->set(
                'password',
                'TabangNow#2026Secure'
            )
            ->set(
                'password_confirmation',
                'TabangNow#2026Secure'
            )
            ->call('register')
            ->assertHasNoErrors();

        $user =
            User::query()
                ->where(
                    'email',
                    'adult.age.gate@example.com'
                )
                ->first();

        $this->assertNotNull(
            $user
        );

        $this->assertSame(
            $dateOfBirth,
            (string) $user->date_of_birth
        );

        $this->assertNotNull(
            $user->age_attested_at
        );

        $this->assertSame(
            'resident',
            $user->role
        );

        $this->assertFalse(
            (bool) $user->is_active
        );
    }

    public function test_underage_declared_dob_is_rejected_server_side(): void
    {
        Volt::test('auth.register')
            ->set(
                'name',
                'Underage Resident'
            )
            ->set(
                'email',
                'underage.age.gate@example.com'
            )
            ->set(
                'contact_number',
                '09123456789'
            )
            ->set(
                'address',
                'Dao, Capiz'
            )
            ->set(
                'date_of_birth',
                now()
                    ->subYears(17)
                    ->toDateString()
            )
            ->set(
                'age_confirmation',
                true
            )
            ->set(
                'password',
                'TabangNow#2026Secure'
            )
            ->set(
                'password_confirmation',
                'TabangNow#2026Secure'
            )
            ->call('register')
            ->assertHasErrors([
                'date_of_birth',
            ]);

        $this->assertDatabaseMissing(
            'users',
            [
                'email' =>
                    'underage.age.gate@example.com',
            ]
        );
    }

    public function test_attestation_checkbox_is_required(): void
    {
        Volt::test('auth.register')
            ->set(
                'name',
                'Unchecked Resident'
            )
            ->set(
                'email',
                'unchecked.age.gate@example.com'
            )
            ->set(
                'contact_number',
                '09123456789'
            )
            ->set(
                'address',
                'Dao, Capiz'
            )
            ->set(
                'date_of_birth',
                now()
                    ->subYears(24)
                    ->toDateString()
            )
            ->set(
                'age_confirmation',
                false
            )
            ->set(
                'password',
                'TabangNow#2026Secure'
            )
            ->set(
                'password_confirmation',
                'TabangNow#2026Secure'
            )
            ->call('register')
            ->assertHasErrors([
                'age_confirmation',
            ]);
    }

    public function test_implausible_declared_dob_is_rejected(): void
    {
        Volt::test('auth.register')
            ->set(
                'name',
                'Implausible Resident'
            )
            ->set(
                'email',
                'implausible.age.gate@example.com'
            )
            ->set(
                'contact_number',
                '09123456789'
            )
            ->set(
                'address',
                'Dao, Capiz'
            )
            ->set(
                'date_of_birth',
                now()
                    ->subYears(121)
                    ->toDateString()
            )
            ->set(
                'age_confirmation',
                true
            )
            ->set(
                'password',
                'TabangNow#2026Secure'
            )
            ->set(
                'password_confirmation',
                'TabangNow#2026Secure'
            )
            ->call('register')
            ->assertHasErrors([
                'date_of_birth',
            ]);
    }
}
