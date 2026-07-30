<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class ProductionHttpSecurityTest extends TestCase
{
    public function test_web_responses_include_baseline_security_headers(): void
    {
        $response = $this->get(
            route('login')
        );

        $response
            ->assertOk()
            ->assertHeader(
                'X-Content-Type-Options',
                'nosniff'
            )
            ->assertHeader(
                'X-Frame-Options',
                'SAMEORIGIN'
            )
            ->assertHeader(
                'Referrer-Policy',
                'strict-origin-when-cross-origin'
            )
            ->assertHeader(
                'Permissions-Policy'
            );
    }

    public function test_hsts_is_not_sent_over_plain_http(): void
    {
        config([
            'security.hsts.enabled' => true,
        ]);

        $this->get(
            route('login')
        )->assertHeaderMissing(
            'Strict-Transport-Security'
        );
    }

    public function test_hsts_is_sent_for_secure_requests_when_enabled(): void
    {
        config([
            'security.hsts.enabled' => true,
            'security.hsts.max_age' => 31536000,
            'security.hsts.include_subdomains' => false,
            'security.hsts.preload' => false,
        ]);

        $this->get(
            secure_url(
                route(
                    'login',
                    absolute: false
                )
            )
        )->assertHeader(
            'Strict-Transport-Security',
            'max-age=31536000'
        );
    }

    public function test_csp_is_disabled_until_explicitly_enabled(): void
    {
        config([
            'security.csp.enabled' => false,
        ]);

        $this->get(
            route('login')
        )
            ->assertHeaderMissing(
                'Content-Security-Policy'
            )
            ->assertHeaderMissing(
                'Content-Security-Policy-Report-Only'
            );
    }

    public function test_csp_can_start_in_report_only_mode(): void
    {
        config([
            'security.csp.enabled' => true,
            'security.csp.report_only' => true,
            'security.csp.policy' => "default-src 'self'",
        ]);

        $this->get(
            route('login')
        )
            ->assertHeader(
                'Content-Security-Policy-Report-Only',
                "default-src 'self'"
            )
            ->assertHeaderMissing(
                'Content-Security-Policy'
            );
    }
}
