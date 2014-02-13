<?
	// Setup the BigTree variable "namespace"
	$bigtree = array();
	
	$bigtree["config"] = array();
	$bigtree["config"]["debug"] = false;
	include str_replace("site/index.php","templates/config.php",strtr(__FILE__, "\\", "/"));
	$bigtree["config"] = isset($config) ? $config : $bigtree["config"]; // Backwards compatibility
	$bigtree["config"]["debug"] = isset($debug) ? $debug : $bigtree["config"]["debug"]; // Backwards compatibility

	// For shared core setups
	$server_root = str_replace("site/index.php","",strtr(__FILE__, "\\", "/"));
	
	if (isset($bigtree["config"]["routing"]) && $bigtree["config"]["routing"] == "basic") {
		if (!isset($_SERVER["PATH_INFO"])) {
			$bigtree["path"] = array();
		} else {
			$bigtree["path"] = explode("/",trim($_SERVER["PATH_INFO"],"/"));
		}
	} else {
		if (!isset($_GET["bigtree_htaccess_url"])) {
			$_GET["bigtree_htaccess_url"] = "";
		}
	
		$bigtree["path"] = explode("/",rtrim($_GET["bigtree_htaccess_url"],"/"));
	}
	$path = $bigtree["path"]; // Backwards compatibility
	
	// Let admin bootstrap itself.  New setup here so the admin can live at any path you choose for obscurity.
	$parts_of_admin = explode("/",trim(str_replace($bigtree["config"]["www_root"],"",$bigtree["config"]["admin_root"]),"/"));
	$in_admin = true;
	$x = 0;

	// Go through each route, make sure the path matches the admin's route paths.
	if (count($bigtree["path"]) < count($parts_of_admin)) {
		$in_admin = false;
	} else {
		foreach ($parts_of_admin as $part) {
			if ($part != $bigtree["path"][$x])	{
				$in_admin = false;
			}
			$x++;
		}
	}
	
	// If we are in the admin, let it bootstrap itself.
	if ($in_admin) {
		// Cut off additional routes from the path, some parts of the admin assume path[0] is "admin" and path[1] begins the routing.
		if ($x > 1) {
			$bigtree["path"] = array_slice($bigtree["path"],$x - 1);
		}
		if (file_exists("../custom/admin/router.php")) {
			include "../custom/admin/router.php";
		} else {
			include "../core/admin/router.php";
		}
		die();
	}
	
	// We're not in the admin, see if caching is enabled and serve up a cached page if it exists
	if ($bigtree["config"]["cache"] && $bigtree["path"][0] != "_preview" && $bigtree["path"][0] != "_preview-pending") {
		$cache_location = $_GET["bigtree_htaccess_url"];
		if (!$cache_location) {
			$cache_location = "!";
		}
		$file = "../cache/".base64_encode($cache_location).".page";
		// If the file is at least 5 minutes fresh, serve it up.
		clearstatcache();
		if (file_exists($file) && filemtime($file) > (time()-300)) {
			// If the web server supports X-Sendfile headers, use that instead of taking up memory by opening the file and echoing it.
			if ($bigtree["config"]["xsendfile"]) {
				header("X-Sendfile: ".str_replace("site/index.php","",strtr(__FILE__, "\\", "/"))."cache/".base64_encode($cache_location).".page");
				header("Content-Type: text/html");
				die();
			// Fall back on file_get_contents otherwise.
			} else {
				die(file_get_contents($file));
			}
		}
	}
	
	// Clean up the variables we set.
	unset($config,$debug,$in_admin,$parts_of_admin,$x);

	// Bootstrap BigTree
	if (file_exists("../custom/bootstrap.php")) {
		include "../custom/bootstrap.php";
	} else {
		include "../core/bootstrap.php";
	}
	// Route BigTree
	if (file_exists("../custom/router.php")) {
		include "../custom/router.php";
	} else {
		include "../core/router.php";
	}
?>