# Phase 8 Batch 2 — Request Validation and Controller Hardening

This package applies conservative request-data and state-integrity hardening
without changing established routes, views, sidebar layout, or database schema.

## Findings addressed

- A profile email change preserved the previous email-verification timestamp.
- Administrator-driven email changes also preserved the previous verification.
- Email uniqueness checks did not consistently use the same lowercase value
  that was stored.
- Complaint contact numbers accepted arbitrary text.
- Complaint status updates were not row-locked.
- Closed and cancelled tanod tasks could be switched between terminal states.
- Incident escalation accepted arbitrary agency strings.
- Incident creation and status updates accepted inactive lookup records.
- Incident evidence delivery referenced an undefined legacy path variable.
- Tanod appointment dates could be set in the future.
- Case contact and case-number inputs lacked basic character restrictions.

## Files modified

- `app/Http/Controllers/IncidentController.php`
- `app/Http/Controllers/ProfileController.php`
- `app/Http/Controllers/UserManagementController.php`
- `app/Http/Controllers/ResidentComplaintController.php`
- `app/Http/Controllers/TanodTaskController.php`
- `app/Http/Controllers/TanodRosterController.php`
- `app/Http/Controllers/CaseManagementController.php`

## New test

- `tests/Feature/Security/RequestValidationAndStateSecurityTest.php`

## Security behaviour

- Changed emails are lowercase-normalized before validation and storage.
- Changing an email clears `email_verified_at`.
- Reset tokens for a user's old email are removed after a self-service change.
- Complaint ownership, initial status, and submission time remain controlled by
  the server.
- Complaint status updates and tanod task terminal transitions are row-locked.
- Only open tanod tasks may be closed or cancelled.
- Incident categories and statuses must be active.
- Escalation agencies must come from the established server-side agency list.
- Evidence delivery uses the resolved secure-storage path and private no-store
  caching.
- No migration or existing database data is included.

## Validation

```bash
php artisan optimize:clear

php -l app/Http/Controllers/IncidentController.php
php -l app/Http/Controllers/ProfileController.php
php -l app/Http/Controllers/UserManagementController.php
php -l app/Http/Controllers/ResidentComplaintController.php
php -l app/Http/Controllers/TanodTaskController.php
php -l app/Http/Controllers/TanodRosterController.php
php -l app/Http/Controllers/CaseManagementController.php
php -l tests/Feature/Security/RequestValidationAndStateSecurityTest.php

php artisan test tests/Feature/Security/RequestValidationAndStateSecurityTest.php
php artisan test
```

The test commands continue to use `tabangnow_test` through `phpunit.xml`.
