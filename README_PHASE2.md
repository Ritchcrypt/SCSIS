# Phase 2 — Login Hardening

## Objective

Protect authentication from brute-force attempts, inactive-account access,
credential leakage, and session fixation while preserving the established login UI.

## Completed controls

- Normalized login input before authentication.
- Used Laravel authentication rather than custom plaintext password comparison.
- Preserved the functional **Remember me** option.
- Passed the remember flag into the authentication attempt.
- Regenerated the session after successful authentication.
- Applied login rate limiting and lockout handling.
- Avoided exposing whether a submitted account exists.
- Blocked inactive accounts from remaining authenticated.
- Invalidated the session and regenerated the CSRF token when access was revoked.
- Kept password values out of logs, validation output, and activity metadata.
- Preserved role-based post-login redirection.
- Maintained support for administrator, official/dao, tanod, and resident accounts.

## Remember-me behavior

When selected, Laravel issues a persistent remember cookie linked to the user's
`remember_token`. The password itself is not stored in the cookie.

Remembered access becomes invalid when the application rotates or clears the
remember token, including security-sensitive account and password operations.

## Main security behavior

Successful authentication:

```text
validate input
apply throttle rules
verify active account
authenticate
regenerate session
redirect by role
```

Failed authentication:

```text
increment throttle state
return a generic error
do not reveal account existence
do not log raw credentials
```

## Verification coverage

Later integrated tests confirmed login rendering, valid authentication, invalid-password rejection, logout behavior, inactive-account blocking, and authentication logging without raw credentials.

## Historical note

The final code and later audits preserve the Phase 2 outcome, but the exact early Phase 2 commit ID was not retained in the available documentation. No commit ID is invented here.
