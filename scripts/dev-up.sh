#!/usr/bin/env bash
# dev-up.sh — bring up a working BigTree stack (MySQL, PHP API + packaged SPA,
# optionally Vite) from a bare checkout. Safe to run again: every step checks
# its own state first, so a second run changes nothing and just reports.
#
# It is the CI recipe (.github/workflows/ci.yml, the smoke and e2e jobs) made
# repeatable: load core/setup/base.sql, run scripts/ci-install.sh, seed a
# developer admin and the About page, start `php -S` behind ci-router.php.
#
# Usage (from anywhere):
#   scripts/dev-up.sh            # MySQL + PHP on :8080
#   scripts/dev-up.sh --vite     # ... plus the Vite dev server on :5173
#   scripts/dev-up.sh --reset    # drop and reload the database, re-run ci-install.sh
#   scripts/dev-up.sh --stop     # stop the PHP and Vite servers this script started
#
# Environment (all optional):
#   BIGTREE_DEV_DB_HOST        default 127.0.0.1
#   BIGTREE_DEV_DB_PORT        default 3306
#   BIGTREE_DEV_DB_NAME        default bigtree_dev
#   BIGTREE_DEV_DB_USER        default bigtree
#   BIGTREE_DEV_DB_PASSWORD    default bigtree-dev
#   BIGTREE_DEV_MYSQL_ADMIN    the admin mysql command used to create the database and
#                              user, default "mysql -uroot" (socket auth, as Ubuntu's
#                              root account uses auth_socket); prefixed with sudo when
#                              not running as root. e.g. "mysql -h127.0.0.1 -P3307 -uroot -proot"
#   BIGTREE_DEV_PHP_PORT       default 8080
#   BIGTREE_DEV_VITE_PORT      default 5173
#   BIGTREE_DEV_EMAIL          default dev@local.test
#   BIGTREE_DEV_PASSWORD       default DevPassword-2026!
#   BIGTREE_DEV_LOG_DIR        default /tmp (bigtree-php.log, bigtree-vite.log, pid files)
#
# This writes custom/, site/, templates/, extensions/ and cache/ (all gitignored).
# It refuses to touch a custom/environment.php it did not write, so it cannot
# clobber a real local site's configuration.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

DB_HOST="${BIGTREE_DEV_DB_HOST:-127.0.0.1}"
DB_PORT="${BIGTREE_DEV_DB_PORT:-3306}"
DB_NAME="${BIGTREE_DEV_DB_NAME:-bigtree_dev}"
DB_USER="${BIGTREE_DEV_DB_USER:-bigtree}"
DB_PASSWORD="${BIGTREE_DEV_DB_PASSWORD:-bigtree-dev}"
PHP_PORT="${BIGTREE_DEV_PHP_PORT:-8080}"
VITE_PORT="${BIGTREE_DEV_VITE_PORT:-5173}"
DEV_EMAIL="${BIGTREE_DEV_EMAIL:-dev@local.test}"
DEV_PASSWORD="${BIGTREE_DEV_PASSWORD:-DevPassword-2026!}"
LOG_DIR="${BIGTREE_DEV_LOG_DIR:-/tmp}"

PHP_LOG="$LOG_DIR/bigtree-php.log"
PHP_PID="$LOG_DIR/bigtree-php.pid"
VITE_LOG="$LOG_DIR/bigtree-vite.log"
VITE_PID="$LOG_DIR/bigtree-vite.pid"
BASE_URL="http://127.0.0.1:${PHP_PORT}/"
API_BASE="${BASE_URL}admin/api/v1"
INSTALL_MARKER="Written by scripts/ci-install.sh"

WANT_VITE=0
WANT_RESET=0
WANT_STOP=0

for arg in "$@"; do
	case "$arg" in
		--vite)
			WANT_VITE=1
			;;
		--reset)
			WANT_RESET=1
			;;
		--stop)
			WANT_STOP=1
			;;
		-h | --help)
			sed -n '2,33p' "$0" | sed 's/^# \{0,1\}//'
			exit 0
			;;
		*)
			echo "dev-up: unknown option $arg (try --help)" >&2
			exit 2
			;;
	esac
done

say() {
	printf '\033[1;34m==>\033[0m %s\n' "$*"
}

die() {
	printf '\033[1;31mdev-up:\033[0m %s\n' "$*" >&2
	exit 1
}

# Stops the server whose pid file is $1, if that process is still alive.
stop_pidfile() {
	local file="$1"
	local label="$2"

	if [ -f "$file" ]; then
		local pid
		pid="$(cat "$file")"

		if kill -0 "$pid" 2>/dev/null; then
			kill "$pid" 2>/dev/null || true
			say "Stopped $label (pid $pid)"
		fi

		rm -f "$file"
	fi
}

if [ "$WANT_STOP" = "1" ]; then
	stop_pidfile "$VITE_PID" "Vite"
	stop_pidfile "$PHP_PID" "PHP server"
	exit 0
fi

# Checked before anything else, so a real site's checkout is left entirely alone.
if [ -f custom/environment.php ] && ! grep -q "$INSTALL_MARKER" custom/environment.php; then
	die "custom/environment.php exists and was not written by ci-install.sh — this looks like a real site, so dev-up will not overwrite it. Use a fresh checkout or git worktree."
fi

mkdir -p "$LOG_DIR"

# ---------------------------------------------------------------------------
# MySQL
# ---------------------------------------------------------------------------

if [ -n "${BIGTREE_DEV_MYSQL_ADMIN:-}" ]; then
	read -r -a MYSQL_ADMIN <<<"$BIGTREE_DEV_MYSQL_ADMIN"
elif [ "$(id -u)" = "0" ]; then
	MYSQL_ADMIN=(mysql -uroot)
else
	MYSQL_ADMIN=(sudo mysql -uroot)
fi

# The app user's password goes in an option file rather than on the command line,
# which keeps it out of `ps` and stops every call warning about it on stderr.
APP_CNF="$LOG_DIR/bigtree-dev-my.cnf"
umask 077
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\nprotocol=TCP\n' \
	"$DB_HOST" "$DB_PORT" "$DB_USER" "$DB_PASSWORD" >"$APP_CNF"
umask 022
MYSQL_APP=(mysql "--defaults-extra-file=$APP_CNF")

command -v mysql >/dev/null || die "the mysql client is not installed (apt-get install -y mysql-server-8.0)"
command -v php >/dev/null || die "php is not installed"

admin_sql() {
	"${MYSQL_ADMIN[@]}" -N -e "$1"
}

app_sql() {
	"${MYSQL_APP[@]}" -N "$DB_NAME" -e "$1"
}

app_sql_quiet() {
	app_sql "$1" 2>/dev/null
}

if ! admin_sql "SELECT 1" >/dev/null 2>&1; then
	say "MySQL is not answering; starting it"

	if command -v service >/dev/null && service mysql start >/dev/null 2>&1; then
		:
	elif command -v mysqld_safe >/dev/null; then
		mkdir -p /var/run/mysqld
		chown mysql:mysql /var/run/mysqld 2>/dev/null || true
		nohup mysqld_safe --user=mysql >"$LOG_DIR/bigtree-mysqld.log" 2>&1 &
	else
		die "MySQL is not running and neither 'service mysql start' nor mysqld_safe is available"
	fi

	for i in $(seq 1 30); do
		if admin_sql "SELECT 1" >/dev/null 2>&1; then
			break
		fi

		if [ "$i" -eq 30 ]; then
			die "MySQL did not come up within 30s (see $LOG_DIR/bigtree-mysqld.log or /var/log/mysql/error.log)"
		fi

		sleep 1
	done
fi

say "MySQL is up ($("${MYSQL_ADMIN[@]}" -N -e 'SELECT VERSION()' 2>/dev/null))"

if [ "$WANT_RESET" = "1" ]; then
	say "Dropping database $DB_NAME"
	admin_sql "DROP DATABASE IF EXISTS \`$DB_NAME\`"
fi

# Both host forms: a TCP connection to 127.0.0.1 can match either, depending on
# whether the server resolves names.
admin_sql "
	CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
	CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
	CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASSWORD';
	GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
	GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'127.0.0.1';
"

app_sql_quiet "SELECT 1" >/dev/null || die "cannot connect as $DB_USER to $DB_NAME on $DB_HOST:$DB_PORT"

if app_sql_quiet "SELECT 1 FROM bigtree_users LIMIT 1" >/dev/null; then
	say "Schema already loaded in $DB_NAME"
else
	say "Loading core/setup/base.sql into $DB_NAME"
	"${MYSQL_APP[@]}" "$DB_NAME" <core/setup/base.sql
fi

# ---------------------------------------------------------------------------
# Site install (custom/, site/, templates/, cache/)
# ---------------------------------------------------------------------------

if [ -f custom/environment.php ] && [ "$WANT_RESET" = "0" ]; then
	say "Site already installed (custom/environment.php present)"
else
	say "Running scripts/ci-install.sh"
	BIGTREE_CI_DB_HOST="$DB_HOST" \
		BIGTREE_CI_DB_USER="$DB_USER" \
		BIGTREE_CI_DB_PASSWORD="$DB_PASSWORD" \
		BIGTREE_CI_SETTINGS_KEY="dev-settings-key-aaaaaaaaaaaaaaaaaaaa" \
		BIGTREE_CI_JWT_SECRET="dev-jwt-secret-at-least-32-bytes-long" \
		scripts/ci-install.sh "$BASE_URL" "$DB_NAME" "$DB_PORT" >/dev/null
fi

# ---------------------------------------------------------------------------
# Seed data (each insert only when missing, so a re-run leaves it alone)
# ---------------------------------------------------------------------------

EMAIL_SQL="${DEV_EMAIL//\'/\'\'}"

if [ "$(app_sql "SELECT COUNT(*) FROM bigtree_users WHERE email='$EMAIL_SQL'")" = "0" ]; then
	say "Seeding developer admin $DEV_EMAIL"
	HASH="$(BT_PW="$DEV_PASSWORD" php -r 'echo password_hash(getenv("BT_PW"), PASSWORD_DEFAULT);')"
	app_sql "INSERT INTO bigtree_users (email,password,new_hash,\`2fa_secret\`,\`2fa_login_token\`,name,company,level,permissions,alerts,daily_digest,timezone,change_password_hash,token_version) VALUES ('$EMAIL_SQL','$HASH','on','','','Dev Admin','',2,'{}','','','UTC','',1)"
else
	say "Developer admin $DEV_EMAIL already exists"
fi

# The About page the e2e job seeds; the home page (id 0) comes from base.sql.
# Columns are quoted because `external` is reserved from MySQL 8.0.27 / 9 on.
app_sql "INSERT IGNORE INTO bigtree_pages (\`id\`,\`trunk\`,\`parent\`,\`in_nav\`,\`nav_title\`,\`route\`,\`path\`,\`title\`,\`meta_keywords\`,\`meta_description\`,\`seo_invisible\`,\`template\`,\`external\`,\`new_window\`,\`resources\`,\`archived\`,\`archived_inherited\`,\`max_age\`,\`last_edited_by\`,\`ga_page_views\`,\`position\`,\`created_at\`,\`updated_at\`) VALUES (1,'',0,'on','About','about','about','About','','','','content','','','{}','','',0,1,0,0,NOW(),NOW())"

# base.sql seeds the revision floor; this only covers a database loaded from an
# older dump. Never raised on an existing row, so pending migrations stay visible.
REV="$(php -r 'include "core/version.php"; echo (int)BIGTREE_REVISION;')"
app_sql "INSERT IGNORE INTO bigtree_settings (id,value,encrypted) VALUES ('bigtree-internal-revision','$REV','')"

# ---------------------------------------------------------------------------
# PHP server
# ---------------------------------------------------------------------------

http_code() {
	curl -s -o /dev/null -w '%{http_code}' "$1" || true
}

if [ "$(http_code "${API_BASE}/auth/login-policy")" = "200" ]; then
	say "PHP server already answering on :$PHP_PORT"
else
	say "Starting PHP server on :$PHP_PORT (log $PHP_LOG)"
	REPO_ROOT="$ROOT/" nohup php -S "127.0.0.1:$PHP_PORT" \
		core/inc/bigtree/api/_smoke/ci-router.php >"$PHP_LOG" 2>&1 &
	echo $! >"$PHP_PID"

	for i in $(seq 1 30); do
		if [ "$(http_code "${API_BASE}/auth/login-policy")" = "200" ]; then
			break
		fi

		if [ "$i" -eq 30 ]; then
			tail -n 40 "$PHP_LOG" >&2 || true
			die "PHP server did not answer /auth/login-policy within 30s"
		fi

		sleep 1
	done
fi

# ---------------------------------------------------------------------------
# Vite (optional)
# ---------------------------------------------------------------------------

if [ "$WANT_VITE" = "1" ]; then
	if [ "$(http_code "http://127.0.0.1:$VITE_PORT/")" = "200" ]; then
		say "Vite already answering on :$VITE_PORT"
	else
		if [ ! -d spa/node_modules ]; then
			say "Installing SPA dependencies (npm ci)"
			(cd spa && npm ci --no-audit --no-fund >/dev/null)
		fi

		say "Starting Vite on :$VITE_PORT (log $VITE_LOG)"
		(
			cd spa
			VITE_API_TARGET="$API_BASE" nohup npm run dev -- --host 127.0.0.1 --port "$VITE_PORT" --strictPort \
				>"$VITE_LOG" 2>&1 &
			echo $! >"$VITE_PID"
		)

		for i in $(seq 1 60); do
			if [ "$(http_code "http://127.0.0.1:$VITE_PORT/")" = "200" ]; then
				break
			fi

			if [ "$i" -eq 60 ]; then
				tail -n 40 "$VITE_LOG" >&2 || true
				die "Vite did not answer within 60s"
			fi

			sleep 1
		done
	fi
fi

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------

cat <<EOF

BigTree is up.
  API            ${API_BASE}
  Packaged SPA   ${BASE_URL}admin/   (core/admin/dist — rebuild + repackage to see SPA source changes)
EOF

if [ "$WANT_VITE" = "1" ]; then
	echo "  Vite SPA       http://127.0.0.1:${VITE_PORT}/   (live source, proxies the API)"
fi

cat <<EOF
  Login          ${DEV_EMAIL} / ${DEV_PASSWORD}
  Database       ${DB_NAME} on ${DB_HOST}:${DB_PORT} (${DB_USER} / ${DB_PASSWORD})
  Logs           ${PHP_LOG}$([ "$WANT_VITE" = "1" ] && echo ", ${VITE_LOG}")
  Stop           scripts/dev-up.sh --stop
EOF
