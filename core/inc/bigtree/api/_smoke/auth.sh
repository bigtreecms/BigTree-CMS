#!/usr/bin/env bash
set -euo pipefail

BASE="${BIGTREE_API_BASE:-http://localhost:8080/admin/api/v1}"
EMAIL="${BIGTREE_TEST_EMAIL:?must set BIGTREE_TEST_EMAIL}"
PASSWORD="${BIGTREE_TEST_PASSWORD:?must set BIGTREE_TEST_PASSWORD}"

JAR="$(mktemp)"
trap "rm -f $JAR" EXIT

say() { printf "\n\033[1;34m▶ %s\033[0m\n" "$*"; }
expect_status() {
  local want="$1" got="$2" label="$3"
  if [ "$got" != "$want" ]; then
    echo "FAIL: $label — expected $want got $got" >&2
    exit 1
  fi
  echo "  ✓ $label ($got)"
}

# 1. Login
say "POST /auth/login"
RESPONSE=$(curl -s -c "$JAR" -w "\n%{http_code}" -X POST "$BASE/auth/login" \
  -H "Content-Type: application/json" \
  --data "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}")
BODY=$(echo "$RESPONSE" | sed '$d')
STATUS=$(echo "$RESPONSE" | tail -n 1)
expect_status 200 "$STATUS" "login returns 200"
ACCESS=$(echo "$BODY" | jq -r '.data.access_token')
[ -n "$ACCESS" ] && [ "$ACCESS" != "null" ] || { echo "no access_token in response"; exit 1; }
echo "  ✓ access_token present"
grep -q "bigtree_refresh" "$JAR" || { echo "FAIL: refresh cookie not set"; exit 1; }
echo "  ✓ refresh cookie set"

# 2. /auth/me
say "GET /auth/me"
RESPONSE=$(curl -s -w "\n%{http_code}" -X GET "$BASE/auth/me" -H "Authorization: Bearer $ACCESS")
STATUS=$(echo "$RESPONSE" | tail -n 1)
expect_status 200 "$STATUS" "/auth/me returns 200"

# 3. /auth/me without token → 401
say "GET /auth/me (no token)"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X GET "$BASE/auth/me")
expect_status 401 "$STATUS" "/auth/me without token returns 401"

# 4. Malformed bearer
say "GET /auth/me (bad token)"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X GET "$BASE/auth/me" -H "Authorization: Bearer not.a.token")
expect_status 401 "$STATUS" "bad token returns 401"

# 5. Refresh
say "POST /auth/refresh"
RESPONSE=$(curl -s -b "$JAR" -c "$JAR" -w "\n%{http_code}" -X POST "$BASE/auth/refresh" \
  -H "Origin: $(echo "$BASE" | sed 's|/admin/api/v1||')")
BODY=$(echo "$RESPONSE" | sed '$d')
STATUS=$(echo "$RESPONSE" | tail -n 1)
expect_status 200 "$STATUS" "refresh returns 200"
NEW_ACCESS=$(echo "$BODY" | jq -r '.data.access_token')
[ "$NEW_ACCESS" != "$ACCESS" ] || { echo "FAIL: access token should have rotated"; exit 1; }
echo "  ✓ access token rotated"

# 6. Refresh-token theft detection: reuse the original refresh value → revokes family
say "POST /auth/refresh (replay should detect theft)"
# We need a copy of the *original* cookie before rotation. Re-login to get a fresh refresh, save it, refresh once, then replay.
RESPONSE=$(curl -s -c "$JAR" -X POST "$BASE/auth/login" -H "Content-Type: application/json" \
  --data "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}")
OLD_COOKIE=$(grep bigtree_refresh "$JAR" | tail -n 1 | awk '{print $7}')
curl -s -b "$JAR" -c "$JAR" -X POST "$BASE/auth/refresh" -H "Origin: $(echo "$BASE" | sed 's|/admin/api/v1||')" > /dev/null
# Replay the old cookie
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/auth/refresh" \
  -H "Origin: $(echo "$BASE" | sed 's|/admin/api/v1||')" \
  -H "Cookie: bigtree_refresh=$OLD_COOKIE")
expect_status 401 "$STATUS" "replayed refresh returns 401"

# 7. Logout
say "POST /auth/logout"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -b "$JAR" -X POST "$BASE/auth/logout")
expect_status 204 "$STATUS" "logout returns 204"

# 8. /auth/login with bad password
say "POST /auth/login (bad pw)"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/auth/login" \
  -H "Content-Type: application/json" \
  --data "{\"email\":\"$EMAIL\",\"password\":\"wrong-password-here\"}")
expect_status 401 "$STATUS" "bad password returns 401"

# 9. Missing fields → 422
say "POST /auth/login (missing fields)"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/auth/login" \
  -H "Content-Type: application/json" \
  --data "{}")
expect_status 422 "$STATUS" "missing fields returns 422"

# 10. Malformed JSON → 400
say "POST /auth/login (malformed JSON)"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/auth/login" \
  -H "Content-Type: application/json" \
  --data "{not json")
expect_status 400 "$STATUS" "malformed JSON returns 400"

echo
echo "All auth smoke tests passed."
