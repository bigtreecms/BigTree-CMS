# Running BigTree in a Claude Code cloud session

A git checkout is not a working site. `custom/`, `site/`, `templates/`, `extensions/`
and `cache/` are all gitignored, and there's no database. `scripts/dev-up.sh` builds
all of that from a bare checkout, the same way CI does
(`.github/workflows/ci.yml`, the `smoke` and `e2e` jobs).

## Prerequisites

The cloud container already has PHP 8.3 (mysqli, gd, mbstring), Node 22, `jq`, a
committed `vendor/`, and Playwright Chromium in `/opt/pw-browsers`. Two things are
missing, and the `bigtree-cms` cloud environment's setup script installs both:

```bash
apt-get install -y mysql-server-8.0 jq
cd spa && npm ci
```

On a container without that environment, run the two commands yourself. Use
`mysql-server-8.0` rather than MariaDB, because CI runs MySQL 8.0.

**Do not run `npx playwright install`.** CI needs it; the cloud container already
has Chromium.

## Bring it up

```bash
scripts/dev-up.sh            # MySQL + PHP on :8080
scripts/dev-up.sh --vite     # ... plus the Vite dev server on :5173
```

The script starts `mysqld` if it isn't running and creates the `bigtree_dev` database
and a `bigtree` user. It loads `core/setup/base.sql` and runs `scripts/ci-install.sh`.
It seeds a developer admin and the About page, then starts `php -S` behind
`core/inc/bigtree/api/_smoke/ci-router.php`. When it finishes it prints:

| What | Where |
|---|---|
| API | `http://127.0.0.1:8080/admin/api/v1` |
| Packaged SPA (`core/admin/dist`) | `http://127.0.0.1:8080/admin/` |
| Vite SPA (live source, `--vite`) | `http://127.0.0.1:5173/` |
| Login | `dev@local.test` / `DevPassword-2026!` (level 2, developer) |
| Database | `bigtree_dev` on `127.0.0.1:3306`, user `bigtree` / `bigtree-dev` |
| Logs | `/tmp/bigtree-php.log`, `/tmp/bigtree-vite.log` |

Running it again is safe. Each step checks its own state first, so a second run
leaves the database, config and seed rows alone and just reports that the servers
are up. Every value above can be overridden with a `BIGTREE_DEV_*` variable; see
the header of `scripts/dev-up.sh`.

The script refuses to run if `custom/environment.php` exists and wasn't written by
`ci-install.sh`. That means it won't overwrite a real local site's config. To try it
on a machine that has one, use a fresh `git worktree`.

## Reset and teardown

```bash
scripts/dev-up.sh --reset    # drop + reload bigtree_dev, re-run ci-install.sh
scripts/dev-up.sh --stop     # stop the PHP and Vite servers it started
```

`--stop` leaves MySQL running, because other things may be using it.

## Running the tests the way CI does

Run these from the repo root unless a step says otherwise, with `dev-up.sh` already up.

### PHP API suite

```bash
php core/inc/bigtree/api/_test/run.php
```

**This suite silently skips its database tests when it can't connect.** It still
reports `Failed: 0` (CI measured about 128 passes without the database and 141 with
it). After a run, check that the database was really reached: `dev-up.sh` printing
"Schema already loaded" is enough, or run CI's "Verify DB connectivity" step. Also
look for lines starting with `  ! `. The runner prints an uncaught exception that way
and doesn't count it as a failure.

### Smoke matrix (HTTP, P0)

```bash
BIGTREE_API_BASE=http://127.0.0.1:8080/admin/api/v1 \
BIGTREE_TEST_EMAIL=dev@local.test \
BIGTREE_TEST_PASSWORD='DevPassword-2026!' \
BIGTREE_SMOKE_FAIL_FAST=1 \
bash core/inc/bigtree/api/_smoke/run-all.sh
```

To run only some scripts, set `BIGTREE_SMOKE_SCRIPTS="auth.sh tags.sh"`.

### SPA checks

Run these from `spa/`:

```bash
npm run typecheck && npm run lint && npm test && npm run build
```

Then, from the repo root, check that the packaged SPA is current:

```bash
bash scripts/package-admin-spa.sh --check
```

### Playwright (critical journeys)

Against the packaged SPA, from `spa/`:

```bash
E2E_BASE_URL=http://127.0.0.1:8080/admin \
E2E_API_BASE=http://127.0.0.1:8080/admin/api/v1 \
E2E_EMAIL=dev@local.test E2E_PASSWORD='DevPassword-2026!' \
npx playwright test --trace off
```

To run against live source instead, add `E2E_USE_VITE=1` and drop `E2E_BASE_URL`.
Playwright then starts Vite itself, or reuses one that `dev-up.sh --vite` started.

### Migrations

```bash
php core/admin/migrate-run.php
```

This applies pending revisions to `bigtree_dev`. To test a new revision from an
older starting point, use `--reset` and load the older schema yourself. The
`migrated` CI job shows how, using the frozen pre-512 fixture.

## Known differences from CI

- **PHP 8.3 vs 8.2.** Something that breaks only on 8.2 won't show up here.
- **Node 22 vs 24** (`spa/.nvmrc`). The `engines` field accepts 20+, so the toolchain
  still runs.
- **No `bigtree_ai_embeddings`.** Its `VECTOR` column needs MySQL 9+, so neither CI
  nor this setup has the table, and semantic search is off. This is expected.
- **The packaged SPA is a build artifact.** After changing SPA source, the page at
  `:8080/admin/` won't show the change until you rebuild and repackage:
  `cd spa && npm run build:package`. Use Vite (`--vite`) to see changes live.
- **No phpstan or php-cs-fixer** unless the environment installs them globally. Never
  add them to the committed `vendor/`.
