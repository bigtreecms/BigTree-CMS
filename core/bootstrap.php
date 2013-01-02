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
	if ($bigtree["config"]["debug"]) {
		error_reporting(E_ALL ^ E_NOTICE);
		ini_set("display_errors","on");
	} else {
		ini_set("display_errors","off");
	}
	
	// Load Up BigTree!
	include BigTree::path("inc/bigtree/cms.php");
	if (BIGTREE_CUSTOM_BASE_CLASS) {
		include BIGTREE_CUSTOM_BASE_CLASS_PATH;
		eval('$cms = new '.BIGTREE_CUSTOM_BASE_CLASS.';');
	} else {
		$cms = new BigTreeCMS;
	}
	
	// Lazy loading of modules
	$bigtree["module_list"] = $cms->ModuleClassList;
	$bigtree["other_classes"] = array(
		"CSSMin" => "inc/lib/CSSMin.php",
		"htmlMimeMail" => "inc/lib/html-mail.inc.php",
		"JSMin" => "inc/lib/JSMin.php",
		"PasswordHash" => "inc/lib/PasswordHash.php",
		"TextStatistics" => "inc/lib/text-statistics.php",
		"BigTreeAdmin" => "inc/bigtree/admin.php",
		"BigTreeAutoModule" => "inc/bigtree/auto-modules.php",
		"BigTreeForms" => "inc/bigtree/forms.php",
		"BigTreeGoogleAnalytics" => "inc/bigtree/google-analytics.php",
		"BigTreeModule" => "inc/bigtree/modules.php",
		"BigTreePaymentGateway" => "inc/bigtree/payment-gateway.php",
		"BigTreeUploadService" => "inc/bigtree/upload-service.php",
		"S3" => "inc/lib/amazon-s3.php",
		"CF_Authentication" => "inc/lib/rackspace/cloud.php"
	);
	
	if (BIGTREE_CUSTOM_ADMIN_CLASS) {
		$bigtree["other_classes"][BIGTREE_CUSTOM_ADMIN_CLASS] = BIGTREE_CUSTOM_ADMIN_CLASS_PATH;
	}
	
	function __autoload($class) {
		global $bigtree;
		
		if (isset($bigtree["other_classes"][$class])) {
			include_once BigTree::path($bigtree["other_classes"][$class]); 
		} elseif (file_exists(SERVER_ROOT."custom/inc/modules/".$bigtree["module_list"][$class].".php")) {
			include_once SERVER_ROOT."custom/inc/modules/".$bigtree["module_list"][$class].".php";
		} elseif (file_exists(SERVER_ROOT."core/inc/modules/".$bigtree["module_list"][$class].".php")) {
			include_once SERVER_ROOT."core/inc/modules/".$bigtree["module_list"][$class].".php";
		} else {
			// Clear the module class list just in case we're missing something.
			unlink(SERVER_ROOT."cache/module-class-list.btc");
		}
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