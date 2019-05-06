<?php
	use BigTree\Auth;
	use BigTree\FileSystem;
	use BigTree\JSON;
	use BigTree\Router;
	use BigTree\SQL;
	use BigTree\Text;
	
	/**
	 * @global array $bigtree
	 * @global array $path
     * @global string $domain
     * @global string $static_root
     * @global string $www_root
	 */
	
	ini_set("log_errors", "false");

    // These vars are defined in launch.php for multi-domain setups
	if (!defined("BIGTREE_SITE_KEY")) {
		define("BIGTREE_SITE_TRUNK", 0);
		
		// Set some config vars automatically and setup some globals.
		$domain = rtrim($bigtree["config"]["domain"], "/");
		$www_root = $bigtree["config"]["www_root"];
		$static_root = isset($bigtree["config"]["static_root"]) ? $bigtree["config"]["static_root"] : $www_root;
	}
	
	$server_root = isset($server_root) ? $server_root : str_replace("core/bootstrap.php", "", strtr(__FILE__, "\\", "/"));
	$site_root = $server_root."site/";
	$secure_root = str_replace("http://", "https://", $www_root);
	$admin_root = $bigtree["config"]["admin_root"];
	
	define("WWW_ROOT", $www_root);
	define("STATIC_ROOT", $static_root);
	define("SECURE_ROOT", $secure_root);
	define("DOMAIN", $domain);
	define("SERVER_ROOT", $server_root);
	define("SITE_ROOT", $site_root);
	define("ADMIN_ROOT", $admin_root);
	
	// Set version
	include SERVER_ROOT."core/version.php";
	
	// Setup the vendor folder if we haven't done this check before
	if (!file_exists(SERVER_ROOT."cache/composer-check.flag")) {
		// Include FileSystem and Text handlers to do composer checks
		include SERVER_ROOT."core/inc/bigtree/classes/FileSystem.php";
		include SERVER_ROOT."core/inc/bigtree/classes/JSON.php";
		include SERVER_ROOT."core/inc/bigtree/classes/Text.php";
		
		$path = str_replace("core/bootstrap.php", "", __FILE__);
		$off_path = str_replace(SERVER_ROOT, "", $path);
		
		if (!file_exists(SERVER_ROOT."vendor/")) {
			if (FileSystem::getDirectoryWritability(SERVER_ROOT."vendor/")) {
				FileSystem::createFile(SERVER_ROOT."cache/composer-check.flag", "done");
				FileSystem::copyDirectory(SERVER_ROOT.$off_path."vendor/", SERVER_ROOT."vendor/");
				FileSystem::copyFile(SERVER_ROOT.$off_path."composer.json", SERVER_ROOT."composer.json");
			} else {
				die(Text::translate("BigTree needs to copy it's vendor directory to ".SERVER_ROOT.
					"<br><br>If you are unable to provide writable permissions to PHP, copy :path1: and :path2: to :server_root: and add a file named composer-check.flag to :cache_root: to bypass this step.",
					false,
					[
						":path1:" => SERVER_ROOT.$off_path."vendor/",
						":path2:" => SERVER_ROOT.$off_path."composer.json",
						":server_root:" => SERVER_ROOT,
						":cache_root:" => SERVER_ROOT."cache/"
					]
				));
			}
		} else {
			if (file_exists(SERVER_ROOT."composer.json") && !is_writable(SERVER_ROOT."composer.json")) {
				die(Text::translate("BigTree needs to write to your composer.json file to ensure a composer update does not wipe required libraries."));
			}
			
			$bigtree_composer_json = json_decode(file_get_contents(SERVER_ROOT.$off_path."composer.json"), true);
			$existing_json = json_decode(file_get_contents(SERVER_ROOT."composer.json"), true);
			
			foreach ($bigtree_composer_json["require"] as $key => $value) {
				if (!isset($existing_json["require"][$key])) {
					$existing_json["require"][$key] = $value;
				}
			}
			
			FileSystem::createFile(SERVER_ROOT."composer.json", JSON::encode($existing_json));
			FileSystem::createFile(SERVER_ROOT."cache/composer-check.flag", "done");
			
			die(Text::translate("BigTree has updated your composer.json file with required libraries. Please run `composer update` before continuing."));
		}
	}
	
	include SERVER_ROOT."vendor/autoload.php";
	
	// Class auto loader
	spl_autoload_register(function ($class) {
		global $bigtree;
		
		$path = null;
		
		// Auto loadable via the class name
		if (substr($class, 0, 8) == "BigTree\\") {
			$path = "inc/bigtree/classes/".str_replace("\\", "/", substr($class, 8)).".php";
		// Known class in the cache file
		} else {
			$path = isset($bigtree["class_list"][$class]) ? $bigtree["class_list"][$class] : null;
		}
		
		if (!$path) {
			// Clear the module class list just in case we're missing something.
			FileSystem::deleteFile(SERVER_ROOT."cache/bigtree-module-cache.json");
			
			trigger_error("Class $class could not be auto-loaded but the cache may be stale. Please try re-loading.",
						  E_USER_ERROR);
			
			return;
		}
		
		if (substr($path, 0, 11) == "extensions/" || substr($path, 0, 7) == "custom/") {
			$path = SERVER_ROOT.$path;
		} else {
			if (file_exists(SERVER_ROOT."custom/".$path)) {
				$path = SERVER_ROOT."custom/".$path;
			} else {
				$path = SERVER_ROOT."core/".$path;
			}
		}
		
		if (file_exists($path)) {
			include_once $path;
		}
	});
	
	// Connect to MySQL and include the shorterner functions
	include Router::getIncludePath("inc/bigtree/classes/SQL.php");
	include Router::getIncludePath("inc/bigtree/compat/sql.php");
	
	// Setup our connections as disconnected by default.
	$bigtree["mysql_read_connection"] = "disconnected";
	$bigtree["mysql_write_connection"] = "disconnected";

	// Load Up BigTree!
	error_reporting(E_ALL);
	ini_set("display_errors", "on");
	Router::boot($bigtree["config"], $path);
	include Router::getIncludePath("inc/bigtree/compat/cms.php");
	
	// If we're in the process of logging into multi-domain sites, login this session and move along
	if (defined("BIGTREE_SITE_KEY") && isset($_GET["bigtree_login_redirect_session_key"])) {
		Auth::loginChainSession($_GET["bigtree_login_redirect_session_key"]);
	}
	
	if (defined("BIGTREE_CUSTOM_BASE_CLASS") && BIGTREE_CUSTOM_BASE_CLASS) {
		include SITE_ROOT.BIGTREE_CUSTOM_BASE_CLASS_PATH;
		eval("class BigTreeCMS extends ".BIGTREE_CUSTOM_BASE_CLASS." {}");
	} else {
		class BigTreeCMS extends BigTreeCMSBase {};
	}
	
	// Initialize DB instance
	$db = new SQL;
	
	include Router::getIncludePath("inc/bigtree/compat/admin.php");
	
	// Setup admin class if it's custom, but don't instantiate the $admin var.
	if (defined("BIGTREE_CUSTOM_ADMIN_CLASS") && BIGTREE_CUSTOM_ADMIN_CLASS) {
		include_once SITE_ROOT.BIGTREE_CUSTOM_ADMIN_CLASS_PATH;
		eval("class BigTreeAdmin extends ".BIGTREE_CUSTOM_ADMIN_CLASS." {}");
	} else {
		class BigTreeAdmin extends BigTreeAdminBase {};
	}
	
	// Bootstrap CMS instance
	$cms = new BigTreeCMS;
