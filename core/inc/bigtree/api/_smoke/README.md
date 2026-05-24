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
