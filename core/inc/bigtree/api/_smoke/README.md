# BigTree REST API smoke tests

Shell scripts that exercise the API end-to-end against a running BigTree
installation. Default base URL is `http://localhost:8080/admin/api/v1`.

## Prerequisites

- `curl`, `jq`, and `php` (GD for resource PNG generation) on PATH
- A running BigTree install with the REST API enabled
- A level-2 (developer) test admin — set `BIGTREE_TEST_EMAIL` and `BIGTREE_TEST_PASSWORD`
- Writable `custom/json-db/` (templates, modules, settings definitions)
- Writable `site/files/resources/` and `site/files/temporary/` (uploads)

## Scripts (Phase 2 P0 matrix)

| Script | Covers |
| --- | --- |
| `auth.sh` | login, refresh rotation/theft, logout, validation |
| `tags.sh` | create, duplicate, list, merge, delete |
| `pages.sh` | publish create, patch, reorder, archive, draft+reject |
| `users.sh` | list/me, create, patch, delete, validation |
| `settings.sh` | definition create, value update, locked, delete |
| `modules-news.sh` | scaffold module + entry CRUD (no example-site required) |
| `developer-templates.sh` | template create/update/delete |
| `resources.sh` | folders, upload, allocate/usage, delete |
| `page-sites.sh` | `/pages/sites`, reserved route suffix, trunk flag |
| `run-all.sh` | ordered matrix with shared ACCESS + rate-limit reset |
| `common.sh` | shared helpers (source from other scripts) |

Optional / non-matrix: `dashboard-analytics.sh`, `module-subresources.sh`, `system-backup.sh`.

## Usage

```bash
export BIGTREE_API_BASE="http://localhost:8080/admin/api/v1"
export BIGTREE_TEST_EMAIL="admin@example.com"
export BIGTREE_TEST_PASSWORD="hunter22hunter"

# Full P0 matrix (recommended)
bash core/inc/bigtree/api/_smoke/run-all.sh

# Single domain
bash core/inc/bigtree/api/_smoke/tags.sh

# Subset
BIGTREE_SMOKE_SCRIPTS="auth.sh tags.sh pages.sh" \
  bash core/inc/bigtree/api/_smoke/run-all.sh
```

Each script exits non-zero on the first failed assertion. `run-all.sh` defaults
to continuing after a failure unless `BIGTREE_SMOKE_FAIL_FAST=1` (CI sets this).

### Shared token + rate limits

`POST /auth/login` is limited to 10 requests/minute/IP. The matrix:

1. Runs `auth.sh` in a clean env (no pre-set token).
2. Truncates `bigtree_api_rate_limits` via PHP bootstrap.
3. Logs in once and exports `ACCESS` for the remaining scripts.

`common.sh` `smoke_login` reuses a valid `ACCESS` when present.

## CI

The `smoke` job in `.github/workflows/ci.yml` is a **blocking gate**. It:

1. Loads `core/setup/base.sql`
2. Writes CI `custom/environment.php` (DB + `settings_key` + `api.jwt_secret`)
3. Creates `custom/json-db/`, `site/files/resources/`, `site/files/temporary/`
4. Seeds a level-2 user
5. Boots `php -S` with `ci-router.php`
6. Runs `run-all.sh` (`BIGTREE_SMOKE_FAIL_FAST=1`)
7. Smoke-checks SPA index serving + classic→SPA redirect

The smoke server needs two config keys beyond standard DB/URL bootstrap:

- `$bigtree["config"]["settings_key"]` — AES settings crypto
- `$bigtree["config"]["api"]["jwt_secret"]` — HS256 access tokens

## Running locally without Apache

```bash
mkdir -p custom/json-db site/files/resources site/files/temporary cache
touch cache/composer-check.flag

REPO_ROOT="$(pwd)/" php -S 127.0.0.1:8080 \
  core/inc/bigtree/api/_smoke/ci-router.php &

export BIGTREE_API_BASE="http://127.0.0.1:8080/admin/api/v1"
export BIGTREE_TEST_EMAIL="admin@example.com"
export BIGTREE_TEST_PASSWORD="your-password"
bash core/inc/bigtree/api/_smoke/run-all.sh
```

Ensure `bigtree_audit_trail_context` includes the `via` column (present in
`base.sql`). Older databases need:

```sql
ALTER TABLE bigtree_audit_trail_context
  ADD COLUMN `via` VARCHAR(32) DEFAULT NULL;
```
