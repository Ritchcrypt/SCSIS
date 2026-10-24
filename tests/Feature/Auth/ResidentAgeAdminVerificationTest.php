<?php

namespace Tests\Feature\Auth;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ResidentAgeAdminVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_page_requires_an_18_plus_birth_date(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Resident age requirement')
            ->assertSee('Date of birth')
            ->assertSee('18 years old or older')
            ->assertSee('I certify that the date of birth I entered is accurate');
    }

    public function test_under_18_resident_is_rejected_before_account_creation(): void
    {
        Volt::test('auth.register')
            ->set('name', 'Underage Resident')
            ->set(
                'email',
                'under18.verification@example.com'
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
                    'under18.verification@example.com',
            ]
        );
    }

    public function test_exactly_18_year_old_resident_can_register_and_age_is_derived(): void
    {
        $dob =
            now()
                ->subYears(18)
                ->toDateString();

        $barangay = Barangay::query()->firstOrCreate(
            ['barangay_name' => 'Duyoc'],
            ['location' => 'Dao, Capiz']
        );

        Volt::test('auth.register')
            ->set('name', 'Adult Resident')
            ->set(
                'email',
                'adult.verification@example.com'
            )
            ->set(
                'contact_number',
                '09123456789'
            )
            ->set(
                'address',
                'Dao, Capiz'
            )
            ->set('barangay_id', (string) $barangay->id)
            ->set(
                'date_of_birth',
                $dob
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

        $resident =
            User::query()
                ->where(
                    'email',
                    'adult.verification@example.com'
                )
                ->firstOrFail();

        $this->assertSame(
            $dob,
            $resident
                ->date_of_birth
                ->toDateString()
        );

        $this->assertSame(
            18,
            $resident->resident_age
        );

        $this->assertNotNull(
            $resident->age_attested_at
        );
        $this->assertSame($barangay->id, (int) $resident->barangay_id);

        $this->assertFalse(
            (bool) $resident->is_active
        );
    }

    public function test_admin_can_see_resident_age_verification_on_list_and_profile(): void
    {
        $admin =
            User::factory()
                ->create([
                    'role' => 'admin',
                    'is_active' => true,
                    'status' => true,
                ]);

        $resident =
            User::factory()
                ->create([
                    'name' => 'Verified Resident',
                    'email' =>
                        'verified.resident@example.com',
                    'role' => 'resident',
                    'is_active' => false,
                    'status' => false,
                    'date_of_birth' =>
                        now()
                            ->subYears(25)
                            ->subDays(10)
                            ->toDateString(),
                    'age_attested_at' => now(),
                ]);

        $this->actingAs($admin)
            ->get(
                route('admin.users.index')
            )
            ->assertOk()
            ->assertSee('Resident Age')
            ->assertSee('25 yrs')
            ->assertSee('18+ gate passed');

        $this->actingAs($admin)
            ->get(
                route(
                    'admin.users.show',
                    $resident
                )
            )
            ->assertOk()
            ->assertSee('Resident Age Verification')
            ->assertSee('Current Age')
            ->assertSee('25 years old')
            ->assertSee('Passed 18+ signup gate');
    }
}
