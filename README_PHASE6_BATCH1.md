# Phase 6 Batch 1 — Session Timeout and Cookie Baseline

This package contains no migration and no database data.

## Local `.env`

Set:

```env
SESSION_DRIVER=database
SESSION_LIFETIME=30
SESSION_EXPIRE_ON_CLOSE=true
SESSION_ENCRYPT=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_SECURE_COOKIE=false
AUTH_PASSWORD_TIMEOUT=900
```

Keep `SESSION_SECURE_COOKIE=false` while using local HTTP.
For production HTTPS, set it to `true` and use an HTTPS `APP_URL`.

Enabling `SESSION_ENCRYPT=true` can log out existing browser sessions once.
It does not delete application records.

## Validate

```bash
php artisan optimize:clear
php -l app/Http/Middleware/EnforceSessionTimeout.php
php -l bootstrap/app.php
php -l resources/views/livewire/auth/login.blade.php
php -l tests/Feature/Security/SessionTimeoutSecurityTest.php
php artisan test tests/Feature/Security/SessionTimeoutSecurityTest.php
php artisan test
```
