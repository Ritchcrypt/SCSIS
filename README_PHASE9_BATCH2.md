# Phase 9 Batch 2 — Report and Export Query Hardening

This package hardens dynamic report queries and large CSV exports.

## Findings addressed

- Report helpers used dynamic table and foreign-key identifiers selected at
  runtime. The candidates were internal, but identifiers cannot use SQL
  parameter bindings and therefore should still be explicitly allowlisted.
- The severity report used MySQL-specific `FIELD(...)` raw ordering.
- User CSV export materialized the complete result set with `get()`.
- CSV downloads did not explicitly prohibit shared caching.

## Changes

- Adds `SafeDatabaseIdentifier::validate()` and
  `SafeDatabaseIdentifier::approved()`.
- Allowlists report table, foreign-key, and dynamic ordering identifiers.
- Replaces `FIELD(...)` ordering with a bound `CASE` expression.
- Uses `selectRaw()` only for constant aggregate expressions.
- Streams user exports with `cursor()` instead of loading every row.
- Keeps CSV formula-injection neutralization.
- Marks CSV exports `private` and `no-store`.
- Adds report-period injection and export-security tests.

## Files

Modified:

- `app/Support/SafeDatabaseIdentifier.php`
- `app/Http/Controllers/ReportController.php`
- `app/Http/Controllers/UserManagementController.php`

New:

- `tests/Feature/Security/ReportExportQuerySecurityTest.php`

No migration, route, view, sidebar, database data, or storage file is included.

## Validation

```bash
php artisan optimize:clear

php -l app/Support/SafeDatabaseIdentifier.php
php -l app/Http/Controllers/ReportController.php
php -l app/Http/Controllers/UserManagementController.php
php -l tests/Feature/Security/ReportExportQuerySecurityTest.php

php artisan test tests/Feature/Security/ReportExportQuerySecurityTest.php
php artisan test tests/Feature/Security/SqlInjectionAndQuerySafetyTest.php
php artisan test
```
