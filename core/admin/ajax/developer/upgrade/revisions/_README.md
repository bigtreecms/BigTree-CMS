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

## Every revision lands in `base.sql` too — same commit

A revision describes how an EXISTING install gets to the new schema.
[`core/setup/base.sql`](../../../../setup/base.sql) describes the schema itself. They are two
statements of one fact, so a revision that creates or alters a table is only half
written until `base.sql` says the same thing:

1. Add the new table (or the altered column, index, width, charset) to `base.sql`.
2. Bump the `bigtree-internal-revision` floor `base.sql` seeds to match
   `BIGTREE_REVISION` in [`core/version.php`](../../../../version.php).
3. Bump `BIGTREE_REVISION` itself.

A fresh install is installed **up to date** — it should have nothing pending on its
first admin visit. When the floor lags, that is the visible symptom; the actual bug
is that `base.sql` has stopped describing the schema the code expects, and every
fresh install is silently relying on migrations to finish the job. `MigrationRunnerTest`
asserts the floor matches, which catches step 2 but not step 1 — step 1 is on you.

The single exception is a table the file cannot portably declare:
`bigtree_ai_embeddings` needs a `VECTOR` column (MySQL 9+ / MariaDB 11.7+), so
`core/setup/install.php` creates it conditionally. Nothing else gets to be absent.

## Running migrations

- **Browser**: the legacy AJAX upgrade flow (`scripts.php`) still drives each
  `revisions/N.php` page-by-page — unchanged.
- **CLI / headless**: `php core/admin/migrate-run.php` applies every pending
  revision (ascending), single-shot and batched, honoring the same begin/finish
  recording. `php core/admin/migrate-run.php --dry-run` reports status without
  running anything; `php core/admin/migrate-status.php` is the read-only status
  command (Phase 2a).
