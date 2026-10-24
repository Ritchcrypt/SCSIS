<?php

namespace Tests\Feature\Auth;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ResidentSignupBarangaySelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_renders_available_barangays(): void
    {
        $barangay = Barangay::query()->firstOrCreate(
            ['barangay_name' => 'Agtambi'],
            ['location' => 'Dao, Capiz']
        );

        $this->get('/register')
            ->assertOk()
            ->assertSee('Barangay of Residence')
            ->assertSee('wire:model="barangay_id"', false)
            ->assertSee(
                '<option value="' . $barangay->id . '">Agtambi</option>',
                false
            );
    }

    public function test_signup_rejects_missing_barangay_and_creates_no_account(): void
    {
        $this->validRegistration('missing.barangay@example.com')
            ->set('barangay_id', '')
            ->call('register')
            ->assertHasErrors(['barangay_id' => 'required']);

        $this->assertDatabaseMissing('users', [
            'email' => 'missing.barangay@example.com',
        ]);
    }

    public function test_signup_rejects_unknown_barangay_and_creates_no_account(): void
    {
        $this->validRegistration('invalid.barangay@example.com')
            ->set('barangay_id', '999999999')
            ->call('register')
            ->assertHasErrors(['barangay_id' => 'exists']);

        $this->assertDatabaseMissing('users', [
            'email' => 'invalid.barangay@example.com',
        ]);
    }

    private function validRegistration(string $email)
    {
        return Volt::test('auth.register')
            ->set('name', 'Signup Barangay Resident')
            ->set('email', $email)
            ->set('contact_number', '09123456789')
            ->set('address', 'Dao, Capiz')
            ->set('date_of_birth', now()->subYears(25)->toDateString())
            ->set('age_confirmation', true)
            ->set('password', 'TabangNow#2026Secure')
            ->set('password_confirmation', 'TabangNow#2026Secure');
    }
}
