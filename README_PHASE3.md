# Phase 3 — Registration Hardening

## Objective

Prevent role escalation, weak-password registration, inconsistent account state,
and unsafe user creation while keeping public resident registration functional.

## Completed controls

- Public registration creates a resident account only.
- Public requests cannot assign administrator, official, dao, or tanod roles.
- Server-side validation is applied to name, email, password, and supported profile fields.
- Email input is normalized before storage.
- Email uniqueness is enforced.
- Passwords are hashed by the `User` model.
- A centralized strong-password policy is applied.
- New accounts receive safe role and account-state defaults.
- `is_active` and legacy `status` behavior are aligned without deleting users.
- Email-verification behavior remains supported.
- Registration does not expose internal role or authorization controls.
- Tests use isolated test data rather than the working `daosystem_db` records.

## Central password policy

```text
minimum 12 characters
mixed uppercase and lowercase letters
numbers
symbols
```

This policy is reused by registration, password reset, profile password changes, and administrator-created accounts.

## Safe user defaults

```text
role: resident
is_active: true
status: true
theme_mode: system
```

Privileged accounts remain an administrator-controlled User Management operation.

## Verification coverage

Later integrated tests confirmed registration rendering, successful resident registration, password-policy application, safe role assignment, normalized and unique email behavior, and isolated test-database execution.

## Historical note

The exact Phase 3 commit ID was not preserved in the later audit material. This README documents the completed and verified behavior only.
