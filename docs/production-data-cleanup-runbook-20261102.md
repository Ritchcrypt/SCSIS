# TabangNow website: production operational cleanup release gate (2026-11-02)

**Release hold — DO NOT MERGE OR DEPLOY while any gate remains open.**
Production Railway is pinned to successful website commit `f768644e2f086e4eebb2f109913ff9d93eaac5fa`.
This is a human-reviewed operations procedure. It is **not** a migration, scheduled task, or auto-deploy action.

## What is allowed for cleanup
Only the allowlisted operational tables in `ProductionOperationalCleanup::DELETE_ORDER`, including their related evidence/message/response/pivot tables. Delete by ordinary SQL DELETE inside one database transaction, with foreign keys enabled. Do not drop tables or reset auto-increment values. The command **never deletes files on disk**.

## What MUST be preserved
All `users`, including every admin/official/tanod/resident, and user passwords, MFA enrollment, recovery codes, profile photos and settings; role profile/employee/resident tables; barangays, lookup values and emergency hotlines; mobile devices and tokens; `system_settings`, the branding logo and auth background; audit logs and deleted-data recovery archives; database migrations, sessions and queues.

No normal application request invokes this command. It is not scheduled. You must use it manually after verifying production evidence.

## Verified blockers (as of November 2, 2026)
1. The Google Drive backup folder exists, but the **newest observed encrypted database and uploads archives are dated October 12, 2026**. They are stale for a November 2 cleanup. No fresh verified backup/restore has been demonstrated.
2. The connected Railway application cannot run arbitrary commands or MySQL SQL. The owner must use authenticated Railway CLI / SSH or the Railway database dashboard to obtain a read-only preview and create/restore-test a current backup.
3. The runtime must have the `mysqldump` binary for `php artisan backup:production --no-retention` to succeed. It was **not confirmed** installed; an earlier production build failed due to a malformed APT package list and a later fix removed those packages.
4. Current production record counts, actual schema and FK dependencies have not been enumerated. Never assume all records are test records.
5. Active incidents, SOS signals, open tasks and pending complaints must be reviewed and resolved; the cleanup command refuses to delete unresolved operations.
6. Production secret configuration is redacted to the connected app, so backup encryption/Drive credentials and session/security values must be verified locally without publishing them.

## Manual gate sequence — each step has to pass
1. **Read-only inventory and classification**: verify the current MySQL database and list record counts; distinguish real records from test/demo records. Never export PII into GitHub issues.
2. **Off-site backup**: create a fresh production MySQL dump and uploads archive, encrypted off-site, without retention pruning. Verify file sizes, encryption, integrity, and that the backup corresponds to the current production database.
3. **Restore drill**: restore the backup into a **separate disposable MySQL database and a separate temporary file directory**, then check tables, user counts and representative data. Never restore over live Railway.
4. **Review scope**: confirm all live operations are resolved, all protected accounts/users will remain, and preview data does not include real resident incidents that must be retained.
5. **Maintenance window**: only after backup/restore verification, stop application writes with Laravel maintenance mode, run the read-only cleanup preview, and record its SHA256 snapshot. The preview is:
   `php artisan tabangnow:operational-cleanup`
6. **Apply only with exact same snapshot**, a separately verified backup reference, the literal authorization `DELETE_OPERATIONS_KEEP_ALL_USERS`, and production maintenance mode. Do not apply if the preview differs or any FK/unrecognized table is detected.
7. **After apply**: verify every `users` account ID, role, password hash, MFA secret/recovery codes and profiles remain unchanged; verify no operational rows and app dashboards, complaints, incident reporting, notification creation and MFA login work.
8. **Leave maintenance** only after smoke testing and preserving the backup reference and audit event record. Keep application code release separate; if CI or smoke tests fail, do not deploy.

## Remote CLI facts
Railway documentation supports `railway ssh -p PROJECT -s SCSIS -e production -- COMMAND`. It requires Railway login/SSH key setup. A remote read-only binary check is:
`railway ssh -p b1d8c978-3c20-49ad-a6e2-d836e2aaca42 -s SCSIS -e production -- sh -lc 'command -v mysqldump || true'`

No command shown in this document performs a production deletion. The cleanup command is currently **only in a draft PR** and cannot be executed on the existing deployment. It must not be promoted until all gates pass.

## Stopped actions
- Do **not** run `migrate:fresh`, `db:wipe`, `TRUNCATE`, `DROP`, `FOREIGN_KEY_CHECKS=0`, `git reset --hard` or any forced push.
- Do **not** assume the old Google Drive archives are current or restorable.
- Do **not** merge PR #30 or accept a Railway deployment while release hold is in effect.
