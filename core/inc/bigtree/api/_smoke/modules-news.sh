#!/usr/bin/env bash
# Phase 2 L2: auto-module entry CRUD via scaffolded module (p0/auto-modules-news.md).
# Uses POST /modules/scaffold so CI only needs base.sql + empty json-db (no example-site).
set -euo pipefail
# shellcheck source=common.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

say "Login"
smoke_login

RAND=$(smoke_rand)
# Table names must be [A-Za-z0-9_]+ and unique
TABLE="zz_smoke_news_${RAND//-/_}"
TABLE=$(echo "$TABLE" | tr -cd 'A-Za-z0-9_' | cut -c1-64)
ROUTE="zz-smoke-news-$RAND"
MODULE_ID=""
ENTRY_ID=""

cleanup() {
	if [ -n "${ENTRY_ID:-}" ] && [ -n "${MODULE_ID:-}" ]; then
		curl -s -o /dev/null -X DELETE "$BASE/modules/$MODULE_ID/entries/$ENTRY_ID" \
			-H "Authorization: Bearer $ACCESS" || true
	fi
	if [ -n "${MODULE_ID:-}" ]; then
		curl -s -o /dev/null -X DELETE "$BASE/modules/$MODULE_ID" \
			-H "Authorization: Bearer $ACCESS" || true
	fi
}
trap cleanup EXIT

# 1. Scaffold a minimal News-like module
say "POST /modules/scaffold"
smoke_request POST "/modules/scaffold" "$(jq -nc \
	--arg name "Smoke News $RAND" \
	--arg table "$TABLE" \
	--arg route "$ROUTE" \
	'{
		name: $name,
		table: $table,
		route: $route,
		item_title: "Story",
		view_title: "Stories",
		view_type: "searchable",
		fields: [
			{title: "Title", type: "text"},
			{title: "Date", type: "date"},
			{title: "Content", type: "html"}
		],
		actions: {edit: true, delete: true}
	}')"
expect_status 201 "$SMOKE_STATUS" "scaffold returns 201"
MODULE_ID=$(smoke_jq '.data.id // .data.module.id // empty')
if [ -z "$MODULE_ID" ] || [ "$MODULE_ID" = "null" ]; then
	# Some responses nest under .data with module key — dump for debugging
	MODULE_ID=$(echo "$SMOKE_BODY" | jq -r '.data.id // empty')
fi
[ -n "$MODULE_ID" ] && [ "$MODULE_ID" != "null" ] || {
	echo "FAIL: could not parse module id from $SMOKE_BODY" >&2
	exit 1
}
echo "  ✓ module id=$MODULE_ID table=$TABLE"

# 2. Create published entry
say "POST /modules/$MODULE_ID/entries (publish)"
smoke_request POST "/modules/$MODULE_ID/entries" "$(jq -nc \
	--arg t "Smoke Story $RAND" \
	--arg d "2026-07-15" \
	'{title:$t, date:$d, content:"<p>Smoke content</p>", __publish__:true}')"
expect_status 201 "$SMOKE_STATUS" "create entry 201"
ENTRY_ID=$(smoke_jq '.data.id')
[ -n "$ENTRY_ID" ] && [ "$ENTRY_ID" != "null" ] || {
	echo "FAIL: no entry id body=$SMOKE_BODY" >&2
	exit 1
}
echo "  ✓ entry id=$ENTRY_ID"

# 3. List entries
say "GET /modules/$MODULE_ID/entries"
smoke_request GET "/modules/$MODULE_ID/entries"
expect_status 200 "$SMOKE_STATUS" "list entries"
# AutoModule list wraps items under .data.items
FOUND=$(echo "$SMOKE_BODY" | jq --argjson id "$ENTRY_ID" '
	(
		if (.data.items | type) == "array" then .data.items
		elif (.data | type) == "array" then .data
		else [] end
	) | map(select(.id == $id or .id == ($id|tostring))) | length
')
[ "$FOUND" -ge 1 ] || { echo "FAIL: entry not in list body=$SMOKE_BODY"; exit 1; }
echo "  ✓ entry listed"

# 4. PATCH entry
say "PATCH /modules/$MODULE_ID/entries/$ENTRY_ID"
smoke_request PATCH "/modules/$MODULE_ID/entries/$ENTRY_ID" "$(jq -nc \
	--arg t "Smoke Story $RAND Edited" \
	'{title:$t, content:"<p>v2</p>", __publish__:true}')"
expect_status 200 "$SMOKE_STATUS" "update entry 200"

# 5. GET entry
say "GET /modules/$MODULE_ID/entries/$ENTRY_ID"
smoke_request GET "/modules/$MODULE_ID/entries/$ENTRY_ID"
expect_status 200 "$SMOKE_STATUS" "get entry"
# Pending wrapper uses .data.item
TITLE=$(echo "$SMOKE_BODY" | jq -r '.data.item.title // .data.title // empty')
echo "$TITLE" | grep -q "Edited" || { echo "FAIL: title not edited ($TITLE) body=$SMOKE_BODY"; exit 1; }
echo "  ✓ title updated"

# 6. DELETE entry
say "DELETE /modules/$MODULE_ID/entries/$ENTRY_ID"
smoke_request DELETE "/modules/$MODULE_ID/entries/$ENTRY_ID"
expect_status 204 "$SMOKE_STATUS" "delete entry"
ENTRY_ID=""

# 7. Delete module
say "DELETE /modules/$MODULE_ID"
smoke_request DELETE "/modules/$MODULE_ID"
expect_status 204 "$SMOKE_STATUS" "delete module"
MODULE_ID=""

echo
echo "All modules (scaffold) smoke tests passed."
