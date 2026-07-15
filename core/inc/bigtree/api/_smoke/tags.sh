#!/usr/bin/env bash
# Phase 2 L2: tags create / list / merge / delete (p0/tags.md)
set -euo pipefail
# shellcheck source=common.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

say "Login"
smoke_login

RAND=$(smoke_rand)
TAG_A="Smoke Alpha $RAND"
TAG_B="Smoke Beta $RAND"
TAG_C="Smoke Gamma $RAND"

# 1. Create tag A
say "POST /tags (create A)"
smoke_request POST "/tags" "{\"tag\":\"$TAG_A\"}"
expect_status 201 "$SMOKE_STATUS" "create A returns 201"
ID_A=$(smoke_jq '.data.id')
NORM_A=$(smoke_jq '.data.tag')
[ -n "$ID_A" ] && [ "$ID_A" != "null" ] || { echo "FAIL: missing id"; exit 1; }
echo "  ✓ id=$ID_A tag='$NORM_A'"

# 2. Duplicate create → 200 same id (idempotent)
say "POST /tags (duplicate A)"
smoke_request POST "/tags" "{\"tag\":\"$TAG_A\"}"
expect_status 200 "$SMOKE_STATUS" "duplicate returns 200"
ID_DUP=$(smoke_jq '.data.id')
[ "$ID_DUP" = "$ID_A" ] || { echo "FAIL: expected same id $ID_A got $ID_DUP"; exit 1; }
echo "  ✓ same id returned"

# 3. Create B and C
say "POST /tags (create B, C)"
smoke_request POST "/tags" "{\"tag\":\"$TAG_B\"}"
expect_status 201 "$SMOKE_STATUS" "create B"
ID_B=$(smoke_jq '.data.id')
smoke_request POST "/tags" "{\"tag\":\"$TAG_C\"}"
expect_status 201 "$SMOKE_STATUS" "create C"
ID_C=$(smoke_jq '.data.id')

# 4. List contains A
say "GET /tags?q=smoke"
smoke_request GET "/tags?q=smoke%20alpha&per_page=50"
# GET with query — path includes ?
# smoke_request builds BASE+path; ok
expect_status 200 "$SMOKE_STATUS" "list returns 200"
COUNT=$(echo "$SMOKE_BODY" | jq --argjson id "$ID_A" '[.data[]? | select(.id == $id)] | length')
[ "$COUNT" -ge 1 ] || { echo "FAIL: list missing tag A; body=$SMOKE_BODY"; exit 1; }
echo "  ✓ list includes A"

# 5. Merge B into A
say "POST /tags/merge (B → A)"
smoke_request POST "/tags/merge" "{\"into\":$ID_A,\"from\":[$ID_B]}"
expect_status 200 "$SMOKE_STATUS" "merge returns 200"
smoke_request GET "/tags/$ID_B"
expect_status 404 "$SMOKE_STATUS" "source B gone"

# 6. C still exists
say "GET /tags/$ID_C"
smoke_request GET "/tags/$ID_C"
expect_status 200 "$SMOKE_STATUS" "C still present"

# 7. Delete A and C
say "DELETE /tags/$ID_A and $ID_C"
smoke_request DELETE "/tags/$ID_A"
expect_status 204 "$SMOKE_STATUS" "delete A"
smoke_request DELETE "/tags/$ID_C"
expect_status 204 "$SMOKE_STATUS" "delete C"

# 8. Empty tag → 400
say "POST /tags (empty after normalize)"
smoke_request POST "/tags" '{"tag":"!!!"}'
expect_status 400 "$SMOKE_STATUS" "empty normalize returns 400"

echo
echo "All tags smoke tests passed."
