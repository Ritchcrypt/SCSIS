<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

/*
|--------------------------------------------------------------------------
| Activity-log retention
|--------------------------------------------------------------------------
|
| Automatic pruning remains disabled until ACTIVITY_LOG_PRUNING_ENABLED=true.
| The command still requires --force and runs without overlapping executions.
|
*/

Schedule::command('activity-logs:prune --force')
    ->dailyAt(
        (string) config(
            'activity.prune_time',
            '02:30'
        )
    )
    ->when(
        fn (): bool => (bool) config(
            'activity.pruning_enabled',
            false
        )
    )
    ->withoutOverlapping();
