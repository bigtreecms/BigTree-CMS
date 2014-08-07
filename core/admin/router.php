<?
	// BigTree Version
	define("BIGTREE_VERSION","4.1.1");
	define("BIGTREE_REVISION",103);

	// Set static root for those without it
	if (!isset($bigtree["config"]["static_root"])) {
		$bigtree["config"]["static_root"] = $bigtree["config"]["www_root"];
	}

	// Make sure no notice gets thrown for $bigtree["path"] being too small.
	$bigtree["path"] = array_pad($bigtree["path"],2,"");

	// Images.
	if ($bigtree["path"][1] == "images") {
		$x = 2;
		$ipath = "";
		while ($x < count($bigtree["path"]) - 1) {
			$ipath .= $bigtree["path"][$x]."/";
			$x++;
		}

		$ifile = (file_exists("../custom/admin/images/".$ipath.$bigtree["path"][$x])) ? "../custom/admin/images/".$ipath.$bigtree["path"][$x] : "../core/admin/images/".$ipath.$bigtree["path"][$x];

		if (function_exists("apache_request_headers")) {
			$headers = apache_request_headers();
			$ims = isset($headers["If-Modified-Since"]) ? $headers["If-Modified-Since"] : "";
		} else {
			$ims = isset($_SERVER["HTTP_IF_MODIFIED_SINCE"]) ? $_SERVER["HTTP_IF_MODIFIED_SINCE"] : "";
		}

		$last_modified = filemtime($ifile);
		if ($ims && strtotime($ims) == $last_modified) {
			header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified).' GMT', true, 304);
			die();
		}

		$type = explode(".",$bigtree["path"][$x]);
		$type = strtolower($type[count($type)-1]);
		if ($type == "gif") {
			header("Content-type: image/gif");
		} elseif ($type == "jpg") {
			header("Content-type: image/jpeg");
		} elseif ($type == "png") {
			header("Content-type: image/png");
		} elseif ($type == "svg") {
			header("Content-type: image/svg+xml");
		}

		header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified).' GMT', true, 200);
		echo file_get_contents($ifile);
		die();
	}

	// CSS
	if ($bigtree["path"][1] == "css") {
		if (file_exists("../custom/inc/bigtree/utils.php")) {
			include "../custom/inc/bigtree/utils.php";
		} else {
			include "../core/inc/bigtree/utils.php";
		}
		$x = 2;
		$ipath = "";
		while ($x < count($bigtree["path"]) - 1) {
			$ipath .= $bigtree["path"][$x]."/";
			$x++;
		}

		$ifile = (file_exists("../custom/admin/css/".$ipath.$bigtree["path"][$x])) ? "../custom/admin/css/".$ipath.$bigtree["path"][$x] : "../core/admin/css/".$ipath.$bigtree["path"][$x];

		if (function_exists("apache_request_headers")) {
			$headers = apache_request_headers();
			$ims = isset($headers["If-Modified-Since"]) ? $headers["If-Modified-Since"] : "";
		} else {
			$ims = isset($_SERVER["HTTP_IF_MODIFIED_SINCE"]) ? $_SERVER["HTTP_IF_MODIFIED_SINCE"] : "";
		}

		$last_modified = filemtime($ifile);
		if ($ims && strtotime($ims) == $last_modified) {
			header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified).' GMT', true, 304);
			die();
		}
		header("Content-type: text/css");
		header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified).' GMT', true, 200);
		echo BigTree::formatCSS3(str_replace("admin_root/",$bigtree["config"]["admin_root"],file_get_contents($ifile)));
		die();
	}

	// JavaScript
	if ($bigtree["path"][1] == "js") {
		$pms = ini_get('post_max_size');
		$mul = substr($pms,-1);
		$mul = ($mul == 'M' ? 1048576 : ($mul == 'K' ? 1024 : ($mul == 'G' ? 1073741824 : 1)));
		$max_file_size = $mul * (int)$pms;

		$x = 2;
		$ipath = "";
		while ($x < count($bigtree["path"]) - 1) {
			$ipath .= $bigtree["path"][$x]."/";
			$x++;
		}

		$ifile = (file_exists("../custom/admin/js/".$ipath.$bigtree["path"][$x])) ? "../custom/admin/js/".$ipath.$bigtree["path"][$x] : "../core/admin/js/".$ipath.$bigtree["path"][$x];

		if (substr($ifile,-4,4) == ".php") {
			include $ifile;
			die();
		}

		if (function_exists("apache_request_headers")) {
			$headers = apache_request_headers();
			$ims = isset($headers["If-Modified-Since"]) ? $headers["If-Modified-Since"] : "";
		} else {
			$ims = isset($_SERVER["HTTP_IF_MODIFIED_SINCE"]) ? $_SERVER["HTTP_IF_MODIFIED_SINCE"] : "";
		}

		$last_modified = filemtime($ifile);
		if ($ims && strtotime($ims) == $last_modified) {
			header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified).' GMT', true, 304);
			die();
		}
		if (substr($bigtree["path"][$x],-3,3) == "css") {
			header("Content-type: text/css");
		} elseif (substr($bigtree["path"][$x],-3,3) == "htm" || substr($bigtree["path"][$x],-4,4) == "html") {
			header("Content-type: text/html");
		} elseif (substr($bigtree["path"][$x],-3,3) == "png") {
			header("Content-type: image/png");
		} elseif (substr($bigtree["path"][$x],-3,3) == "gif") {
			header("Content-type: image/gif");
		} elseif (substr($bigtree["path"][$x],-3,3) == "jpg") {
			header("Content-type: image/jpeg");
		} else {
			header("Content-type: text/javascript");
		}

		header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified).' GMT', true, 200);
		$find = array("{max_file_size}","www_root/","admin_root/","static_root/");
		$replace = array($max_file_size,$bigtree["config"]["www_root"],$bigtree["config"]["admin_root"],$bigtree["config"]["static_root"]);
		foreach ($_GET as $key => $val) {
			$find[] = "{".$key."}";
			$replace[] = "$val";
		}
		echo str_replace($find,$replace,file_get_contents($ifile));
		die();
	}

	// Bootstrap BigTree Environment
	if (file_exists("../custom/bootstrap.php")) {
		include "../custom/bootstrap.php";
	} else {
		include "../core/bootstrap.php";
	}
	
	// Connect to MySQL and begin sessions and output buffering.
	if (!$bigtree["mysql_read_connection"]) {
		$bigtree["mysql_read_connection"] = bigtree_setup_sql_connection();
	}
	ob_start();
	session_start();

	// Make it easier to extend the nav tree without overwriting important things.
	include BigTree::path("admin/_nav-tree.php");

	// Initialize BigTree's additional CSS and JS arrays for inclusion in the admin's header
	if (isset($bigtree["config"]["admin_js"]) && is_array($bigtree["config"]["admin_js"])) {
		$bigtree["js"] = $bigtree["config"]["admin_js"];
	} else {
		$bigtree["js"] = array();
	}
	if (isset($bigtree["config"]["admin_css"]) && is_array($bigtree["config"]["admin_css"])) {
		$bigtree["css"] = $bigtree["config"]["admin_css"];
	} else {
		$bigtree["css"] = array();
	}

	// Instantiate the $admin var with either a custom class or the normal BigTreeAdmin.
	if (BIGTREE_CUSTOM_ADMIN_CLASS) {
		// Can't instantiate class from a constant name, so we use a variable then unset it.
		$c = BIGTREE_CUSTOM_ADMIN_CLASS;
		$admin = new $c;
		unset($c);
	} else {
		$admin = new BigTreeAdmin;
	}

	// Load the default layout.
	$bigtree["layout"] = "default";
	$bigtree["subnav_extras"] = array();

	// If we're not logged in and we're not trying to login or access an embedded form, redirect to the login page.
	if (!isset($admin->ID) && $bigtree["path"][1] != "login") {
		if (implode(array_slice($bigtree["path"],1),"/") != "ajax/auto-modules/embeddable-form") {
			$_SESSION["bigtree_login_redirect"] = DOMAIN.$_SERVER["REQUEST_URI"];
			BigTree::redirect(ADMIN_ROOT."login/");
		}
	}
	

	// Developer Mode On?
	if (isset($admin->ID) && !empty($bigtree["config"]["developer_mode"]) && $admin->Level < 2) {
		include BigTree::path("admin/pages/developer-mode.php");
		$admin->stop();
	}

	// Redirect to dashboard by default if we're not requesting anything.
	if (!$bigtree["path"][1]) {
		BigTree::redirect(ADMIN_ROOT."dashboard/");
	}

	// See if we're requesting something in /ajax/
	if ($bigtree["path"][1] == "ajax") {
		$module = false;
		if ($bigtree["path"][2] != "auto-modules") {
			// If the current user isn't allowed in the module for the ajax, stop them.
			$module = $admin->getModuleByRoute($bigtree["path"][2]);
			if ($module && !$admin->checkAccess($module["id"])) {
				die("Permission denied to module: ".$module["name"]);
			} elseif (!$admin->ID) {
				die("Please login.");
			}
			
			if ($module) {
				$bigtree["current_module"] = $bigtree["module"] = $module;
			}
		}

		$ajax_path = array_slice($bigtree["path"],2);
		// Check custom
		list($inc,$commands) = BigTree::route(SERVER_ROOT."custom/admin/ajax/",$ajax_path);
		// Check core if we didn't find the page or if we found the page but it had commands (because we may be overriding a page earlier in the chain but using the core further down)
		if (!$inc || count($commands)) {
			list($core_inc,$core_commands) = BigTree::route(SERVER_ROOT."core/admin/ajax/",$ajax_path);
			// If we either never found the custom file or if there are more routes found in the core file use the core.
			if (!$inc || ($inc && $core_inc && count($core_commands) < count($commands))) {
				$inc = $core_inc;
				$commands = $core_commands;
			}
		}

		if (!file_exists($inc)) {
			header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
			die("File not found.");
		}
		$bigtree["commands"] = $commands;

		// Get the pieces of the location so we can get header and footers. Take away the first 3 routes since they're either custom/admin/modules or core/admin/modules.
		$pieces = array_slice(explode("/",str_replace(SERVER_ROOT,"",$inc)),3);
		// Include all headers in the module directory in the order they occur.
		$inc_path = "";
		$headers = $footers = array();
		foreach ($pieces as $piece) {
			if (substr($piece,-4,4) != ".php") {
				$inc_path .= $piece."/";
				$header = BigTree::path("admin/ajax/".$inc_path."_header.php");
				$footer = BigTree::path("admin/ajax/".$inc_path."_footer.php");
				if (file_exists($header)) {
					$headers[] = $header;
				}
				if (file_exists($footer)) {
					$footers[] = $footer;
				}
			}
		}
		// Draw the headers.
		foreach ($headers as $header) {
			include $header;
		}
		// Draw the main page.
		include $inc;
		// Draw the footers.
		$footers = array_reverse($footers);
		foreach ($footers as $footer) {
			include $footer;
		}
		die();
	}

	// We're loading a page in the admin, so let's pass some headers
	header("Content-Type: text/html; charset=utf-8");
	header("X-Frame-Options: SAMEORIGIN");
	if (function_exists("header_remove")) {
		header_remove("Server");
		header_remove("X-Powered-By");
	}

	// Execute cron tab functions if they haven't been run in 24 hours
	$last_check = $cms->getSetting("bigtree-internal-cron-last-run");
	if ($last_check === false) {
		$admin->createSetting(array(
			"id" => "bigtree-internal-cron-last-run",
			"system" => "on"
		));
	}
	// It's been more than 24 hours since we last ran cron.
	if ((time() - $last_check) > (24 * 60 * 60)) {
		// Update the setting.
		$admin->updateSettingValue("bigtree-internal-cron-last-run",time());
		// Email the daily digest
		$admin->emailDailyDigest();
		// Cache google analytics
		$ga = new BigTreeGoogleAnalyticsAPI;
		if ($ga->API && $ga->Profile) {
			// The Google Analytics wrappers can cause Exceptions and we don't want the page failing to load due to them.
			try {
				$ga->cacheInformation();
			} catch (Exception $e) {
				// We should log this in 4.1
			}
		}
	}
	
	// Normal page routing.
	$ispage = false;
	$inc = false;
	$primary_route = $bigtree["path"][1];

	$module_path = array_slice($bigtree["path"],1);
	// Check custom
	list($inc,$commands) = BigTree::route(SERVER_ROOT."custom/admin/modules/",$module_path);
	// Check core if we didn't find the page or if we found the page but it had commands (because we may be overriding a page earlier in the chain but using the core further down)
	if (!$inc || count($commands)) {
		list($core_inc,$core_commands) = BigTree::route(SERVER_ROOT."core/admin/modules/",$module_path);
		// If we either never found the custom file or if there are more routes found in the core file use the core.
		if (!$inc || ($inc && $core_inc && count($core_commands) < count($commands))) {
			$inc = $core_inc;
			$commands = $core_commands;
		}
	}
	if (count($commands)) {
		$bigtree["module_path"] = array_slice($module_path,1,-1 * count($commands));
	} else {
		$bigtree["module_path"] = array_slice($module_path,1);
	}
	// Check pages
	if (!$inc) {
		$inc = BigTree::path("admin/pages/$primary_route.php");
		if (file_exists($inc)) {
			$ispage = true;
		} else {
			$inc = false;
		}
	}

	$bigtree["in_module"] = false;
	// If this is a module or an auto module, check permissions on it.
	if (!$ispage || !$inc) {
		$bigtree["current_module"] = $module = $admin->getModuleByRoute($primary_route);
		// If this is a module and the user doesn't have access, include the denied page and stop.
		if ($module && !$admin->checkAccess($module["id"])) {
			$admin->stop(file_get_contents(BigTree::path("admin/pages/_denied.php")));
		} elseif ($module) {
			$bigtree["in_module"] = true;
			// Append module navigation.
			$actions = $admin->getModuleActions($module);
		
			// Append module info to the admin nav to draw the headers and breadcrumb and such.
			$bigtree["nav_tree"]["auto-module"] = array("title" => $module["name"],"link" => $module["route"],"icon" => "modules","children" => array());
			foreach ($actions as $action) {
				$hidden = $action["in_nav"] ? false : true;
				$route = $action["route"] ? $module["route"]."/".$action["route"] : $module["route"];
				$bigtree["nav_tree"]["auto-module"]["children"][] = array("title" => $action["name"],"link" => $route,"nav_icon" => $action["class"],"hidden" => $hidden,"level" => $action["level"]);
			}

			// Bring in related modules if this one is in a group.
			if ($module["group"]) {
				$related_modules = $admin->getModulesByGroup($module["group"]);
				$related_group = $admin->getModuleGroup($module["group"]);
				if (count($related_modules) > 1) {
					$bigtree["related_modules"] = array();
					$bigtree["related_group"] = $related_group["name"];
					foreach ($related_modules as $rm) {
						$bigtree["related_modules"][] = array("title" => $rm["name"],"link" => $rm["route"]);
					}
				}
			}

			// Give modules their information.
			$bigtree["module"] = $module;
			define("MODULE_ROOT",ADMIN_ROOT.$module["route"]."/");
		}

		$route_response = $admin->getModuleActionByRoute($module["id"],array_slice($bigtree["path"],2));
		if ($route_response) {
			$bigtree["module_action"] = $route_response["action"];
			$bigtree["commands"] = $route_response["commands"];
		}

		if ($module && ($bigtree["module_action"]["view"] || $bigtree["module_action"]["form"] || $bigtree["module_action"]["report"])) {
			if ($bigtree["module_action"]["form"]) {
				include BigTree::path("admin/auto-modules/form.php");
			} elseif ($bigtree["module_action"]["view"]) {
				include BigTree::path("admin/auto-modules/view.php");
			} elseif ($bigtree["module_action"]["report"]) {
				include BigTree::path("admin/auto-modules/report.php");
			}
		} elseif ($inc) {
			// Setup the commands array.
			$bigtree["commands"] = $commands;
			// Get the pieces of the location so we can get header and footers. Take away the first 3 routes since they're either custom/admin/modules or core/admin/modules.
			$pieces = array_slice(explode("/",str_replace(SERVER_ROOT,"",$inc)),3);
			// Include all headers in the module directory in the order they occur.
			$inc_path = "";
			$headers = $footers = array();
			foreach ($pieces as $piece) {
				if (substr($piece,-4,4) != ".php") {
					$inc_path .= $piece."/";
					$header = BigTree::path("admin/modules/".$inc_path."_header.php");
					$footer = BigTree::path("admin/modules/".$inc_path."_footer.php");
					if (file_exists($header)) {
						$headers[] = $header;
					}
					if (file_exists($footer)) {
						$footers[] = $footer;
					}
				}
			}
			// Draw the headers.
			foreach ($headers as $header) {
				include $header;
			}
			// Draw the main page.
			include $inc;
			// Draw the footers.
			$footers = array_reverse($footers);
			foreach ($footers as $footer) {
				include $footer;
			}
		} else {
			header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
			define("BIGTREE_404",true);
			include BigTree::path("admin/pages/_404.php");
		}
	// If we have a page, just include it.
	} else {
		include $inc;
	}

	$bigtree["content"] = ob_get_clean();

	include BigTree::path("admin/layouts/".$bigtree["layout"].".php");
?>