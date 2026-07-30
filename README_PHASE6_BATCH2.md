# Phase 6 Batch 2 — Session Revocation

This package changes only:

- `app/Http/Controllers/ProfileController.php`
- `resources/views/livewire/auth/reset-password.blade.php`
- `tests/Feature/Security/SessionRevocationSecurityTest.php`

It contains no migration and no database data.

## Security behaviour

- A profile password change keeps the current browser logged in.
- Other database sessions are revoked after a profile password change.
- Remember-me tokens and personal access tokens are invalidated.
- A completed email password reset revokes all existing sessions.
- A completed email password reset clears online presence.
- Admin deactivation continues to revoke all target sessions and reset tokens.
- Self-account deletion removes database sessions and reset tokens.

The admin “Send password reset link” action does not immediately log out the
target user. Revocation occurs when the password is actually changed. This
prevents an administrator or repeated email action from unnecessarily ending
valid sessions before the owner completes the reset.

## Validation

```bash
php artisan optimize:clear

php -l app/Http/Controllers/ProfileController.php
php -l resources/views/livewire/auth/reset-password.blade.php
php -l tests/Feature/Security/SessionRevocationSecurityTest.php

php artisan test tests/Feature/Security/SessionRevocationSecurityTest.php
php artisan test
```
