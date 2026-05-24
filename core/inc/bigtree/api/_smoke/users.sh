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

say "Login to obtain admin token"
ACCESS=$(curl -s -X POST "$BASE/auth/login" -H "Content-Type: application/json" \
  --data "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}" | jq -r '.data.access_token')
[ -n "$ACCESS" ] && [ "$ACCESS" != "null" ] || { echo "could not obtain access token"; exit 1; }

# 1. GET /users/me
say "GET /users/me"
STATUS=$(curl -s -o /tmp/me.json -w "%{http_code}" "$BASE/users/me" -H "Authorization: Bearer $ACCESS")
expect_status 200 "$STATUS" "/users/me returns 200"
ME_ID=$(jq -r '.data.id' /tmp/me.json)
echo "  ✓ me.id = $ME_ID"

# 2. GET /users (list)
say "GET /users"
RESPONSE=$(curl -s -w "\n%{http_code}" "$BASE/users?per_page=5" -H "Authorization: Bearer $ACCESS")
BODY=$(echo "$RESPONSE" | sed '$d')
STATUS=$(echo "$RESPONSE" | tail -n 1)
expect_status 200 "$STATUS" "/users list returns 200"
COUNT=$(echo "$BODY" | jq '.data | length')
echo "  ✓ list returned $COUNT items"

# 3. POST /users (create)
say "POST /users (create)"
RAND=$(date +%s)
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$BASE/users" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $ACCESS" \
  --data "{\"email\":\"test-$RAND@example.com\",\"name\":\"Test $RAND\",\"password\":\"hunter22hunter\",\"level\":0}")
BODY=$(echo "$RESPONSE" | sed '$d')
STATUS=$(echo "$RESPONSE" | tail -n 1)
expect_status 201 "$STATUS" "create returns 201"
NEW_ID=$(echo "$BODY" | jq -r '.data.id')
echo "  ✓ created user id $NEW_ID"

# 4. PATCH /users/{id}
say "PATCH /users/$NEW_ID"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X PATCH "$BASE/users/$NEW_ID" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $ACCESS" \
  --data "{\"name\":\"Renamed $RAND\"}")
expect_status 200 "$STATUS" "patch returns 200"

# 5. DELETE /users/{id}
say "DELETE /users/$NEW_ID"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X DELETE "$BASE/users/$NEW_ID" \
  -H "Authorization: Bearer $ACCESS")
expect_status 204 "$STATUS" "delete returns 204"

# 6. GET deleted user → 404
say "GET /users/$NEW_ID (deleted)"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/users/$NEW_ID" -H "Authorization: Bearer $ACCESS")
expect_status 404 "$STATUS" "deleted user returns 404"

# 7. Cannot delete self
say "DELETE /users/$ME_ID (self)"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X DELETE "$BASE/users/$ME_ID" \
  -H "Authorization: Bearer $ACCESS")
expect_status 400 "$STATUS" "self-delete returns 400"

# 8. Validation: bad email
say "POST /users (bad email)"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/users" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $ACCESS" \
  --data "{\"email\":\"not-an-email\",\"name\":\"x\"}")
expect_status 422 "$STATUS" "validation error returns 422"

# 9. Unknown field
say "POST /users (unknown field)"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/users" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $ACCESS" \
  --data "{\"email\":\"unknown-$RAND@example.com\",\"name\":\"x\",\"password\":\"hunter22hunter\",\"banana\":\"yes\"}")
expect_status 422 "$STATUS" "unknown field returns 422"

echo
echo "All users smoke tests passed."
