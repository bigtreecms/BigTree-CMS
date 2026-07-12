#!/usr/bin/env bash
# package-admin-spa.sh — copy spa/dist → core/admin/dist for shipping.
#
# Usage (from repo root):
#   cd spa && npm ci && npm run build
#   ./scripts/package-admin-spa.sh
#
# Options:
#   --check   Build is already in spa/dist; sync to a temp dir and diff against
#             the committed core/admin/dist. Exit 1 if they differ (CI staleness).
#             Does not modify core/admin/dist.
#
# Requirements: spa/dist/index.html must exist (run npm run build first).
# Source maps are never packaged (even if a local Vite build emitted them).

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="${ROOT}/spa/dist"
DEST="${ROOT}/core/admin/dist"
CHECK=0

if [[ "${1:-}" == "--check" ]]; then
	CHECK=1
fi

if [[ ! -f "${SRC}/index.html" ]]; then
	echo "error: ${SRC}/index.html missing — run: cd spa && npm run build" >&2
	exit 1
fi

sync_to() {
	local target="$1"

	mkdir -p "${target}"

	if command -v rsync >/dev/null 2>&1; then
		# Trailing slashes: copy contents of SRC into target.
		rsync -a --delete --exclude='*.map' "${SRC}/" "${target}/"
	else
		rm -rf "${target}"
		mkdir -p "${target}"
		cp -R "${SRC}/." "${target}/"
		find "${target}" -name '*.map' -type f -delete
	fi

	# Belt-and-suspenders: refuse any leftover maps.
	if find "${target}" -name '*.map' -type f | grep -q .; then
		echo "error: source maps present under ${target} — packaging must strip *.map" >&2
		exit 1
	fi

	if [[ ! -f "${target}/index.html" ]]; then
		echo "error: package failed — index.html missing in ${target}" >&2
		exit 1
	fi
}

if [[ "${CHECK}" -eq 1 ]]; then
	if [[ ! -f "${DEST}/index.html" ]]; then
		echo "error: committed ${DEST}/index.html missing — run ./scripts/package-admin-spa.sh and commit core/admin/dist" >&2
		exit 1
	fi

	TMP="$(mktemp -d "${TMPDIR:-/tmp}/bigtree-spa-package.XXXXXX")"
	# shellcheck disable=SC2064
	trap 'rm -rf "${TMP}"' EXIT

	sync_to "${TMP}"

	# Content compare only (ignore ownership/timestamps from rsync -a).
	if ! diff -rq "${TMP}" "${DEST}" >/tmp/bigtree-spa-dist-diff.txt 2>&1; then
		echo "error: core/admin/dist is stale relative to spa/dist." >&2
		echo "Rebuild and repackage, then commit core/admin/dist:" >&2
		echo "  cd spa && npm ci && npm run build && cd .. && ./scripts/package-admin-spa.sh" >&2
		echo "" >&2
		echo "diff -rq summary:" >&2
		cat /tmp/bigtree-spa-dist-diff.txt >&2 || true
		exit 1
	fi

	echo "OK: core/admin/dist matches spa/dist (no source maps)."
	exit 0
fi

sync_to "${DEST}"
echo "Packaged SPA → core/admin/dist/ (maps stripped)."
