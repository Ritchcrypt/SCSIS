<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RetiredCaseSchemaContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_database_does_not_recreate_retired_case_table(): void
    {
        $this->assertFalse(Schema::hasTable('case_records'));
    }
}
