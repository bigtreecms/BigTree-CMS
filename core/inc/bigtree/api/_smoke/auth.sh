#!/usr/bin/env bash
set -euo pipefail

BASE="${BIGTREE_API_BASE:-http://localhost:8080/admin/api/v1}"
EMAIL="${BIGTREE_TEST_EMAIL:?must set BIGTREE_TEST_EMAIL}"
PASSWORD="${BIGTREE_TEST_PASSWORD:?must set BIGTREE_TEST_PASSWORD}"

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
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$BASE/auth/login" \
  -H "Content-Type: application/json" \
  --data "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}")
BODY=$(echo "$RESPONSE" | sed '$d')
STATUS=$(echo "$RESPONSE" | tail -n 1)
expect_status 200 "$STATUS" "login returns 200"
ACCESS=$(echo "$BODY" | jq -r '.data.access_token')
[ -n "$ACCESS" ] && [ "$ACCESS" != "null" ] || { echo "no access_token in response"; exit 1; }
echo "  ✓ access_token present"
REFRESH=$(echo "$BODY" | jq -r '.data.refresh_token')
[ -n "$REFRESH" ] && [ "$REFRESH" != "null" ] || { echo "no refresh_token in response"; exit 1; }
echo "  ✓ refresh_token present in JSON body"

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

# 5. Refresh — POST with JSON body {"refresh_token": "..."}
say "POST /auth/refresh"
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$BASE/auth/refresh" \
  -H "Content-Type: application/json" \
  --data "{\"refresh_token\":\"$REFRESH\"}")
BODY=$(echo "$RESPONSE" | sed '$d')
STATUS=$(echo "$RESPONSE" | tail -n 1)
expect_status 200 "$STATUS" "refresh returns 200"
NEW_ACCESS=$(echo "$BODY" | jq -r '.data.access_token')
NEW_REFRESH=$(echo "$BODY" | jq -r '.data.refresh_token')
[ "$NEW_ACCESS" != "$ACCESS" ] || { echo "FAIL: access token should have rotated"; exit 1; }
echo "  ✓ access token rotated"
[ -n "$NEW_REFRESH" ] && [ "$NEW_REFRESH" != "null" ] || { echo "no refresh_token in refresh response"; exit 1; }
echo "  ✓ new refresh_token returned (rotated)"

# 6. Refresh-token theft detection: login fresh → capture refresh A → rotate to B → replay A → expect 401
say "POST /auth/refresh (replay should detect theft)"
# Get a fresh login so we have a clean token family to test against
RESPONSE=$(curl -s -X POST "$BASE/auth/login" \
  -H "Content-Type: application/json" \
  --data "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}")
REFRESH_A=$(echo "$RESPONSE" | jq -r '.data.refresh_token')
# Rotate A → B
curl -s -o /dev/null -X POST "$BASE/auth/refresh" \
  -H "Content-Type: application/json" \
  --data "{\"refresh_token\":\"$REFRESH_A\"}" > /dev/null
# Replay the already-rotated A — should trigger theft detection and return 401
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/auth/refresh" \
  -H "Content-Type: application/json" \
  --data "{\"refresh_token\":\"$REFRESH_A\"}")
expect_status 401 "$STATUS" "replayed refresh returns 401"

# 7. Logout — login fresh then logout with JSON body {"refresh_token": "..."}
say "POST /auth/logout"
RESPONSE=$(curl -s -X POST "$BASE/auth/login" \
  -H "Content-Type: application/json" \
  --data "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}")
LOGOUT_ACCESS=$(echo "$RESPONSE" | jq -r '.data.access_token')
LOGOUT_REFRESH=$(echo "$RESPONSE" | jq -r '.data.refresh_token')
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/auth/logout" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $LOGOUT_ACCESS" \
  --data "{\"refresh_token\":\"$LOGOUT_REFRESH\"}")
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
