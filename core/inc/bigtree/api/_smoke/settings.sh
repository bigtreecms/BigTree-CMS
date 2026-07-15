#!/usr/bin/env bash
# Phase 2 L2: settings definition + value update (p0/settings.md)
set -euo pipefail
# shellcheck source=common.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

say "Login"
smoke_login

RAND=$(smoke_rand)
SID="zz_smoke_notice_$RAND"

# 1. Create setting definition (level 2)
say "POST /settings (definition)"
smoke_request POST "/settings" "$(jq -nc \
	--arg id "$SID" \
	'{id:$id,name:"Smoke Notice",type:"text",locked:false,system:false,encrypted:false}')"
expect_status 201 "$SMOKE_STATUS" "create definition 201"

# 2. PATCH value only
say "PATCH /settings/$SID (value)"
smoke_request PATCH "/settings/$SID" '{"value":"Hello from smoke"}'
expect_status 200 "$SMOKE_STATUS" "value update 200"

# 3. GET reflects value
say "GET /settings/$SID"
smoke_request GET "/settings/$SID"
expect_status 200 "$SMOKE_STATUS" "get setting"
VAL=$(smoke_jq '.data.value')
# value may be JSON string or bare string
echo "$SMOKE_BODY" | jq -e '
	.data.value == "Hello from smoke"
	or .data.value == "\"Hello from smoke\""
' > /dev/null || {
	# accept if value string contains the text
	echo "$VAL" | grep -q "Hello from smoke" || {
		echo "FAIL: unexpected value $VAL body=$SMOKE_BODY" >&2
		exit 1
	}
}
echo "  ✓ value round-trip"

# 4. List includes setting
say "GET /settings?q=smoke"
smoke_request GET "/settings?q=$SID&per_page=50"
expect_status 200 "$SMOKE_STATUS" "list settings"
FOUND=$(echo "$SMOKE_BODY" | jq --arg id "$SID" '[.data[]? | select(.id == $id)] | length')
[ "$FOUND" -ge 1 ] || { echo "FAIL: setting not listed"; exit 1; }
echo "  ✓ listed"

# 5. Locked setting: create locked, value update as same level-2 user OK
say "POST /settings (locked) + value"
LID="zz_smoke_locked_$RAND"
smoke_request POST "/settings" "$(jq -nc --arg id "$LID" \
	'{id:$id,name:"Smoke Locked",type:"text",locked:true}')"
expect_status 201 "$SMOKE_STATUS" "create locked"
smoke_request PATCH "/settings/$LID" '{"value":"dev ok"}'
expect_status 200 "$SMOKE_STATUS" "dev can set locked value"

# 6. Cleanup
say "DELETE settings"
smoke_request DELETE "/settings/$SID"
expect_status 204 "$SMOKE_STATUS" "delete notice"
smoke_request DELETE "/settings/$LID"
expect_status 204 "$SMOKE_STATUS" "delete locked"

echo
echo "All settings smoke tests passed."
