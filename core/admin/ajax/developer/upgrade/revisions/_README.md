# Upgrade revisions

Each `N.php` is one schema/data migration. It runs in the admin AJAX upgrade
context (or via the `core/admin/migrate-run.php` CLI) with the `$cms`, `$admin`,
and `SERVER_ROOT` globals available, must be idempotent (guard every DDL with
`CREATE TABLE IF NOT EXISTS` / `SHOW COLUMNS … LIKE`), and ends by echoing
`BigTree::json([...])`.

## Self-recording convention (017 Phase 2b — current)

As of revision 505 there is a migration ledger (`bigtree_migrations`). NEW
revisions self-record around their work and **no longer write the
`bigtree-internal-revision` integer themselves** — `finish()` mirrors it.

Start from [`_template.php`](_template.php) (the idempotency checklist + the
begin/finish boilerplate):

```php
// At the TOP, before any work (idempotent; safe on every page of a batched run):
\BigTree\Services\MigrationService::begin(N);

// ... guarded, idempotent DDL + (batched) backfill ...

// On completion (single-shot: at the end; batched: only the {complete:true} branch):
\BigTree\Services\MigrationService::finish(N);

echo BigTree::json(["complete" => true, "response" => "Short migration name (N)"]);
```

- `begin(N)` writes a `success=0` "in-flight" row (with the real
  `checksumFor(N)`), so a crash before `finish()` leaves a resume signal.
- `finish(N)` flips it to `success=1`, records `duration_ms`, and mirrors the
  legacy integer to the highest applied revision (clamped so it never lowers).
- Do **NOT** call `$admin->updateInternalSettingValue("bigtree-internal-revision", N)`
  in a new revision, and do **NOT** use `record()` (that is the 505 backfill
  primitive). The `checksum` written by `begin()` is what Phase 2a drift
  detection (`MigrationService::verifyIntegrity()`) verifies later.

Do NOT retrofit the historical revisions (≤505); revision 505 backfills them.

## Running migrations

- **Browser**: the legacy AJAX upgrade flow (`scripts.php`) still drives each
  `revisions/N.php` page-by-page — unchanged.
- **CLI / headless**: `php core/admin/migrate-run.php` applies every pending
  revision (ascending), single-shot and batched, honoring the same begin/finish
  recording. `php core/admin/migrate-run.php --dry-run` reports status without
  running anything; `php core/admin/migrate-status.php` is the read-only status
  command (Phase 2a).
