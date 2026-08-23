<?php

namespace Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ProductionBackupCommandTest extends TestCase
{
    public function test_production_backup_refuses_to_run_when_disabled(): void
    {
        Config::set('backup.enabled', false);

        $this->artisan('backup:production')
            ->expectsOutputToContain(
                'Production backups are disabled.'
            )
            ->assertExitCode(Command::FAILURE);
    }
}
