# Phase 1 — Authentication and Role Audit

## Objective

Map the existing authentication, role, route, middleware, and account-state design
before applying hardening changes.

## Audited application roles

The application recognizes these roles:

```text
admin
official
dao
tanod
resident
```

`dao` is treated as an official-role alias where established application behavior requires it.

## Areas reviewed

- Laravel `web` authentication guard.
- Login, logout, registration, password-reset, password-confirmation, and email-verification routes.
- Role-based dashboard redirection.
- `User` role helpers and active-account behavior.
- Route groups for administrator, official, tanod, and resident access.
- Existing role middleware and active-user middleware.
- Controller authorization patterns.
- User Management account creation and status behavior.
- Notification, incident, complaint, tanod, report, map, branding, and case routes.
- Database columns related to role, status, `is_active`, sessions, and `remember_token`.

## Important findings carried forward

- Authorization could not rely only on whether a sidebar link was visible.
- Sensitive actions required route middleware plus controller or policy checks.
- The application used both `official` and `dao`, so hardening had to preserve that established alias.
- Existing user records used `status`; later migrations added and repaired `is_active` without deleting user data.
- Role defaults had to be enforced server-side rather than accepted from a public registration request.
- The test environment required database-engine compatibility fixes before the security suite could reliably run.

## Result

Phase 1 produced the authoritative security map used by Phases 2 through 6. It was primarily an audit and planning phase; no unsupported early-phase commit ID is claimed in this document.
