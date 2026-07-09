<?php
	namespace BigTree\Services;

	use BigTree\Api\Jwt;
	use BigTree\Api\Manifest;
	use BigTree\Api\OpenApi;
	use BigTree\Api\Pagination;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\AuthenticationException;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\ConflictException;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTreeAdmin;
	use BigTreeCMS;
	use BigTreeUpdater;
	use BigTree;
	use SQL;

	/**
	 * System utilities exposed to the SPA: version, cache clear, security policy,
	 * and DB backup generation/download.
	 */
	class SystemService {

		// Where API-generated backup files live. Top-level .htaccess rewrites every
		// request to site/, so this directory is not web-accessible — backup files
		// are only reachable via the signed download endpoint below.
		const BACKUP_DIR = "cache/api-backups/";
		// Backups expire after this many seconds. GC sweeps on each new backup call.
		const BACKUP_TTL_SECONDS = 3600;
		// Download tokens are tighter than backup retention so a leaked URL has a
		// narrower window of usefulness.
		const DOWNLOAD_TOKEN_TTL_SECONDS = 900;

		// Upstream release feed the legacy admin polls. Downloads are only ever
		// fetched from this host (see the SSRF guard in downloadUpgrade).
		const VERSION_CHECK_URL = "https://www.bigtreecms.org/ajax/version-check/";

		public function version(Request $request) {
			$revision = (int)BigTreeCMS::getSetting("bigtree-internal-revision");
			$version_file = SERVER_ROOT . "core/version.php";
			$version = "";

			if (file_exists($version_file)) {
				include $version_file;
				$version = $bigtree_version ?? "";
			}

			return Response::ok([
				"version" => $version,
				"revision" => $revision,
				"php" => PHP_VERSION,
			]);
		}

		/**
		 * GET /openapi.json
		 *
		 * Returns an OpenAPI 3.0.3 document generated from the live route manifest.
		 * Served without authentication so external clients and tooling can discover
		 * the API contract without a token. Change permission to ["level" => 0] if
		 * the spec should be admin-only.
		 */
		public function openapi(Request $request) {
			return Response::raw(200, OpenApi::build(Manifest::load()));
		}

		public function clearCache(Request $request) {
			$dir = SERVER_ROOT . "cache/";
			// Preserve: gitkeep, hooks registry (legacy admin manages it), and
			// API-generated backups (separate explicit lifecycle).
			$preserve = [".gitkeep", "bigtree-hooks.json"];
			$preserve_dirs = ["api-backups"];

			foreach (scandir($dir) ?: [] as $f) {
				if ($f === "." || $f === "..") {
					continue;
				}

				if (in_array($f, $preserve, true)) {
					continue;
				}
				$path = $dir . $f;

				if (is_dir($path)) {
					if (in_array($f, $preserve_dirs, true)) {
						continue;
					}
					BigTree::deleteDirectory($path);
				} else {
					@unlink($path);
				}
			}

			Manifest::clearCache();

			return Response::noContent();
		}

		public function getSecurityPolicy(Request $request) {
			$policy = BigTreeCMS::getSetting("bigtree-internal-security-policy") ?: [];

			return Response::ok($policy);
		}

		public function updateSecurityPolicy(Request $request) {
			$existing = BigTreeCMS::getSetting("bigtree-internal-security-policy") ?: [];
			$merged = array_replace_recursive(is_array($existing) ? $existing : [], $request->body);
			BigTreeAdmin::updateInternalSettingValue("bigtree-internal-security-policy", $merged);

			return Response::ok($merged);
		}

		public function unbanIP(Request $request) {
			$ip = (string)$request->body["ip"];
			SQL::query("DELETE FROM bigtree_login_bans WHERE ip = ?", ip2long($ip));

			return Response::noContent();
		}

		public function unbanUser(Request $request) {
			$user_id = (int)$request->body["user_id"];
			SQL::query("UPDATE bigtree_login_bans SET expires = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE user = ?", $user_id);

			return Response::noContent();
		}

		/**
		 * GET /system/site
		 *
		 * Lightweight site context for the SPA shell header:
		 *   - nav_title of the root page (id 0) — matches legacy admin header exactly
		 *   - www_root from config so "View site" links to the public frontend
		 */
		public function site(Request $request) {
			global $bigtree;

			$root = SQL::fetch("SELECT nav_title FROM bigtree_pages WHERE id = 0");

			return Response::ok([
				"nav_title" => html_entity_decode((string)($root["nav_title"] ?? "BigTree"), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
				"www_root" => $bigtree["config"]["www_root"] ?? "",
				// The classic admin's root — the SPA links here for legacy-only
				// surfaces (custom-PHP module actions, front-end preview, etc.).
				"admin_root" => $bigtree["config"]["admin_root"] ?? "",
			]);
		}

		/**
		 * GET /system/status
		 *
		 * Faithful port of the legacy developer "Site Status" page
		 * (core/admin/modules/developer/status.php). Two groups:
		 *
		 *   warnings   — directory writability (fixed list + every upload field's
		 *                configured directory across forms/embed forms/templates/
		 *                callouts), pages that link to the admin, and a missing
		 *                favicon.
		 *   parameters — PHP environment checks. The obsolete legacy checks
		 *                (magic_quotes_gpc/runtime, short_open_tag, the bare `mysql`
		 *                extension) are dropped; `mysql` is modernized to `mysqli`.
		 *
		 * status values match the legacy CSS classes: "bad" (critical / red),
		 * "ok" (warning / yellow), "good" (success / green).
		 */
		public function siteStatus(Request $request) {
			$warnings = [];

			// — Fixed list of directories that must be writable —
			$writable_directories = [
				"cache/",
				"custom/inc/modules/",
				"custom/admin/field-types/",
				"templates/routed/",
				"templates/basic/",
				"templates/callouts/",
				"site/files/",
				"custom/json-db/",
			];

			foreach ($writable_directories as $directory) {
				if (!BigTree::isDirectoryWritable(SERVER_ROOT . $directory)) {
					$warnings[] = [
						"parameter" => "Directory Permissions Error",
						"rec" => "Make " . SERVER_ROOT . $directory . " writable.",
						"status" => "bad",
					];
				}
			}

			// — Every upload field's configured directory must be writable —
			// Recurses matrix sub-columns and dedupes so a directory used by many
			// fields only warns once.
			$directory_warnings = [];

			$recurse_fields = function ($fields) use (&$recurse_fields, &$warnings, &$directory_warnings) {
				foreach (array_filter((array)$fields) as $data) {
					if (empty($data["settings"]) && !empty($data["options"])) {
						$data["settings"] = $data["options"];
					}

					if (empty($data["settings"])) {
						$data["settings"] = [];
					}

					$settings = is_string($data["settings"]) ? array_filter((array)json_decode($data["settings"], true)) : $data["settings"];

					if (($data["type"] ?? "") == "matrix") {
						$recurse_fields($settings["columns"] ?? []);
					} elseif (!empty($settings["directory"])) {
						if (!BigTree::isDirectoryWritable(SITE_ROOT . $settings["directory"]) && !in_array($settings["directory"], $directory_warnings)) {
							$directory_warnings[] = $settings["directory"];
							$warnings[] = [
								"parameter" => "Directory Permissions Error",
								"rec" => "Make " . SITE_ROOT . $settings["directory"] . " writable.",
								"status" => "bad",
							];
						}
					}
				}
			};

			$forms = array_merge(BigTreeAdmin::getModuleForms(), BigTreeAdmin::getModuleEmbedForms());

			foreach ($forms as $form) {
				$recurse_fields($form["fields"]);
			}

			$templates = array_merge(BigTreeAdmin::getTemplates(), BigTreeAdmin::getCallouts());

			foreach ($templates as $template) {
				$recurse_fields($template["resources"]);
			}

			// — Pages whose content links directly to the admin —
			// The SPA builds its own link from page_id/nav_title (legacy emitted
			// raw <a> markup pointing at the old admin route).
			foreach (BigTreeAdmin::getPageAdminLinks() as $page) {
				$warnings[] = [
					"parameter" => "Bad Admin Links",
					"rec" => "Remove links to the admin in this page's content.",
					"status" => "ok",
					"page_id" => (int)$page["id"],
					"nav_title" => html_entity_decode((string)$page["nav_title"], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
				];
			}

			if (!file_exists(SITE_ROOT . "favicon.ico")) {
				$warnings[] = [
					"parameter" => "Missing Favicon",
					"rec" => "Create a favicon and place it in the /site/ root.",
					"status" => "ok",
				];
			}

			// — Server parameters —
			$upload_max_filesize = ini_get("upload_max_filesize");
			$post_max_size = ini_get("post_max_size");
			$max_file = (intval($upload_max_filesize) > intval($post_max_size)) ? intval($post_max_size) : intval($upload_max_filesize);

			$max_check = "bad";

			if ($max_file >= 4) {
				$max_check = "ok";
			}

			if ($max_file >= 8) {
				$max_check = "good";
			}

			$mem_limit = ini_get("memory_limit");

			$parameters = [
				[
					"parameter" => "Allow File Uploads",
					"rec" => "\u{201C}file_uploads = On\u{201D} in php.ini",
					"status" => ini_get("file_uploads") ? "good" : "bad",
				],
				[
					"parameter" => "Allow 4MB Uploads",
					"rec" => "\u{201C}upload_max_filesize\u{201D} and \u{201C}post_max_size\u{201D} > 4M \u{2014} ideally 8M or higher in php.ini",
					"status" => $max_check,
					"value" => $max_file . "M",
				],
				[
					"parameter" => "Memory Limit",
					"rec" => "\u{201C}memory_limit\u{201D} > 32M in php.ini",
					"status" => (intval($mem_limit) > 32) ? "good" : "bad",
					"value" => $mem_limit,
				],
				[
					"parameter" => "MySQL Support",
					"rec" => "MySQLi extension is required",
					"status" => extension_loaded("mysqli") ? "good" : "bad",
				],
				[
					"parameter" => "Image Processing",
					"rec" => "GD extension is required",
					"status" => extension_loaded("gd") ? "good" : "bad",
				],
				[
					"parameter" => "cURL Support",
					"rec" => "cURL extension is required",
					"status" => extension_loaded("curl") ? "good" : "bad",
				],
			];

			return Response::ok([
				"warnings" => $warnings,
				"parameters" => $parameters,
			]);
		}

		// — Database backups —

		/**
		 * POST /system/backup
		 *
		 * Generates a SQL dump of every table via SQL::backup() and writes it to
		 * cache/api-backups/{id}.sql. Returns metadata + a fully-formed signed
		 * download URL the SPA can drop into an <a> tag — the download endpoint
		 * doesn't require a JWT (it uses a short-lived HMAC token instead) so a
		 * browser-native download works without JS blob handling.
		 *
		 * Synchronous. Backups on a multi-GB database take minutes — clients should
		 * set a generous timeout. A sentinel file prevents concurrent runs so we
		 * don't fight ourselves over the same SHOW TABLES + dump cycle.
		 *
		 * GC: every call sweeps backups older than BACKUP_TTL_SECONDS so leftover
		 * dumps from previous sessions don't pile up.
		 */
		public function createBackup(Request $request) {
			$dir = SERVER_ROOT . self::BACKUP_DIR;
			$this->ensureBackupDir();
			$this->gcExpiredBackups();

			$sentinel = $dir . ".building.flag";
			if (file_exists($sentinel) && (time() - @filemtime($sentinel)) < 1800) {
				$age = time() - @filemtime($sentinel);
				throw new ConflictException("Backup already in progress (started {$age}s ago)", "backup_in_progress");
			}
			@file_put_contents($sentinel, (string)time());

			$backup_id = bin2hex(random_bytes(16));
			$path = $dir . $backup_id . ".sql";

			$started_at = microtime(true);
			try {
				$ok = SQL::backup($path);
			} catch (\Throwable $e) {
				@unlink($sentinel);
				@unlink($path);
				throw new BadRequestException(
					"Backup failed: " . $e->getMessage(),
					"backup_failed",
					500
				);
			}
			@unlink($sentinel);

			if (!$ok || !file_exists($path)) {
				throw new BadRequestException(
					"SQL::backup returned false (cache/ may not be writable)",
					"backup_failed",
					500
				);
			}

			$size = (int)@filesize($path);
			$expires_at = time() + self::BACKUP_TTL_SECONDS;

			return Response::created([
				"backup_id" => $backup_id,
				"filename" => "bigtree-backup-" . date("Y-m-d-His") . ".sql",
				"size_bytes" => $size,
				"created_at" => date("c"),
				"expires_at" => date("c", $expires_at),
				"elapsed_ms" => (int)round((microtime(true) - $started_at) * 1000),
				"download_url" => $this->buildDownloadUrl($backup_id, (int)$request->user->id),
			], null);
		}

		/**
		 * GET /system/backup
		 *
		 * Lists the backups that haven't expired yet so the user can clean up
		 * stragglers or grab a download link without re-running a backup.
		 */
		public function listBackups(Request $request) {
			$this->ensureBackupDir();
			$this->gcExpiredBackups();

			$dir = SERVER_ROOT . self::BACKUP_DIR;
			$files = glob($dir . "*.sql") ?: [];
			$out = [];
			foreach ($files as $file) {
				$backup_id = basename($file, ".sql");
				$mtime = (int)@filemtime($file);
				$out[] = [
					"backup_id" => $backup_id,
					"size_bytes" => (int)@filesize($file),
					"created_at" => date("c", $mtime),
					"expires_at" => date("c", $mtime + self::BACKUP_TTL_SECONDS),
					"age_seconds" => time() - $mtime,
					"download_url" => $this->buildDownloadUrl($backup_id, (int)$request->user->id),
				];
			}
			// Most recent first
			usort($out, function ($a, $b) {
				return strcmp($b["created_at"], $a["created_at"]);
			});
			return Response::ok($out);
		}

		/**
		 * DELETE /system/backup/{id}
		 *
		 * Removes a backup file explicitly. (TTL-based GC also handles it eventually.)
		 */
		public function deleteBackup(Request $request) {
			$backup_id = $this->sanitizeBackupId($request->routeParam("id"));
			$path = SERVER_ROOT . self::BACKUP_DIR . $backup_id . ".sql";
			if (!file_exists($path)) {
				throw new NotFoundException("Backup not found", "backup_not_found");
			}
			@unlink($path);
			return Response::noContent();
		}

		/**
		 * GET /system/backup/{id}/download?token=...
		 *
		 * Streams the backup file via PHP's readfile() — no buffering, no memory
		 * pressure regardless of dump size. Public route (no Bearer required) so
		 * the SPA can use a regular <a download> link; the URL itself is gated by
		 * an HMAC-signed token (issued by createBackup/listBackups) bound to a
		 * specific backup_id with a short TTL.
		 *
		 * The token is BEARER-style, not user-scoped: because the route is
		 * "public" (Authenticate skips it before $request->user is loaded) there
		 * is no authenticated user to compare against, so anyone holding a valid,
		 * unexpired token for this backup_id may redeem it. We validate the claims
		 * we can enforce here — bid (backup id) and exp (expiry). The uid claim is
		 * minted for audit/correlation only and is intentionally NOT enforced (see
		 * buildDownloadUrl). Keep these checks in sync with the claims minted there.
		 *
		 * The token check uses hash_equals via Pagination::decodeCursor for
		 * constant-time comparison.
		 */
		public function downloadBackup(Request $request) {
			$backup_id = $this->sanitizeBackupId($request->routeParam("id"));
			$raw_token = $request->queryString("token", "", false);
			if ($raw_token === "") {
				throw new AuthenticationException("Missing download token", "missing_token");
			}

			try {
				$payload = Pagination::decodeCursor($raw_token, Jwt::currentSecret());
			} catch (\Throwable $e) {
				throw new AuthenticationException("Invalid download token", "invalid_token");
			}

			// Enforce the claims minted in buildDownloadUrl. The uid claim is
			// deliberately NOT checked: this is a public route, so there is no
			// authenticated user to compare it against (see method docblock).
			if (($payload["bid"] ?? "") !== $backup_id) {
				throw new AuthenticationException("Token does not match this backup", "token_backup_mismatch");
			}
			if (((int)($payload["exp"] ?? 0)) < time()) {
				throw new AuthenticationException("Download token expired", "token_expired");
			}

			$path = SERVER_ROOT . self::BACKUP_DIR . $backup_id . ".sql";
			if (!file_exists($path)) {
				throw new NotFoundException("Backup file not found or expired", "backup_not_found");
			}

			// We bypass the JSON envelope and stream the file directly. Setting
			// is_envelope = false on a Response prevents the body from being
			// json_encoded; body = null prevents anything other than headers being
			// emitted by Response::send(). We then echo the file contents ourselves.
			$response = Response::raw(200, []);
			$response->is_envelope = false;
			$response->body = null;
			$filename = "bigtree-backup-" . date("Y-m-d-His", (int)@filemtime($path)) . ".sql";
			$response
				->header("Content-Type", "application/sql")
				->header("Content-Disposition", 'attachment; filename="' . $filename . '"')
				->header("Content-Length", (string)@filesize($path))
				->header("Cache-Control", "private, no-store")
				->header("X-Content-Type-Options", "nosniff");

			// We can't rely on the Kernel to readfile for us — Response::send only
			// echoes the JSON body. Send headers + stream + die, bypassing the
			// envelope/audit middleware tail (auditing a 4-second download is moot).
			$response->send(null);

			// readfile streams to output; no buffering required.
			@readfile($path);
			exit;
		}

		// — Core upgrade —
		//
		// The legacy developer/upgrade module was a chain of page loads; this is
		// the same chain expressed as discrete API calls the SPA drives in order:
		//
		//   1. GET  /system/upgrade/check       — what's available + how we can write
		//   2. POST /system/upgrade/download     — fetch + integrity-check the archive
		//   3. POST /system/upgrade/install      — back up + swap in the new core
		//   4. GET  /system/upgrade/migrations   — compute the pending DB script queue
		//   5. POST /system/upgrade/migrate      — run one script (page) at a time
		//
		// Steps 3–5 are intentionally separate requests: install renames /core/ on
		// disk, but the running process already has the old BIGTREE_REVISION
		// constant loaded. Only the *next* request boots from the new version.php,
		// so the migration queue (which compares the stored revision setting to the
		// BIGTREE_REVISION constant) must be computed in a fresh request after the
		// file swap — exactly why the legacy wizard navigated between pages.

		/**
		 * GET /system/upgrade/check
		 *
		 * Polls the upstream release feed and reports the install method we can use
		 * (Local / FTP / SFTP, or null if none) so the SPA knows whether it'll need
		 * to prompt for credentials. Major releases are flagged non-installable —
		 * they're never backwards compatible and must be done by hand.
		 */
		public function checkUpgrade(Request $request) {
			global $bigtree;

			$updater = new BigTreeUpdater();
			$config_ignored = !empty($bigtree["config"]["ignore_admin_updates"]);
			$updates = $this->fetchVersionCheck();
			$out = [];

			foreach ($updates as $type => $update) {
				if (!is_array($update) || empty($update["version"])) {
					continue;
				}

				$out[] = [
					"type" => (string)$type,
					"version" => (string)$update["version"],
					"release_date" => isset($update["release_date"]) ? (string)$update["release_date"] : null,
					"note" => $this->upgradeNote((string)$type),
					"installable" => $type !== "major" && $updater->Method !== false,
				];
			}

			return Response::ok([
				"current_version" => defined("BIGTREE_VERSION") ? BIGTREE_VERSION : "",
				"current_revision" => (int)BigTreeCMS::getSetting("bigtree-internal-revision"),
				"method" => $updater->Method ?: null,
				"config_ignored" => $config_ignored,
				"updates" => $out,
			]);
		}

		/**
		 * POST /system/upgrade/download
		 *
		 * Resolves the download URL server-side from the release feed (never trusts
		 * a client-supplied URL — see the host guard), streams it to cache/update.zip,
		 * and verifies the archive opens cleanly before reporting success.
		 */
		public function downloadUpgrade(Request $request) {
			$type = (string)$request->body["type"];

			$updater = new BigTreeUpdater();

			if ($updater->Method === false) {
				throw new ConflictException("This server can't write to /core/ via local, FTP, or SFTP — upgrade manually.", "upgrade_method_unavailable");
			}

			$updates = $this->fetchVersionCheck();

			if (empty($updates[$type]) || empty($updates[$type]["file"])) {
				throw new NotFoundException("No $type update is currently available", "upgrade_not_available");
			}

			$url = (string)$updates[$type]["file"];
			$scheme = parse_url($url, PHP_URL_SCHEME);
			$host = (string)parse_url($url, PHP_URL_HOST);

			// SSRF guard: the feed is trusted but we still pin the download to HTTPS
			// on the bigtreecms.org domain so a poisoned feed can't redirect us into
			// fetching an attacker-controlled (or internal) URL.
			if ($scheme !== "https" || !preg_match('/(^|\.)bigtreecms\.org$/i', $host)) {
				throw new BadRequestException("Refusing to download from an unexpected source", "upgrade_bad_source");
			}

			$zip_path = SERVER_ROOT . "cache/update.zip";
			@unlink($zip_path);

			BigTree::cURL($url, false, [CURLOPT_TIMEOUT => 300], true, $zip_path);

			if (!file_exists($zip_path) || @filesize($zip_path) === 0) {
				@unlink($zip_path);
				throw new BadRequestException("Download failed or produced an empty file", "upgrade_download_failed", 500);
			}

			if (!$updater->checkZip()) {
				@unlink($zip_path);
				throw new BadRequestException("The downloaded archive is corrupt", "upgrade_zip_corrupt", 500);
			}

			return Response::ok([
				"ok" => true,
				"method" => $updater->Method,
				"version" => (string)$updates[$type]["version"],
				"size_bytes" => (int)@filesize($zip_path),
				"needs_credentials" => $updater->Method !== "Local",
			]);
		}

		/**
		 * POST /system/upgrade/install
		 *
		 * Extracts the archive then swaps the new core into place, backing up the
		 * existing /core/ and a fresh DB dump first. Local installs run in one call.
		 * FTP/SFTP installs need credentials and possibly the install path; when
		 * those are missing we return a "needs" flag instead of failing so the SPA
		 * can collect them and re-submit.
		 */
		public function installUpgrade(Request $request) {
			$zip_path = SERVER_ROOT . "cache/update.zip";

			if (!file_exists($zip_path)) {
				throw new ConflictException("No downloaded update found — run download first", "upgrade_no_archive");
			}

			$updater = new BigTreeUpdater();

			if ($updater->Method === false) {
				throw new ConflictException("This server can't write to /core/ via local, FTP, or SFTP — upgrade manually.", "upgrade_method_unavailable");
			}

			if (!$updater->extract()) {
				$updater->cleanup();
				throw new BadRequestException("Failed to extract the update archive", "upgrade_extract_failed", 500);
			}

			if ($updater->Method === "Local") {
				$updater->installLocal();

				return Response::ok(["ok" => true, "method" => "Local", "next" => "migrate"]);
			}

			// FTP / SFTP path — credentials arrive in the body.
			$username = $request->bodyString("ftp_username", "", false);
			$password = $request->bodyString("ftp_password", "", false);

			if ($username === "") {
				return Response::ok(["ok" => false, "needs_credentials" => true, "method" => $updater->Method]);
			}

			if (!$updater->ftpLogin($username, $password)) {
				throw new AuthenticationException("{$updater->Method} login failed", "upgrade_ftp_login_failed");
			}

			$ftp_root = $request->bodyString("ftp_root");

			if ($ftp_root === "") {
				$detected = $updater->getFTPRoot();

				if ($detected === false) {
					return Response::ok(["ok" => false, "needs_ftp_root" => true, "method" => $updater->Method]);
				}

				$ftp_root = $detected;
			} else {
				// Remember an operator-supplied path so a later retry can suggest it,
				// mirroring the legacy set-ftp-directory step.
				BigTreeAdmin::updateInternalSettingValue("bigtree-internal-ftp-upgrade-root", $ftp_root);

				if (!$updater->Connection->changeDirectory(rtrim($ftp_root, "/") . "/core/inc/bigtree/")) {
					return Response::ok(["ok" => false, "needs_ftp_root" => true, "method" => $updater->Method, "bad_root" => $ftp_root]);
				}
			}

			$updater->installFTP($ftp_root);

			return Response::ok(["ok" => true, "method" => $updater->Method, "next" => "migrate"]);
		}

		/**
		 * GET /system/upgrade/migrations
		 *
		 * Returns the ordered list of pending DB migration script keys. Identical
		 * logic to the legacy scripts.php so the same revision files are reused.
		 */
		public function upgradeMigrations(Request $request) {
			return Response::ok([
				"current_revision" => (int)BigTreeCMS::getSetting("bigtree-internal-revision"),
				"target_revision" => defined("BIGTREE_REVISION") ? BIGTREE_REVISION : null,
				"queue" => $this->buildMigrationQueue(),
			]);
		}

		/**
		 * POST /system/upgrade/migrate
		 *
		 * Runs a single migration script (one page of it). The script key must be a
		 * member of the freshly-computed queue, which both keeps callers in order
		 * and prevents arbitrary file inclusion. The legacy revision scripts echo a
		 * JSON blob and expect the admin ajax environment, so we reproduce $_GET and
		 * the $admin/$cms globals, then capture their output — see runLegacyScript
		 * for why a shutdown handler is involved.
		 *
		 * Returns the legacy contract verbatim: { complete, response, pages?, error? }.
		 */
		public function runUpgradeMigration(Request $request) {
			$script = (string)$request->body["script"];

			if (!in_array($script, $this->buildMigrationQueue(), true)) {
				throw new BadRequestException("Unknown or out-of-order migration script", "upgrade_bad_script");
			}

			$file = SERVER_ROOT . "core/admin/ajax/developer/upgrade/" . $script . ".php";

			if (!file_exists($file)) {
				throw new NotFoundException("Migration script is missing", "upgrade_script_missing");
			}

			$page = isset($request->body["page"]) ? (int)$request->body["page"] : 0;
			$total_pages = isset($request->body["total_pages"]) ? (int)$request->body["total_pages"] : 0;

			if ($page > 0) {
				$_GET["page"] = $page;
			} else {
				unset($_GET["page"]);
			}

			if ($total_pages > 0) {
				$_GET["total_pages"] = $total_pages;
			}

			global $admin, $cms, $bigtree;

			if (!($admin instanceof BigTreeAdmin)) {
				$admin = new BigTreeAdmin();
			}

			if (!($cms instanceof BigTreeCMS)) {
				$cms = new BigTreeCMS();
			}

			return Response::ok($this->runLegacyScript($file, $request));
		}

		// — upgrade helpers —

		private function fetchVersionCheck() {
			$version = defined("BIGTREE_VERSION") ? BIGTREE_VERSION : "";
			$raw = BigTree::cURL(self::VERSION_CHECK_URL . "?current_version=" . urlencode($version));
			$data = json_decode((string)$raw, true);

			return is_array($data) ? $data : [];
		}

		private function upgradeNote($type) {
			switch ($type) {
				case "revision":
					return "Bugfix release — recommended for all installs.";

				case "minor":
					return "Feature release — should be backwards compatible, but test on staging first.";

				case "major":
					return "Major release — not backwards compatible. Must be installed manually.";
			}

			return "";
		}

		private function buildMigrationQueue() {
			$current_revision = (int)BigTreeCMS::getSetting("bigtree-internal-revision");
			$queue = [];

			if ($current_revision < 22) {
				$queue[] = "roll-up-scripts/beta-to-4.0";
			}

			if ($current_revision < 100) {
				$queue[] = "roll-up-scripts/4.0-to-4.1";
			}

			if ($current_revision < 200) {
				$queue[] = "roll-up-scripts/4.1-to-4.2";
				$current_revision = 200;
			}

			$target = defined("BIGTREE_REVISION") ? (int)BIGTREE_REVISION : $current_revision;

			while ($current_revision < $target) {
				$current_revision++;

				if (file_exists(SERVER_ROOT . "core/admin/ajax/developer/upgrade/revisions/$current_revision.php")) {
					$queue[] = "revisions/$current_revision";
				}
			}

			return $queue;
		}

		/**
		 * Runs a legacy upgrade ajax script and returns its decoded JSON payload.
		 *
		 * These scripts were written for one-script-per-request admin ajax calls:
		 * they `echo BigTree::json(...)` and frequently `die()` immediately after.
		 * A bare include would let that die() abort the request before the Kernel
		 * emits the API envelope. So we buffer the script's output and register a
		 * shutdown handler that, if the script exited, re-wraps the captured JSON
		 * in a normal API response. Scripts that fall through instead return here
		 * and we wrap inline; the shared $sent flag stops the handler double-sending.
		 */
		private function runLegacyScript($file, Request $request) {
			$sent = false;
			$base_level = ob_get_level();
			ob_start();

			register_shutdown_function(function () use (&$sent, $base_level, $request) {
				if ($sent) {
					return;
				}

				$raw = "";

				while (ob_get_level() > $base_level) {
					$raw .= (string)ob_get_clean();
				}

				$sent = true;
				(Response::ok($this->decodeScriptOutput($raw)))->send($request->request_id ?? null);
			});

			include $file;

			$raw = (string)ob_get_clean();
			$sent = true;

			return $this->decodeScriptOutput($raw);
		}

		private function decodeScriptOutput($raw) {
			$data = json_decode(trim((string)$raw), true);

			if (!is_array($data)) {
				// No parseable JSON means the script printed an error/notice instead
				// of its envelope. Surface it rather than reporting a phantom success.
				return [
					"complete" => true,
					"error" => "Migration script produced unexpected output",
					"response" => trim((string)$raw),
				];
			}

			return $data;
		}

		// — backup helpers —

		private function ensureBackupDir() {
			$dir = SERVER_ROOT . self::BACKUP_DIR;
			if (!is_dir($dir)) {
				@mkdir($dir, 0700, true);
			}
		}

		private function gcExpiredBackups() {
			$dir = SERVER_ROOT . self::BACKUP_DIR;
			if (!is_dir($dir)) return;
			$cutoff = time() - self::BACKUP_TTL_SECONDS;
			foreach (glob($dir . "*.sql") ?: [] as $file) {
				if (@filemtime($file) < $cutoff) @unlink($file);
			}
		}

		/**
		 * Force backup IDs to the format we generated them in (32 hex chars).
		 * Defeats path traversal — a malicious caller can't supply "../etc/passwd"
		 * because the format check rejects non-hex characters.
		 */
		private function sanitizeBackupId($id) {
			$id = (string)$id;
			if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
				throw new BadRequestException("Invalid backup id", "invalid_backup_id");
			}
			return $id;
		}

		/**
		 * Build an absolute download URL with an HMAC-signed token that scopes the
		 * download to a specific backup_id with a short TTL. Reuses the cursor
		 * encoder so we don't reinvent the HMAC primitive.
		 *
		 * The token is BEARER-style: the /download route is public (no Bearer
		 * required) so a browser <a download> link works, which means downloadBackup
		 * cannot enforce the issuing user. The uid claim is included for
		 * audit/correlation only and is NOT validated at redemption — do not rely on
		 * it for access control. Any claim added here that SHOULD gate access must
		 * also be checked in downloadBackup.
		 */
		private function buildDownloadUrl($backup_id, $user_id) {
			$payload = [
				"uid" => (int)$user_id,
				"bid" => $backup_id,
				"exp" => time() + self::DOWNLOAD_TOKEN_TTL_SECONDS,
			];
			$token = Pagination::encodeCursor($payload, Jwt::currentSecret());
			return rtrim(ADMIN_ROOT, "/") . "/api/v1/system/backup/$backup_id/download?token=" . urlencode($token);
		}
	}
