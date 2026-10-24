<?php

namespace Tests\Feature\Security;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class ExpiredLogoutRedirectTest extends TestCase
{
    public function test_expired_web_logout_redirects_to_login_instead_of_419(): void
    {
        $response = app(ExceptionHandler::class)->render(
            Request::create('/logout', 'POST'),
            new TokenMismatchException
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(route('login'), $response->headers->get('Location'));
    }

    public function test_other_csrf_protected_posts_keep_their_419_response(): void
    {
        $response = app(ExceptionHandler::class)->render(
            Request::create('/profile', 'POST'),
            new TokenMismatchException
        );

        $this->assertSame(419, $response->getStatusCode());
    }

    public function test_json_logout_csrf_failure_keeps_419_response(): void
    {
        $request = Request::create('/logout', 'POST', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response = app(ExceptionHandler::class)->render(
            $request,
            new TokenMismatchException
        );

        $this->assertSame(419, $response->getStatusCode());
    }

    public function test_logout_get_does_not_bypass_csrf_error(): void
    {
        $response = app(ExceptionHandler::class)->render(
            Request::create('/logout', 'GET'),
            new TokenMismatchException
        );

        $this->assertSame(419, $response->getStatusCode());
    }

    public function test_unrelated_http_419_is_not_swallowed(): void
    {
        $response = app(ExceptionHandler::class)->render(
            Request::create('/logout', 'POST'),
            new HttpException(419)
        );

        $this->assertSame(419, $response->getStatusCode());
    }

    public function test_normal_web_logout_remains_post_only_and_csrf_protected(): void
    {
        $route = app('router')->getRoutes()->getByName('logout');

        $this->assertNotNull($route);
        $this->assertSame(['POST'], $route->methods());

        $middleware = app('router')->gatherRouteMiddleware($route);

        $this->assertContains(
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            $middleware
        );
    }
}
