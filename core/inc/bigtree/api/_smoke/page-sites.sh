#!/usr/bin/env bash
set -euo pipefail
# shellcheck source=common.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

# Requires a level:2 admin (developer) so trunk flag works.
say "Login"
smoke_login

# 1. /pages/sites is always callable; returns [] in single-site installs.
say "GET /pages/sites"
RESPONSE=$(curl -s -w "\n%{http_code}" "$BASE/pages/sites" -H "Authorization: Bearer $ACCESS")
BODY=$(echo "$RESPONSE" | sed '$d')
STATUS=$(echo "$RESPONSE" | tail -n 1)
expect_status 200 "$STATUS" "sites returns 200"
SITE_COUNT=$(echo "$BODY" | jq '.data | length')
echo "  ℹ $SITE_COUNT site(s) configured"

# 2. Reserved top-level route — request route='ajax', expect server to auto-suffix.
say "POST /pages (parent=0, route='ajax' → should auto-suffix)"
RAND=$(date +%s)
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$BASE/pages" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $ACCESS" \
  --data "{\"parent\":0,\"nav_title\":\"Reserved Smoke $RAND\",\"route\":\"ajax\",\"publish\":true}")
STATUS=$(echo "$RESPONSE" | tail -n 1)
expect_status 201 "$STATUS" "create with reserved route returns 201"
NEW_ROUTE=$(echo "$RESPONSE" | sed '$d' | jq -r '.data.route')
if [ "$NEW_ROUTE" = "ajax" ]; then
  echo "FAIL: route should have auto-suffixed to avoid 'ajax' (got '$NEW_ROUTE')"; exit 1
fi
NEW_ID=$(echo "$RESPONSE" | sed '$d' | jq -r '.data.id')
echo "  ✓ reserved 'ajax' → '$NEW_ROUTE'"

# 3. trunk flag in list view + show ‘false' for non-trunk pages
say "GET /pages/$NEW_ID (should include trunk: false)"
TRUNK=$(curl -s "$BASE/pages/$NEW_ID" -H "Authorization: Bearer $ACCESS" | jq -r '.data.trunk')
[ "$TRUNK" = "false" ] || { echo "FAIL: expected trunk=false, got $TRUNK"; exit 1; }
echo "  ✓ trunk flag exposed as boolean"

# 4. PATCH route → triggers route_history insertion
say "PATCH /pages/$NEW_ID (rename route)"
NEW_ROUTE_2="renamed-smoke-$RAND"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X PATCH "$BASE/pages/$NEW_ID" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $ACCESS" \
  --data "{\"route\":\"$NEW_ROUTE_2\",\"publish\":true}")
expect_status 200 "$STATUS" "route rename returns 200"

# 5. Clean up
say "DELETE /pages/$NEW_ID"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X DELETE "$BASE/pages/$NEW_ID" \
  -H "Authorization: Bearer $ACCESS")
expect_status 204 "$STATUS" "delete test page"

echo
echo "All page-sites smoke tests passed."
