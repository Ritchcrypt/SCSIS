<?php

namespace Tests\Unit;

use App\Support\AccountActionReadiness;
use PHPUnit\Framework\TestCase;

class AccountActionReadinessTest extends TestCase
{
    public function test_missing_table_is_extracted_without_exposing_raw_sql(): void
    {
        $error = new \RuntimeException(
            "SQLSTATE[42S02]: Base table or view not found: 1146 Table 'mydb.case_records' doesn't exist (Connection: mysql, SQL: secret values)"
        );
        $this->assertSame('case_records', AccountActionReadiness::missingDatabaseObject($error));
    }

    public function test_unknown_error_does_not_claim_a_missing_table(): void
    {
        $this->assertNull(AccountActionReadiness::missingDatabaseObject(new \RuntimeException('Other error')));
    }
}
