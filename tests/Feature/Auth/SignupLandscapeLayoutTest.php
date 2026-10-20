<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class SignupLandscapeLayoutTest extends TestCase
{
    public function test_signup_layout_has_responsive_landscape_columns(): void
    {
        $layout = file_get_contents(
            resource_path('views/components/layouts/auth.blade.php')
        );

        $register = file_get_contents(
            resource_path('views/livewire/auth/register.blade.php')
        );

        $this->assertIsString($layout);
        $this->assertIsString($register);
        $this->assertStringContainsString(
            "request()->routeIs('register')",
            $layout
        );
        $this->assertStringContainsString(
            '.tn21-register-hero .tn21-card',
            $layout
        );
        $this->assertStringContainsString(
            'grid-template-columns: repeat(2, minmax(0, 1fr));',
            $layout
        );
        $this->assertStringContainsString(
            '@media (min-width: 900px)',
            $layout
        );
        $this->assertStringContainsString(
            'class="tn21-register-grid"',
            $register
        );
        $this->assertSame(
            2,
            substr_count($register, 'class="tn21-register-column"')
        );

        foreach ([
            'wire:model="name"',
            'wire:model="email"',
            'wire:model="contact_number"',
            'wire:model="address"',
            'wire:model="barangay_id"',
            'wire:model="date_of_birth"',
            'wire:model="age_confirmation"',
            'wire:model="password"',
            'wire:model="password_confirmation"',
        ] as $binding) {
            $this->assertStringContainsString($binding, $register);
        }
    }
}
