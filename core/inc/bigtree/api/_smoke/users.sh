#!/usr/bin/env bash
set -euo pipefail
# shellcheck source=common.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

say "Login"
smoke_login

# 1. GET /users/me
say "GET /users/me"
smoke_request GET "/users/me"
expect_status 200 "$SMOKE_STATUS" "/users/me returns 200"
ME_ID=$(smoke_jq '.data.id')
echo "  ✓ me.id = $ME_ID"

# 2. GET /users (list)
say "GET /users"
smoke_request GET "/users?per_page=5"
expect_status 200 "$SMOKE_STATUS" "/users list returns 200"
COUNT=$(smoke_jq '.data | length')
echo "  ✓ list returned $COUNT items"

# 3. POST /users (create)
say "POST /users (create)"
RAND=$(smoke_rand)
smoke_request POST "/users" "$(jq -nc \
	--arg email "test-$RAND@example.com" \
	--arg name "Test $RAND" \
	'{email:$email,name:$name,password:"hunter22hunter",level:0}')"
expect_status 201 "$SMOKE_STATUS" "create returns 201"
NEW_ID=$(smoke_jq '.data.id')
echo "  ✓ created user id $NEW_ID"

# 4. PATCH /users/{id}
say "PATCH /users/$NEW_ID"
smoke_request PATCH "/users/$NEW_ID" "$(jq -nc --arg name "Renamed $RAND" '{name:$name}')"
expect_status 200 "$SMOKE_STATUS" "patch returns 200"

# 5. DELETE /users/{id}
say "DELETE /users/$NEW_ID"
smoke_request DELETE "/users/$NEW_ID"
expect_status 204 "$SMOKE_STATUS" "delete returns 204"

# 6. GET deleted user → 404
say "GET /users/$NEW_ID (deleted)"
smoke_request GET "/users/$NEW_ID"
expect_status 404 "$SMOKE_STATUS" "deleted user returns 404"

# 7. Cannot delete self
say "DELETE /users/$ME_ID (self)"
smoke_request DELETE "/users/$ME_ID"
expect_status 400 "$SMOKE_STATUS" "self-delete returns 400"

# 8. Validation: bad email
say "POST /users (bad email)"
smoke_request POST "/users" '{"email":"not-an-email","name":"x"}'
expect_status 422 "$SMOKE_STATUS" "validation error returns 422"

# 9. Unknown field
say "POST /users (unknown field)"
smoke_request POST "/users" "$(jq -nc \
	--arg email "unknown-$RAND@example.com" \
	'{email:$email,name:"x",password:"hunter22hunter",banana:"yes"}')"
expect_status 422 "$SMOKE_STATUS" "unknown field returns 422"

echo
echo "All users smoke tests passed."
