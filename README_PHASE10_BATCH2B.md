# Phase 10 Batch 2B — HTTP and Reverse-Proxy Hardening

## Scope

This package adds:

- Baseline browser security headers.
- Optional HSTS that is emitted only for secure requests.
- Optional Content Security Policy in report-only mode.
- Explicit reverse-proxy trust configuration through `TRUSTED_PROXIES`.
- A production environment template containing no credentials.
- Five focused security tests.

## Deliberately not changed

- No database migration or database record.
- No route or controller behavior.
- No Blade view, sidebar, navbar, or visual layout.
- No upload or backup file.
- No Composer or npm dependency file.
- No deployment-provider configuration.

## Reverse proxy rule

Leave `TRUSTED_PROXIES` empty for direct local hosting.

Set it to `*` only when every public request reaches the application through a
managed platform proxy. Otherwise provide a comma-separated list of approved
proxy addresses.

## CSP rollout rule

CSP remains disabled by default because the established application contains
inline scripts and may use map resources. During deployment:

1. Enable `SECURITY_CSP_ENABLED=true`.
2. Keep `SECURITY_CSP_REPORT_ONLY=true`.
3. Inspect browser violations and update the policy.
4. Switch report-only to false only after all modules pass smoke testing.

## HSTS rollout rule

Do not enable HSTS until the permanent production domain and all required
subdomains work through HTTPS. Start without `includeSubDomains` or `preload`.
