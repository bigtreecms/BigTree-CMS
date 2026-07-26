<?php
	// Site root: prefer the constant set by the public root bootstrap (index.php).
	// Fall back to two levels above this file (core/setup/ → project root).
	$bigtree_site_root = defined("BIGTREE_SITE_ROOT")
		? BIGTREE_SITE_ROOT
		: dirname(__DIR__, 2);

	if (!@chdir($bigtree_site_root)) {
		http_response_code(500);
		die("BigTree installer could not chdir to the site root: ".htmlspecialchars($bigtree_site_root));
	}

	// Surface install failures — a silent white screen after the DB step is hard to debug.
	error_reporting(E_ALL);
	ini_set("display_errors", "1");
	ini_set("html_errors", "1");

	// Set version
	include "core/version.php";
	include "core/inc/bigtree/utils.php";

	// Setup SQL functions for MySQL extension if we have it.
	if (function_exists("mysql_connect")) {
		function sqlconnect($server, $user, $password, $port, $socket) {
			$port = $port ?: 3306;
			$server = $socket ? ":".ltrim($socket, ":") : $server.":".$port;

			return mysql_connect($server, $user, $password);
		}

		function sqlselectdb($db) {
			return mysql_select_db($db);
		}

		function sqlquery($query) {
			$result = mysql_query($query);

			if ($result === false) {
				throw new RuntimeException("SQL error: ".mysql_error()." — ".bigtree_install_sql_preview($query));
			}

			return $result;
		}

		function sqlescape($string) {
			return mysql_real_escape_string($string);
		}
	// Otherwise Use MySQLi
	} else {
		function sqlconnect($server, $user, $password, $port, $socket) {
			// PHP 8.1+ throws mysqli_sql_exception on connect/query errors by default.
			mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

			return mysqli_connect($server, $user, $password, "", $port ?: 3306, $socket);
		}

		function sqlselectdb($db) {
			global $sql_connection;

			return $sql_connection->select_db($db);
		}

		function sqlquery($query) {
			global $sql_connection;

			try {
				return $sql_connection->query($query);
			} catch (mysqli_sql_exception $e) {
				throw new RuntimeException(
					"SQL error: ".$e->getMessage()." — ".bigtree_install_sql_preview($query),
					(int) $e->getCode(),
					$e
				);
			}
		}

		function sqlescape($string) {
			global $sql_connection;

			return $sql_connection->real_escape_string($string);
		}
	}

	/**
	 * Shorten a SQL statement for error messages.
	 */
	function bigtree_install_sql_preview($query) {
		$flat = preg_replace('/\s+/', " ", trim((string) $query));

		if (strlen($flat) > 180) {
			$flat = substr($flat, 0, 180)."…";
		}

		return $flat;
	}

	/**
	 * Run a SQL dump that may contain multi-line statements.
	 *
	 * base.sql used to be one statement per line; newer tables (refresh tokens,
	 * migrations, etc.) are pretty-printed across lines. Splitting on "\n" and
	 * querying each line caused: CREATE TABLE `foo` (  → syntax error near ''.
	 */
	function bigtree_install_run_sql($sql) {
		// Normalize line endings.
		$sql = str_replace(["\r\n", "\r"], "\n", (string) $sql);
		$statements = [];
		$buffer = "";

		foreach (explode("\n", $sql) as $line) {
			$trimmed = trim($line);

			// Skip empty / full-line comments while not inside a statement.
			if ($buffer === "" && ($trimmed === "" || str_starts_with($trimmed, "--"))) {
				continue;
			}

			$buffer .= ($buffer === "" ? "" : "\n").$line;

			// Statement ends at a line whose trimmed form ends with ';'
			// (all installer SQL dumps use this convention; no procedure bodies).
			if (str_ends_with($trimmed, ";")) {
				$statement = trim($buffer);
				$buffer = "";

				// Drop a trailing semicolon — mysqli accepts either form.
				if (str_ends_with($statement, ";")) {
					$statement = substr($statement, 0, -1);
				}

				$statement = trim($statement);

				if ($statement !== "") {
					$statements[] = $statement;
				}
			}
		}

		$trailing = trim($buffer);

		if ($trailing !== "") {
			$statements[] = $trailing;
		}

		foreach ($statements as $statement) {
			sqlquery($statement);
		}

		return count($statements);
	}

	/**
	 * Load and run a .sql file from disk.
	 */
	function bigtree_install_run_sql_file($path) {
		$sql = file_get_contents($path);

		if ($sql === false) {
			throw new RuntimeException("Could not read SQL file: ".$path);
		}

		return bigtree_install_run_sql($sql);
	}

	/**
	 * Whether the connected DB can store VECTOR columns (MySQL 9+ / MariaDB 11.7+).
	 * Mirrors BigTreeAI::vectorStoreSupported() without loading the full CMS.
	 */
	function bigtree_install_vector_store_supported() {
		$result = sqlquery("SELECT VERSION()");

		if (!$result) {
			return false;
		}

		$row = function_exists("mysql_fetch_row") && !($result instanceof mysqli_result)
			? mysql_fetch_row($result)
			: $result->fetch_row();
		$version = is_array($row) ? (string)($row[0] ?? "") : "";

		if ($version === "") {
			return false;
		}

		$is_maria = stripos($version, "mariadb") !== false;

		if (!preg_match('/^(\d+)\.(\d+)/', $version, $m)) {
			return false;
		}

		$major = (int)$m[1];
		$minor = (int)$m[2];

		if ($is_maria) {
			return $major > 11 || ($major === 11 && $minor >= 7);
		}

		return $major >= 9;
	}

	/**
	 * Create bigtree_ai_embeddings + status setting when VECTOR is available.
	 * Safe no-op on older MySQL/MariaDB so base.sql stays portable.
	 */
	function bigtree_install_maybe_create_ai_embeddings() {
		// Standalone copy of the embeddings DDL for fresh base installs. The
		// canonical version lives in BigTree\Services\EmbeddingService::ensureTable()
		// (which revision 506 + Configure → AI call) — keep the two in sync.
		$dimensions = 1536;
		$supported = bigtree_install_vector_store_supported();
		$table_ready = false;

		if ($supported) {
			// IF NOT EXISTS for idempotent re-runs / partial installs.
			sqlquery("
				CREATE TABLE IF NOT EXISTS `bigtree_ai_embeddings` (
					`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
					`source_type` VARCHAR(32) NOT NULL,
					`source_id` VARCHAR(191) NOT NULL,
					`module_id` VARCHAR(191) NULL,
					`module_route` VARCHAR(191) NULL,
					`table_name` VARCHAR(191) NULL,
					`chunk_index` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
					`title` VARCHAR(500) NOT NULL DEFAULT '',
					`content_text` MEDIUMTEXT NOT NULL,
					`content_hash` CHAR(64) NOT NULL DEFAULT '',
					`embedding` VECTOR($dimensions) NOT NULL,
					`model` VARCHAR(100) NOT NULL DEFAULT '',
					`updated_at` DATETIME NOT NULL,
					PRIMARY KEY (`id`),
					UNIQUE KEY `source_chunk_model` (`source_type`, `source_id`, `chunk_index`, `model`),
					KEY `source` (`source_type`, `source_id`),
					KEY `module` (`module_id`),
					KEY `updated` (`updated_at`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
			");
			$table_ready = true;

			// Best-effort MariaDB vector index (syntax varies; ignore failures).
			try {
				$version_result = sqlquery("SELECT VERSION()");
				$version_row = function_exists("mysql_fetch_row") && !($version_result instanceof mysqli_result)
					? mysql_fetch_row($version_result)
					: $version_result->fetch_row();
				$version = is_array($version_row) ? (string)($version_row[0] ?? "") : "";

				if (stripos($version, "mariadb") !== false) {
					$idx = sqlquery("SHOW INDEX FROM `bigtree_ai_embeddings` WHERE Key_name = 'embedding_vec'");
					$has = false;

					if ($idx) {
						$has = function_exists("mysql_num_rows") && !($idx instanceof mysqli_result)
							? mysql_num_rows($idx) > 0
							: $idx->num_rows > 0;
					}

					if (!$has) {
						sqlquery(
							"ALTER TABLE `bigtree_ai_embeddings`
							 ADD VECTOR INDEX `embedding_vec` (`embedding`) M=16 DISTANCE=cosine"
						);
					}
				}
			} catch (Throwable $e) {
				// ORDER BY distance still works without an ANN index.
			}
		}

		$status = json_encode([
			"supported" => $supported,
			"table_ready" => $table_ready,
			"dimensions" => $dimensions,
			"last_backfill_at" => null,
			"last_error" => null,
		]);
		$status_sql = sqlescape($status);
		// Replace if a partial install already wrote the row.
		sqlquery(
			"INSERT INTO `bigtree_settings` (`id`, `value`, `encrypted`)
			 VALUES ('bigtree-internal-ai-embeddings-status', '$status_sql', '')
			 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
		);

		return $table_ready;
	}

	// Turn off errors
	
	// Allow for passing in $_POST via command line for automatic installs.
	if (isset($argv) && count($argv) > 1) {
		// Cut off the first argument.
		$vars = array_slice($argv, 1);
		// Loop through the variables passed in.
		foreach ($vars as $v) {
			list($key,$val) = explode("=",$v);
			$_POST[$key] = $val;
		}
	}

	// Refuse to run if BigTree is already installed. The public root index.php is
	// a one-shot install bootstrap that is replaced or deleted on success (see the
	// routing branch + cleanup near the end of this script); this guard is
	// defense-in-depth for when that replacement fails and the installer is left
	// web-reachable — re-running it would recreate tables and insert a fresh
	// level-2 admin user, enabling takeover + data loss. To intentionally
	// reinstall, delete custom/environment.php first.
	if (file_exists($bigtree_site_root."/custom/environment.php")) {
		// Already installed — send the user somewhere useful instead of a bare 403 body.
		$site_index = $bigtree_site_root.DIRECTORY_SEPARATOR."site".DIRECTORY_SEPARATOR."index.php";
		$admin_guess = "site/index.php/admin/";

		if (is_file($bigtree_site_root.DIRECTORY_SEPARATOR.".htaccess")) {
			$admin_guess = "admin/";
		}

		header("HTTP/1.1 403 Forbidden");
		header("Content-Type: text/html; charset=utf-8");
		echo "<!doctype html><html lang=\"en\"><head><meta charset=\"utf-8\"><title>Already installed</title></head><body style=\"font:14px/1.5 system-ui,sans-serif;max-width:36rem;margin:3rem auto;padding:0 1rem\">";
		echo "<h1>BigTree is already installed</h1>";
		echo "<p>To reinstall, remove <code>custom/environment.php</code> and run this script again.</p>";

		if (is_file($site_index)) {
			echo "<p><a href=\"".htmlspecialchars($admin_guess)."\">Go to the admin</a></p>";
		}

		echo "</body></html>";
		exit;
	}

	// Issues that are game enders first.
	$fails = [];
	if (version_compare(PHP_VERSION,"5.3.0","<")) {
		$fails[] = "PHP 5.3 or higher is required.";
	}

	if (!extension_loaded('json')) {
		$fails[] = "PHP does not have the JSON extension installed.";
	}

	if (!extension_loaded("mysql") && !extension_loaded("mysqli")) {
		$fails[] = "PHP does not have the MySQL extension installed.";
	}

	if (!extension_loaded('gd')) {
		$fails[] = "PHP does not have the GD extension installed.";
	}

	if (!extension_loaded('curl')) {
		$fails[] = "PHP does not have the cURL extension installed.";
	}

	if (!extension_loaded('ctype')) {
		$fails[] = "PHP does not have the ctype extension installed.";
	}

	if (!extension_loaded("openssl")) {
		$fails[] = "PHP does not have the OpenSSL extension installed.";
	}

	if (!ini_get('file_uploads')) {
		$fails[] = "PHP does not have file uploads enabled.";
	}

	if (!is_writable(".")) {
		$fails[] = "Please make the current directory writable.";
	}

	// Issues that could cause problems next.
	$warnings = [];
	
	if (function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc()) {
		$fails[] = "magic_quotes_gpc is on. This is a deprecated setting that will break BigTree. Please disable it in php.ini.";
	}

	if (intval(ini_get('upload_max_filesize')) < 4) {
		$warnings[] = "Max upload filesize (upload_max_filesize in php.ini) is currently less than 4MB. 8MB or higher is recommended.";
	}

	if (intval(ini_get('post_max_size')) < 4) {
		$warnings[] = "Max POST size (post_max_size in php.ini) is currently less than 4MB. 8MB or higher is recommended.";
	}

	if (intval(ini_get("memory_limit")) < 32) {
		$warnings[] = "PHP's memory limit is currently under 32MB. BigTree recommends at least 32MB of memory be available to PHP.";
	}

	// Determine if we're on Apache or IIS
	if (strpos($_SERVER["SERVER_SOFTWARE"] ?? "", "IIS") !== false) {
		$iis = $iis_rewrite = true;
		$warnings[] = "You are running Microsoft IIS. BigTree is only tested on Apache; proceed with caution in production environments.";
		// See if we have the equivalent of rewrite installed.
		if (!isset($_SERVER["IIS_UrlRewriteModule"])) {
			$warnings[] = "You do not seem to have the IIS rewrite module installed; only basic routing is available.";
			$iis_rewrite = false;
		}
	} else {
		$iis = false;
	}

	// mod_rewrite check
	$rewrite_enabled = true;
	if (function_exists("apache_get_modules")) {		
		$apache_modules = apache_get_modules();
		if (in_array('mod_rewrite', $apache_modules) === false) {
			$warnings[] = "Apache's mod_rewrite is not installed. Only basic routing is available without mod_rewrite.";
			$rewrite_enabled = false;
		}
	}

	// Clean all post variables up, prevent SESSION hijacking.
	foreach ($_POST as $key => $val) {
		if (substr($key,0,1) != "_") {
			$$key = $val;
		}
	}

	// Defaults for optional / routing fields so PHP 8+ never treats them as undefined.
	$host = $host ?? "localhost";
	$password = $password ?? "";
	$port = $port ?? "";
	$socket = $socket ?? "";
	$routing = $routing ?? "basic";
	$slash_behavior = $slash_behavior ?? "remove";
	$session_handler = $session_handler ?? "db";
	$db = $db ?? "";
	$user = $user ?? "";
	$cms_user = $cms_user ?? "";
	$cms_pass = $cms_pass ?? "";
	$write_host = $write_host ?? "";
	$write_user = $write_user ?? "";
	$write_password = $write_password ?? "";
	$write_db = $write_db ?? "";
	$write_port = $write_port ?? "";
	$write_socket = $write_socket ?? "";

	$error = null;
	$success = false;
	$installed = false;
	// When true, replace/delete the public root index.php *after* the success HTML is sent.
	$finalize_entry_point = false;

	if (count($_POST) && !($db && $host && $user && $cms_user && $cms_pass)) {
		$error = "Errors found! Please fix the highlighted fields and submit the form again.";
	} elseif (count($_POST)) {
		if ($write_host && $write_user && $write_password) {
			$sql_connection = @sqlconnect($write_host,$write_user,$write_password,$write_port,$write_socket);
		} else {
			$sql_connection = @sqlconnect($host,$user,$password,$port,$socket);
		}

		if (!$sql_connection) {
			$error = "Could not connect to MySQL server.";
		} else {
			// Try to create the database
			sqlquery("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
			// The database's own default is what every later CREATE TABLE that names
			// no charset inherits — an extension installer, a developer's SQL, the
			// output of SQL::compareTables — and the server default is latin1 on
			// MySQL 5.7. This covers a database that already existed.
			sqlquery("ALTER DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
			// Try to select it
			$select = sqlselectdb($db);
			if (!$select) {
				$error = "Error accessing/creating database &ldquo;$db&rdquo;.";
			}
		}

		if (!filter_var($cms_user, FILTER_VALIDATE_EMAIL)) {
			$error = "An invalid email address was entered for the CMS user.";
		}
	}
	
	if (empty($error) && count($_POST)) {
	try {

		// Let domain/www_root/static_root be set by post for command line installs
		if (!isset($domain)) {
			$scheme = BigTree::getIsSSL() ? "https" : "http";
			$domain = $scheme."://".($_SERVER["HTTP_HOST"] ?? "localhost");
			// Public entry is root index.php (or DirectoryIndex "/"); strip it for base.
			// Also strip a trailing query string so www_root is a clean path.
			$request_path = strtok($_SERVER["REQUEST_URI"] ?? "/", "?");
			$install_base = str_replace("index.php", "", $request_path);

			if ($routing == "basic") {
				$static_root = $domain.$install_base."site/";
				$www_root = $static_root."index.php/";
			} elseif ($routing == "iis") {
				$www_root = $static_root = $domain.$install_base."site/";
			} else {
				$www_root = $static_root = $domain.$install_base;
			}
		}
		
		// Cryptographically random secrets for settings encryption + SPA JWT auth.
		$settings_key = uniqid("", true);
		$jwt_secret = bin2hex(random_bytes(32));

		$find = [
			"[host]",
			"[db]",
			"[user]",
			"[password]",
			"[port]",
			"[socket]",
			"[write_host]",
			"[write_db]",
			"[write_user]",
			"[write_password]",
			"[write_port]",
			"[write_socket]",
			"[domain]",
			"[wwwroot]",
			"[staticroot]",
			"[email]",
			"[settings_key]",
			"[jwt_secret]",
			"[force_secure_login]",
			"[routing]",
			"[slash_behavior]",
			"[session_handler]",
		];
		
		$replace = [
			$host,
			$db,
			$user,
			$password,
			$port,
			$socket,
			(isset($loadbalanced)) ? $write_host : "",
			(isset($loadbalanced)) ? $write_db : "",
			(isset($loadbalanced)) ? $write_user : "",
			(isset($loadbalanced)) ? $write_password : "",
			(isset($loadbalanced)) ? $write_port : "",
			(isset($loadbalanced)) ? $write_socket : "",
			$domain,
			$www_root,
			$static_root,
			$cms_user,
			$settings_key,
			$jwt_secret,
			(isset($force_secure_login)) ? "true" : "false",
			($routing == "basic") ? "basic" : "htaccess",
			$slash_behavior,
			$session_handler,
		];
		
		// Make sure we're not running in a special mode that forces values for textareas that aren't allowing null.
		// base.sql also sets NO_AUTO_VALUE_ON_ZERO for the id=0 homepage seed.
		sqlquery("SET SESSION sql_mode = ''");
		bigtree_install_run_sql_file("core/setup/base.sql");

		// Optional VECTOR table — not in base.sql so installs on MySQL 5.7/8.x keep working.
		// On MySQL 9+ / MariaDB 11.7+ this creates bigtree_ai_embeddings + status setting
		// (same shape as upgrade revision 506).
		bigtree_install_maybe_create_ai_embeddings();

		// Allow for a theme SQL dump next to the public entry point.
		if (file_exists("bigtree-theme.sql")) {
			bigtree_install_run_sql_file("bigtree-theme.sql");
		}
		
		$enc_pass = sqlescape(password_hash(trim($cms_pass), PASSWORD_DEFAULT));
		sqlquery("INSERT INTO bigtree_users (`email`,`password`,`new_hash`,`name`,`level`) VALUES ('$cms_user','$enc_pass','on','Developer','2')");
		
		// Determine whether Apache is running as the owner of the BigTree files -- only works if we have posix_getuid
		// We do this to determine whether we need to make the files the script writes 777
		if (function_exists("posix_getuid")) {
			if (posix_getuid() == getmyuid()) {
				define("BT_SU_EXEC",true);
			} else {
				define("BT_SU_EXEC",false);
			}
		} else {
			define("BT_SU_EXEC",false);
		}

		function bt_mkdir_writable($dir) {
			global $root;

			$path = $root.$dir;

			// Site root / empty path is already the install cwd — nothing to create.
			if ($path === "" || $path === "." || $path === "./") {
				return;
			}

			// Idempotent — a partial prior install (or pre-created dirs) must not
			// fatal with "mkdir(): File exists" under strict error handlers.
			if (!is_dir($path)) {
				if (!@mkdir($path, 0777, true) && !is_dir($path)) {
					throw new RuntimeException("Could not create directory: ".$path);
				}
			}

			if (!BT_SU_EXEC && function_exists("chmod")) {
				@chmod($path, 0777);
			}
		}

		function bt_touch_writable($file, $contents = "") {
			if (!file_exists($file)) {
				if (@file_put_contents($file, $contents) === false) {
					throw new RuntimeException("Could not write file: ".$file);
				}
			}

			if (!BT_SU_EXEC && function_exists("chmod")) {
				@chmod($file, 0777);
			}
		}

		function bt_copy_dir($from, $to) {
			global $root;

			$d = @opendir($root.$from);

			if ($d === false) {
				throw new RuntimeException("Could not read directory: ".$root.$from);
			}

			// Empty $to means the site root (cwd). Do not mkdir("") — that fails and
			// is what example-site install uses: bt_copy_dir("core/example-site/", "").
			$dest = $root.$to;
			$dest_is_root = ($to === "" || $to === "." || $to === "./" || $dest === "" || $dest === ".");

			if (!$dest_is_root && !is_dir($dest)) {
				if (!@mkdir($dest, 0777, true) && !is_dir($dest)) {
					closedir($d);
					throw new RuntimeException("Could not create directory: ".$dest);
				}

				if (!BT_SU_EXEC && function_exists("chmod")) {
					@chmod($dest, 0777);
				}
			}

			while (($f = readdir($d)) !== false) {
				if ($f != "." && $f != "..") {
					if (is_dir($root.$from.$f)) {
						bt_copy_dir($from.$f."/", $to.$f."/");
					} else {
						$target = $to.$f;

						if (!file_exists($target)) {
							if (!@copy($from.$f, $target)) {
								closedir($d);
								throw new RuntimeException("Could not copy file: ".$from.$f." → ".$target);
							}
						}

						if (!BT_SU_EXEC && function_exists("chmod")) {
							@chmod($target, 0777);
						}
					}
				}
			}

			closedir($d);
		}
		
		$root = "";
		
		bt_mkdir_writable("cache/");
		bt_mkdir_writable("custom/");
		bt_mkdir_writable("custom/admin/");
		bt_mkdir_writable("custom/admin/ajax/");
		bt_mkdir_writable("custom/admin/css/");
		bt_mkdir_writable("custom/admin/images/");
		bt_mkdir_writable("custom/admin/modules/");
		bt_mkdir_writable("custom/admin/pages/");
		bt_mkdir_writable("custom/admin/field-types/");
		bt_mkdir_writable("custom/inc/");
		bt_mkdir_writable("custom/inc/modules/");
		bt_mkdir_writable("custom/inc/required/");
		bt_mkdir_writable("extensions/");
		bt_mkdir_writable("site");
		bt_mkdir_writable("site/css/");
		bt_mkdir_writable("site/extensions/");
		bt_mkdir_writable("site/files/");
		bt_mkdir_writable("site/files/pages/");
		bt_mkdir_writable("site/files/resources/");
		bt_mkdir_writable("site/images/");
		bt_mkdir_writable("site/js/");
		bt_mkdir_writable("templates");
		bt_mkdir_writable("templates/ajax/");
		bt_mkdir_writable("templates/layouts/");
		bt_mkdir_writable("templates/routed/");
		bt_mkdir_writable("templates/basic/");
		bt_mkdir_writable("templates/callouts/");

		// Force-write critical config (bt_touch_writable skips existing files).
		$environment_template = file_get_contents("core/setup/environment.php");

		if ($environment_template === false) {
			throw new RuntimeException("Could not read core/setup/environment.php");
		}

		if (@file_put_contents("custom/environment.php", str_replace($find, $replace, $environment_template)) === false) {
			throw new RuntimeException("Could not write custom/environment.php — check directory permissions.");
		}

		if (!BT_SU_EXEC && function_exists("chmod")) {
			@chmod("custom/environment.php", 0777);
		}

		bt_touch_writable("cache/composer-check.flag", "true");

		// Install the example site if they asked for it.
		if (!empty($install_example_site)) {
			bt_copy_dir("core/example-site/", "");
			bigtree_install_run_sql_file("core/setup/example-site.sql");
			$settings_template = file_get_contents("core/example-site/custom/settings.php");
		} else {
			$settings_template = file_get_contents("core/setup/settings.php");
			bt_mkdir_writable("custom/json-db/");
			bt_copy_dir("core/setup/json-db/", "custom/json-db/");
		}

		if ($settings_template === false) {
			throw new RuntimeException("Could not read settings template");
		}

		if (@file_put_contents("custom/settings.php", str_replace($find, $replace, $settings_template)) === false) {
			throw new RuntimeException("Could not write custom/settings.php — check directory permissions.");
		}

		if (!BT_SU_EXEC && function_exists("chmod")) {
			@chmod("custom/settings.php", 0777);
		}

		// Now copy over the default templates
		bt_touch_writable("templates/layouts/_header.php");
		bt_touch_writable("templates/layouts/default.php",'<?php
	include "_header.php";
	echo $bigtree["content"];
	include "_footer.php";
?>');
		bt_touch_writable("templates/layouts/_footer.php");
		bt_touch_writable("templates/basic/_404.php","<h1>404 - Page Not Found</h1>");
		bt_touch_writable("templates/basic/_maintenance.php","<h1>Under Construction</h1><p>Maintenance mode has been enabled.</p>");
		bt_touch_writable("templates/basic/_sitemap.php","<h1>Sitemap</h1>");
		bt_touch_writable("templates/basic/home.php");
		bt_touch_writable("templates/basic/content.php",'<h1><?=$page_header?></h1>
<?=$page_content?>');

		// Create a cron runner script for symlinked cores
		bt_touch_writable("cron-run.php", '<?php
	$server_root = str_replace("cron-run.php", "", strtr(__FILE__, "\\\\", "/"));	
	include $server_root."core/cron.php";
');

		// Create site/index.php (force-write — must not leave a stale partial).
		$site_index = '<?php
	$server_root = str_replace("site/index.php","",strtr(__FILE__, "\\\\", "/"));
	include "../core/launch.php";
';

		if (@file_put_contents("site/index.php", $site_index) === false) {
			throw new RuntimeException("Could not write site/index.php");
		}

		if (!BT_SU_EXEC && function_exists("chmod")) {
			@chmod("site/index.php", 0777);
		}
		
		if ($routing == "advanced") {
			bt_touch_writable("site/.htaccess",'<IfModule mod_deflate.c>
	<IfModule mod_setenvif.c>
		<IfModule mod_headers.c>
			SetEnvIfNoCase ^(Accept-EncodXng|X-cept-Encoding|X{15}|~{15}|-{15})$ ^((gzip|deflate)\s*,?\s*)+|[X~-]{4,13}$ HAVE_Accept-Encoding
			RequestHeader append Accept-Encoding "gzip,deflate" env=HAVE_Accept-Encoding
		</IfModule>
	</IfModule>
	<IfModule mod_mime.c>
		AddEncoding gzip svgz
	</IfModule>
	<IfModule mod_filter.c>
		AddOutputFilterByType DEFLATE "application/atom+xml" \
			"application/javascript" \
			"application/json" \
			"application/ld+json" \
			"application/manifest+json" \
			"application/rss+xml" \
			"application/vnd.geo+json" \
			"application/vnd.ms-fontobject" \
			"application/x-font-ttf" \
			"application/x-web-app-manifest+json" \
			"application/xhtml+xml" \
			"application/xml" \
			"font/opentype" \
			"image/svg+xml" \
			"image/x-icon" \
			"text/cache-manifest" \
			"text/css" \
			"text/html" \
			"text/plain" \
			"text/vtt" \
			"text/x-component" \
			"text/xml" \
			"text/javascript"
	</IfModule>
</IfModule>

<IfModule mod_expires.c>

    ExpiresActive on
    ExpiresDefault                                      "access plus 1 month"

  # CSS
    ExpiresByType text/css                              "access plus 1 year"

  # Data interchange
    ExpiresByType application/atom+xml                  "access plus 1 hour"
    ExpiresByType application/rdf+xml                   "access plus 1 hour"
    ExpiresByType application/rss+xml                   "access plus 1 hour"

    ExpiresByType application/json                      "access plus 0 seconds"
    ExpiresByType application/ld+json                   "access plus 0 seconds"
    ExpiresByType application/schema+json               "access plus 0 seconds"
    ExpiresByType application/vnd.geo+json              "access plus 0 seconds"
    ExpiresByType application/xml                       "access plus 0 seconds"
    ExpiresByType text/xml                              "access plus 0 seconds"

  # Favicon (cannot be renamed!) and cursor images
    ExpiresByType image/vnd.microsoft.icon              "access plus 1 week"
    ExpiresByType image/x-icon                          "access plus 1 week"

  # HTML
    ExpiresByType text/html                             "access plus 0 seconds"

  # JavaScript
    ExpiresByType application/javascript                "access plus 1 year"
    ExpiresByType application/x-javascript              "access plus 1 year"
    ExpiresByType text/javascript                       "access plus 1 year"

  # Manifest files
    ExpiresByType application/manifest+json             "access plus 1 year"

    ExpiresByType application/x-web-app-manifest+json   "access plus 0 seconds"
    ExpiresByType text/cache-manifest                   "access plus 0 seconds"

  # Media files
    ExpiresByType audio/ogg                             "access plus 1 month"
    ExpiresByType image/bmp                             "access plus 1 month"
    ExpiresByType image/gif                             "access plus 1 month"
    ExpiresByType image/jpeg                            "access plus 1 month"
    ExpiresByType image/png                             "access plus 1 month"
    ExpiresByType image/svg+xml                         "access plus 1 month"
    ExpiresByType image/webp                            "access plus 1 month"
    ExpiresByType video/mp4                             "access plus 1 month"
    ExpiresByType video/ogg                             "access plus 1 month"
    ExpiresByType video/webm                            "access plus 1 month"

  # Web fonts

    # Embedded OpenType (EOT)
    ExpiresByType application/vnd.ms-fontobject         "access plus 1 month"
    ExpiresByType font/eot                              "access plus 1 month"

    # OpenType
    ExpiresByType font/opentype                         "access plus 1 month"

    # TrueType
    ExpiresByType application/x-font-ttf                "access plus 1 month"

    # Web Open Font Format (WOFF) 1.0
    ExpiresByType application/font-woff                 "access plus 1 month"
    ExpiresByType application/x-font-woff               "access plus 1 month"
    ExpiresByType font/woff                             "access plus 1 month"

    # Web Open Font Format (WOFF) 2.0
    ExpiresByType application/font-woff2                "access plus 1 month"

  # Other
    ExpiresByType text/x-cross-domain-policy            "access plus 1 week"

</IfModule>

<IfModule mod_headers.c>
	<FilesMatch "\.(ttf|otf|eot|woff)$">
		Header set Access-Control-Allow-Origin "*"
	</FilesMatch>

	Header set X-UA-Compatible "IE=edge"
	<FilesMatch "\.(appcache|atom|crx|css|cur|eot|f4[abpv]|flv|geojson|gif|htc|ico|jpe?g|js|json(ld)?|m4[av]|manifest|map|mp4|oex|og[agv]|opus|otf|pdf|png|rdf|rss|safariextz|svgz?|swf|topojson|tt[cf]|txt|vcf|vtt|webapp|web[mp]|woff2?|xml|xpi)$">
		Header unset X-UA-Compatible
	</FilesMatch>
	
	Header set X-Content-Type-Options "nosniff"
	Header set X-XSS-Protection "1; mode=block"
	Header set X-Permitted-Cross-Domain-Policies "master-only"
</IfModule>

AddType image/svg+xml svg
AddType video/ogg .ogv
AddType video/mp4 .mp4
AddType video/webm .webm

IndexIgnore */*

Options -MultiViews

RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)$ index.php?bigtree_htaccess_url=$1 [QSA,L]

RewriteRule .* - [E=HTTP_IF_MODIFIED_SINCE:%{HTTP:If-Modified-Since}]
RewriteRule .* - [E=HTTP_BIGTREE_PARTIAL:%{HTTP:BigTree-Partial}]');
			
		} elseif ($routing == "simple") {
			bt_touch_writable("site/.htaccess",'IndexIgnore */*

Options -MultiViews

RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)$ index.php?bigtree_htaccess_url=$1 [QSA,L]

RewriteRule .* - [E=HTTP_IF_MODIFIED_SINCE:%{HTTP:If-Modified-Since}]
RewriteRule .* - [E=HTTP_BIGTREE_PARTIAL:%{HTTP:BigTree-Partial}]');
		}

		if ($routing != "basic" && $routing != "iis") {
			// Overwrite any existing root .htaccess so rewrite routing is applied.
			if (@file_put_contents(".htaccess", 'RewriteEngine On
RewriteRule ^$ site/ [L]
RewriteRule (.*) site/$1 [L]') === false) {
				throw new RuntimeException("Could not write root .htaccess for rewrite routing.");
			}

			if (!BT_SU_EXEC && function_exists("chmod")) {
				@chmod(".htaccess", 0777);
			}
		}

		// Harden serving of user-uploaded resources: never let a stored SVG render
		// inline in the site origin (a <script>/on*-handler in an uploaded SVG is a
		// stored-XSS vector when another admin opens the file URL). Scoped to the
		// resources tree so legitimate inline SVGs elsewhere on the site are
		// unaffected. Pairs with ResourceService::sanitizeSvg (store-time strip).
		bt_touch_writable("site/files/resources/.htaccess",'<IfModule mod_headers.c>
	<FilesMatch "\.svg$">
		Header set Content-Disposition "attachment"
		Header set Content-Security-Policy "default-src \'none\'; style-src \'unsafe-inline\'; sandbox"
		Header set X-Content-Type-Options "nosniff"
	</FilesMatch>
</IfModule>');

		// Optional seed files / docs — ignore failures.
		@unlink($bigtree_site_root."/bigtree-theme.sql");
		@unlink($bigtree_site_root."/README.md");

		// Entry-point swap is deferred until after the success page is fully sent so
		// overwriting/deleting root index.php never interrupts this response.
		$installed = true;
		$finalize_entry_point = true;

	} catch (Throwable $e) {
		// If environment.php already landed, treat as installed so the user still
		// gets a success screen + entry-point finalization rather than a white page.
		if (file_exists($bigtree_site_root."/custom/environment.php")) {
			$installed = true;
			$finalize_entry_point = true;
			$warnings[] = "Installation finished with a warning: ".$e->getMessage();
		} else {
			$error = "Installation failed: ".$e->getMessage();
			$installed = false;
			$finalize_entry_point = false;
		}
	}
	}
	
	// Set localhost as the default MySQL host
	$host = !empty($host) ? $host : "localhost";
?>
<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>Install BigTree <?=BIGTREE_VERSION?></title>
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">
		<style type="text/css">
			<?php
				// Plain CSS (SPA design tokens). Lives next to this installer under
				// core/setup/ so styles survive removing the legacy core/admin tree.
				echo file_get_contents(__DIR__ . "/install.css");
			?>
		</style>
	</head>
	<body class="install">
		<div class="install_wrapper">
			<?php if ($installed) { ?>
			<header class="install_brand">
				<div class="install_brand_mark" aria-hidden="true">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
						<path d="M12 2 4 12h4v8h8v-8h4L12 2Z" />
					</svg>
				</div>
				<div>
					<h1>BigTree <?=BIGTREE_VERSION?> installed</h1>
					<p>Your site is ready to go.</p>
				</div>
			</header>
			<form method="post" action="" class="module">
				<h2>Installation complete</h2>
				<fieldset class="clear">
					<p>Your new BigTree site is ready. Sign in to the CMS with the email and password you just created.</p>
					<?php if (!empty($warnings)) { ?>
						<?php foreach ($warnings as $warning) { ?>
					<p class="warning_message"><?=$warning?></p>
						<?php } ?>
					<?php } ?>
					<?php if ($routing == "iis") { ?>
					<p class="error_message iis_message">To set up rewrite routing for IIS, import the following .htaccess rules into the /site/ directory:</p>
					<code>
						RewriteCond %{REQUEST_FILENAME} !-d<br />
						RewriteCond %{REQUEST_FILENAME} !-f<br />
						RewriteRule ^(.*)$ index.php?bigtree_htaccess_url=$1 [QSA,L]
					</code>
					<p class="delete_message">To remove the /site/ path from your BigTree install you will need to setup a separate IIS Site for your BigTree install and set its document root to the /site/ folder (as well as moving the rewrite rules to apply to the main Site instead of the /site/ directory). After doing so, edit your /custom/environment.php file to adjust your domain, www_root, static_root, and admin_root variables.</p>
					<?php } ?>
				</fieldset>

				<hr />

				<h2>Public site</h2>
				<fieldset class="clear">
					<p><small>URL</small><a href="<?=$www_root?>"><?=$www_root?></a></p>
				</fieldset>

				<hr />

				<h2>Administration area</h2>
				<fieldset class="clear">
					<p>
						<small>URL</small><a href="<?=$www_root?>admin/"><?=$www_root?>admin/</a><br />
						<small>Email</small><?=$cms_user?><br />
						<small>Password</small><?php for ($i = 0, $count = strlen($cms_pass); $i < $count; $i++) { echo "*"; } ?><br />
					</p>
				</fieldset>

				<br class="clear" /><br />
			</form>
			<?php } else { ?>
			<header class="install_brand">
				<div class="install_brand_mark" aria-hidden="true">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
						<path d="M12 2 4 12h4v8h8v-8h4L12 2Z" />
					</svg>
				</div>
				<div>
					<h1>Install BigTree <?=BIGTREE_VERSION?></h1>
					<p>Set up your database, admin account, and routing.</p>
				</div>
			</header>
			<form method="post" action="" class="module">
				<h2>Getting started</h2>
				<fieldset class="clear">
					<p>Welcome to the BigTree installer. If you need help with installation, <a href="https://www.bigtreecms.org/docs/dev-guide/installation/" target="_blank" rel="noopener noreferrer">check out the developer docs</a>.</p>
				</fieldset>
				<?php
					if (count($warnings)) {
						echo '<br class="clear" />';
						foreach ($warnings as $warning) {
				?>
				<p class="warning_message"><?=$warning?></p>
				<?php
						}
					}
					if (count($fails)) {
						echo '<br class="clear" />';
						foreach ($fails as $fail) {
				?>
				<p class="error_message"><?=$fail?></p>
				<?php
						}
						echo '<br class="clear" /><fieldset class="clear"><p><strong>Resolve the errors above before installing BigTree.</strong></p></fieldset><br class="clear" />';
					} else {
						if ($error) {
							echo '<br class="clear" />';
				?>
				<p class="error_message"><?=$error?></p>
				<?php
						}
				?>
				<hr />

				<h2>Session &amp; database</h2>
				<fieldset class="clear">
					<p>Enter your MySQL database information below.</p>
				</fieldset>
				<br class="clear" />
				<fieldset class="left<?php if (count($_POST) && !$host) { ?> form_error<?php } ?>">
					<label for="db_host">Hostname</label>
					<input class="text" type="text" id="db_host" required name="host" value="<?=htmlspecialchars($host ?? "")?>" tabindex="1" />
				</fieldset>
				<fieldset class="right<?php if (count($_POST) && !$db) { ?> form_error<?php } ?>">
					<label for="db_name">Database</label>
					<input class="text" type="text" id="db_name" required name="db" value="<?=htmlspecialchars($db ?? "")?>" tabindex="2" />
				</fieldset>
				<br class="clear" />
				<fieldset class="left<?php if (count($_POST) && !$user) { ?> form_error<?php } ?>">
					<label for="db_user">Username</label>
					<input class="text" type="text" id="db_user" required name="user" value="<?=htmlspecialchars($user ?? "")?>" tabindex="3" autocomplete="off" />
				</fieldset>
				<fieldset class="right">
					<label for="db_pass">Password</label>
					<input class="text" type="password" id="db_pass" required name="password" value="<?=htmlspecialchars($password ?? "")?>" tabindex="4" autocomplete="off" />
				</fieldset>
				<div class="db_port_or_socket_settings"<?php if (empty($db_port_or_socket)) { ?> style="display: none;"<?php } ?>>
					<br class="clear" />
					<fieldset class="left">
						<label>Port <small>(defaults to 3306)</small></label>
						<input class="text" type="text" name="port" value="<?=htmlspecialchars($port ?? "")?>" tabindex="7" />
					</fieldset>
					<fieldset class="right">
						<label>Socket</label>
						<input class="text" type="text" name="socket" value="<?=htmlspecialchars($socket ?? "")?>" tabindex="8" />
					</fieldset>
				</div>
				<br class="clear" />
				<fieldset class="clear">
					<label class="for_checkbox">
						<input type="checkbox" class="checkbox" name="db_port_or_socket" id="db_port_or_socket"<?php if (!empty($db_port_or_socket)) { ?> checked="checked"<?php } ?> tabindex="5" />
						Connect via socket or alternate port
					</label>
					<label class="for_checkbox">
						<input type="checkbox" class="checkbox" name="loadbalanced" id="loadbalanced"<?php if (!empty($loadbalanced)) { ?> checked="checked"<?php } ?> tabindex="6" />
						Load balanced MySQL
					</label>
				</fieldset>

				<br class="clear" />
				<fieldset class="clear">
					<label for="session_handler">Session storage <small>(Database handler required for enhanced security features)</small></label>
					<select name="session_handler" id="session_handler" tabindex="7">
						<option value="db">BigTree Database Handler</option>
						<option value="default">Default PHP Handler</option>
					</select>
				</fieldset>

				<div id="loadbalanced_settings"<?php if (empty($loadbalanced)) { ?> style="display: none;"<?php } ?>>
					<hr />

					<h2>Write database</h2>
					<fieldset class="clear">
						<p>If you are hosting a load balanced setup with multiple MySQL servers, enter the master write server information below.</p>
					</fieldset>
					<br class="clear" />
					<fieldset class="left<?php if (count($_POST) && !empty($loadbalanced) && empty($write_host)) { ?> form_error<?php } ?>">
						<label for="db_write_host">Hostname</label>
						<input class="text" type="text" id="db_write_host" name="write_host" value="<?=htmlspecialchars($write_host ?? "")?>" tabindex="8" />
					</fieldset>
					<fieldset class="right<?php if (count($_POST) && !empty($loadbalanced) && empty($write_db)) { ?> form_error<?php } ?>">
						<label for="db_write_name">Database</label>
						<input class="text" type="text" id="db_write_name" name="write_db" value="<?=htmlspecialchars($write_db ?? "")?>" tabindex="9" />
					</fieldset>
					<br class="clear" />
					<fieldset class="left<?php if (count($_POST) && !empty($loadbalanced) && empty($write_user)) { ?> form_error<?php } ?>">
						<label for="db_write_user">Username</label>
						<input class="text" type="text" id="db_write_user" name="write_user" value="<?=htmlspecialchars($write_user ?? "")?>" tabindex="10" autocomplete="off" />
					</fieldset>
					<fieldset class="right<?php if (count($_POST) && !empty($loadbalanced) && empty($write_password)) { ?> form_error<?php } ?>">
						<label for="db_write_pass">Password</label>
						<input class="text" type="password" id="db_write_pass" name="write_password" value="<?=htmlspecialchars($write_password ?? "")?>" tabindex="11" autocomplete="off" />
					</fieldset>
					<div class="db_port_or_socket_settings"<?php if (empty($db_port_or_socket)) { ?> style="display: none;"<?php } ?>>
						<br class="clear" />
						<fieldset class="left">
							<label>Port <small>(defaults to 3306)</small></label>
							<input class="text" type="text" name="write_port" value="<?=htmlspecialchars($write_port ?? "")?>" tabindex="12" />
						</fieldset>
						<fieldset class="right">
							<label>Socket</label>
							<input class="text" type="text" name="write_socket" value="<?=htmlspecialchars($write_socket ?? "")?>" tabindex="13" />
						</fieldset>
					</div>
					<br class="clear" />
				</div>

				<hr />

				<h2>Administrator account</h2>
				<fieldset class="clear">
					<p>Enter the email and password for your site's developer account.</p>
				</fieldset>
				<br class="clear" />
				<fieldset class="left<?php if (count($_POST) && empty($cms_user)) { ?> form_error<?php } ?>">
					<label for="cms_user">Email address</label>
					<input class="text" type="email" required id="cms_user" name="cms_user" value="<?=htmlspecialchars($cms_user ?? "")?>" tabindex="14" autocomplete="off" />
				</fieldset>
				<fieldset class="right<?php if (count($_POST) && empty($cms_pass)) { ?> form_error<?php } ?>">
					<label for="cms_pass">Password</label>
					<input class="text" type="password" required id="cms_pass" name="cms_pass" value="<?=htmlspecialchars($cms_pass ?? "")?>" tabindex="15" autocomplete="off" />
				</fieldset>
				<br class="clear" />
				<fieldset class="clear">
					<label class="for_checkbox">
						<input type="checkbox" class="checkbox" name="force_secure_login" id="force_secure_login"<?php if (!empty($force_secure_login)) { ?> checked="checked"<?php } ?> tabindex="16" />
						Force HTTPS logins
					</label>
				</fieldset>

				<hr />

				<?php if (!$iis || $iis_rewrite) { ?>
				<h2>Site routing</h2>
				<fieldset class="clear">
					<?php if ($iis) { ?>
					<p>BigTree makes your URLs pretty but URL Rewrite support can make them even more pretty. By choosing "Rewrite Routing" you can remove /index.php/ from your URLs.</p>
					<?php } else { ?>
					<p>BigTree makes your URLs pretty but mod_rewrite support can make them even more pretty. If your server supports .htaccess overrides and mod_rewrite support you can remove /index.php/ from your URLs.</p>
					<?php } ?>
					<ul>
						<?php if ($iis) { ?>
						<li>Choose <strong>"Basic Routing"</strong> if you are not familiar with importing .htaccess rules into IIS.</li>
						<li>Choose <strong>"Rewrite Routing"</strong> if you want cleaner looking URLs and can import .htaccess rules.</li>
						<?php } else { ?>
						<li>Choose <strong>"Basic Routing"</strong> if you are unsure your server supports .htaccess overrides and mod_rewrite.</li>
						<li>Choose <strong>"Simple Rewrite Routing"</strong> if your server supports .htaccess and mod_rewrite but does not allow for php_flags and content compression.</li>
						<li>Choose <strong>"Advanced Routing"</strong> to install an .htaccess that enables caching, compression, and routing.</li>
						<?php } ?>
					</ul>
				</fieldset>
				<br class="clear" />
				<div class="contain">
					<fieldset class="left">
						<label for="routing">Routing</label>
						<select name="routing" id="routing" tabindex="17">
							<?php
								if ($iis) {
							?>
							<option value="basic"<?php if (empty($routing) || $routing == "basic") { ?> selected="selected"<?php } ?>>Basic Routing</option>
							<option value="iis"<?php if ($routing == "iis") { ?> selected="selected"<?php } ?>>Rewrite Routing</option>
							<?php
								} else {
							?>
							<option value="basic"<?php if (empty($routing) || $routing == "basic") { ?> selected="selected"<?php } ?>>Basic Routing</option>
							<?php
									if ($rewrite_enabled) {
							?>
							<option value="simple"<?php if (!empty($routing) && $routing == "simple") { ?> selected="selected"<?php } ?>>Simple Rewrite Routing</option>
							<option value="advanced"<?php if (!empty($routing) && $routing == "advanced") { ?> selected="selected"<?php } ?>>Advanced Routing</option>
							<?php
									}
								}
							?>
						</select>
					</fieldset>
					<fieldset class="left">
						<label for="slash_behavior">URL behavior</label>
						<select name="slash_behavior" id="slash_behavior" tabindex="18">
							<option value="append">URLs End With /</option>
							<option value="remove"<?php if (empty($slash_behavior) || $slash_behavior == "remove") { ?> selected="selected"<?php } ?>>URLs End With Page Slug</option>
							<option value="none"<?php if (!empty($slash_behavior) && $slash_behavior == "none") { ?> selected="selected"<?php } ?>>Allow Either</option>
						</select>
					</fieldset>
				</div>

				<hr />
				<?php } else { ?>
				<input type="hidden" name="routing" value="basic" />
				<?php } ?>

				<h2>Example site</h2>
				<fieldset class="clear">
					<p>Optionally install the BigTree example site. Demo templates and modules help you learn the system.</p>
					<label class="for_checkbox">
						<input type="checkbox" class="checkbox" name="install_example_site" id="install_example_site"<?php if (!empty($install_example_site)) { ?> checked="checked"<?php } ?> tabindex="19" />
						Install example site
					</label>
				</fieldset>

				<fieldset class="lower">
					<input type="submit" class="button blue" value="Install now" tabindex="20" />
				</fieldset>
				<?php
					}
				?>
			</form>
			<script>
				(function () {
					var loadBalanced = document.getElementById("loadbalanced");
					var loadBalancedSettings = document.getElementById("loadbalanced_settings");
					var portOrSocket = document.getElementById("db_port_or_socket");
					var portOrSocketSettings = document.querySelectorAll(".db_port_or_socket_settings");

					if (loadBalanced && loadBalancedSettings) {
						loadBalanced.addEventListener("change", function () {
							loadBalancedSettings.style.display = loadBalanced.checked ? "block" : "none";
						});
					}

					if (portOrSocket && portOrSocketSettings.length) {
						portOrSocket.addEventListener("change", function () {
							var display = portOrSocket.checked ? "block" : "none";
							portOrSocketSettings.forEach(function (el) {
								el.style.display = display;
							});
						});
					}
				})();
			</script>
			<?php } ?>
			<footer class="install_footer">
				<a href="https://www.bigtreecms.org" target="_blank" rel="noopener noreferrer">BigTree CMS</a>
				<a href="https://www.fastspot.com" target="_blank" rel="noopener noreferrer">&copy; <?=date("Y")?> Fastspot</a>
			</footer>
		</div>
	</body>
</html>
<?php
	// Finalize the public entry point only after the success page has been sent.
	// Mutating root index.php earlier can interrupt this response on some SAPIs.
	if (!empty($finalize_entry_point) && !empty($installed)) {
		$entry = $bigtree_site_root.DIRECTORY_SEPARATOR."index.php";

		if ($routing === "basic") {
			// Replace the installer bootstrap with a redirect into /site/.
			$redirect = "<?php\n\theader(\"Location: site/index.php/\");\n";
			@file_put_contents($entry, $redirect);

			if (defined("BT_SU_EXEC") && !BT_SU_EXEC && function_exists("chmod")) {
				@chmod($entry, 0777);
			}
		} else {
			// Rewrite / IIS: drop the installer; .htaccess or site docroot handles traffic.
			@unlink($entry);
		}

		// Flush so the browser receives the success HTML before we exit.
		if (function_exists("fastcgi_finish_request")) {
			@fastcgi_finish_request();
		} else {
			if (ob_get_level() > 0) {
				@ob_end_flush();
			}

			@flush();
		}
	}
