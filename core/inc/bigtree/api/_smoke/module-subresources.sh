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

# 1. Create a parent module
say "POST /modules (parent)"
RAND=$(date +%s)
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$BASE/modules" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $ACCESS" \
  --data "{\"name\":\"Smoke $RAND\",\"icon\":\"document\"}")
BODY=$(echo "$RESPONSE" | sed '$d')
STATUS=$(echo "$RESPONSE" | tail -n 1)
expect_status 201 "$STATUS" "create module"
MODULE_ID=$(echo "$BODY" | jq -r '.data.id')
echo "  ✓ module id = $MODULE_ID"

# 2. Create a view
say "POST /modules/$MODULE_ID/views"
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$BASE/modules/$MODULE_ID/views" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $ACCESS" \
  --data '{"title":"List","description":"All entries","table":"smoke_table","type":"draggable","settings":{},"fields":{},"actions":{}}')
STATUS=$(echo "$RESPONSE" | tail -n 1)
expect_status 201 "$STATUS" "create view"
VIEW_ID=$(echo "$RESPONSE" | sed '$d' | jq -r '.data.id')
echo "  ✓ view id = $VIEW_ID"

# 3. Create a form
say "POST /modules/$MODULE_ID/forms"
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$BASE/modules/$MODULE_ID/forms" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $ACCESS" \
  --data '{"title":"Edit","table":"smoke_table","fields":[{"column":"name","type":"text","title":"Name"}],"return_view":1}')
STATUS=$(echo "$RESPONSE" | tail -n 1)
expect_status 201 "$STATUS" "create form"
FORM_ID=$(echo "$RESPONSE" | sed '$d' | jq -r '.data.id')
echo "  ✓ form id = $FORM_ID"

# 4. Create an action tied to the view
say "POST /modules/$MODULE_ID/actions (linked to view)"
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$BASE/modules/$MODULE_ID/actions" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $ACCESS" \
  --data "{\"name\":\"View All\",\"route\":\"view\",\"in_nav\":true,\"icon\":\"list\",\"view\":$VIEW_ID}")
STATUS=$(echo "$RESPONSE" | tail -n 1)
expect_status 201 "$STATUS" "create view action"
ACTION1=$(echo "$RESPONSE" | sed '$d' | jq -r '.data.id')
echo "  ✓ action id = $ACTION1"

# 5. Create an action tied to the form (route 'add' triggers auto-rename when form title changes)
say "POST /modules/$MODULE_ID/actions (linked to form, route='add')"
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$BASE/modules/$MODULE_ID/actions" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $ACCESS" \
  --data "{\"name\":\"Add\",\"route\":\"add\",\"in_nav\":true,\"icon\":\"add\",\"form\":$FORM_ID}")
STATUS=$(echo "$RESPONSE" | tail -n 1)
expect_status 201 "$STATUS" "create form action"
ACTION2=$(echo "$RESPONSE" | sed '$d' | jq -r '.data.id')
echo "  ✓ action id = $ACTION2"

# 6. List actions
say "GET /modules/$MODULE_ID/actions"
RESPONSE=$(curl -s -w "\n%{http_code}" "$BASE/modules/$MODULE_ID/actions" -H "Authorization: Bearer $ACCESS")
COUNT=$(echo "$RESPONSE" | sed '$d' | jq '.data | length')
[ "$COUNT" -eq 2 ] || { echo "FAIL: expected 2 actions, got $COUNT"; exit 1; }
echo "  ✓ list returned 2 actions"

# 7. Duplicate route auto-suffixes (uniqueModuleActionRoute)
say "POST /modules/$MODULE_ID/actions (duplicate route='view' should become 'view-2')"
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$BASE/modules/$MODULE_ID/actions" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $ACCESS" \
  --data "{\"name\":\"Other View\",\"route\":\"view\",\"icon\":\"list\"}")
STATUS=$(echo "$RESPONSE" | tail -n 1)
expect_status 201 "$STATUS" "create dup-route action"
DUP_ROUTE=$(echo "$RESPONSE" | sed '$d' | jq -r '.data.route')
[ "$DUP_ROUTE" = "view-2" ] || { echo "FAIL: expected route 'view-2', got '$DUP_ROUTE'"; exit 1; }
echo "  ✓ duplicate route became 'view-2'"

# 8. Update form title — referenced add-action should auto-rename to "Add {new_title}"
say "PATCH /modules/$MODULE_ID/forms/$FORM_ID (rename title)"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X PATCH "$BASE/modules/$MODULE_ID/forms/$FORM_ID" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $ACCESS" \
  --data '{"title":"Edit Entry"}')
expect_status 200 "$STATUS" "patch form title"
ADD_NAME=$(curl -s "$BASE/modules/$MODULE_ID/actions" -H "Authorization: Bearer $ACCESS" \
  | jq -r ".data[] | select(.id==$ACTION2) | .name")
[ "$ADD_NAME" = "Add Edit Entry" ] || { echo "FAIL: expected 'Add Edit Entry', got '$ADD_NAME'"; exit 1; }
echo "  ✓ form title cascade renamed add action"

# 9. Delete the form → its add action should also be deleted (legacy semantics)
say "DELETE /modules/$MODULE_ID/forms/$FORM_ID"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X DELETE "$BASE/modules/$MODULE_ID/forms/$FORM_ID" \
  -H "Authorization: Bearer $ACCESS")
expect_status 204 "$STATUS" "delete form"
ACTION_COUNT=$(curl -s "$BASE/modules/$MODULE_ID/actions" -H "Authorization: Bearer $ACCESS" | jq '.data | length')
[ "$ACTION_COUNT" -eq 2 ] || { echo "FAIL: expected 2 actions left after form delete, got $ACTION_COUNT"; exit 1; }
echo "  ✓ cascade removed dependent action ($ACTION_COUNT remaining)"

# 10. Delete the view action — view has only that action, so view should cascade-delete too
say "DELETE /modules/$MODULE_ID/actions/$ACTION1 (sole action for view)"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X DELETE "$BASE/modules/$MODULE_ID/actions/$ACTION1" \
  -H "Authorization: Bearer $ACCESS")
expect_status 204 "$STATUS" "delete action"
VIEW_LIST=$(curl -s "$BASE/modules/$MODULE_ID/views" -H "Authorization: Bearer $ACCESS" | jq '.data | length')
[ "$VIEW_LIST" -eq 0 ] || { echo "FAIL: expected 0 views after sole action removed, got $VIEW_LIST"; exit 1; }
echo "  ✓ orphaned view auto-deleted"

# 11. Validation: missing required title for form → 422
say "POST /modules/$MODULE_ID/forms (missing title)"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/modules/$MODULE_ID/forms" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $ACCESS" \
  --data '{"table":"smoke_table"}')
expect_status 422 "$STATUS" "missing title returns 422"

# 12. Clean up module
say "DELETE /modules/$MODULE_ID"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X DELETE "$BASE/modules/$MODULE_ID" \
  -H "Authorization: Bearer $ACCESS")
expect_status 204 "$STATUS" "delete module"

echo
echo "All module sub-resource smoke tests passed."
