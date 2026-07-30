# Phase 6 — Sessions, Cookies, CSRF, Timeouts, and Session Revocation

## Objective

Protect authenticated sessions from fixation, indefinite inactivity, stale
remembered access, cross-site request forgery, and unauthorized concurrent use.

## Completed controls

- Database-backed sessions.
- Session regeneration after login.
- Session invalidation and CSRF-token regeneration during logout or forced removal.
- POST-only logout behavior.
- CSRF protection for state-changing web routes.
- Password confirmation for sensitive actions.
- Configurable inactivity timeout middleware.
- Presence-heartbeat requests do not extend the authenticated inactivity window.
- Expired sessions are logged out and redirected safely.
- Session revocation after password changes.
- Full session revocation after password reset.
- Target-user session revocation after administrator deactivation.
- Authentication cleanup during self-account deletion.
- A protected “sign out other devices” operation.
- Remember-token rotation or invalidation during relevant security operations.
- Secure cookie configuration support for production HTTPS.
- `HttpOnly` session cookies and configurable `SameSite` behavior.
- Tests for timeout, revocation, CSRF, password confirmation, and cookie defaults.

## Middleware

```text
EnforceSessionTimeout
UpdateUserPresence
```

The timeout middleware tracks authenticated activity while excluding background presence-heartbeat behavior from extending the security timeout.

## Session configuration

```text
SESSION_DRIVER
SESSION_LIFETIME
SESSION_EXPIRE_ON_CLOSE
SESSION_ENCRYPT
SESSION_SECURE_COOKIE
SESSION_HTTP_ONLY
SESSION_SAME_SITE
```

## Security tests

```text
SessionTimeoutSecurityTest
SessionRevocationSecurityTest
OtherDeviceSessionSecurityTest
CsrfAndPasswordConfirmationSecurityTest
```

## Phase checkpoints

```text
33a5d6c Add Phase 6 session timeout and revocation security
351344d Complete Phase 6 session and CSRF security hardening
```

## Integrated verification

After the complete security-hardening branch was merged into `main`, the final integrated suite passed:

```text
130 tests
595 assertions
0 failures
```
