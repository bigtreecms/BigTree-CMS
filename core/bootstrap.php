<?
	ini_set("log_errors","false");

	// Set some config vars automatically and setup some globals.
	$GLOBALS["config"] = &$config;
	$GLOBALS["domain"] = rtrim($config["domain"],"/");
	$GLOBALS["server_root"] = str_replace("core/bootstrap.php","",__FILE__);
	$GLOBALS["site_root"] = $GLOBALS["server_root"]."site/";
	$GLOBALS["www_root"] = $config["www_root"];
	$GLOBALS["resource_root"] = $config["resource_root"];
	$GLOBALS["gmaps_key"] = $config["gmaps_key"];
	$GLOBALS["admin_ajax_root"] = $GLOBALS["server_root"]."core/admin/ajax/";
	if (isset($config["root_page"])) {
		$GLOBALS["root_page"] = $config["root_page"];
	} else {
		$GLOBALS["root_page"] = 0;
	}
	
	$config["server_root"] = $GLOBALS["server_root"];
	
	define("WWW_ROOT",$config["www_root"]);
	define("SERVER_ROOT",$GLOBALS["server_root"]);
	define("SITE_ROOT",$GLOBALS["site_root"]);
	
	// Include required utility functions
	if (file_exists($server_root."custom/inc/bigtree/utils.php")) {
		include $server_root."custom/inc/bigtree/utils.php";
	} else {
		include $server_root."core/inc/bigtree/utils.php";
	}
	
	// Connect to MySQL and include the shorterner functions
	include BigTree::path("inc/utils/mysql.inc.php");
	
	// Setup our connections as disconnected by default.
	$GLOBALS["mysql_read_connection"] = "disconnected";
	$GLOBALS["mysql_write_connection"] = "disconnected";
	
	// Turn on debugging if we're in debug mode.
	if ($debug) {
		error_reporting(E_ALL ^ E_NOTICE);
		ini_set("display_errors","on");
	} else {
		ini_set("display_errors","off");
	}
	
	// Load Up BigTree!
	include BigTree::path("inc/bigtree/core.php");
	if (BIGTREE_CUSTOM_BASE_CLASS) {
		include BIGTREE_CUSTOM_BASE_CLASS_PATH;
		eval('$cms = new '.BIGTREE_CUSTOM_BASE_CLASS.';');
	} else {
		$cms = new BigTreeCMS;
	}
	
	$GLOBALS["cms"] = &$cms;
		
	// Lazy loading of modules
	$GLOBALS["module_list"] = $cms->ModuleClassList;
	$GLOBALS["other_classes"] = array(
		"CSSMin" => "inc/utils/CSSMin.php",
		"gapi" => "inc/utils/google-analytics.php",
		"htmlMimeMail" => "inc/utils/html-mail.inc.php",
		"JSMin" => "inc/utils/JSMin.php",
		"PasswordHash" => "inc/utils/PasswordHash.php",
		"TextStatistics" => "inc/utils/text-statistics.php",
		"BigTreeUploadService" => "inc/bigtree/upload-service.php",
		"BigTreeAdmin" => "inc/bigtree/admin.php",
		"BigTreeGoogleAnalytics" => "inc/bigtree/google-analytics.php",
		"BigTreeAutoModule" => "inc/bigtree/auto-modules.php",
		"S3" => "inc/utils/amazon-s3.php",
		"CF_Authentication" => "inc/utils/rackspace-cloud.php"
	);
	
	if (BIGTREE_CUSTOM_ADMIN_CLASS) {
		$GLOBALS["other_classes"][BIGTREE_CUSTOM_ADMIN_CLASS] = BIGTREE_CUSTOM_ADMIN_CLASS_PATH;
	}
	
	function __autoload($class) {
		if (isset($GLOBALS["other_classes"][$class])) {
			include_once BigTree::path($GLOBALS["other_classes"][$class]); 
		} elseif (file_exists($GLOBALS["server_root"]."custom/inc/modules/".$GLOBALS["module_list"][$class].".php")) {
			include_once $GLOBALS["server_root"]."custom/inc/modules/".$GLOBALS["module_list"][$class].".php";
		} elseif (file_exists($GLOBALS["server_root"]."core/inc/modules/".$GLOBALS["module_list"][$class].".php")) {
			include_once $GLOBALS["server_root"]."core/inc/modules/".$GLOBALS["module_list"][$class].".php";
		} else {
			echo "Critical Error: Could not load class $class.";
		}
	}
	
	
	// Load everything in the custom extras folder.
	$d = opendir($GLOBALS["server_root"]."custom/inc/required/");
	$custom_required_includes = array();
	while ($f = readdir($d)) {
		if ($f != "." && $f != "..") {
			$custom_required_includes[] = $GLOBALS["server_root"]."custom/inc/required/$f";
		}
	}
	
	foreach ($custom_required_includes as $r) {
		include $r;
	}
?>