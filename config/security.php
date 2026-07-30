<?php

return [
    'headers' => [
        'enabled' => env(
            'SECURITY_HEADERS_ENABLED',
            true
        ),

        'frame_options' => env(
            'SECURITY_FRAME_OPTIONS',
            'SAMEORIGIN'
        ),

        'referrer_policy' => env(
            'SECURITY_REFERRER_POLICY',
            'strict-origin-when-cross-origin'
        ),

        'permissions_policy' => env(
            'SECURITY_PERMISSIONS_POLICY',
            'accelerometer=(), autoplay=(), camera=(), '
                . 'geolocation=(self), gyroscope=(), microphone=(), '
                . 'payment=(), usb=()'
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Strict Transport Security
    |--------------------------------------------------------------------------
    |
    | Enable this only after the production domain is permanently available
    | through HTTPS. The header is sent only for secure requests.
    |
    */

    'hsts' => [
        'enabled' => env(
            'SECURITY_HSTS_ENABLED',
            false
        ),

        'max_age' => (int) env(
            'SECURITY_HSTS_MAX_AGE',
            31536000
        ),

        'include_subdomains' => env(
            'SECURITY_HSTS_INCLUDE_SUBDOMAINS',
            false
        ),

        'preload' => env(
            'SECURITY_HSTS_PRELOAD',
            false
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | CSP starts disabled and in report-only mode because the established
    | application contains inline scripts and may load map resources. Enable
    | report-only mode during deployment testing, review violations, and only
    | then switch to enforcement.
    |
    */

    'csp' => [
        'enabled' => env(
            'SECURITY_CSP_ENABLED',
            false
        ),

        'report_only' => env(
            'SECURITY_CSP_REPORT_ONLY',
            true
        ),

        'policy' => env(
            'SECURITY_CSP_POLICY',
            "default-src 'self'; "
                . "base-uri 'self'; "
                . "object-src 'none'; "
                . "frame-ancestors 'self'; "
                . "form-action 'self'; "
                . "img-src 'self' data: blob: https:; "
                . "font-src 'self' data:; "
                . "style-src 'self' 'unsafe-inline'; "
                . "script-src 'self' 'unsafe-inline'; "
                . "connect-src 'self' https: wss:"
        ),
    ],
];
