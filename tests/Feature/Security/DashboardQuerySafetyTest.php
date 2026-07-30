<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Support\SafeDatabaseIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class DashboardQuerySafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_table_allowlist_rejects_unapproved_identifiers(): void
    {
        $approved = SafeDatabaseIdentifier::approved(
            'employees',
            [
                'tanod_profiles',
                'employees',
                'tanods',
                'tanod_rosters',
                'users',
            ]
        );

        $this->assertSame(
            'employees',
            $approved
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        SafeDatabaseIdentifier::approved(
            'employees UNION SELECT password FROM users',
            [
                'tanod_profiles',
                'employees',
                'tanods',
                'tanod_rosters',
                'users',
            ]
        );
    }

    public function test_admin_dashboard_guards_dynamic_table_and_column_identifiers(): void
    {
        $source = file_get_contents(
            app_path(
                'Http/Controllers/Admin/DashboardController.php'
            )
        );

        $this->assertIsString(
            $source
        );

        $this->assertStringContainsString(
            'SafeDatabaseIdentifier::approved',
            $source
        );

        $this->assertStringContainsString(
            'SafeDatabaseIdentifier::wrap',
            $source
        );

        $this->assertStringNotContainsString(
            'LOWER({$table}.role)',
            $source
        );

        $this->assertStringNotContainsString(
            'TRIM({$table}.{$column})',
            $source
        );
    }

    public function test_role_dashboard_does_not_build_raw_aliases_from_table_variables(): void
    {
        $source = file_get_contents(
            app_path(
                'Http/Controllers/RoleDashboardController.php'
            )
        );

        $this->assertIsString(
            $source
        );

        $this->assertStringContainsString(
            'SafeDatabaseIdentifier::approved',
            $source
        );

        $this->assertStringContainsString(
            "\$taskTable . '.title as task_title'",
            $source
        );

        $this->assertMatchesRegularExpression(
            '/\\$taskTable\\s*\\.\\s*\'\\.description as task_description\'/',
            $source
        );

        $this->assertStringNotContainsString(
            'DB::raw($taskTable',
            $source
        );
    }

    public function test_admin_dashboard_still_renders_after_query_hardening(): void
    {
        $admin = $this->activeUser(
            'admin'
        );

        $this
            ->actingAs($admin)
            ->get(
                route(
                    'admin.dashboard'
                )
            )
            ->assertOk();
    }

    public function test_official_dashboard_still_renders_after_query_hardening(): void
    {
        $official = $this->activeUser(
            'official'
        );

        $this
            ->actingAs($official)
            ->get(
                route(
                    'official.dashboard'
                )
            )
            ->assertOk();
    }

    private function activeUser(
        string $role
    ): User {
        /** @var User $user */
        $user = User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'status' => true,
        ]);

        return $user;
    }
}
