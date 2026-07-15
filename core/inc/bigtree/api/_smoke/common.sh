#!/usr/bin/env bash
# Shared helpers for BigTree API smoke scripts.
# Source from sibling scripts:  # shellcheck source=common.sh
#   source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"
#
# Required env (set by CI or the caller):
#   BIGTREE_API_BASE   e.g. http://127.0.0.1:8080/admin/api/v1
#   BIGTREE_TEST_EMAIL
#   BIGTREE_TEST_PASSWORD

set -euo pipefail

BASE="${BIGTREE_API_BASE:-http://localhost:8080/admin/api/v1}"
EMAIL="${BIGTREE_TEST_EMAIL:?must set BIGTREE_TEST_EMAIL}"
PASSWORD="${BIGTREE_TEST_PASSWORD:?must set BIGTREE_TEST_PASSWORD}"

# Last HTTP status / body from smoke_request (body path may be empty for 204).
SMOKE_STATUS=""
SMOKE_BODY=""
SMOKE_BODY_FILE=""

say() { printf "\n\033[1;34m▶ %s\033[0m\n" "$*"; }

expect_status() {
	local want="$1" got="$2" label="$3"
	if [ "$got" != "$want" ]; then
		echo "FAIL: $label — expected $want got $got" >&2
		if [ -n "${SMOKE_BODY:-}" ]; then
			echo "  body: $SMOKE_BODY" >&2
		elif [ -n "${SMOKE_BODY_FILE:-}" ] && [ -f "$SMOKE_BODY_FILE" ]; then
			echo "  body: $(head -c 500 "$SMOKE_BODY_FILE")" >&2
		fi
		exit 1
	fi
	echo "  ✓ $label ($got)"
}

# Login and export ACCESS (and optionally REFRESH).
# If ACCESS is already set (e.g. run-all.sh logged in once for the matrix),
# reuse it so we do not trip /auth/login's 10/min rate limit.
smoke_login() {
	if [ -n "${ACCESS:-}" ] && [ "$ACCESS" != "null" ]; then
		# Cheap validity check — if the token is still good, keep it.
		local code
		code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/auth/me" \
			-H "Authorization: Bearer $ACCESS" || true)
		if [ "$code" = "200" ]; then
			echo "  ✓ reusing access token ($EMAIL)"
			return 0
		fi
		echo "  ℹ cached ACCESS rejected ($code); logging in again"
		ACCESS=""
	fi

	local response access
	response=$(curl -s -X POST "$BASE/auth/login" \
		-H "Content-Type: application/json" \
		--data "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}")
	access=$(echo "$response" | jq -r '.data.access_token // empty')
	if [ -z "$access" ] || [ "$access" = "null" ]; then
		echo "FAIL: could not obtain access token" >&2
		echo "  response: $response" >&2
		exit 1
	fi
	ACCESS="$access"
	REFRESH=$(echo "$response" | jq -r '.data.refresh_token // empty')
	export ACCESS REFRESH
	echo "  ✓ logged in as $EMAIL"
}

# smoke_request METHOD PATH [json_body]
# Sets SMOKE_STATUS and SMOKE_BODY. Uses Bearer $ACCESS when set.
smoke_request() {
	local method="$1" path="$2"
	local url="${BASE}${path}"
	local tmp
	tmp=$(mktemp)
	SMOKE_BODY_FILE="$tmp"

	local -a args=(-s -w "%{http_code}" -o "$tmp" -X "$method")
	if [ -n "${ACCESS:-}" ]; then
		args+=(-H "Authorization: Bearer $ACCESS")
	fi
	if [ "$#" -ge 3 ]; then
		args+=(-H "Content-Type: application/json" --data "$3")
	fi

	SMOKE_STATUS=$(curl "${args[@]}" "$url")
	SMOKE_BODY=$(cat "$tmp")
	rm -f "$tmp"
	SMOKE_BODY_FILE=""
}

# jq extract from SMOKE_BODY
smoke_jq() {
	echo "$SMOKE_BODY" | jq -r "$@"
}

smoke_rand() {
	# Portable short unique token (seconds + pid + RANDOM).
	echo "$(date +%s)-$$-$RANDOM"
}
