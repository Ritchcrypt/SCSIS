# Phase 7 Batch 1 — Authentication Activity Logging

This package introduces the activity-log infrastructure and authentication
security audit events.

## Changed files

- `app/Providers/AppServiceProvider.php`
- `app/Http/Middleware/EnsureUserIsActive.php`
- `app/Http/Middleware/EnforceSessionTimeout.php`

## New files

- `app/Models/ActivityLog.php`
- `app/Services/ActivityLogger.php`
- `app/Listeners/RecordAuthenticationActivity.php`
- `database/migrations/2026_07_30_112500_create_activity_logs_table.php`
- `tests/Feature/Security/AuthenticationActivityLogTest.php`

## Events recorded

- Successful login
- Failed login
- Login lockout
- Logout
- Inactivity timeout logout
- Inactive-account logout
- Resident registration
- Completed password reset
- Email verification

Passwords, reset tokens, cookies, authorisation headers, and raw failed-login
identifiers are not written to the audit table.

The migration adds one new table and does not alter or delete existing tables
or application records.

## Validation

```bash
php artisan optimize:clear

php -l app/Providers/AppServiceProvider.php
php -l app/Http/Middleware/EnsureUserIsActive.php
php -l app/Http/Middleware/EnforceSessionTimeout.php
php -l app/Models/ActivityLog.php
php -l app/Services/ActivityLogger.php
php -l app/Listeners/RecordAuthenticationActivity.php
php -l database/migrations/2026_07_30_112500_create_activity_logs_table.php
php -l tests/Feature/Security/AuthenticationActivityLogTest.php

php artisan event:list
php artisan test tests/Feature/Security/AuthenticationActivityLogTest.php
php artisan test

php artisan migrate --pretend
php artisan migrate
```

The test commands continue to use `tabangnow_test` through `phpunit.xml`.
The final two migration commands apply the new table to the local development
database configured in `.env`.
