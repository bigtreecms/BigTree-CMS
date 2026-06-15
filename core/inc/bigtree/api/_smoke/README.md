# BigTree REST API smoke tests

Shell scripts that exercise the API end-to-end against a running BigTree
installation. Default base URL is `http://localhost:8080/admin/api/v1`.

## Prerequisites
- `curl` and `jq` available on PATH
- A running BigTree install with the REST API enabled (revision 503 applied)
- A test admin user — set `BIGTREE_TEST_EMAIL` and `BIGTREE_TEST_PASSWORD`

## Usage
```
export BIGTREE_API_BASE="http://localhost:8080/admin/api/v1"
export BIGTREE_TEST_EMAIL="admin@example.com"
export BIGTREE_TEST_PASSWORD="hunter22hunter"
./auth.sh
./users.sh
```

Each script exits non-zero on the first failed assertion. Output is the
HTTP status + relevant JSON fields for each request.

## CI

The `smoke` job in `.github/workflows/ci.yml` runs `auth.sh` on every push and
pull request as a **blocking gate**. It boots the real front controller under
PHP's built-in server via `ci-router.php` (which emulates the `.htaccess`
rewrite), loads `core/setup/base.sql`, seeds a level-2 admin, then runs the
script.

The smoke server needs two config keys beyond the standard DB/URL bootstrap,
because the login/token path dereferences them (dummy CI values are fine):

- `$bigtree["config"]["settings_key"]` — settings are stored `AES_ENCRYPT`-ed
  and read back with this key (`core/inc/bigtree/cms.php`); a null key makes
  login 500.
- `$bigtree["config"]["api"]["jwt_secret"]` — HS256 signing secret for the
  access token (`AuthService::issueTokens`); login returns
  `server_misconfigured` without it.

## Running locally without Apache

`ci-router.php` lets you run the API under `php -S` with no web server config:

```
REPO_ROOT="$(pwd)/" php -S 127.0.0.1:8080 core/inc/bigtree/api/_smoke/ci-router.php &
export BIGTREE_API_BASE="http://127.0.0.1:8080/admin/api/v1"
export BIGTREE_TEST_EMAIL="admin@example.com"
export BIGTREE_TEST_PASSWORD="your-password"
./auth.sh
```
