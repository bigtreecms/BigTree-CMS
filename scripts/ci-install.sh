#!/usr/bin/env bash
# ci-install.sh — stand up a CI checkout the way core/setup/install.php does.
#
# A git checkout is NOT a working BigTree site: /custom, /templates, /site,
# /cache and /extensions are all gitignored, and the installer is what creates
# them, seeds custom/json-db/ from core/setup/json-db/ and renders
# custom/settings.php from core/setup/settings.php. CI used to hand-roll a
# subset of that inline in each job, which drifted: no seeded templates (so
# every create-page fixture failed), no settings_key (30+ "Undefined array key"
# warnings per run out of cms.php), no templates/callouts/ (create_callout
# refused on an unwritable directory).
#
# Mirroring the installer here keeps the three CI jobs on one definition. When
# core/setup/install.php learns about a new directory or seed file, update this
# script alongside it.
#
# Usage (from repo root):
#   scripts/ci-install.sh <base-url> <db-name> [db-port]
#
# Example:
#   scripts/ci-install.sh http://127.0.0.1:8080/ bigtree_test 3306
#
# Environment:
#   BIGTREE_CI_DB_HOST      default 127.0.0.1
#   BIGTREE_CI_DB_USER      default root
#   BIGTREE_CI_DB_PASSWORD  default root
#   BIGTREE_CI_SETTINGS_KEY default a fixed dummy key
#   BIGTREE_CI_JWT_SECRET   default a fixed dummy secret

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

BASE_URL="${1:-http://localhost/}"
DB_NAME="${2:-bigtree_test}"
DB_PORT="${3:-3306}"
DB_HOST="${BIGTREE_CI_DB_HOST:-127.0.0.1}"
DB_USER="${BIGTREE_CI_DB_USER:-root}"
DB_PASSWORD="${BIGTREE_CI_DB_PASSWORD:-root}"
SETTINGS_KEY="${BIGTREE_CI_SETTINGS_KEY:-ci-settings-key-aaaaaaaaaaaaaaaaaaaa}"
JWT_SECRET="${BIGTREE_CI_JWT_SECRET:-ci-jwt-secret-at-least-32-bytes-long}"

# The installer's directory list (core/setup/install.php, the bt_mkdir_writable
# block). site/files/temporary is not in the installer's list but the resource
# upload path expects it, so CI creates it too.
mkdir -p \
	cache \
	custom/admin/ajax \
	custom/admin/css \
	custom/admin/field-types \
	custom/admin/images \
	custom/admin/modules \
	custom/admin/pages \
	custom/inc/modules \
	custom/inc/required \
	custom/json-db \
	extensions \
	site/css \
	site/extensions \
	site/files/pages \
	site/files/resources \
	site/files/temporary \
	site/images \
	site/js \
	templates/ajax \
	templates/basic \
	templates/callouts \
	templates/layouts \
	templates/routed

# bootstrap.php:44-74 re-merges composer.json and die()s with "BigTree has
# updated your composer.json file" before any test runs unless this exists.
touch cache/composer-check.flag

# The JSON DB seed the installer copies for a non-example-site install. Without
# it there are no templates, and every page fixture (and the "Add page" default
# template the assistant mirrors) has nothing to point at.
cp -R core/setup/json-db/. custom/json-db/

# custom/settings.php rendered from the installer's own template, so CI gets
# every key the app dereferences unconditionally — settings_key above all, which
# cms.php:1436 passes to AES_DECRYPT on every getSetting() call.
BT_SETTINGS_KEY="$SETTINGS_KEY" BT_JWT_SECRET="$JWT_SECRET" php -r '
	$template = file_get_contents("core/setup/settings.php");
	$rendered = str_replace(
		["[settings_key]", "[slash_behavior]"],
		[getenv("BT_SETTINGS_KEY"), "none"],
		$template
	);
	$rendered .= "\n\t// CI-only: JWT signing secret (AuthService::issueTokens).\n";
	$rendered .= "\t\$bigtree[\"config\"][\"api\"][\"jwt_secret\"] = \"" . getenv("BT_JWT_SECRET") . "\";\n";
	file_put_contents("custom/settings.php", $rendered);
'

# environment.php is the installer's other rendered file: DB credentials plus the
# handful of non-DB keys bootstrap.php:6-14 reads before anything else.
cat > custom/environment.php <<PHP
<?php
	// Written by scripts/ci-install.sh — dummy CI values.
	\$bigtree["config"]["debug"] = false;
	\$bigtree["config"]["domain"] = "${BASE_URL}";
	\$bigtree["config"]["www_root"] = "${BASE_URL}";
	\$bigtree["config"]["static_root"] = "${BASE_URL}";
	\$bigtree["config"]["admin_root"] = "${BASE_URL}admin/";
	\$bigtree["config"]["sql_interface"] = "mysqli";

	// Read database.
	\$bigtree["config"]["db"]["host"] = "${DB_HOST}";
	\$bigtree["config"]["db"]["name"] = "${DB_NAME}";
	\$bigtree["config"]["db"]["user"] = "${DB_USER}";
	\$bigtree["config"]["db"]["password"] = "${DB_PASSWORD}";
	\$bigtree["config"]["db"]["port"] = ${DB_PORT};
	\$bigtree["config"]["db"]["socket"] = null;

	// Write database (identical block — single-server CI setup).
	\$bigtree["config"]["db_write"]["host"] = "${DB_HOST}";
	\$bigtree["config"]["db_write"]["name"] = "${DB_NAME}";
	\$bigtree["config"]["db_write"]["user"] = "${DB_USER}";
	\$bigtree["config"]["db_write"]["password"] = "${DB_PASSWORD}";
	\$bigtree["config"]["db_write"]["port"] = ${DB_PORT};
	\$bigtree["config"]["db_write"]["socket"] = null;
PHP

echo "CI install complete (base ${BASE_URL}, db ${DB_NAME}@${DB_HOST}:${DB_PORT})."
