<?
	// Setup the BigTree variable "namespace"
	$bigtree = array();
	
	$bigtree["config"] = array();
	$bigtree["config"]["debug"] = false;

	// Newer installs should use a strict $server_root variable to launch properly from shared cores
	if (empty($server_root)) {
		$server_root = str_replace("core/launch.php","",strtr(__FILE__, "\\", "/"));
	}

	include $server_root."custom/environment.php";
	include $server_root."custom/settings.php";

	// Basic routing
	if (isset($bigtree["config"]["routing"]) && $bigtree["config"]["routing"] == "basic") {
		if (!isset($_SERVER["PATH_INFO"])) {
			$bigtree["path"] = array();
			$bigtree["trailing_slash_present"] = false;
		} else {
			$bigtree["path"] = explode("/",trim($_SERVER["PATH_INFO"],"/"));
			$bigtree["trailing_slash_present"] = (substr($_SERVER["PATH_INFO"],-1,1) === "/");
		}

	// "Advanced" or "Simple Rewrite" routing
	} else {
		if (!isset($_GET["bigtree_htaccess_url"])) {
			$_GET["bigtree_htaccess_url"] = "";
		}
	
		$bigtree["path"] = explode("/",rtrim($_GET["bigtree_htaccess_url"],"/"));
		$bigtree["trailing_slash_present"] = (substr($_GET["bigtree_htaccess_url"],-1,1) === "/");
	}

	// Prevent path manipulations
	$bigtree["path"] = array_filter($bigtree["path"],function($val) {
		if ($val == "..") {
			die();
		}
		return true;
	});
	
	$path = $bigtree["path"]; // Backwards compatibility
	
	// Figure out if we're requesting a page in the admin
	$generic_www_root = str_replace(array("http://","https://"),"",$bigtree["config"]["www_root"]);
	$generic_admin_root = str_replace(array("http://","https://"),"",$bigtree["config"]["admin_root"]);
	$parts_of_admin = explode("/",trim(str_replace($generic_www_root,"",$generic_admin_root),"/"));
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

    // See if we're in a multi-domain setup
    if (!empty($bigtree["config"]["sites"]) && count($bigtree["config"]["sites"])) {
        // Figure out which domain we're in
        foreach ($bigtree["config"]["sites"] as $site_key => $site_data) {
            $domain_match = str_replace(array("http://", "https://"), "", $site_data["domain"]);

            if ($domain_match == $_SERVER["HTTP_HOST"]) {
                define("BIGTREE_SITE_KEY", $site_key);
                define("BIGTREE_SITE_TRUNK", intval($site_data["trunk"]));
                define("BIGTREE_CACHE_DIRECTORY", $server_root."cache/".BIGTREE_SITE_KEY."/");

                if (!file_exists(BIGTREE_CACHE_DIRECTORY)) {
                    mkdir(BIGTREE_CACHE_DIRECTORY, 0777);
                }

                $domain = rtrim($site_data["domain"], "/");
                $www_root = $site_data["www_root"];
                $static_root = !empty($site_data["static_root"]) ? $site_data["static_root"] : $www_root;
            }
        }
    }

    if (!defined("BIGTREE_SITE_KEY")) {
        define("BIGTREE_CACHE_DIRECTORY", $server_root."cache/");
    }
	
	// We're not in the admin, see if caching is enabled and serve up a cached page if it exists
	if ($bigtree["config"]["cache"] && $bigtree["path"][0] != "_preview" && $bigtree["path"][0] != "_preview-pending") {
		$cache_location = md5(json_encode($_GET));
		$file = BIGTREE_CACHE_DIRECTORY.$cache_location.".page";

        // If the file is at least 5 minutes fresh, serve it up.
		clearstatcache();
		$ttl = !empty($bigtree["config"]["cache_ttl"]) ? $bigtree["config"]["cache_ttl"] : 300;

		if (file_exists($file) && filemtime($file) > (time() - $ttl)) {
			readfile($file);
			die();
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