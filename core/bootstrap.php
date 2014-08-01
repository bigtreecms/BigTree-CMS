<?
	ini_set("log_errors","false");

	// Set some config vars automatically and setup some globals.
	$domain = rtrim($bigtree["config"]["domain"],"/");
	// This is set now in index.php but is left for backwards compatibility.
	$server_root = isset($server_root) ? $server_root : str_replace("core/bootstrap.php","",strtr(__FILE__, "\\", "/"));
	$site_root = $server_root."site/";
	$www_root = $bigtree["config"]["www_root"];
	$admin_root = $bigtree["config"]["admin_root"];
	$static_root = isset($bigtree["config"]["static_root"]) ? $bigtree["config"]["static_root"] : $www_root;
	$secure_root = str_replace("http://","https://",$www_root);
	
	define("WWW_ROOT",$www_root);
	define("STATIC_ROOT",$static_root);
	define("SECURE_ROOT",$secure_root);
	define("DOMAIN",$domain);
	define("SERVER_ROOT",$server_root);
	define("SITE_ROOT",$site_root);
	define("ADMIN_ROOT",$admin_root);

	// Include required utility functions
	if (file_exists(SERVER_ROOT."custom/inc/bigtree/utils.php")) {
		include SERVER_ROOT."custom/inc/bigtree/utils.php";
	} else {
		include SERVER_ROOT."core/inc/bigtree/utils.php";
	}
	
	// Connect to MySQL and include the shorterner functions
	include BigTree::path("inc/bigtree/sql.php");
	
	// Setup our connections as disconnected by default.
	$bigtree["mysql_read_connection"] = "disconnected";
	$bigtree["mysql_write_connection"] = "disconnected";
	
	// Turn on debugging if we're in debug mode.
	if ($bigtree["config"]["debug"] === "full") {
		error_reporting(E_ALL);
		ini_set("display_errors","on");
	} elseif ($bigtree["config"]["debug"]) {
		error_reporting(E_ALL ^ E_NOTICE ^ E_STRICT);
		ini_set("display_errors","on");
	} else {
		ini_set("display_errors","off");
	}
	
	// Load Up BigTree!
	include BigTree::path("inc/bigtree/cms.php");
	if (defined("BIGTREE_CUSTOM_BASE_CLASS") && BIGTREE_CUSTOM_BASE_CLASS) {
		include SITE_ROOT.BIGTREE_CUSTOM_BASE_CLASS_PATH;
		// Can't instantiate class from a constant name, so we use a variable then unset it.
		$c = BIGTREE_CUSTOM_BASE_CLASS;
		$cms = new $c;
		unset($c);
	} else {
		$cms = new BigTreeCMS;
	}
	
	// Lazy loading of modules
	$bigtree["module_list"] = $cms->ModuleClassList;
	$bigtree["other_classes"] = array(
		"BigTreeAdmin" => "inc/bigtree/admin.php",
		"BigTreeAutoModule" => "inc/bigtree/auto-modules.php",
		"BigTreeModule" => "inc/bigtree/modules.php",
		"BigTreeFTP" => "inc/bigtree/ftp.php",
		"BigTreeGoogleAnalyticsAPI" => "inc/bigtree/apis/google-analytics.php",
		"BigTreePaymentGateway" => "inc/bigtree/apis/payment-gateway.php",
		"BigTreeUploadService" => "inc/bigtree/apis/storage.php", // Backwards compat
		"BigTreeStorage" => "inc/bigtree/apis/storage.php",
		"BigTreeCloudStorage" => "inc/bigtree/apis/cloud-storage.php",
		"BigTreeGeocoding" => "inc/bigtree/apis/geocoding.php",
		"BigTreeTwitterAPI" => "inc/bigtree/apis/twitter.php",
		"BigTreeInstagramAPI" => "inc/bigtree/apis/instagram.php",
		"BigTreeGooglePlusAPI" => "inc/bigtree/apis/google-plus.php",
		"BigTreeYouTubeAPI" => "inc/bigtree/apis/youtube.php",
		"BigTreeFlickrAPI" => "inc/bigtree/apis/flickr.php",
		"BigTreeSalesforceAPI" => "inc/bigtree/apis/salesforce.php",
		"BigTreeDisqusAPI" => "inc/bigtree/apis/disqus.php",
		"BigTreeYahooBOSSAPI" => "inc/bigtree/apis/yahoo-boss.php",
		"S3" => "inc/lib/amazon-s3.php",
		"CF_Authentication" => "inc/lib/rackspace/cloud.php",
		"CSSMin" => "inc/lib/CSSMin.php",
		"htmlMimeMail" => "inc/lib/html-mail.php",
		"JShrink" => "inc/lib/JShrink.php",
		"PasswordHash" => "inc/lib/PasswordHash.php",
		"TextStatistics" => "inc/lib/text-statistics.php",
		"lessc" => "inc/lib/less-compiler.php"
	);
	
	// Auto load classes	
	spl_autoload_register("BigTree::classAutoLoader");

	// Just include the admin class if it's custom.
	if (defined("BIGTREE_CUSTOM_ADMIN_CLASS") && BIGTREE_CUSTOM_ADMIN_CLASS) {
		include_once SITE_ROOT.BIGTREE_CUSTOM_ADMIN_CLASS_PATH;
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
?>