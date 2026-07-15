#!/usr/bin/env bash
set -euo pipefail
# shellcheck source=common.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

say "Login"
smoke_login

# 1. List folders at root
say "GET /resource-folders (root)"
RESPONSE=$(curl -s -w "\n%{http_code}" "$BASE/resource-folders?parent=0" -H "Authorization: Bearer $ACCESS")
STATUS=$(echo "$RESPONSE" | tail -n 1)
expect_status 200 "$STATUS" "list root folders"

# 2. Create a folder
say "POST /resource-folders"
RAND=$(date +%s)
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$BASE/resource-folders" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $ACCESS" \
  --data "{\"parent\":0,\"name\":\"smoke-$RAND\"}")
BODY=$(echo "$RESPONSE" | sed '$d')
STATUS=$(echo "$RESPONSE" | tail -n 1)
expect_status 201 "$STATUS" "create folder"
FOLDER_ID=$(echo "$BODY" | jq -r '.data.id')
echo "  ✓ folder id = $FOLDER_ID"

# 3. Upload a tiny PNG (multipart)
say "POST /resources/upload (1x1 PNG)"
TMP_PNG="$(mktemp -t bigtree-smoke-XXXXXX.png)"
# Generate a real 1×1 PNG via GD so image processing never sees a corrupt blob.
php -r '
$im = imagecreatetruecolor(1, 1);
$transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
imagefill($im, 0, 0, $transparent);
imagesavealpha($im, true);
imagepng($im, $argv[1]);
' "$TMP_PNG"
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$BASE/resources/upload" \
  -H "Authorization: Bearer $ACCESS" \
  -F "folder=$FOLDER_ID" -F "name=Smoke Test PNG" -F "file=@$TMP_PNG;type=image/png")
BODY=$(echo "$RESPONSE" | sed '$d')
STATUS=$(echo "$RESPONSE" | tail -n 1)
rm -f "$TMP_PNG"
expect_status 201 "$STATUS" "upload returns 201"
RES_ID=$(echo "$BODY" | jq -r '.data.id')
echo "  ✓ resource id = $RES_ID"

# 4. Get the resource
say "GET /resources/$RES_ID"
STATUS=$(curl -s -o /tmp/res.json -w "%{http_code}" "$BASE/resources/$RES_ID" -H "Authorization: Bearer $ACCESS")
expect_status 200 "$STATUS" "fetch resource"
jq -e '.data.is_image == true' /tmp/res.json > /dev/null && echo "  ✓ is_image flag set"

# 5. Allocate to a real table so /usage can resolve without 500ing on missing schema.
# Prefer an existing page id; fall back to "0" (homepage row in base/example installs).
ALLOC_ENTRY=$(curl -s "$BASE/pages?parent=0" -H "Authorization: Bearer $ACCESS" \
	| jq -r '(.data // [])[0].id // "0"')
say "POST /resources/$RES_ID/allocations (bigtree_pages/$ALLOC_ENTRY)"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/resources/$RES_ID/allocations" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $ACCESS" \
  --data "{\"table\":\"bigtree_pages\",\"entry\":\"$ALLOC_ENTRY\"}")
expect_status 200 "$STATUS" "allocate returns 200"

# 6. List allocations
say "GET /resources/$RES_ID/allocations"
RESPONSE=$(curl -s -w "\n%{http_code}" "$BASE/resources/$RES_ID/allocations" -H "Authorization: Bearer $ACCESS")
BODY=$(echo "$RESPONSE" | sed '$d')
STATUS=$(echo "$RESPONSE" | tail -n 1)
expect_status 200 "$STATUS" "list allocations"
COUNT=$(echo "$BODY" | jq '.data | length')
[ "$COUNT" -ge 1 ] || { echo "FAIL: expected at least 1 allocation, got $COUNT"; exit 1; }
echo "  ✓ allocation count = $COUNT"

# 6b. Enriched usage list — resolves each allocation into location/title/status/link.
say "GET /resources/$RES_ID/usage"
RESPONSE=$(curl -s -w "\n%{http_code}" "$BASE/resources/$RES_ID/usage" -H "Authorization: Bearer $ACCESS")
BODY=$(echo "$RESPONSE" | sed '$d')
STATUS=$(echo "$RESPONSE" | tail -n 1)
expect_status 200 "$STATUS" "usage returns 200"
jq -e '.data | length >= 1' <<<"$BODY" > /dev/null || { echo "FAIL: expected >= 1 usage row"; exit 1; }
jq -e '.data[0] | has("location") and has("title") and has("status") and has("updated_at") and has("link")' \
  <<<"$BODY" > /dev/null || { echo "FAIL: usage row missing expected keys body=$BODY"; exit 1; }
echo "  ✓ usage row shape ok (status=$(jq -r '.data[0].status' <<<"$BODY"))"

# 7. Deallocate
say "DELETE /resources/$RES_ID/allocations"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X DELETE "$BASE/resources/$RES_ID/allocations" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $ACCESS" \
  --data "{\"table\":\"bigtree_pages\",\"entry\":\"$ALLOC_ENTRY\"}")
expect_status 204 "$STATUS" "deallocate returns 204"

# 8. Search
say "GET /resources/search?q=Smoke"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/resources/search?q=Smoke" -H "Authorization: Bearer $ACCESS")
expect_status 200 "$STATUS" "search returns 200"

# 9. Update resource metadata
say "PATCH /resources/$RES_ID"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X PATCH "$BASE/resources/$RES_ID" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $ACCESS" \
  --data '{"name":"Renamed Smoke PNG"}')
expect_status 200 "$STATUS" "patch returns 200"

# 10. Delete the resource
say "DELETE /resources/$RES_ID"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X DELETE "$BASE/resources/$RES_ID" \
  -H "Authorization: Bearer $ACCESS")
expect_status 204 "$STATUS" "delete resource"

# 11. Delete the folder
say "DELETE /resource-folders/$FOLDER_ID"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X DELETE "$BASE/resource-folders/$FOLDER_ID" \
  -H "Authorization: Bearer $ACCESS")
expect_status 204 "$STATUS" "delete folder"

# 12. Upload without a file → 400
say "POST /resources/upload (no file)"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/resources/upload" \
  -H "Authorization: Bearer $ACCESS" \
  -F "folder=0" -F "name=No File")
expect_status 400 "$STATUS" "missing file returns 400"

# 13. Upload as multipart but with wrong content-type header (using JSON) → 400
say "POST /resources/upload (wrong content-type)"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/resources/upload" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $ACCESS" \
  --data '{}')
expect_status 400 "$STATUS" "wrong content-type returns 400"

echo
echo "All resource smoke tests passed."
