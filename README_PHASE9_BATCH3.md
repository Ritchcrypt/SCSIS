# Phase 9 Batch 3 — Dashboard Query Hardening

This package hardens the remaining dynamic dashboard identifiers identified by
the Phase 9 Batch 3 audit.

## Audit findings addressed

- `Admin/DashboardController` interpolated internal table and column variables
  into raw SQL expressions used for tanod on-duty counts.
- `RoleDashboardController` built raw select aliases from `$taskTable`.
- The role dashboard selected a response status column dynamically without an
  explicit allowlist.

The audit did not show request data being concatenated into those expressions.
This batch adds defence in depth so a future refactor cannot turn those internal
variables into an SQL injection path.

## Changes

Modified:

- `app/Http/Controllers/Admin/DashboardController.php`
- `app/Http/Controllers/RoleDashboardController.php`

New:

- `tests/Feature/Security/DashboardQuerySafetyTest.php`

## Security behaviour

- Dashboard source tables are restricted to:
  - `tanod_profiles`
  - `employees`
  - `tanods`
  - `tanod_rosters`
  - `users`
- Tanod duty columns are restricted to:
  - `duty_status`
  - `status`
  - `availability_status`
  - `duty_state`
- Approved dynamic identifiers are quoted with the active Laravel database
  grammar before use in raw expressions.
- Tanod task response and task table names are explicitly approved.
- Dynamic task title and description aliases use Query Builder identifier
  wrapping instead of `DB::raw($taskTable ...)`.
- The response status column is restricted to `response_status` or `status`.
- Fixed raw expressions such as `NULL as status` remain fixed application code.

## Validation

```bash
php artisan optimize:clear

php -l app/Http/Controllers/Admin/DashboardController.php
php -l app/Http/Controllers/RoleDashboardController.php
php -l tests/Feature/Security/DashboardQuerySafetyTest.php

php artisan test tests/Feature/Security/DashboardQuerySafetyTest.php
php artisan test tests/Feature/Security/SqlInjectionAndQuerySafetyTest.php
php artisan test tests/Feature/Security/ReportExportQuerySecurityTest.php
php artisan test
```

The test suite continues to use `tabangnow_test` through `phpunit.xml`.
