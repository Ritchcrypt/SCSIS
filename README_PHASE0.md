# Phase 0 — Safety, Git, and Test Isolation

## Objective

Establish a recoverable security-hardening workflow before changing authentication,
authorization, sessions, uploads, or database behavior.

## Completed safeguards

- Created and used the dedicated `security-hardening` branch.
- Preserved the working application while security changes were developed.
- Required a clean Git status before every major phase or merge.
- Used explicit file staging instead of broad commands such as `git add .`.
- Kept `storage/backups/` local-only and excluded from Git.
- Prohibited destructive database commands against the working database.
- Reserved `daosystem_db` for the working application data.
- Isolated automated tests from the working database.
- Used Git commits and tags as recovery checkpoints.
- Preserved established modules, routes, sidebar styling, and database records unless a security correction required a targeted change.

## Operational rules established

Never use these commands against the working database:

```bash
php artisan migrate:fresh
php artisan migrate:reset
php artisan db:wipe
```

Never stage or push:

```text
storage/backups/
.env
database dumps
credentials
private keys
test audit output files
```

Before a security change:

```bash
git status --short
git branch --show-current
php artisan test
```

After a security change:

```bash
php artisan optimize:clear
php artisan test
git diff --check
git status --short
```

## Recovery checkpoints

The completed hardening work was later tagged as:

```text
security-hardening-final-2026-07-30
```

The hardened branch was merged into `main` through merge commit:

```text
3c4f4ad Merge security hardening into main
```

## Historical note

The later audit files preserved the completed Phase 0 rules and final checkpoints,
but did not preserve a single dedicated Phase 0 commit ID. This document records
the completed safety process without inventing an unsupported commit reference.
