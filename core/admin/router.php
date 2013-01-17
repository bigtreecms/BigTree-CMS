<?
	// BigTree Version
	define("BIGTREE_VERSION","4.0RC2");
	define("BIGTREE_REVISION",18);

	// BigTree Admin Nav Tree
	$bigtree["nav_tree"] = array(
		"dashboard" => array("title" => "Dashboard","link" => "dashboard","icon" => "dashboard","related" => true,"children" => array(
			array("title" => "Pending Changes","link" => "dashboard/pending-changes","icon" => "pending","hidden" => true),
			"messages" => array("title" => "Message Center","link" => "dashboard/messages","icon" => "messages","hidden" => true,"children" => array(
				array("title" => "View Messages","link" => "dashboard/messages","icon" => "messages","nav_icon" => "list"),
				array("title" => "New Message","link" => "dashboard/messages/new","icon" => "add_message","nav_icon" => "add")
			)),
			array("title" => "Vitals & Statistics","link" => "dashboard/vitals-statistics","icon" => "vitals","related" => true,"hidden" => true,"children" => array(
				array("title" => "Analytics","link" => "dashboard/vitals-statistics/analytics","hidden" => true,"icon" => "analytics","children" => array(
					array("title" => "Statistics","link" => "dashboard/vitals-statistics/analytics","nav_icon" => "bar_graph"),
					array("title" => "Service Providers","link" => "dashboard/vitals-statistics/analytics/service-providers","nav_icon" => "network"),
					array("title" => "Traffic Sources","link" => "dashboard/vitals-statistics/analytics/traffic-sources","nav_icon" => "car"),
					array("title" => "Keywords","link" => "dashboard/vitals-statistics/analytics/keywords","nav_icon" => "key"),
					array("title" => "Configure","link" => "dashboard/vitals-statistics/analytics/configure","nav_icon" => "setup","level" => 1),
					array("title" => "Caching Data","link" => "dashboard/vitals-statistics/analytics/cache","hidden" => true)
				)),
				array("title" => "404 Report","link" => "dashboard/vitals-statistics/404","hidden" => true,"level" => 1,"icon" => "page_404","children" => array(
					array("title" => "Active 404s","link" => "dashboard/vitals-statistics/404","nav_icon" => "error"),
					array("title" => "Ignored 404s","link" => "dashboard/vitals-statistics/404/ignored","nav_icon" => "ignored"),
					array("title" => "301 Redirects","link" => "dashboard/vitals-statistics/404/301","nav_icon" => "redirect"),
					array("title" => "Clear 404s","link" => "dashboard/vitals-statistics/404/clear","nav_icon" => "delete")
				)),
				array("title" => "Site Integrity","link" => "dashboard/vitals-statistics/integrity","icon" => "integrity","hidden" => true,"level" => 1)
			)),
			array("title" => "System Update","link" => "dashboard/update","icon" => "developer","hidden" => true,"level" => 1)
		)),
		"pages" => array("title" => "Pages","link" => "pages","icon" => "page","nav_icon" => "pages","children" => array(
			"view-tree" => array("title" => "View Subpages","link" => "pages/view-tree/{id}","nav_icon" => "list"),
			"add" => array("title" => "Add Subpage","link" => "pages/add/{id}","icon" => "add_page","nav_icon" => "add"),
			"edit" => array("title" => "Edit Page","link" => "pages/edit/{id}","icon" => "edit_page","nav_icon" => "edit"),
			"revisions" => array("title" => "Revisions","link" => "pages/revisions/{id}","icon" => "page_versions","nav_icon" => "refresh"),
			"move" => array("title" => "Move Page","link" => "pages/move/{id}","icon" => "move_page","nav_icon" => "truck","level" => 1)
		)),
		"modules" => array("title" => "Modules","link" => "modules","icon" => "modules","children" => array()),
		"users" => array("title" => "Users","link" => "users","icon" => "users","level" => 1,"children" => array(
			array("title" => "View Users","link" => "users","nav_icon" => "list"),
			array("title" => "Add User","link" => "users/add","nav_icon" => "add"),
			array("title" => "Edit User","link" => "users/edit","icon" => "gravatar","hidden" => true),
			array("title" => "Profile","link" => "users/profile","icon" => "gravatar","hidden" => true)
		)),
		"settings" => array("title" => "Settings","link" => "settings","icon" => "settings","children" => array(
			array("title" => "Edit Setting","link" => "settings/edit","hidden" => true)
		)),
		"developer" => array("title" => "Developer","link" => "developer","icon" => "developer","nav_icon" => "developer","level" => 2,"related" => true,"children" => array(
			array("title" => "Templates","link" => "developer/templates","icon" => "templates","hidden" => true,"children" => array(
				array("title" => "View Templates","link" => "developer/templates","nav_icon" => "list"),
				array("title" => "Add Template","link" => "developer/templates/add","nav_icon" => "add"),
				array("title" => "Edit Template","link" => "developer/templates/edit","hidden" => true)
			)),
			array("title" => "Modules","link" => "developer/modules","icon" => "modules","hidden" => true,"children" => array(
				array("title" => "View Modules","link" => "developer/modules","nav_icon" => "list"),
				array("title" => "Add Module","link" => "developer/modules/add","nav_icon" => "add"),
				array("title" => "Module Designer","link" => "developer/modules/designer","nav_icon" => "edit"),
				array("title" => "View Groups","link" => "developer/modules/groups","nav_icon" => "list"),
				array("title" => "Add Group","link" => "developer/modules/groups/add","nav_icon" => "add"),
				array("title" => "Edit Module","link" => "developer/modules/edit","hidden" => true),
				array("title" => "Edit Group","link" => "developer/modules/groups/edit","hidden" => true),
				array("title" => "Module Created","link" => "developer/modules/create","hidden" => true),
				array("title" => "Add View","link" => "developer/modules/views/add","hidden" => true),
				array("title" => "Edit View","link" => "developer/modules/views/edit","hidden" => true),
				array("title" => "Style View","link" => "developer/modules/views/style","hidden" => true),
				array("title" => "Created View","link" => "developer/modules/views/create","hidden" => true),
				array("title" => "Add Form","link" => "developer/modules/forms/add","hidden" => true),
				array("title" => "Edit Form","link" => "developer/modules/forms/edit","hidden" => true),
				array("title" => "Created Form","link" => "developer/modules/forms/create","hidden" => true),
				array("title" => "Add Action","link" => "developer/modules/actions/add","hidden" => true),
				array("title" => "Edit Action","link" => "developer/modules/actions/edit","hidden" => true)
			)),
			array("title" => "Callouts","link" => "developer/callouts","icon" => "callouts","hidden" => true,"children" => array(
				array("title" => "View Callouts","link" => "developer/callouts","nav_icon" => "list"),
				array("title" => "Add Callout","link" => "developer/callouts/add","nav_icon" => "add"),
				array("title" => "Edit Callout","link" => "developer/callouts/edit","hidden" => true)
			)),
			array("title" => "Field Types","link" => "developer/field-types","icon" => "field_types","hidden" => true,"children" => array(
				array("title" => "View Field Types","link" => "developer/field-types","nav_icon" => "list"),
				array("title" => "Add Field Type","link" => "developer/field-types/add","nav_icon" => "add"),
				array("title" => "Edit Field Type","link" => "developer/field-types/edit","hidden" => true),
				array("title" => "Field Type Created","link" => "developer/field-types/new","hidden" => true)
			)),
			array("title" => "Feeds","link" => "developer/feeds","icon" => "feeds","hidden" => true,"children" => array(
				array("title" => "View Feeds","link" => "developer/feeds","nav_icon" => "list"),
				array("title" => "Add Feed","link" => "developer/feeds/add","nav_icon" => "add"),
				array("title" => "Edit Feed","link" => "developer/feeds/edit","hidden" => true),
				array("title" => "Created Feed","link" => "developer/feeds/create","hidden" => true)
			)),
			array("title" => "Settings","link" => "developer/settings","icon" => "settings","hidden" => true,"children" => array(
				array("title" => "View Settings","link" => "developer/settings","nav_icon" => "list"),
				array("title" => "Add Setting","link" => "developer/settings/add","nav_icon" => "add"),
				array("title" => "Edit Setting","link" => "developer/settings/edit","hidden" => true)
			)),
			array("title" => "Foundry","link" => "developer/foundry","icon" => "package","hidden" => true,"children" => array(
				array("title" => "Install Package","link" => "developer/foundry/install","hidden" => true),
				array("title" => "Unpacked Package","link" => "developer/foundry/install/unpack","hidden" => true),
				array("title" => "Package Installed","link" => "developer/foundry/install/complete","hidden" => true)
			)),
			array("title" => "Cloud Storage","link" => "developer/cloud-storage","icon" => "cloud","hidden" => true,"children" => array(
				array("title" => "Local Storage","link" => "developer/cloud-storage/local","icon" => "local_storage","hidden" => true),
				array("title" => "Amazon S3","link" => "developer/cloud-storage/amazon","icon" => "amazon","hidden" => true),
				array("title" => "Rackspace Cloud","link" => "developer/cloud-storage/rackspace","icon" => "rackspace","hidden" => true)
			)),
			array("title" => "Payment Gateway","link" => "developer/payment-gateway","icon" => "payment","hidden" => true,"children" => array(
				array("title" => "Authorize.Net","link" => "developer/payment-gateway/authorize","icon" => "authorize","hidden" => true),
				array("title" => "PayPal Payments Pro","link" => "developer/payment-gateway/paypal","icon" => "paypal","hidden" => true),
				array("title" => "PayPal Payflow Gateway","link" => "developer/payment-gateway/payflow","icon" => "payflow","hidden" => true),
				array("title" => "First Data / LinkPoint","link" => "developer/payment-gateway/linkpoint","icon" => "linkpoint","hidden" => true)
			)),
			array("title" => "Site Status","link" => "developer/status","icon" => "vitals","hidden" => true)
		)),
		"search" => array("title" => "Advanced Search","link" => "search","icon" => "search","hidden" => true),
		"credits" => array("title" => "Credits & Licenses","link" => "credits","icon" => "credits","hidden" => true)
	);

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
		echo BigTree::formatCSS3(file_get_contents($ifile));
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
		echo str_replace(array("{max_file_size}","www_root/","admin_root/","static_root/"),array($max_file_size,$bigtree["config"]["www_root"],$bigtree["config"]["admin_root"],$bigtree["config"]["static_root"]),file_get_contents($ifile));
		die();
	}

	// Bootstrap BigTree Environment
	if (file_exists("../custom/bootstrap.php")) {
		include "../custom/bootstrap.php";
	} else {
		include "../core/bootstrap.php";
	}

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

	// Connect to MySQL and begin sessions and output buffering.
	if (!$bigtree["mysql_read_connection"]) {
		$bigtree["mysql_read_connection"] = bigtree_setup_sql_connection();
	}
	ob_start();
	session_start();

	// Instantiate the $admin var with either a custom class or the normal BigTreeAdmin.
	if (BIGTREE_CUSTOM_ADMIN_CLASS) {
		eval('$admin = new '.BIGTREE_CUSTOM_ADMIN_CLASS.';');
	} else {
		$admin = new BigTreeAdmin;
	}

	// Load the default layout.
	$bigtree["layout"] = "default";

	// If we're not logged in and we're not trying to login, redirect to the login page.
	if (!isset($admin->ID) && $bigtree["path"][1] != "login") {
		$_SESSION["bigtree_login_redirect"] = DOMAIN.$_SERVER["REQUEST_URI"];
		BigTree::redirect(ADMIN_ROOT."login/");
	}

	// Redirect to dashboard by default if we're not requesting anything.
	if (!$bigtree["path"][1]) {
		BigTree::redirect(ADMIN_ROOT."dashboard/");
	}

	// See if we're requesting something in /ajax/
	if ($bigtree["path"][1] == "ajax") {
		// If the current user isn't allowed in the module for the ajax, stop them.
		$module = $admin->getModuleByRoute($bigtree["path"][2]);
		if ($module && !$admin->checkAccess($module["id"])) {
			die("Permission denied to module: ".$module["name"]);
		}

		$ajax_path = array_slice($bigtree["path"],2);
		list($inc,$commands) = BigTree::route(SERVER_ROOT."custom/admin/ajax/",$ajax_path);
		if (!$inc) {
			list($inc,$commands) = BigTree::route(SERVER_ROOT."core/admin/ajax/",$ajax_path);
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

	// Execute cron tab functions if they haven't been run in 24 hours
	if (!$admin->settingExists("bigtree-internal-cron-last-run")) {
		$admin->createSetting(array(
			"id" => "bigtree-internal-cron-last-run",
			"system" => "on"
		));
	}

	$last_check = $cms->getSetting("bigtree-internal-cron-last-run");
	// It's been more than 24 hours since we last ran cron.
	if ((time() - $last_check) > (24 * 60 * 60)) {
		// Update the setting.
		$admin->updateSettingValue("bigtree-internal-cron-last-run",time());
		// Email the daily digest
		$admin->emailDailyDigest();
		// Cache google analytics
		$ga = new BigTreeGoogleAnalytics;
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
	// Check core
	if (!$inc) {
		list($inc,$commands) = BigTree::route(SERVER_ROOT."core/admin/modules/",$module_path);
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

	// If this is a module or an auto module, check permissions on it.
	if (!$ispage || !$inc) {
		$module = $admin->getModuleByRoute($primary_route);
		// If this is a module and the user doesn't have access, include the denied page and stop.
		if ($module && !$admin->checkAccess($module["id"])) {
			$admin->stop(file_get_contents(BigTree::path("admin/pages/_denied.php")));
		} elseif ($module) {
			$in_module = true;
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

		if ($module && ($bigtree["module_action"]["view"] || $bigtree["module_action"]["form"])) {
			if ($bigtree["module_action"]["form"]) {
				// If the last command is numeric then we're editing something.
				if (is_numeric(end($bigtree["commands"])) || is_numeric(substr(end($bigtree["commands"]),1))) {
					$edit_id = end($bigtree["commands"]);
				// Otherwise we're adding something, at least most likely.
				} else {
					$edit_id = false;
				}
				include BigTree::path("admin/auto-modules/form.php");
			} else {
				include BigTree::path("admin/auto-modules/view.php");
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