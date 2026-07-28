<?php

namespace Tests\Feature\Security;

use App\Models\CaseRecord;
use App\Models\Incident;
use App\Models\ResidentComplaint;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RbacAndRouteSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_routes_have_no_duplicate_method_and_uri_pairs(): void
    {
        $seen = [];
        $duplicates = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($route->methods() as $method) {
                $key = $method . ' ' . $route->uri();

                if (isset($seen[$key])) {
                    $duplicates[] = $key;
                }

                $seen[$key] = true;
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($duplicates)),
            'Duplicate HTTP method and URI registrations were found.'
        );
    }

    public function test_registered_route_names_are_unique(): void
    {
        $seen = [];
        $duplicates = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if (! $name) {
                continue;
            }

            if (isset($seen[$name])) {
                $duplicates[] = $name;
            }

            $seen[$name] = true;
        }

        $this->assertSame(
            [],
            array_values(array_unique($duplicates)),
            'Duplicate route names were found.'
        );
    }

    public function test_sensitive_admin_routes_require_authentication_active_account_and_admin_role(): void
    {
        foreach ([
            'admin.reports.index',
            'admin.cases.index',
            'admin.users.index',
            'admin.system-branding.edit',
            'admin.users.presence',
            'admin.incidents.destroy',
        ] as $routeName) {
            $this->assertProtectedRoute($routeName, 'admin');
        }
    }

    public function test_sensitive_official_routes_require_the_official_or_dao_role(): void
    {
        foreach ([
            'official.map.index',
            'official.tanods.index',
            'official.emergency-mode.store',
            'official.resident-complaints.update-status',
        ] as $routeName) {
            $this->assertProtectedRoute($routeName, 'official,dao');
        }
    }

    public function test_sensitive_tanod_and_resident_routes_require_the_correct_role(): void
    {
        $this->assertProtectedRoute('tanod.tanod-alerts.index', 'tanod');
        $this->assertProtectedRoute('tanod.tanod-tasks.respond', 'tanod');
        $this->assertProtectedRoute('resident.resident-complaints.store', 'resident');
        $this->assertProtectedRoute('resident.incidents.store', 'resident');
    }

    public function test_cross_role_requests_are_forbidden_before_the_controller_runs(): void
    {
        $resident = $this->activeUser('resident');
        $official = $this->activeUser('official');
        $tanod = $this->activeUser('tanod');

        $this->actingAs($resident)
            ->get(route('admin.system-branding.edit'))
            ->assertForbidden();

        $this->actingAs($official)
            ->get(route('admin.reports.index'))
            ->assertForbidden();

        $this->actingAs($tanod)
            ->get(route('official.map.index'))
            ->assertForbidden();

        $this->actingAs($resident)
            ->get(route('tanod.tanod-alerts.index'))
            ->assertForbidden();
    }

    public function test_inactive_accounts_are_logged_out_and_blocked(): void
    {
        $inactiveUser = $this->activeUser('resident', false);

        $response = $this
            ->actingAs($inactiveUser)
            ->get('/dashboard');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_notification_policy_blocks_cross_user_idor_access(): void
    {
        $owner = $this->activeUser('resident');
        $otherUser = $this->activeUser('resident');

        $notification = new UserNotification();
        $notification->forceFill([
            'user_id' => $owner->id,
        ]);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $notification));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $notification));
        $this->assertTrue(Gate::forUser($owner)->allows('delete', $notification));

        $this->assertTrue(Gate::forUser($otherUser)->denies('view', $notification));
        $this->assertTrue(Gate::forUser($otherUser)->denies('update', $notification));
        $this->assertTrue(Gate::forUser($otherUser)->denies('delete', $notification));
    }

    public function test_resident_complaint_policy_blocks_cross_resident_idor_access(): void
    {
        $owner = $this->activeUser('resident');
        $otherResident = $this->activeUser('resident');
        $official = $this->activeUser('official');

        $complaint = new ResidentComplaint();
        $complaint->forceFill([
            'resident_id' => $owner->id,
        ]);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $complaint));
        $this->assertTrue(Gate::forUser($otherResident)->denies('view', $complaint));
        $this->assertTrue(Gate::forUser($official)->allows('view', $complaint));
        $this->assertTrue(Gate::forUser($official)->allows('update', $complaint));
        $this->assertTrue(Gate::forUser($official)->denies('delete', $complaint));
    }

    public function test_incident_policy_blocks_cross_resident_idor_access(): void
    {
        $owner = $this->activeUser('resident');
        $otherResident = $this->activeUser('resident');
        $admin = $this->activeUser('admin');

        $incident = new Incident();
        $incident->forceFill([
            'reporter_id' => $owner->id,
        ]);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $incident));
        $this->assertTrue(Gate::forUser($otherResident)->denies('view', $incident));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $incident));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $incident));
    }

    public function test_admin_only_gates_and_case_policy_reject_non_admin_users(): void
    {
        $admin = $this->activeUser('admin');
        $official = $this->activeUser('official');

        $this->assertTrue(Gate::forUser($admin)->allows('viewReports'));
        $this->assertTrue(Gate::forUser($admin)->allows('manageSystemBranding'));
        $this->assertTrue(Gate::forUser($admin)->allows('viewUserPresence'));

        $this->assertTrue(Gate::forUser($official)->denies('viewReports'));
        $this->assertTrue(Gate::forUser($official)->denies('manageSystemBranding'));
        $this->assertTrue(Gate::forUser($official)->denies('viewUserPresence'));
        $this->assertTrue(Gate::forUser($official)->denies('viewAny', CaseRecord::class));
    }

    private function assertProtectedRoute(string $routeName, string $expectedRoles): void
    {
        $route = Route::getRoutes()->getByName($routeName);

        $this->assertInstanceOf(
            LaravelRoute::class,
            $route,
            "Expected route [{$routeName}] was not registered."
        );

        $middleware = collect($route->gatherMiddleware())
            ->map(static fn (mixed $value): string => (string) $value);

        $this->assertTrue(
            $middleware->contains(
                static fn (string $value): bool => $value === 'auth'
                    || str_contains($value, 'Authenticate')
            ),
            "Route [{$routeName}] does not require authentication."
        );

        $this->assertTrue(
            $middleware->contains(
                static fn (string $value): bool => $value === 'active.user'
                    || str_contains($value, 'EnsureUserIsActive')
            ),
            "Route [{$routeName}] does not require an active account."
        );

        $this->assertTrue(
            $middleware->contains(
                static fn (string $value): bool => str_contains($value, 'role:' . $expectedRoles)
                    || str_contains($value, 'RoleMiddleware:' . $expectedRoles)
            ),
            "Route [{$routeName}] does not require role [{$expectedRoles}]."
        );
    }

    private function activeUser(string $role, bool $active = true): User
    {
        /** @var User $user */
        $user = User::factory()->create([
            'role' => $role,
            'is_active' => $active,
            'status' => $active,
        ]);

        return $user;
    }
}
