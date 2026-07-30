<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CsrfAndPasswordConfirmationSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD_CONFIRMATION_TEST_URI =
        '/_security-test/password-confirmed';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware([
            'web',
            'auth',
            'password.confirm',
        ])->get(
            self::PASSWORD_CONFIRMATION_TEST_URI,
            fn () => response('confirmed')
        );

        /*
        |--------------------------------------------------------------------------
        | Refresh route lookups after registering the test-only route
        |--------------------------------------------------------------------------
        |
        | The application routes are already loaded before this test's setUp
        | method runs. Refreshing lookups ensures the newly registered route is
        | available to the router during the request.
        |
        */

        app('router')
            ->getRoutes()
            ->refreshNameLookups();

        app('router')
            ->getRoutes()
            ->refreshActionLookups();
    }

    public function test_secure_session_defaults_are_enabled(): void
    {
        $this->assertSame(
            30,
            (int) config('session.lifetime')
        );

        $this->assertTrue(
            (bool) config('session.expire_on_close')
        );

        $this->assertTrue(
            (bool) config('session.encrypt')
        );

        $this->assertTrue(
            (bool) config('session.http_only')
        );

        $this->assertContains(
            config('session.same_site'),
            [
                'lax',
                'strict',
            ]
        );

        $this->assertSame(
            900,
            (int) config('auth.password_timeout')
        );
    }

    public function test_logout_route_accepts_post_only(): void
    {
        $route = app('router')
            ->getRoutes()
            ->getByName('logout');

        $this->assertNotNull($route);

        $this->assertSame(
            ['POST'],
            $route->methods()
        );
    }

    public function test_critical_state_changing_routes_include_csrf_middleware(): void
    {
        $routeNames = [
            'theme-preference.update',
            'notifications.open',
            'profile.update',
            'profile.password.update',
            'profile.other-sessions.destroy',
            'profile.self-delete',
            'admin.system-branding.update',
            'admin.users.store',
            'admin.users.update',
            'admin.users.activate',
            'admin.users.deactivate',
            'admin.users.reset-password',
            'admin.users.destroy',
        ];

        foreach ($routeNames as $routeName) {
            $route = app('router')
                ->getRoutes()
                ->getByName($routeName);

            $this->assertNotNull(
                $route,
                "Missing expected route [{$routeName}]."
            );

            /*
            |--------------------------------------------------------------------------
            | Resolve middleware groups and aliases
            |--------------------------------------------------------------------------
            |
            | Route::gatherMiddleware() may contain the "web" group alias.
            | Router::gatherRouteMiddleware() expands it into the actual
            | middleware classes, including ValidateCsrfToken.
            |
            */

            $resolvedMiddleware = app('router')
                ->gatherRouteMiddleware($route);

            $this->assertContains(
                ValidateCsrfToken::class,
                $resolvedMiddleware,
                "Route [{$routeName}] is missing CSRF middleware."
            );
        }
    }

    public function test_password_confirmation_is_required_when_no_recent_confirmation_exists(): void
    {
        /** @var User $user */
        $user = $this->activeResident();

        $response = $this
            ->actingAs($user)
            ->get(self::PASSWORD_CONFIRMATION_TEST_URI);

        $response->assertRedirect(
            route('password.confirm')
        );
    }

    public function test_recent_password_confirmation_allows_access(): void
    {
        /** @var User $user */
        $user = $this->activeResident();

        $response = $this
            ->actingAs($user)
            ->withSession([
                'auth.password_confirmed_at' => time(),
            ])
            ->get(self::PASSWORD_CONFIRMATION_TEST_URI);

        $response
            ->assertOk()
            ->assertSee('confirmed');
    }

    public function test_expired_password_confirmation_requires_confirmation_again(): void
    {
        config([
            'auth.password_timeout' => 900,
        ]);

        /** @var User $user */
        $user = $this->activeResident();

        $response = $this
            ->actingAs($user)
            ->withSession([
                'auth.password_confirmed_at' => time() - 901,
            ])
            ->get(self::PASSWORD_CONFIRMATION_TEST_URI);

        $response->assertRedirect(
            route('password.confirm')
        );
    }

    private function activeResident(): User
    {
        /** @var User $user */
        $user = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
        ]);

        return $user;
    }
}
