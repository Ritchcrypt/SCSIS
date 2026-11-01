<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\AdminTotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;
use Livewire\Volt\Volt as LivewireVolt;
use Tests\TestCase;

class AdminMfaCsrfRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_password_step_preserves_livewire_csrf_token(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $admin->forceFill([
            'mfa_secret' => Crypt::encryptString(app(AdminTotpService::class)->newSecret()),
            'mfa_enabled_at' => now(),
        ])->save();

        // The browser remains on the same Livewire page between password and MFA.
        // Rotating its CSRF token here causes the next Livewire request to fail with 419.
        $this->startSession();
        $csrfBefore = Session::token();
        $this->assertNotEmpty($csrfBefore);

        LivewireVolt::test('auth.login')
            ->set('email', $admin->email)
            ->set('password', 'password')
            ->call('login')
            ->assertSet('awaitingMfa', true);

        $this->assertSame($csrfBefore, Session::token());
        $this->assertGuest();
    }
}
