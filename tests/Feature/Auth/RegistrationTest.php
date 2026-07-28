<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
    }

    public function test_new_users_can_register(): void
    {
        $password = 'TabangNow#2026Secure';
        $email = 'resident.test@example.com';

        Volt::test('auth.register')
            ->set('name', 'Resident Test')
            ->set('email', $email)
            ->set('contact_number', '09123456789')
            ->set('address', 'Poblacion, Dao, Capiz')
            ->set('password', $password)
            ->set('password_confirmation', $password)
            ->call('register')
            ->assertHasNoErrors();

        $user = User::query()
            ->where('email', $email)
            ->first();

        $this->assertNotNull($user);
        $this->assertSame('Resident Test', $user->name);
        $this->assertSame('resident', $user->role);
        $this->assertSame('09123456789', $user->contact_number);
        $this->assertSame('Poblacion, Dao, Capiz', $user->address);
        $this->assertFalse((bool) $user->is_active);
        $this->assertTrue(Hash::check($password, $user->password));

        /*
        |--------------------------------------------------------------------------
        | Pending account approval
        |--------------------------------------------------------------------------
        |
        | Publicly registered residents remain logged out until an administrator
        | activates the account.
        |
        */

        $this->assertGuest();
    }
}