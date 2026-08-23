<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Activity-log retention
|--------------------------------------------------------------------------
|
| Automatic pruning remains disabled until
| ACTIVITY_LOG_PRUNING_ENABLED=true.
|
| The command still requires --force and runs without overlapping
| executions.
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

/*
|--------------------------------------------------------------------------
| Production off-site backups
|--------------------------------------------------------------------------
|
| The command is a no-op unless BACKUP_ENABLED=true. The Railway runtime
| must run Laravel's scheduler for this schedule to execute automatically.
|
*/

Schedule::command('backup:production')
    ->dailyAt(
        (string) config(
            'backup.schedule_time',
            '03:15'
        )
    )
    ->timezone(
        (string) config(
            'app.timezone',
            'Asia/Manila'
        )
    )
    ->when(
        fn (): bool => (bool) config(
            'backup.enabled',
            false
        )
    )
    ->withoutOverlapping(180);
