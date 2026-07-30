# Phase 9 Batch 1 — Query Safety Foundation

This package applies the first Phase 9 database-query hardening changes.

## Audit conclusion

The current application generally passes request values through Laravel query
builder bindings. The audit did not show a direct request value being
concatenated into a raw SQL statement.

Two issues still required correction:

1. Some internal table or column identifiers were interpolated into raw SQL.
   Those values currently came from internal schema checks, but SQL bindings
   cannot protect identifiers. A future change could accidentally make one of
   them request-controlled.
2. Search terms used `%` and `_` directly inside LIKE patterns. This is not SQL
   injection because Laravel binds the value, but users could use wildcard
   characters to broaden a search unexpectedly.

## New protections

- `SafeDatabaseIdentifier` accepts only simple table and column identifiers,
  then quotes them through the active database grammar.
- `SqlLikePattern` normalizes search text, limits it to 200 characters, escapes
  `%`, `_`, and the escape character, and always binds the final pattern.
- Incident, user-management, case-management, and tanod-roster searches now
  treat wildcard characters as literal user data.
- User-management role, online-status, and date filters are explicitly
  allowlisted before they enter a query.
- The dynamic barangay name column in IncidentController is validated and
  grammar-quoted before raw SQL use.

## Files

New:

- `app/Support/SafeDatabaseIdentifier.php`
- `app/Support/SqlLikePattern.php`
- `tests/Feature/Security/SqlInjectionAndQuerySafetyTest.php`

Modified:

- `app/Http/Controllers/IncidentController.php`
- `app/Http/Controllers/CaseManagementController.php`
- `app/Http/Controllers/UserManagementController.php`
- `app/Http/Controllers/TanodRosterController.php`

No migration, database data, route, view, sidebar, or layout change is included.

## Validation

```bash
php artisan optimize:clear

php -l app/Support/SafeDatabaseIdentifier.php
php -l app/Support/SqlLikePattern.php
php -l app/Http/Controllers/IncidentController.php
php -l app/Http/Controllers/CaseManagementController.php
php -l app/Http/Controllers/UserManagementController.php
php -l app/Http/Controllers/TanodRosterController.php
php -l tests/Feature/Security/SqlInjectionAndQuerySafetyTest.php

php artisan test tests/Feature/Security/SqlInjectionAndQuerySafetyTest.php
php artisan test
```

The test commands continue to use `tabangnow_test` through `phpunit.xml`.
