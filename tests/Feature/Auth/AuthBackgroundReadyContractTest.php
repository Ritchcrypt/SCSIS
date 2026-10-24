<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

final class AuthBackgroundReadyContractTest extends TestCase
{
    public function test_public_auth_pages_share_one_preloaded_photo_with_decoded_reveal(): void
    {
        $source = file_get_contents(resource_path('views/components/layouts/auth.blade.php'));

        $this->assertIsString($source);

        $this->assertStringContainsString(
            'TABANGNOW_PUBLIC_AUTH_LIGHT_PREPAINT',
            $source
        );

        $this->assertStringContainsString(
            '$authBackgroundUrl = asset(',
            $source
        );

        $this->assertStringContainsString(
            'href="{{ $authBackgroundUrl }}"',
            $source
        );

        $this->assertStringContainsString(
            'src="{{ $authBackgroundUrl }}"',
            $source
        );

        $this->assertStringContainsString(
            'loading="eager"',
            $source
        );

        $this->assertStringContainsString(
            'fetchpriority="high"',
            $source
        );

        $this->assertStringContainsString(
            'decoding="async"',
            $source
        );

        $this->assertStringContainsString(
            'image.decode()',
            $source
        );

        $this->assertStringContainsString(
            "image.classList.add('is-ready')",
            $source
        );

        $this->assertSame(
            1,
            substr_count($source, 'class="tn21-background-photo"')
        );

        $this->assertStringNotContainsString(
            'dao-login-landscape.svg',
            $source
        );

        $this->assertStringNotContainsString(
            'auth-background-20261022_121831_429.jpg',
            $source
        );
    }
}
