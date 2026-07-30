<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Support\SafeDatabaseIdentifier;
use App\Support\SqlLikePattern;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class SqlInjectionAndQuerySafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_identifier_guard_rejects_sql_fragments(): void
    {
        foreach ([
            'users; DROP TABLE users',
            'users.email DESC',
            'users.email --',
            'users.`email`',
            'users email',
            'users/email',
            '',
        ] as $identifier) {
            try {
                SafeDatabaseIdentifier::wrap(
                    $identifier
                );

                $this->fail(
                    "Unsafe identifier [{$identifier}] was accepted."
                );
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $wrapped = SafeDatabaseIdentifier::wrap(
            'users.email'
        );

        $this->assertStringContainsString(
            'users',
            $wrapped
        );

        $this->assertStringContainsString(
            'email',
            $wrapped
        );
    }

    public function test_like_pattern_treats_sql_wildcards_as_literal_data(): void
    {
        $this->assertSame(
            '%100!%!_complete!!%',
            SqlLikePattern::contains(
                '100%_complete!'
            )
        );

        $this->assertSame(
            'spaced search',
            SqlLikePattern::normalize(
                "  spaced\tsearch  "
            )
        );

        $this->assertNull(
            SqlLikePattern::contains(
                " \r\n\t "
            )
        );
    }

    public function test_sql_injection_search_payload_is_bound_and_does_not_broaden_results(): void
    {
        $admin = $this->activeUser(
            'admin',
            'Security Administrator'
        );

        $firstUser = $this->activeUser(
            'resident',
            'Unrelated Alpha Resident'
        );

        $secondUser = $this->activeUser(
            'resident',
            'Unrelated Bravo Resident'
        );

        $payload = "%' OR 1=1 --";

        $queries = [];

        DB::listen(
            function (QueryExecuted $query) use (&$queries): void {
                $queries[] = [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                ];
            }
        );

        $response = $this
            ->actingAs($admin)
            ->get(
                route(
                    'admin.users.index',
                    [
                        'search' => $payload,
                    ]
                )
            );

        $response
            ->assertOk()
            ->assertDontSee(
                $firstUser->name
            )
            ->assertDontSee(
                $secondUser->name
            );

        $this->assertTrue(
            Schema::hasTable('users')
        );

        $this->assertFalse(
            collect($queries)->contains(
                fn (array $query): bool => str_contains(
                    $query['sql'],
                    $payload
                )
            ),
            'The untrusted search payload was interpolated into SQL text.'
        );

        $expectedBinding = SqlLikePattern::contains(
            $payload
        );

        $this->assertTrue(
            collect($queries)
                ->flatMap(
                    fn (array $query): array => $query['bindings']
                )
                ->contains($expectedBinding),
            'The escaped search value was not found in query bindings.'
        );
    }

    public function test_percent_character_searches_for_a_literal_percent_sign(): void
    {
        $admin = $this->activeUser(
            'admin',
            'Query Administrator'
        );

        $literalPercentUser = $this->activeUser(
            'resident',
            'Coverage 100% Resident'
        );

        $ordinaryUser = $this->activeUser(
            'resident',
            'Coverage 100X Resident'
        );

        $response = $this
            ->actingAs($admin)
            ->get(
                route(
                    'admin.users.index',
                    [
                        'search' => '100%',
                    ]
                )
            );

        $response
            ->assertOk()
            ->assertSee(
                $literalPercentUser->name
            )
            ->assertDontSee(
                $ordinaryUser->name
            );
    }

    public function test_incident_barangay_identifier_is_guarded_before_raw_sql_use(): void
    {
        $source = file_get_contents(
            app_path(
                'Http/Controllers/IncidentController.php'
            )
        );

        $this->assertIsString(
            $source
        );

        $this->assertStringContainsString(
            'SafeDatabaseIdentifier::wrap',
            $source
        );

        $this->assertStringNotContainsString(
            'LOWER({$nameColumn})',
            $source
        );
    }

    private function activeUser(
        string $role,
        string $name
    ): User {
        /** @var User $user */
        $user = User::factory()->create([
            'name' => $name,
            'role' => $role,
            'is_active' => true,
            'status' => true,
        ]);

        return $user;
    }
}
