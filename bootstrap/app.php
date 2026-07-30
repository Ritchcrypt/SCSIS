<?php

use App\Http\Middleware\EnforceSessionTimeout;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\UpdateUserPresence;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
        |--------------------------------------------------------------------------
        | Reverse-proxy HTTPS detection
        |--------------------------------------------------------------------------
        |
        | Leave TRUSTED_PROXIES empty for direct local hosting. For a managed
        | platform where every public request enters through its proxy, set the
        | value to "*" or to a comma-separated list of approved proxy addresses.
        |
        */

        $trustedProxies = trim(
            (string) env(
                'TRUSTED_PROXIES',
                ''
            )
        );

        if ($trustedProxies !== '') {
            $proxyAddresses = $trustedProxies === '*'
                ? '*'
                : array_values(
                    array_filter(
                        array_map(
                            'trim',
                            explode(
                                ',',
                                $trustedProxies
                            )
                        )
                    )
                );

            $middleware->trustProxies(
                at: $proxyAddresses,
                headers: Request::HEADER_X_FORWARDED_FOR
                    | Request::HEADER_X_FORWARDED_HOST
                    | Request::HEADER_X_FORWARDED_PORT
                    | Request::HEADER_X_FORWARDED_PROTO
            );
        }

        $middleware->web(append: [
            EnforceSessionTimeout::class,
            UpdateUserPresence::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'active.user' => \App\Http\Middleware\EnsureUserIsActive::class,
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
