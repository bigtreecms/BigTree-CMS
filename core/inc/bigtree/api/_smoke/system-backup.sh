#!/usr/bin/env bash
set -euo pipefail

BASE="${BIGTREE_API_BASE:-http://localhost:8080/admin/api/v1}"
EMAIL="${BIGTREE_TEST_EMAIL:?must set BIGTREE_TEST_EMAIL}"
PASSWORD="${BIGTREE_TEST_PASSWORD:?must set BIGTREE_TEST_PASSWORD}"

say() { printf "\n\033[1;34m▶ %s\033[0m\n" "$*"; }
expect_status() {
  local want="$1" got="$2" label="$3"
  if [ "$got" != "$want" ]; then echo "FAIL: $label — expected $want got $got" >&2; exit 1; fi
  echo "  ✓ $label ($got)"
}

# Requires a level:2 admin (developer).
say "Login"
ACCESS=$(curl -s -X POST "$BASE/auth/login" -H "Content-Type: application/json" \
  --data "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}" | jq -r '.data.access_token')

# 1. Create a backup
say "POST /system/backup"
RESPONSE=$(curl -s --max-time 600 -w "\n%{http_code}" -X POST "$BASE/system/backup" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $ACCESS" --data '{}')
BODY=$(echo "$RESPONSE" | sed '$d')
STATUS=$(echo "$RESPONSE" | tail -n 1)
expect_status 201 "$STATUS" "backup returns 201"

BACKUP_ID=$(echo "$BODY" | jq -r '.data.backup_id')
SIZE=$(echo "$BODY" | jq -r '.data.size_bytes')
DOWNLOAD_URL=$(echo "$BODY" | jq -r '.data.download_url')
ELAPSED=$(echo "$BODY" | jq -r '.data.elapsed_ms')

[ -n "$BACKUP_ID" ] && [ "$BACKUP_ID" != "null" ] || { echo "FAIL: no backup_id in response"; exit 1; }
[ -n "$DOWNLOAD_URL" ] && [ "$DOWNLOAD_URL" != "null" ] || { echo "FAIL: no download_url"; exit 1; }
echo "  ✓ backup id $BACKUP_ID, size ${SIZE}B, ${ELAPSED}ms"

# 2. List backups — should include ours
say "GET /system/backup"
RESPONSE=$(curl -s -w "\n%{http_code}" "$BASE/system/backup" -H "Authorization: Bearer $ACCESS")
BODY=$(echo "$RESPONSE" | sed '$d')
STATUS=$(echo "$RESPONSE" | tail -n 1)
expect_status 200 "$STATUS" "list returns 200"
FOUND=$(echo "$BODY" | jq --arg id "$BACKUP_ID" '.data | map(select(.backup_id == $id)) | length')
[ "$FOUND" = "1" ] || { echo "FAIL: created backup not in list"; exit 1; }
echo "  ✓ backup present in listing"

# 3. Download — should stream SQL with attachment headers (no Bearer needed; token in URL)
say "GET download URL (no Bearer)"
TMP=$(mktemp)
HTTP_INFO=$(curl -s -o "$TMP" -D - "$DOWNLOAD_URL" 2>&1)
HEADERS=$(echo "$HTTP_INFO" | grep -i "HTTP/\|content-type\|content-disposition" || true)
echo "$HEADERS" | head -3
echo "$HEADERS" | grep -qi "application/sql" || { echo "FAIL: missing application/sql content-type"; rm -f "$TMP"; exit 1; }
echo "$HEADERS" | grep -qi "attachment.*\.sql" || { echo "FAIL: missing attachment header"; rm -f "$TMP"; exit 1; }
echo "  ✓ stream returned SQL with attachment headers"

# Verify it's a valid-looking SQL dump
head -1 "$TMP" | grep -q "SET SESSION sql_mode" || { echo "FAIL: dump doesn't look like SQL::backup output"; rm -f "$TMP"; exit 1; }
grep -q "DROP TABLE IF EXISTS \`bigtree_users\`" "$TMP" || { echo "FAIL: dump missing bigtree_users"; rm -f "$TMP"; exit 1; }
echo "  ✓ dump contains expected SQL"
rm -f "$TMP"

# 4. Bad token → 401
say "GET download (bad token)"
BAD_URL=$(echo "$DOWNLOAD_URL" | sed 's/token=.*$/token=garbage.payload/')
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BAD_URL")
expect_status 401 "$STATUS" "bad token returns 401"

# 5. Tampered backup id in URL → 401 (token doesn't match)
say "GET download (mismatched backup id)"
OTHER_ID=$(printf '%032x' $((RANDOM*RANDOM*RANDOM)) | head -c 32)
# Substitute backup_id in URL but keep original token — token bound to original id should mismatch
WRONG_URL=$(echo "$DOWNLOAD_URL" | sed "s|/system/backup/$BACKUP_ID/|/system/backup/$OTHER_ID/|")
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$WRONG_URL")
expect_status 401 "$STATUS" "mismatched backup id returns 401"

# 6. Missing token → 422 (validator catches)
say "GET download (missing token)"
NO_TOKEN_URL=$(echo "$DOWNLOAD_URL" | sed 's/?token=.*$//')
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$NO_TOKEN_URL")
expect_status 422 "$STATUS" "missing token returns 422"

# 7. Invalid backup id format → 400
say "GET download (invalid id format)"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/system/backup/not-hex/download?token=garbage.x")
expect_status 400 "$STATUS" "non-hex id returns 400"

# 8. Concurrent backup attempt should 409
say "POST /system/backup (concurrent) → 409 if a second runs while first sentinel exists"
# We can't easily test the sentinel collision without timing, so this just verifies a normal second call works
STATUS=$(curl -s --max-time 600 -o /dev/null -w "%{http_code}" -X POST "$BASE/system/backup" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $ACCESS" --data '{}')
expect_status 201 "$STATUS" "second backup also returns 201 (sentinel cleared after first)"

# 9. Delete our first backup
say "DELETE /system/backup/$BACKUP_ID"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X DELETE "$BASE/system/backup/$BACKUP_ID" \
  -H "Authorization: Bearer $ACCESS")
expect_status 204 "$STATUS" "delete returns 204"

# 10. Deleted backup → download 404
say "GET download (deleted backup)"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$DOWNLOAD_URL")
expect_status 404 "$STATUS" "deleted backup returns 404"

echo
echo "All DB backup smoke tests passed."
