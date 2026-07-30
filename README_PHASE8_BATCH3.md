# Phase 8 Batch 3 — Private File Lifecycle Security

This package hardens private-file delivery, rollback cleanup, committed
deletion cleanup, and legacy public-file migration.

## Findings addressed

- The Laravel local disk still had `serve => true`, registering framework
  storage GET/PUT routes that are unnecessary because private files already use
  authorised application controllers.
- Incident evidence files could remain orphaned when a later database operation
  rolled back.
- Incident and complaint deletion removed physical files inside database
  transactions. A database rollback could restore the records after the files
  had already been permanently deleted.
- Complaint evidence and proof files could remain orphaned if database insertion
  or notification creation failed.
- Self-service profile updates could leave a newly stored profile photo behind
  if the database update failed.
- Self-account deletion did not remove the user's profile photo.
- The Evidence model generated direct public `/storage/...` URLs.
- Complaint file responses relied on a raw Cache-Control header rather than
  applying private/no-store directives to the final BinaryFileResponse.
- Legacy sensitive files may still exist under the public disk.

## New files

- `app/Console/Commands/MigrateLegacySensitiveUploads.php`
- `tests/Feature/Security/PrivateFileLifecycleSecurityTest.php`

## Modified files

- `config/filesystems.php`
- `config/secure_uploads.php`
- `app/Services/SecureUploadService.php`
- `app/Http/Controllers/IncidentController.php`
- `app/Http/Controllers/ResidentComplaintController.php`
- `app/Http/Controllers/ProfileController.php`
- `app/Models/Evidence.php`

## Behaviour

- Laravel's automatic local-disk serving routes are disabled.
- Uploads created before a database failure are deleted during rollback cleanup.
- Physical files are deleted only after related database deletions commit.
- Private complaint responses use `private`, `no-store`, and `nosniff`.
- Evidence URLs use the authorised controller route.
- Self-account deletion removes the user's private profile photo.
- A controlled command can move legacy sensitive public files to private
  storage without changing stored database paths.
- `system-branding/` is intentionally excluded because the logo must remain
  publicly available before login.
- No migration, view, route, sidebar, or database-data change is included.

## Legacy migration safety

First run a dry run:

```bash
php artisan secure-uploads:migrate-legacy --dry-run
```

Do not use `--force` until the dry-run count has been reviewed.

The actual migration command is:

```bash
php artisan secure-uploads:migrate-legacy --force
```

It moves only:

- `incidents/evidence/`
- `resident-complaints/`
- `profile-photos/`

The command does not overwrite a different private file. It reports a conflict
instead.

## Validation

```bash
php artisan optimize:clear

php -l app/Console/Commands/MigrateLegacySensitiveUploads.php
php -l app/Services/SecureUploadService.php
php -l app/Http/Controllers/IncidentController.php
php -l app/Http/Controllers/ResidentComplaintController.php
php -l app/Http/Controllers/ProfileController.php
php -l app/Models/Evidence.php
php -l config/filesystems.php
php -l config/secure_uploads.php
php -l tests/Feature/Security/PrivateFileLifecycleSecurityTest.php

php artisan list | grep "secure-uploads:migrate-legacy"
php artisan route:list | grep -E "storage/\\{path\\}|storage.local"

php artisan secure-uploads:migrate-legacy --dry-run

php artisan test tests/Feature/Security/PrivateFileLifecycleSecurityTest.php
php artisan test
```

After `optimize:clear`, the storage route grep should return no output.
Tests continue to use `tabangnow_test`.
