# Phase 8 Batch 1 — Secure Upload Foundation

This package applies the first Phase 8 upload hardening changes.

## Main findings addressed

- Incident evidence, complaint evidence, complaint proofs, and profile photos
  were explicitly stored on the public disk.
- Authorised controller routes existed, but direct `/storage/...` paths could
  bypass those controller authorisation checks.
- Profile upload limits were inconsistent: 50 MB in Profile and 5 MB in User
  Management.
- Five 50 MB incident files could not fit inside the current 70 MB PHP POST
  limit.
- File validation relied mainly on extension/MIME validation without a shared
  signature check.
- Image pixel limits were inconsistent.

## New behaviour

- New sensitive uploads use the private `local` disk.
- Existing legacy public files remain readable only through authorised routes.
- Incident evidence is limited to five files of 10 MB each.
- Complaint evidence and proofs are limited to 10 MB.
- Profile photos remain limited to 5 MB.
- File signatures, actual MIME types, extensions, image dimensions, and image
  pixel counts are checked.
- Server-generated UUID filenames are used.
- Executable double-extension filenames such as `file.php.jpg` are rejected.
- Branding remains public because it is required before login, but it receives
  the same signature and image-decoding checks.
- No migration, database data, route, view, or sidebar change is included.

## Files

New:

- `app/Rules/SecureUploadedFile.php`
- `app/Services/SecureUploadService.php`
- `config/secure_uploads.php`
- `tests/Feature/Security/SecureUploadFoundationTest.php`

Modified:

- `app/Http/Controllers/IncidentController.php`
- `app/Http/Controllers/ResidentComplaintController.php`
- `app/Http/Controllers/ProfileController.php`
- `app/Http/Controllers/UserManagementController.php`
- `app/Http/Controllers/SystemBrandingController.php`

## Validation

```bash
php artisan optimize:clear

php -l app/Rules/SecureUploadedFile.php
php -l app/Services/SecureUploadService.php
php -l config/secure_uploads.php
php -l app/Http/Controllers/IncidentController.php
php -l app/Http/Controllers/ResidentComplaintController.php
php -l app/Http/Controllers/ProfileController.php
php -l app/Http/Controllers/UserManagementController.php
php -l app/Http/Controllers/SystemBrandingController.php
php -l tests/Feature/Security/SecureUploadFoundationTest.php

php artisan test tests/Feature/Security/SecureUploadFoundationTest.php
php artisan test
```

The test commands continue to use `tabangnow_test` through `phpunit.xml`.
