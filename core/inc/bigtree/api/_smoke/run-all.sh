#!/usr/bin/env bash
# Run the Phase 2 P0 smoke matrix against a live API.
#
# Usage:
#   export BIGTREE_API_BASE=http://127.0.0.1:8080/admin/api/v1
#   export BIGTREE_TEST_EMAIL=...
#   export BIGTREE_TEST_PASSWORD=...
#   bash core/inc/bigtree/api/_smoke/run-all.sh
#
# Optional: BIGTREE_SMOKE_SCRIPTS="auth.sh tags.sh" to run a subset.
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$DIR/../../../../../" && pwd)"
cd "$DIR"

: "${BIGTREE_API_BASE:?must set BIGTREE_API_BASE}"
: "${BIGTREE_TEST_EMAIL:?must set BIGTREE_TEST_EMAIL}"
: "${BIGTREE_TEST_PASSWORD:?must set BIGTREE_TEST_PASSWORD}"

# Order: auth first (token gate + many login attempts), then mutative domains.
DEFAULT_SCRIPTS=(
	auth.sh
	tags.sh
	pages.sh
	users.sh
	settings.sh
	modules-news.sh
	developer-templates.sh
	resources.sh
	page-sites.sh
)

if [ -n "${BIGTREE_SMOKE_SCRIPTS:-}" ]; then
	# shellcheck disable=SC2206
	SCRIPTS=($BIGTREE_SMOKE_SCRIPTS)
else
	SCRIPTS=("${DEFAULT_SCRIPTS[@]}")
fi

FAILED=0
PASSED=0

printf "\n\033[1mBigTree API smoke matrix\033[0m\n"
printf "  base=%s\n  user=%s\n\n" "$BIGTREE_API_BASE" "$BIGTREE_TEST_EMAIL"

# Reset auth rate-limit counters so the matrix can proceed after auth.sh
# (login is 10/min/IP; auth alone can consume most of a window).
smoke_reset_rate_limits() {
	# Prefer PHP + project env (works in CI and local). Fail soft if DB unreachable.
	if [ -f "$REPO_ROOT/custom/environment.php" ]; then
		(cd "$REPO_ROOT" && php -r '
			$bigtree = ["config" => ["debug" => false]];
			@include "custom/environment.php";
			@include "custom/settings.php";
			if (empty($bigtree["config"]["db"]["host"])) { exit(0); }
			require "core/bootstrap.php";
			try {
				SQL::query("TRUNCATE TABLE bigtree_api_rate_limits");
				SQL::query("TRUNCATE TABLE bigtree_login_attempts");
			} catch (Throwable $e) {
				// ignore — table may not exist on partial installs
			}
		') 2>/dev/null || true
	fi
}

# Obtain a single ACCESS token for scripts that source common.sh / smoke_login.
smoke_matrix_login() {
	local response
	response=$(curl -s -X POST "$BIGTREE_API_BASE/auth/login" \
		-H "Content-Type: application/json" \
		--data "{\"email\":\"$BIGTREE_TEST_EMAIL\",\"password\":\"$BIGTREE_TEST_PASSWORD\"}")
	ACCESS=$(echo "$response" | jq -r '.data.access_token // empty')
	if [ -z "$ACCESS" ] || [ "$ACCESS" = "null" ]; then
		echo "FAIL: matrix login failed: $response" >&2
		exit 1
	fi
	export ACCESS
	echo "  ✓ matrix access token ready"
}

for script in "${SCRIPTS[@]}"; do
	path="$DIR/$script"
	if [ ! -f "$path" ]; then
		echo "SKIP: $script (not found)"
		continue
	fi
	printf "\n\033[1;35m══ %s ══\033[0m\n" "$script"

	# After auth.sh, clear rate limits and mint one shared token for the rest.
	if [ "$script" != "auth.sh" ] && [ -z "${ACCESS:-}" ]; then
		smoke_reset_rate_limits
		smoke_matrix_login
	fi

	# auth.sh must not inherit a token — it exercises login itself.
	if [ "$script" = "auth.sh" ]; then
		if env -u ACCESS -u REFRESH bash "$path"; then
			PASSED=$((PASSED + 1))
		else
			echo "FAIL: $script exited non-zero" >&2
			FAILED=$((FAILED + 1))
			if [ "${BIGTREE_SMOKE_FAIL_FAST:-0}" = "1" ]; then
				exit 1
			fi
		fi
		# Prepare shared token for subsequent scripts.
		smoke_reset_rate_limits
		smoke_matrix_login
		continue
	fi

	if bash "$path"; then
		PASSED=$((PASSED + 1))
	else
		echo "FAIL: $script exited non-zero" >&2
		FAILED=$((FAILED + 1))
		if [ "${BIGTREE_SMOKE_FAIL_FAST:-0}" = "1" ]; then
			exit 1
		fi
	fi
done

echo
echo "----------------------------------------"
echo "Smoke scripts passed: $PASSED    failed: $FAILED"
if [ "$FAILED" -gt 0 ]; then
	exit 1
fi
echo "All selected smoke scripts passed."
