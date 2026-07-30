# Phase 8 Batch 1 — Cache-Control Correction

This correction fixes the remaining SecureUploadFoundationTest failure.

## Cause

Laravel/Symfony's BinaryFileResponse can prepare file responses with a public
cache directive. Supplying a raw Cache-Control header inside response()->file()
was not sufficient in this environment.

## Fix

- Construct the file response first.
- Call setPrivate() after construction.
- Set the one-hour browser max age with setMaxAge(3600).
- Test Cache-Control directives semantically because directive order may vary.
- Explicitly assert that the response does not contain a public directive.

## Files

- app/Http/Controllers/UserManagementController.php
- tests/Feature/Security/SecureUploadFoundationTest.php

## Validation

```bash
php artisan optimize:clear
php -l app/Http/Controllers/UserManagementController.php
php -l tests/Feature/Security/SecureUploadFoundationTest.php
php artisan test tests/Feature/Security/SecureUploadFoundationTest.php
php artisan test
```
