<?php
	ini_set("log_errors","false");
	
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

	// Include required utility functions
	if (file_exists(SERVER_ROOT."custom/inc/bigtree/utils.php")) {
		include SERVER_ROOT."custom/inc/bigtree/utils.php";
	} else {
		include SERVER_ROOT."core/inc/bigtree/utils.php";
	}

	// Setup the vendor folder if we haven't done this check before
	if (!file_exists(SERVER_ROOT."cache/composer-check.flag")) {
		$path = str_replace("core/bootstrap.php", "", __FILE__);
		$off_path = str_replace(SERVER_ROOT, "", $path);

		if (!file_exists(SERVER_ROOT."vendor/")) {
			if (BigTree::isDirectoryWritable(SERVER_ROOT."vendor/")) {
				BigTree::putFile(SERVER_ROOT."cache/composer-check.flag", "done");
				BigTree::copyDirectory(SERVER_ROOT.$off_path."vendor/", SERVER_ROOT."vendor/");
				BigTree::copyFile(SERVER_ROOT.$off_path."composer.json", SERVER_ROOT."composer.json");
			} else {
				die("BigTree needs to copy it's vendor directory to ".SERVER_ROOT.
					"<br><br>If you are unable to provide writable permissions to PHP, copy ".SERVER_ROOT.$off_path."vendor/ and ".SERVER_ROOT.$off_path."composer.json to ".SERVER_ROOT." and add a file named composer-check.flag to ".SERVER_ROOT."cache/ to bypass this step.");
			}
		} else {
			if (file_exists(SERVER_ROOT."composer.json") && !is_writable(SERVER_ROOT."composer.json")) {
				die("BigTree needs to write to your composer.json file to ensure a composer update does not wipe required libraries.");
			}

			$bigtree_composer_json = json_decode(file_get_contents(SERVER_ROOT.$off_path."composer.json"), true);
			$existing_json = json_decode(file_get_contents(SERVER_ROOT."composer.json"), true);

			foreach ($bigtree_composer_json["require"] as $key => $value) {
				if (!isset($existing_json["require"][$key])) {
					$existing_json["require"][$key] = $value;
				}
			}

			BigTree::putFile(SERVER_ROOT."composer.json", BigTree::json($existing_json));
			BigTree::putFile(SERVER_ROOT."cache/composer-check.flag", "done");

			die("BigTree has updated your composer.json file with required libraries. Please run `composer update` before attempting to use BigTree 4.3.");
		}
	}

	include SERVER_ROOT."vendor/autoload.php";
	
	// Connect to MySQL and include the shorterner functions
	include BigTree::path("inc/bigtree/sql.php");

	// Require PHP 5.4 to use the new class
	if (version_compare(PHP_VERSION, "5.4.0") >= 0) {
		include BigTree::path("inc/bigtree/sql-class.php");
	}
	
	// Setup our connections as disconnected by default.
	$bigtree["mysql_read_connection"] = "disconnected";
	$bigtree["mysql_write_connection"] = "disconnected";
	
	// Turn on debugging if we're in debug mode.
	if ($bigtree["config"]["debug"] === "full") {
		error_reporting(E_ALL);
		ini_set("display_errors","on");
	} elseif ($bigtree["config"]["debug"]) {
		error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);
		ini_set("display_errors","on");
	} else {
		ini_set("display_errors","off");
	}

	// Core classes for auto-loading
	$bigtree["other_classes"] = array(
		"BigTreeAdminBase" => "inc/bigtree/admin.php",
		"BigTreeAutoModule" => "inc/bigtree/auto-modules.php",
		"BigTreeModule" => "inc/bigtree/modules.php",
		"BigTreeFTP" => "inc/bigtree/ftp.php",
		"BigTreeSFTP" => "inc/bigtree/sftp.php",
		"BigTreeUpdater" => "inc/bigtree/updater.php",
		"BigTreeSessionHandler" => "inc/bigtree/sessions.php",
		"BigTreeGoogleAnalyticsAPI" => "inc/bigtree/apis/google-analytics.php",
		"BigTreePaymentGateway" => "inc/bigtree/apis/payment-gateway.php",
		"BigTreeUploadService" => "inc/bigtree/apis/storage.php", // Backwards compat
		"BigTreeStorage" => "inc/bigtree/apis/storage.php",
		"BigTreeCloudStorage" => "inc/bigtree/apis/cloud-storage.php",
		"BigTreeGeocoding" => "inc/bigtree/apis/geocoding.php",
		"BigTreeEmailService" => "inc/bigtree/apis/email-service.php",
		"BigTreeTwitterAPI" => "inc/bigtree/apis/twitter.php",
		"BigTreeInstagramAPI" => "inc/bigtree/apis/instagram.php",
		"BigTreeGooglePlusAPI" => "inc/bigtree/apis/google-plus.php",
		"BigTreeYouTubeAPI" => "inc/bigtree/apis/youtube.php",
		"BigTreeFlickrAPI" => "inc/bigtree/apis/flickr.php",
		"BigTreeSalesforceAPI" => "inc/bigtree/apis/salesforce.php",
		"BigTreeDisqusAPI" => "inc/bigtree/apis/disqus.php",
		"BigTreeFacebookAPI" => "inc/bigtree/apis/facebook.php",
		"BigTreeImage" => "inc/bigtree/image.php",
		"BigTreeJSONDB" => "inc/bigtree/json-db.php",
		"BigTreeJSONDBSubset" => "inc/bigtree/json-db-subset.php",
		"S3" => "inc/lib/amazon-s3.php",
		"CF_Authentication" => "inc/lib/rackspace/cloud.php",
		"CSSMin" => "inc/lib/CSSMin.php",
		"PHPMailer" => "inc/lib/phpmailer.php",
		"JShrink" => "inc/lib/JShrink.php",
		"PasswordHash" => "inc/lib/PasswordHash.php",
		"TextStatistics" => "inc/lib/text-statistics.php",
		"lessc" => "inc/lib/less-compiler.php"
	);

	spl_autoload_register("BigTree::classAutoLoader");
	
	// Load Up BigTree!
	include BigTree::path("inc/bigtree/cms.php");
	if (defined("BIGTREE_CUSTOM_BASE_CLASS") && BIGTREE_CUSTOM_BASE_CLASS) {
		include SITE_ROOT.BIGTREE_CUSTOM_BASE_CLASS_PATH;
		eval("class BigTreeCMS extends ".BIGTREE_CUSTOM_BASE_CLASS." {}");
	} else {
		class BigTreeCMS extends BigTreeCMSBase {};
	}
	$cms = new BigTreeCMS;

	// Lazy loading of modules
	$bigtree["module_list"] = $cms->ModuleClassList;

	// Setup admin class if it's custom, but don't instantiate the $admin var.
	if (defined("BIGTREE_CUSTOM_ADMIN_CLASS") && BIGTREE_CUSTOM_ADMIN_CLASS) {
		include_once SITE_ROOT.BIGTREE_CUSTOM_ADMIN_CLASS_PATH;
		eval("class BigTreeAdmin extends ".BIGTREE_CUSTOM_ADMIN_CLASS." {}");
	} else {
		class BigTreeAdmin extends BigTreeAdminBase {};
	}
	
	// If we're in the process of logging into sites
	if (defined("BIGTREE_SITE_KEY") && isset($_GET["bigtree_login_redirect_session_key"])) {
		BigTreeAdmin::loginSession($_GET["bigtree_login_redirect_session_key"]);
	}

	// Load everything in the custom extras folder.
	$d = opendir(SERVER_ROOT."custom/inc/required/");
	$custom_required_includes = array();
	while ($f = readdir($d)) {
		if (substr($f,0,1) != "." && !is_dir(SERVER_ROOT."custom/inc/required/$f")) {
			$custom_required_includes[] = SERVER_ROOT."custom/inc/required/$f";
		}
	}
	closedir($d);
	
	foreach ($custom_required_includes as $r) {
		include $r;
	}
	
	// Clean up
	unset($d,$r,$custom_required_includes);
