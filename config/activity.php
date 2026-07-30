<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Activity-log retention
    |--------------------------------------------------------------------------
    |
    | Automatic pruning is disabled until an authorised retention policy has
    | been approved. The command supports dry runs and requires --force before
    | deleting records.
    |
    */

    'pruning_enabled' => filter_var(
        env('ACTIVITY_LOG_PRUNING_ENABLED', false),
        FILTER_VALIDATE_BOOL
    ),

    'retention_days' => (int) env(
        'ACTIVITY_LOG_RETENTION_DAYS',
        365
    ),

    /*
    |--------------------------------------------------------------------------
    | Technical deletion guardrail
    |--------------------------------------------------------------------------
    |
    | The pruning command refuses retention periods shorter than this value,
    | even when a smaller --days option is supplied.
    |
    */

    'minimum_retention_days' => 90,

    'prune_batch_size' => (int) env(
        'ACTIVITY_LOG_PRUNE_BATCH_SIZE',
        1000
    ),

    'prune_time' => env(
        'ACTIVITY_LOG_PRUNE_TIME',
        '02:30'
    ),

    'failure_log_channel' => env(
        'ACTIVITY_LOG_FAILURE_CHANNEL',
        'security'
    ),

];
