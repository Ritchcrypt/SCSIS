cat > routes/console.php <<'PHP'
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
