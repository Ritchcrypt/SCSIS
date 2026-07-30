# Activity Logs Admin Module

## Exact scope

This package adds a read-only administrator Activity Logs module.

The only navigation change is one **Activity Logs** link inserted immediately
below **User Management** in `resources/views/layouts/admin.blade.php`.

It does not redesign, reorder, restyle, or replace the established sidebar.

## Added files

- `app/Http/Controllers/Admin/ActivityLogController.php`
- `resources/views/admin/activity-logs/index.blade.php`
- `resources/views/admin/activity-logs/show.blade.php`
- `tests/Feature/Security/ActivityLogModuleTest.php`
- `tools/install_activity_logs_module.php`

## Patched files

The installer makes two minimal edits:

- `routes/web.php`
  - Adds the ActivityLogController import.
  - Adds two GET-only routes inside the existing administrator route group.

- `resources/views/layouts/admin.blade.php`
  - Adds one navigation link immediately below User Management.

## Security behavior

- Active administrator access only.
- Controller and policy authorization.
- GET-only routes.
- No create, edit, update, or delete endpoints.
- Bound and escaped search values through `SqlLikePattern`.
- Validated category, event, actor, date, and pagination filters.
- Metadata is redacted again before display.
- Existing immutable ActivityLog model and policy remain unchanged.
- No migration or database-record modification.

## Installation

Extract the package into the Laravel project root and run:

```bash
php tools/install_activity_logs_module.php
```

Then remove the temporary installer:

```bash
rm -f tools/install_activity_logs_module.php
```

Clear caches:

```bash
php artisan optimize:clear
```

Run syntax checks:

```bash
php -l app/Http/Controllers/Admin/ActivityLogController.php
php -l tests/Feature/Security/ActivityLogModuleTest.php
php -l routes/web.php
```

Verify routes:

```bash
php artisan route:list --name=admin.activity-logs
```

Run targeted tests:

```bash
php artisan test tests/Feature/Security/ActivityLogModuleTest.php
```

Run the complete suite:

```bash
php artisan test
```
