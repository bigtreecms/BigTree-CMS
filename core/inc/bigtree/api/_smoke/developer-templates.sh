#!/usr/bin/env bash
# Phase 2 L2: developer templates CRUD (JSON-DB)
set -euo pipefail
# shellcheck source=common.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

say "Login"
smoke_login

RAND=$(smoke_rand)
TID="zz_smoke_tpl_$RAND"
# ids must be alphanumeric with -/_
TID=$(echo "$TID" | tr -cd 'A-Za-z0-9_-' | cut -c1-127)

# 1. Create template
say "POST /templates"
smoke_request POST "/templates" "$(jq -nc \
	--arg id "$TID" \
	'{id:$id,name:("Smoke Template "+$id),level:0,routed:false,resources:[],hooks:{}}')"
expect_status 201 "$SMOKE_STATUS" "create template 201"
GOT=$(smoke_jq '.data.id')
[ "$GOT" = "$TID" ] || { echo "FAIL: id $GOT != $TID body=$SMOKE_BODY"; exit 1; }
echo "  ✓ template id=$TID"

# 2. GET template
say "GET /templates/$TID"
smoke_request GET "/templates/$TID"
expect_status 200 "$SMOKE_STATUS" "get template"
NAME=$(smoke_jq '.data.name')
echo "  ✓ name=$NAME"

# 3. PATCH name
say "PATCH /templates/$TID"
smoke_request PATCH "/templates/$TID" "$(jq -nc --arg n "Smoke Template Renamed $RAND" '{name:$n}')"
expect_status 200 "$SMOKE_STATUS" "update template"
NEW_NAME=$(smoke_jq '.data.name')
echo "$NEW_NAME" | grep -q "Renamed" || { echo "FAIL: name not renamed"; exit 1; }
echo "  ✓ renamed"

# 4. List includes template
say "GET /templates"
smoke_request GET "/templates"
expect_status 200 "$SMOKE_STATUS" "list templates"
FOUND=$(echo "$SMOKE_BODY" | jq --arg id "$TID" '[.data[]? | select(.id == $id)] | length')
[ "$FOUND" -ge 1 ] || { echo "FAIL: template not in list"; exit 1; }
echo "  ✓ listed"

# 5. Duplicate id → 409 or 422
say "POST /templates (duplicate id)"
smoke_request POST "/templates" "$(jq -nc --arg id "$TID" '{id:$id,name:"Dup"}')"
if [ "$SMOKE_STATUS" != "409" ] && [ "$SMOKE_STATUS" != "400" ] && [ "$SMOKE_STATUS" != "422" ]; then
	echo "FAIL: duplicate expected 409/400/422 got $SMOKE_STATUS body=$SMOKE_BODY" >&2
	exit 1
fi
echo "  ✓ duplicate rejected ($SMOKE_STATUS)"

# 6. DELETE
say "DELETE /templates/$TID"
smoke_request DELETE "/templates/$TID"
expect_status 204 "$SMOKE_STATUS" "delete template"
smoke_request GET "/templates/$TID"
expect_status 404 "$SMOKE_STATUS" "deleted template 404"

echo
echo "All developer-templates smoke tests passed."
