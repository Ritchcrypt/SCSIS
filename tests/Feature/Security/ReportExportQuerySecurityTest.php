<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Support\SafeDatabaseIdentifier;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class ReportExportQuerySecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_identifier_must_be_in_the_explicit_allowlist(): void
    {
        $this->assertSame(
            'case_records',
            SafeDatabaseIdentifier::approved(
                'case_records',
                [
                    'case_records',
                    'announcements',
                ]
            )
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        SafeDatabaseIdentifier::approved(
            'users',
            [
                'case_records',
                'announcements',
            ]
        );
    }

    public function test_report_period_payload_falls_back_without_entering_sql_text(): void
    {
        $admin = $this->activeAdmin();
        $payload = "week' OR 1=1 --";

        $queries = [];

        DB::listen(
            function (QueryExecuted $query) use (&$queries): void {
                $queries[] = $query->sql;
            }
        );

        $response = $this
            ->actingAs($admin)
            ->get(
                route(
                    'admin.reports.index',
                    [
                        'period' => $payload,
                    ]
                )
            );

        $response
            ->assertOk()
            ->assertViewHas(
                'period',
                'week'
            );

        $this->assertFalse(
            collect($queries)->contains(
                fn (string $sql): bool => str_contains(
                    $sql,
                    $payload
                )
            ),
            'The untrusted report period was interpolated into SQL text.'
        );
    }

    public function test_valid_report_period_is_preserved(): void
    {
        $admin = $this->activeAdmin();

        $response = $this
            ->actingAs($admin)
            ->get(
                route(
                    'admin.reports.index',
                    [
                        'period' => 'month',
                    ]
                )
            );

        $response
            ->assertOk()
            ->assertViewHas(
                'period',
                'month'
            );
    }

    public function test_user_csv_export_is_private_and_neutralizes_formula_cells(): void
    {
        $admin = $this->activeAdmin();

        User::factory()->create([
            'name' => '=2+2',
            'email' => 'formula@example.test',
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(
                route(
                    'admin.users.export'
                )
            );

        $response
            ->assertOk()
            ->assertHeader(
                'X-Content-Type-Options',
                'nosniff'
            );

        $cacheControl = (string) $response->headers->get(
            'Cache-Control'
        );

        $this->assertStringContainsString(
            'private',
            $cacheControl
        );

        $this->assertStringContainsString(
            'no-store',
            $cacheControl
        );

        $content = $response->streamedContent();

        $this->assertStringContainsString(
            "'=2+2",
            $content
        );
    }

    public function test_user_export_uses_a_cursor_instead_of_materializing_all_rows(): void
    {
        $source = file_get_contents(
            app_path(
                'Http/Controllers/UserManagementController.php'
            )
        );

        $this->assertIsString(
            $source
        );

        $exportStart = strpos(
            $source,
            'public function export'
        );

        $filteredQueryStart = strpos(
            $source,
            'private function filteredUsersQuery'
        );

        $this->assertNotFalse(
            $exportStart
        );

        $this->assertNotFalse(
            $filteredQueryStart
        );

        $exportSource = substr(
            $source,
            (int) $exportStart,
            (int) $filteredQueryStart - (int) $exportStart
        );

        $this->assertStringContainsString(
            '->cursor()',
            $exportSource
        );

        $this->assertStringNotContainsString(
            "->get();\n\n        \$barangays",
            $exportSource
        );
    }

    private function activeAdmin(): User
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'status' => true,
        ]);

        return $admin;
    }
}
