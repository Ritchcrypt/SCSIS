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
        api: [
            __DIR__ . '/../routes/api.php',
            __DIR__ . '/../app/Routing/mobile_emergency_api.php',
            __DIR__ . '/../app/Routing/public_branding_api.php',
        ],
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function (): void {
            /*
            |--------------------------------------------------------------------------
            | Mobile Emergency Website Routes
            |--------------------------------------------------------------------------
            |
            | The mobile registration and emergency API routes are loaded above as
            | canonical API route files. Keep only the authenticated website
            | emergency-response routes here.
            |
            */
            require base_path('routes/emergency_web.php');

            /*
            |--------------------------------------------------------------------------
            | Activity Log Browser Delete Action
            |--------------------------------------------------------------------------
            |
            | Keep the browser-only POST-backed destructive action separate from
            | the established REST DELETE route so website and mobile behavior can
            | stay aligned without changing the ActivityLog model's immutability.
            |
            */
            require base_path('routes/activity_log_web.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', [
            \App\Http\Middleware\PreventAuthenticatedPageCaching::class,
        ]);

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
        /*
        |--------------------------------------------------------------------------
        | TABANGNOW_EXPIRED_LOGOUT_REDIRECT
        |--------------------------------------------------------------------------
        |
        | When the session has already expired, the logout form contains an old
        | CSRF token. Redirect only that stale logout request to the login page.
        | Other CSRF failures keep Laravel's normal 419 response.
        |
        */

        $exceptions->render(function (
            \Illuminate\Session\TokenMismatchException $exception,
            \Illuminate\Http\Request $request
        ) {
            if ($request->expectsJson()) {
                return null;
            }

            $isLogoutRequest =
                $request->routeIs('logout')
                || (
                    $request->isMethod('post')
                    && $request->is('logout')
                );

            if (! $isLogoutRequest) {
                return null;
            }

            return redirect()
                ->route('login')
                ->with(
                    'status',
                    'Your session expired. Please sign in again.'
                );
        });
    })
    ->create();
