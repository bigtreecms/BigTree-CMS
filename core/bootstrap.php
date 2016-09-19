<?php
	/**
	 * @global array $bigtree
	 */
	
	ini_set("log_errors", "false");

	// See if we're in a multi-domain setup
	if (!empty($bigtree["config"]["sites"]) && count($bigtree["config"]["sites"])) {
		// Figure out which domain we're in
		foreach ($bigtree["config"]["sites"] as $site_key => $site_data) {
			$domain_match = str_replace(array("http://", "https://"), "", $site_data["domain"]);
			
			if ($domain_match == $_SERVER["HTTP_HOST"]) {
				define("BIGTREE_SITE_KEY", $site_key);
				define("BIGTREE_SITE_TRUNK", intval($site_data["trunk"]));
				
				$domain = rtrim($site_data["domain"], "/");
				$www_root = $site_data["www_root"];
				$static_root = !empty($site_data["static_root"]) ? $site_data["static_root"] : $www_root;
			}
		}
	}
	
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
	
	// Adjust server parameters in case we're running on CloudFlare
	if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
		$_SERVER["REMOTE_ADDR"] = $_SERVER["HTTP_CF_CONNECTING_IP"];
	}
	
	// Set version
	include SERVER_ROOT."core/version.php";
	
	// For servers that don't have multibyte string extensions.
	if (!function_exists("mb_strlen")) {
		function mb_strlen($string) {
			return strlen($string);
		}
	}
	
	if (!function_exists("mb_strtolower")) {
		function mb_strtolower($string) {
			return strtolower($string);
		}
	}
	
	// Class auto loader
	spl_autoload_register(function ($class) {
		global $bigtree;
		
		// Auto loadable via the class name
		if (substr($class, 0, 8) == "BigTree\\") {
			$path = "inc/bigtree/classes/".str_replace("\\", "/", substr($class, 8)).".php";
		// Known class in the cache file
		} else {
			$path = $bigtree["class_list"][$class];
		}
		
		if (!$path) {
			// Clear the module class list just in case we're missing something.
			BigTree\FileSystem::deleteFile(SERVER_ROOT."cache/bigtree-module-cache.json");
			
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
	include BigTree\Router::getIncludePath("inc/bigtree/classes/SQL.php");
	include BigTree\Router::getIncludePath("inc/bigtree/compat/sql.php");
	
	// Setup our connections as disconnected by default.
	$bigtree["mysql_read_connection"] = "disconnected";
	$bigtree["mysql_write_connection"] = "disconnected";
	
	// Load Up BigTree!
	BigTree\Router::boot($bigtree["config"]);
	include BigTree\Router::getIncludePath("inc/bigtree/compat/cms.php");

	// If we're in the process of logging into multi-domain sites, login this session and move along
	if (defined("BIGTREE_SITE_KEY") && isset($_GET["bigtree_login_redirect_session_key"])) {
		session_start();
		Auth::loginChainSession($_GET["bigtree_login_redirect_session_key"]);
	}
	
	if (defined("BIGTREE_CUSTOM_BASE_CLASS") && BIGTREE_CUSTOM_BASE_CLASS) {
		include SITE_ROOT.BIGTREE_CUSTOM_BASE_CLASS_PATH;
		eval("class BigTreeCMS extends ".BIGTREE_CUSTOM_BASE_CLASS." {}");
	} else {
		class BigTreeCMS extends BigTreeCMSBase {};
	}
	
	// Initialize DB instance
	$db = new BigTree\SQL;
	
	include BigTree\Router::getIncludePath("inc/bigtree/compat/admin.php");
	
	// Setup admin class if it's custom, but don't instantiate the $admin var.
	if (defined("BIGTREE_CUSTOM_ADMIN_CLASS") && BIGTREE_CUSTOM_ADMIN_CLASS) {
		include_once SITE_ROOT.BIGTREE_CUSTOM_ADMIN_CLASS_PATH;
		eval("class BigTreeAdmin extends ".BIGTREE_CUSTOM_ADMIN_CLASS." {}");
	} else {
		class BigTreeAdmin extends BigTreeAdminBase {};
	}
	
	// Bootstrap CMS instance
	$cms = new BigTreeCMS;
