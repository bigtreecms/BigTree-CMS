<?php
	namespace BigTree\Services;

	use BigTree\Api\Jwt;
	use BigTree\Api\Manifest;
	use BigTree\Api\Pagination;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\AuthenticationException;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\ConflictException;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTreeAdmin;
	use BigTreeCMS;
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
				throw new ConflictException(
					"Backup already in progress (started {$age}s ago)",
					"backup_in_progress",
					409
				);
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
			$backup_id = $this->sanitizeBackupId($request->route_params["id"] ?? "");
			$path = SERVER_ROOT . self::BACKUP_DIR . $backup_id . ".sql";
			if (!file_exists($path)) {
				throw new NotFoundException("Backup not found", "backup_not_found", 404);
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
		 * an HMAC-signed token (issued by createBackup/listBackups, scoped to the
		 * issuing user_id + backup_id, short TTL).
		 *
		 * The token check uses hash_equals via Pagination::decodeCursor for
		 * constant-time comparison.
		 */
		public function downloadBackup(Request $request) {
			$backup_id = $this->sanitizeBackupId($request->route_params["id"] ?? "");
			$raw_token = (string)($request->query["token"] ?? "");
			if ($raw_token === "") {
				throw new AuthenticationException("Missing download token", "missing_token", 401);
			}

			try {
				$payload = Pagination::decodeCursor($raw_token, Jwt::currentSecret());
			} catch (\Throwable $e) {
				throw new AuthenticationException("Invalid download token", "invalid_token", 401);
			}

			if (($payload["bid"] ?? "") !== $backup_id) {
				throw new AuthenticationException("Token does not match this backup", "token_backup_mismatch", 401);
			}
			if (((int)($payload["exp"] ?? 0)) < time()) {
				throw new AuthenticationException("Download token expired", "token_expired", 401);
			}

			$path = SERVER_ROOT . self::BACKUP_DIR . $backup_id . ".sql";
			if (!file_exists($path)) {
				throw new NotFoundException("Backup file not found or expired", "backup_not_found", 404);
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
				throw new BadRequestException("Invalid backup id", "invalid_backup_id", 400);
			}
			return $id;
		}

		/**
		 * Build an absolute download URL with an HMAC-signed token that scopes the
		 * download to a specific user + backup + short TTL. Reuses the cursor
		 * encoder so we don't reinvent the HMAC primitive.
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
