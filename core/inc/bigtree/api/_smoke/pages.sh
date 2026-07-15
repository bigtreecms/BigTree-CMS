#!/usr/bin/env bash
# Phase 2 L2: pages create publish / patch / reorder / archive / delete (p0/pages.md)
set -euo pipefail
# shellcheck source=common.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

say "Login"
smoke_login

RAND=$(smoke_rand)
ROUTE="smoke-page-$RAND"

# 1. Create published page under root
say "POST /pages (publish)"
smoke_request POST "/pages" "$(jq -nc \
	--arg nav "Smoke Page $RAND" \
	--arg title "Smoke Title $RAND" \
	--arg route "$ROUTE" \
	'{parent:0,nav_title:$nav,title:$title,route:$route,template:"content",in_nav:true,publish:true}')"
expect_status 201 "$SMOKE_STATUS" "create publish returns 201"
PAGE_ID=$(smoke_jq '.data.id')
GOT_ROUTE=$(smoke_jq '.data.route')
[ -n "$PAGE_ID" ] && [ "$PAGE_ID" != "null" ] || { echo "FAIL: no page id body=$SMOKE_BODY"; exit 1; }
[ "$GOT_ROUTE" = "$ROUTE" ] || { echo "FAIL: route $GOT_ROUTE != $ROUTE"; exit 1; }
echo "  ✓ page id=$PAGE_ID route=$GOT_ROUTE"

# 2. GET page
say "GET /pages/$PAGE_ID"
smoke_request GET "/pages/$PAGE_ID"
expect_status 200 "$SMOKE_STATUS" "get page"
TITLE=$(smoke_jq '.data.title')
echo "  ✓ title=$TITLE"

# 3. PATCH title (publish)
say "PATCH /pages/$PAGE_ID"
smoke_request PATCH "/pages/$PAGE_ID" "$(jq -nc \
	--arg title "Smoke Updated $RAND" \
	'{title:$title,publish:true}')"
expect_status 200 "$SMOKE_STATUS" "patch publish returns 200"
NEW_TITLE=$(smoke_jq '.data.title')
echo "$NEW_TITLE" | grep -q "Updated" || { echo "FAIL: title not updated ($NEW_TITLE)"; exit 1; }
echo "  ✓ title updated"

# 4. Create sibling for reorder
say "POST /pages (sibling)"
ROUTE2="smoke-page-b-$RAND"
smoke_request POST "/pages" "$(jq -nc \
	--arg nav "Smoke Sibling $RAND" \
	--arg route "$ROUTE2" \
	'{parent:0,nav_title:$nav,route:$route,template:"content",publish:true}')"
expect_status 201 "$SMOKE_STATUS" "create sibling"
PAGE_B=$(smoke_jq '.data.id')

# 5. Reorder under parent 0
say "POST /pages/0/reorder"
smoke_request POST "/pages/0/reorder" "$(jq -nc --argjson a "$PAGE_ID" --argjson b "$PAGE_B" '{ids:[$b,$a]}')"
if [ "$SMOKE_STATUS" != "200" ] && [ "$SMOKE_STATUS" != "204" ]; then
	echo "FAIL: reorder expected 200 or 204 got $SMOKE_STATUS body=$SMOKE_BODY" >&2
	exit 1
fi
echo "  ✓ reorder accepted ($SMOKE_STATUS)"

# 6. Archive / unarchive
say "POST /pages/$PAGE_ID/archive"
smoke_request POST "/pages/$PAGE_ID/archive"
expect_status 204 "$SMOKE_STATUS" "archive"
say "POST /pages/$PAGE_ID/unarchive"
smoke_request POST "/pages/$PAGE_ID/unarchive"
expect_status 204 "$SMOKE_STATUS" "unarchive"

# 7. List children of root includes our pages
say "GET /pages?parent=0"
smoke_request GET "/pages?parent=0"
expect_status 200 "$SMOKE_STATUS" "list root children"
FOUND=$(echo "$SMOKE_BODY" | jq --argjson id "$PAGE_ID" '[.data[]? | select(.id == $id)] | length')
[ "$FOUND" -ge 1 ] || { echo "FAIL: page not in list"; exit 1; }
echo "  ✓ listed under parent=0"

# 8. Delete both
say "DELETE pages"
smoke_request DELETE "/pages/$PAGE_ID"
expect_status 204 "$SMOKE_STATUS" "delete A"
smoke_request DELETE "/pages/$PAGE_B"
expect_status 204 "$SMOKE_STATUS" "delete B"
smoke_request GET "/pages/$PAGE_ID"
expect_status 404 "$SMOKE_STATUS" "deleted returns 404"

# 9. Draft path: create without publish → pending
say "POST /pages (draft, no publish)"
smoke_request POST "/pages" "$(jq -nc \
	--arg nav "Smoke Draft $RAND" \
	--arg route "smoke-draft-$RAND" \
	'{parent:0,nav_title:$nav,route:$route,template:"content"}')"
expect_status 201 "$SMOKE_STATUS" "draft create 201"
PENDING=$(smoke_jq '.data.pending')
PCID=$(smoke_jq '.data.pending_change_id')
[ "$PENDING" = "true" ] || { echo "FAIL: expected pending=true body=$SMOKE_BODY"; exit 1; }
[ -n "$PCID" ] && [ "$PCID" != "null" ] || { echo "FAIL: missing pending_change_id"; exit 1; }
echo "  ✓ pending_change_id=$PCID"

# Clean pending via reject
say "POST /pending-changes/$PCID/reject"
smoke_request POST "/pending-changes/$PCID/reject"
expect_status 204 "$SMOKE_STATUS" "reject draft"

echo
echo "All pages smoke tests passed."
