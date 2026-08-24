<?php

namespace Tests\Feature;

use Tests\TestCase;

class MobileVersionApiTest extends TestCase
{
    public function test_mobile_version_policy_is_public_and_uses_configured_values(): void
    {
        config()->set('tabangnow.mobile.version', '1.2.3');
        config()->set('tabangnow.mobile.build_number', 12);
        config()->set('tabangnow.mobile.minimum_supported_build_number', 10);
        config()->set(
            'tabangnow.mobile.update_message',
            'Please install the latest TabangNow release.'
        );

        $response = $this->getJson('/api/v1/mobile/version');

        $response
            ->assertOk()
            ->assertJsonPath('data.latest_version', '1.2.3')
            ->assertJsonPath('data.latest_build_number', 12)
            ->assertJsonPath('data.minimum_supported_build_number', 10)
            ->assertJsonPath(
                'data.message',
                'Please install the latest TabangNow release.'
            )
            ->assertJsonPath(
                'data.download_url',
                route('download')
            );

        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control')
        );
    }
}