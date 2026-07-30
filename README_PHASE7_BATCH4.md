# Phase 7 Batch 4 — Audit Integrity and Retention

This is the final backend batch for Phase 7.

## Modified files

- `app/Models/ActivityLog.php`
- `app/Services/ActivityLogger.php`
- `app/Providers/AppServiceProvider.php`
- `app/Http/Controllers/UserManagementController.php`
- `config/logging.php`
- `routes/console.php`

## New files

- `app/Console/Commands/PruneActivityLogs.php`
- `app/Policies/ActivityLogPolicy.php`
- `config/activity.php`
- `tests/Feature/Security/ActivityLogIntegrityAndRetentionTest.php`

## Security behaviour

- Existing audit records cannot be updated or deleted through Eloquent.
- User deletion no longer rewrites historical `actor_id` or `target_user_id`.
- Only active administrators may view future Activity Logs pages.
- No web route or sidebar item is added yet.
- Audit database-write failures fall back to `storage/logs/security.log`
  without copying exception messages, credentials, request payloads, cookies,
  or metadata values.
- Retention pruning supports dry runs and requires `--force`.
- Retention periods shorter than 90 days are rejected.
- Automatic pruning is disabled by default.

## Optional environment settings

Do not enable automatic deletion until the retention policy is approved.

```env
ACTIVITY_LOG_PRUNING_ENABLED=false
ACTIVITY_LOG_RETENTION_DAYS=365
ACTIVITY_LOG_PRUNE_BATCH_SIZE=1000
ACTIVITY_LOG_PRUNE_TIME=02:30
ACTIVITY_LOG_FAILURE_CHANNEL=security
SECURITY_LOG_LEVEL=warning
SECURITY_LOG_DAYS=30
```

## Validation

```bash
php artisan optimize:clear

php -l app/Models/ActivityLog.php
php -l app/Services/ActivityLogger.php
php -l app/Providers/AppServiceProvider.php
php -l app/Http/Controllers/UserManagementController.php
php -l app/Console/Commands/PruneActivityLogs.php
php -l app/Policies/ActivityLogPolicy.php
php -l config/activity.php
php -l config/logging.php
php -l routes/console.php
php -l tests/Feature/Security/ActivityLogIntegrityAndRetentionTest.php

php artisan list | grep "activity-logs:prune"
php artisan schedule:list

php artisan activity-logs:prune --days=365 --dry-run

php artisan test tests/Feature/Security/ActivityLogIntegrityAndRetentionTest.php
php artisan test
```

The test commands continue to use `tabangnow_test` through `phpunit.xml`.
No migration or existing database data is included in this package.
