<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeaders
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        /** @var Response $response */
        $response = $next($request);

        if (! (bool) config('security.headers.enabled', true)) {
            return $response;
        }

        $this->setHeaderUnlessPresent(
            $response,
            'X-Content-Type-Options',
            'nosniff'
        );

        $this->setHeaderUnlessPresent(
            $response,
            'X-Frame-Options',
            (string) config(
                'security.headers.frame_options',
                'SAMEORIGIN'
            )
        );

        $this->setHeaderUnlessPresent(
            $response,
            'Referrer-Policy',
            (string) config(
                'security.headers.referrer_policy',
                'strict-origin-when-cross-origin'
            )
        );

        $this->setHeaderUnlessPresent(
            $response,
            'Permissions-Policy',
            (string) config(
                'security.headers.permissions_policy',
                'accelerometer=(), autoplay=(), camera=(), '
                    . 'geolocation=(self), gyroscope=(), microphone=(), '
                    . 'payment=(), usb=()'
            )
        );

        $this->applyStrictTransportSecurity(
            $request,
            $response
        );

        $this->applyContentSecurityPolicy(
            $response
        );

        return $response;
    }

    private function applyStrictTransportSecurity(
        Request $request,
        Response $response
    ): void {
        if (
            ! $request->isSecure()
            || ! (bool) config('security.hsts.enabled', false)
        ) {
            return;
        }

        $maximumAge = max(
            0,
            (int) config(
                'security.hsts.max_age',
                31536000
            )
        );

        $value = 'max-age=' . $maximumAge;

        if ((bool) config(
            'security.hsts.include_subdomains',
            false
        )) {
            $value .= '; includeSubDomains';
        }

        if ((bool) config(
            'security.hsts.preload',
            false
        )) {
            $value .= '; preload';
        }

        $this->setHeaderUnlessPresent(
            $response,
            'Strict-Transport-Security',
            $value
        );
    }

    private function applyContentSecurityPolicy(
        Response $response
    ): void {
        if (! (bool) config('security.csp.enabled', false)) {
            return;
        }

        $policy = trim(
            (string) config(
                'security.csp.policy',
                ''
            )
        );

        if ($policy === '') {
            return;
        }

        $header = (bool) config(
            'security.csp.report_only',
            true
        )
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        $this->setHeaderUnlessPresent(
            $response,
            $header,
            $policy
        );
    }

    private function setHeaderUnlessPresent(
        Response $response,
        string $name,
        string $value
    ): void {
        if (
            $value !== ''
            && ! $response->headers->has($name)
        ) {
            $response->headers->set(
                $name,
                $value
            );
        }
    }
}
