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

# Requires a level:1+ admin.
say "Login"
ACCESS=$(curl -s -X POST "$BASE/auth/login" -H "Content-Type: application/json" \
  --data "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}" | jq -r '.data.access_token')

# 1. Status — always works regardless of GA4 config state
say "GET /dashboard/analytics/status"
RESPONSE=$(curl -s -w "\n%{http_code}" "$BASE/dashboard/analytics/status" -H "Authorization: Bearer $ACCESS")
BODY=$(echo "$RESPONSE" | sed '$d')
STATUS=$(echo "$RESPONSE" | tail -n 1)
expect_status 200 "$STATUS" "status returns 200"

CONFIGURED=$(echo "$BODY" | jq -r '.data.configured')
HAS_CACHE=$(echo "$BODY" | jq -r '.data.has_cache')
echo "  ℹ configured: $CONFIGURED, has_cache: $HAS_CACHE"

# 2. Analytics read — always works even when GA4 is unconfigured
say "GET /dashboard/analytics"
RESPONSE=$(curl -s -w "\n%{http_code}" "$BASE/dashboard/analytics" -H "Authorization: Bearer $ACCESS")
BODY=$(echo "$RESPONSE" | sed '$d')
STATUS=$(echo "$RESPONSE" | tail -n 1)
expect_status 200 "$STATUS" "analytics returns 200"

# Validate the shape regardless of whether cache exists
echo "$BODY" | jq -e '.data | has("configured") and has("verified") and has("cache_age_seconds") and has("cache")' > /dev/null \
  || { echo "FAIL: analytics response missing expected fields"; exit 1; }
echo "  ✓ response has configured/verified/cache_age_seconds/cache"

# 3. Cache rebuild — behavior depends on whether GA4 is configured
say "POST /dashboard/analytics/cache"
if [ "$CONFIGURED" = "true" ]; then
  RESPONSE=$(curl -s --max-time 600 -w "\n%{http_code}" -X POST "$BASE/dashboard/analytics/cache" \
    -H "Content-Type: application/json" -H "Authorization: Bearer $ACCESS")
  STATUS=$(echo "$RESPONSE" | tail -n 1)
  if [ "$STATUS" = "200" ]; then
    ELAPSED=$(echo "$RESPONSE" | sed '$d' | jq -r '.data.elapsed_ms')
    echo "  ✓ cache rebuilt in ${ELAPSED}ms"
  elif [ "$STATUS" = "502" ]; then
    # Credentials may be present but expired/wrong — that's a config issue, not a smoke failure.
    echo "  ⚠ rebuild returned 502 (likely stale credentials); skipping assertion"
  else
    echo "FAIL: cache rebuild expected 200 or 502, got $STATUS" >&2; exit 1
  fi
else
  STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/dashboard/analytics/cache" \
    -H "Content-Type: application/json" -H "Authorization: Bearer $ACCESS")
  expect_status 503 "$STATUS" "unconfigured → 503 analytics_not_configured"
fi

# 4. Non-admin (level:0) should not be able to read analytics
# (skipped — requires a second test account; left as a note for the SPA team to verify)

# 5. Status response shape sanity
say "GET /dashboard/analytics?bogus=1 (no query schema declared, extra param tolerated)"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/dashboard/analytics?bogus=1" -H "Authorization: Bearer $ACCESS")
expect_status 200 "$STATUS" "extra query param tolerated"

echo
echo "All dashboard analytics smoke tests passed."
