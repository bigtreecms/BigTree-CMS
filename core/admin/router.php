<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 * @global string $server_root
	 */
	
	// Set a definition to check for being in the admin
	define("BIGTREE_ADMIN_ROUTED", true);
	
	// Set static root for those without it
	if (!isset($bigtree["config"]["static_root"])) {
		$bigtree["config"]["static_root"] = $bigtree["config"]["www_root"];
	}
	
	// Make sure no notice gets thrown for $bigtree["path"] being too small.
	$bigtree["path"] = array_pad($bigtree["path"], 2, "");
	
	// If we're routing through * it means we're accessing an extension's assets
	if ($bigtree["path"][1] == "*") {
		$bigtree["extension_context"] = $bigtree["path"][2];
		define("EXTENSION_ROOT", $server_root."extensions/".$bigtree["path"][2]."/");
		
		$bigtree["path"] = array_merge([$bigtree["path"][0]], array_slice($bigtree["path"], 3));
	}
	
	// Images.
	if ($bigtree["path"][1] == "images") {
		// Get additional image folder path
		$image_path = implode("/", array_slice($bigtree["path"], 2));
		
		if (defined("EXTENSION_ROOT")) {
			$image_file = EXTENSION_ROOT."images/$image_path";
		} else {
			$image_file = file_exists("../custom/admin/images/$image_path") ? "../custom/admin/images/$image_path" : "../core/admin/images/$image_path";
		}
		
		if (function_exists("apache_request_headers")) {
			$headers = apache_request_headers();
			$ims = isset($headers["If-Modified-Since"]) ? $headers["If-Modified-Since"] : "";
		} else {
			$ims = isset($_SERVER["HTTP_IF_MODIFIED_SINCE"]) ? $_SERVER["HTTP_IF_MODIFIED_SINCE"] : "";
		}
		
		$last_modified = filemtime($image_file);
		
		if ($ims && strtotime($ims) == $last_modified) {
			header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified).' GMT', true, 304);
			die();
		}
		
		$type = strtolower(substr($image_file, -3, 3));
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
		readfile($image_file);
		die();
	}
	
	// CSS
	if ($bigtree["path"][1] == "css") {
		$css_path = implode("/", array_slice($bigtree["path"], 2));
		
		if (defined("EXTENSION_ROOT")) {
			$css_file = EXTENSION_ROOT."css/$css_path";
		} else {
			$css_file = file_exists("../custom/admin/css/$css_path") ? "../custom/admin/css/$css_path" : "../core/admin/css/$css_path";
		}
		
		if (function_exists("apache_request_headers")) {
			$headers = apache_request_headers();
			$ims = isset($headers["If-Modified-Since"]) ? $headers["If-Modified-Since"] : "";
		} else {
			$ims = isset($_SERVER["HTTP_IF_MODIFIED_SINCE"]) ? $_SERVER["HTTP_IF_MODIFIED_SINCE"] : "";
		}
		
		$last_modified = filemtime($css_file);
	
		if ($ims && strtotime($ims) == $last_modified) {
			header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified).' GMT', true, 304);
			die();
		}
		
		header("Content-type: text/css");
		header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified).' GMT', true, 200);
		
		// Handle LESS
		if (strtolower(substr($css_file, -5, 5)) == ".less") {
			$server_root = isset($server_root) ? $server_root : str_replace("core/admin/router.php", "", strtr(__FILE__, "\\", "/"));
			$cache_file = $server_root."cache/admin-compiled-css-".md5($css_file).".css";
			
			// Already compiled this, just return it
			if (file_exists($cache_file) && filemtime($cache_file) >= $last_modified) {
				readfile($cache_file);
				die();
			}
			
			
			// Load LESS compiler
			require_once $server_root."vendor/oyejorge/less.php/lib/Less/Autoloader.php";
			\Less_Autoloader::register();
			$parser = new \Less_Parser(["compress" => true]);
			
			try {
				$parser->parseFile($css_file);
				$css = $parser->getCss();
				
				// Cache and return
				file_put_contents($cache_file, $css);
				die($css);
			} catch (\Exception $e) {
				die("Failed to parse LESS.");
			}
		}
		
		// Regular old CSS
		readfile($css_file);
	}
	
	// JavaScript
	if ($bigtree["path"][1] == "js") {
		// Calcuate the maximum post size so we can pass it along to scripts
		$pms = ini_get('post_max_size');
		$mul = substr($pms, -1);
		$mul = ($mul == 'M' ? 1048576 : ($mul == 'K' ? 1024 : ($mul == 'G' ? 1073741824 : 1)));
		$max_file_size = $mul * (int) $pms;
		$js_path = implode("/", array_slice($bigtree["path"], 2));
		
		if (defined("EXTENSION_ROOT")) {
			$js_file = EXTENSION_ROOT."js/$js_path";
		} else {
			$js_file = file_exists("../custom/admin/js/$js_path") ? "../custom/admin/js/$js_path" : "../core/admin/js/$js_path";
		}
		
		// If we're serving php, just include it instead of trying to parse it as JS
		if (substr($js_file, -4, 4) == ".php") {
			header("Content-type: text/javascript");
			include $js_file;
			die();
		}
		
		// Serve different headers since some JS serves CSS/images from the JS directory
		$type = substr($js_file, -3, 3);
		if ($type == "css") {
			header("Content-type: text/css");
		} elseif ($type == "htm" || substr($js_file, -4, 4) == "html") {
			header("Content-type: text/html");
		} elseif ($type == "png") {
			header("Content-type: image/png");
		} elseif ($type == "gif") {
			header("Content-type: image/gif");
		} elseif ($type == "jpg") {
			header("Content-type: image/jpeg");
		} elseif ($type == "ttf") {
			header("Content-type: font/ttf");
		} elseif (substr($js_file, -4, 4) == "woff") {
			header("Content-type: font/x-woff");
		} else {
			header("Content-type: text/javascript");
		}
		
		if (function_exists("apache_request_headers")) {
			$headers = apache_request_headers();
			$ims = isset($headers["If-Modified-Since"]) ? $headers["If-Modified-Since"] : "";
		} else {
			$ims = isset($_SERVER["HTTP_IF_MODIFIED_SINCE"]) ? $_SERVER["HTTP_IF_MODIFIED_SINCE"] : "";
		}
		
		$last_modified = filemtime($js_file);
		
		if ($ims && strtotime($ims) == $last_modified && count($_GET) == 1) {
			header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified).' GMT', true, 304);
			die();
		}
		
		header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified).' GMT', true, 200);
		$find = ['$max_file_size', "www_root/", "admin_root/", "static_root/"];
		$replace = [$max_file_size, $bigtree["config"]["www_root"], $bigtree["config"]["admin_root"], $bigtree["config"]["static_root"]];
		
		// Allow GET variables to serve as replacements in JS using $var and file.js?var=whatever
		foreach ($_GET as $key => $val) {
			$find[] = '$'.$key;
			$find[] = "{".$key."}";
			$replace[] = $val;
			$replace[] = $val;
		}
		
		die(str_replace($find, $replace, file_get_contents($js_file)));
	}
	
	// We're loading a page in the admin, so add and remove some content / security headers
	$csp_domains = [];
	
	if (is_array($bigtree["config"]["sites"]) && count($bigtree["config"]["sites"])) {
		foreach ($bigtree["config"]["sites"] as $site) {
			$clean_csp_domain = str_replace(["https://", "http://"], "", $site["domain"]);
			$csp_domains[] = "http://".$clean_csp_domain;
			$csp_domains[] = "https://".$clean_csp_domain;
		}
	} else {
		$clean_csp_domain = str_replace(["https://", "http://"], "", $bigtree["config"]["domain"]);
		$csp_domains[] = "http://".$clean_csp_domain;
		$csp_domains[] = "https://".$clean_csp_domain;
	}
	
	header("Content-Type: text/html; charset=utf-8");
	header("Content-Security-Policy: frame-ancestors ".implode(" ", $csp_domains));
	
	if (is_array($bigtree["config"]["sites"]) && count($bigtree["config"]["sites"])) {
		$csp_domains = [];
		
		foreach ($bigtree["config"]["sites"] as $site) {
			$csp_domains[] = str_replace(["https://", "http://"], "", $site["domain"]);
		}
		
		header("Content-Security-Policy: frame-ancestors ".implode(" ", $csp_domains));
	} else {
		header("Content-Security-Policy: frame-ancestors ".str_replace(["https://", "http://"], "", DOMAIN));
	}
	
	if (function_exists("header_remove")) {
		header_remove("Server");
		header_remove("X-Powered-By");
	}
	
	// Bootstrap BigTree Environment
	if (file_exists("../custom/bootstrap.php")) {
		include "../custom/bootstrap.php";
	} else {
		include "../core/bootstrap.php";
	}
	
	ob_start();
	SessionHandler::start();
	
	// Set date format if it wasn't defined in config
	if (empty($bigtree["config"]["date_format"])) {
		$bigtree["config"]["date_format"] = "m/d/Y";
	}
	
	// Make it easier to extend the nav tree without overwriting important things.
	include Router::getIncludePath("admin/_nav-tree.php");
	
	// Initialize BigTree's additional CSS and JS arrays for inclusion in the admin's header
	$bigtree["js"] = [];
	$bigtree["css"] = [];
	
	// Instantiate the $admin var (user system)
	$admin = new \BigTreeAdmin;
	
	// Load the default layout.
	$bigtree["layout"] = "default";
	$bigtree["subnav_extras"] = [];
	
	// Setup security policy
	Auth::initSecurity();
	
	// If we're not logged in and we're not trying to login, redirect to the login page.
	if (is_null(Auth::user()->ID) && $bigtree["path"][1] != "login") {
		if (implode(array_slice($bigtree["path"], 1, 2), "/") != "ajax/two-factor-check") {
			$_SESSION["bigtree_login_redirect"] = DOMAIN.$_SERVER["REQUEST_URI"];
			
			Router::redirect(ADMIN_ROOT."login/");
		}
	}
	
	// Developer Mode On?
	if (!Auth::user()->ID && !empty($bigtree["config"]["developer_mode"]) && Auth::user()->Level < 2) {
		include Router::getIncludePath("admin/pages/developer-mode.php");
		Auth::stop();
	}
	
	// Redirect to dashboard by default if we're not requesting anything.
	if (!$bigtree["path"][1]) {
		Router::redirect(ADMIN_ROOT."dashboard/");
	}
	
	// Let route registration take over if it finds something
	$registry_found = false;
	$registry_commands = [];
	$registry_rule = false;
	
	foreach (Router::$Registry["admin"] as $registration) {
		if (!$registry_found) {
			$registry_commands = Router::getRegistryCommands("/".implode("/", array_slice($bigtree["path"], 1)), $registration["pattern"]);
			
			if (!is_null($registry_commands)) {
				$registry_found = true;
				$registry_rule = $registration;
			}
		}
	}
	
	if ($registry_found) {
		// Emulate commands at indexes as well as with requested variable keys
		$bigtree["commands"] = [];
		$x = 0;
		
		foreach ($registry_commands as $key => $value) {
			$bigtree["commands"][$x] = $bigtree["commands"][$key] = $value;
			$x++;
		}
		
		list($bigtree["routed_headers"], $bigtree["routed_footers"]) = Router::getRoutedLayoutPartials($registry_rule["file"]);
		
		// Draw the headers.
		foreach ($bigtree["routed_headers"] as $header) {
			include $header;
		}
		
		// Draw the main page.
		include SERVER_ROOT.$registry_rule["file"];
		
		// Draw the footers.
		foreach ($bigtree["routed_footers"] as $footer) {
			include $footer;
		}
		
		$bigtree["content"] = ob_get_clean();
		
		include Router::getIncludePath("admin/layouts/".$bigtree["layout"].".php");
		die();
	}
	
	// See if we're requesting something in /ajax/
	if ($bigtree["path"][1] == "ajax") {
		$core_ajax_directories = array("two-factor-check","auto-modules","callouts","dashboard","file-browser","pages","tags");
		
		if ($bigtree["path"] && !in_array($bigtree["path"][2], $core_ajax_directories)) {
			// If the current user isn't allowed in the module for the ajax, stop them.
			$module = Module::getByRoute($bigtree["path"][2]);
			
			if ($module && !$module->UserCanAccess) {
				die("Permission denied to module: ".$module->Name);
			} elseif (!Auth::user()->ID) {
				die("Please login.");
			}
			
			// Backwards compatibility with array formats < 4.3
			if ($module) {
				$bigtree["current_module"] = $bigtree["module"] = $module->Array;
			}
		}
		
		$ajax_path = array_slice($bigtree["path"], 2);
		
		// Extensions must use this directory
		if (defined("EXTENSION_ROOT")) {
			list($inc, $commands) = Router::getRoutedFileAndCommands(EXTENSION_ROOT."ajax/", $ajax_path);
			// Check custom/core
		} else {
			list($inc, $commands) = Router::getRoutedFileAndCommands(SERVER_ROOT."custom/admin/ajax/", $ajax_path);
			
			// Check core if we didn't find the page or if we found the page but it had commands (because we may be overriding a page earlier in the chain but using the core further down)
			if (!$inc || count($commands)) {
				list($core_inc, $core_commands) = Router::getRoutedFileAndCommands(SERVER_ROOT."core/admin/ajax/", $ajax_path);
				
				// If we either never found the custom file or if there are more routes found in the core file use the core.
				if (!$inc || ($inc && $core_inc && count($core_commands) < count($commands))) {
					$inc = $core_inc;
					$commands = $core_commands;
				}
			}
		}
		
		if (!file_exists($inc)) {
			header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
			die("File not found.");
		}
		
		$bigtree["commands"] = $commands;
		$bigtree["ajax_inc"] = $inc;
		
		list($bigtree["ajax_headers"], $bigtree["ajax_footers"]) = Router::getRoutedLayoutPartials($inc);
		
		// Draw the headers.
		foreach ($bigtree["ajax_headers"] as $header) {
			include $header;
		}
		
		// Draw the main page.
		include $bigtree["ajax_inc"];
		
		// Draw the footers.
		foreach ($bigtree["ajax_footers"] as $footer) {
			include $footer;
		}
		
		die();
	}
	
	// Execute cron tab functions if they haven't been run in 24 hours
	$last_check = Setting::value("bigtree-internal-cron-last-run");
	
	if ((time() - $last_check) > (24 * 60 * 60)) {
		Cron::run();
	}
	
	$primary_route = $bigtree["path"][1];
	$module_path = array_slice($bigtree["path"], 1);
	$module = Module::getByRoute($primary_route);
	$complete = false;
	
	// We're routing through a module, so get module information and check permissions
	if ($module) {
		// Setup environment vars
		$bigtree["current_module"] = $bigtree["module"] = $module->Array;
		define("MODULE_ROOT", ADMIN_ROOT.$module->Route."/");
		
		if ($module->Extension) {
			$bigtree["extension_context"] = $module->Extension;
			define("EXTENSION_ROOT", SERVER_ROOT."extensions/".$module->Extension."/");
		}
		
		// Find out what module action we're trying to hit
		$route_response = $module->getActionForPath(array_slice($bigtree["path"], 2));
		
		if ($route_response) {
			$bigtree["module_action"] = $route_response["action"]->Array;
			$bigtree["commands"] = $route_response["commands"];
		}
		
		// Make sure the user has access to the module
		if (!Auth::user()->canAccess($route_response["action"])) {
			Auth::stop(file_get_contents(Router::getIncludePath("admin/pages/_denied.php")));
		}
		
		// Append module info to the admin nav to draw the headers and breadcrumb and such.
		$bigtree["nav_tree"]["auto-module"] = [
			"title" => $module->Name,
			"link" => $module->Route,
			"icon" => "modules",
			"children" => [],
			"hidden" => true
		];
		
		foreach ($module->Actions as $action) {
			$bigtree["nav_tree"]["auto-module"]["children"][] = [
				"title" => $action->Name,
				"link" => $action->Route ? $module->Route."/".$action->Route : $module->Route,
				"nav_icon" => $action->Icon,
				"hidden" => $action->InNav ? false : true,
				"level" => $action->Level
			];
		}
		
		// Bring in related modules if this one is in a group.
		if ($module->Group) {
			$related_modules = Module::allByGroup($module->Group);
			$related_group = new ModuleGroup($module->Group);
			
			if (count($related_modules) > 1) {
				$bigtree["related_modules"] = [];
				$bigtree["related_group"] = $related_group->Name;
				
				foreach ($related_modules as $related_module) {
					$bigtree["related_modules"][] = [
						"title" => $related_module->Name,
						"link" => $related_module->Route
					];
				}
			}
		}
		
		// Handle interface actions
		if ($bigtree["module_action"]["interface"]) {
			define("INTERFACE_ROOT", ADMIN_ROOT.$module->Route."/".$bigtree["module_action"]["route"]."/");
			$interface = new ModuleInterface($bigtree["module_action"]["interface"]);
			
			if (strpos($interface->Type, "*") === false) {
				include Router::getIncludePath("admin/auto-modules/".$interface->Type.".php");
				
				$complete = true;
			} else {
				list($extension, $interface_type) = explode("*", $interface->Type);
				$base_directory = SERVER_ROOT."extensions/$extension/plugins/interfaces/$interface_type/parser/";
				list($include_file, $bigtree["commands"]) = Router::getRoutedFileAndCommands($base_directory, $bigtree["commands"]);
				
				include $include_file;
				
				$complete = true;
			}
		}
	}
	
	// Auto actions are going to be already done so we don't need to try manual routing.
	if (!$complete) {
		// Check custom if it's not an extension, otherwise use the extension directory
		if ($module && $module->Extension) {
			$module_path[0] = str_replace($module->Extension."*", "", $module_path[0]);
			list($inc, $commands) = Router::getRoutedFileAndCommands(SERVER_ROOT."extensions/".$module->Extension."/modules/", $module_path);
			
			$bigtree["extension_context"] = $module->Extension;
			define("EXTENSION_ROOT", SERVER_ROOT."extensions/".$module->Extension."/");
		} else {
			list($inc, $commands) = Router::getRoutedFileAndCommands(SERVER_ROOT."custom/admin/modules/", $module_path);
			
			// Check core if we didn't find the page or if we found the page but it had commands (because we may be overriding a page earlier in the chain but using the core further down)
			if (!$inc || count($commands)) {
				list($core_inc, $core_commands) = Router::getRoutedFileAndCommands(SERVER_ROOT."core/admin/modules/", $module_path);
				
				// If we either never found the custom file or if there are more routes found in the core file use the core.
				if (!$inc || ($inc && $core_inc && count($core_commands) < count($commands))) {
					$inc = $core_inc;
					$commands = $core_commands;
				}
			}
		}
		
		if (count($commands)) {
			$bigtree["module_path"] = array_slice($module_path, 1, -1 * count($commands));
		} else {
			$bigtree["module_path"] = array_slice($module_path, 1);
		}
		
		// Check pages
		if (!$inc) {
			$inc = Router::getIncludePath("admin/pages/$primary_route.php");
			
			if (file_exists($inc)) {
				include $inc;
				$complete = true;
			} else {
				$inc = false;
			}
		}
		
		// If we didn't find anything, it's a 404
		if (!$inc) {
			header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
			define("BIGTREE_404", true);
			include Router::getIncludePath("admin/pages/_404.php");
		// It's a manually created module page, include it
		} elseif (!$complete) {
			// Setup the commands array.
			$bigtree["commands"] = $commands;
			$bigtree["routed_inc"] = $inc;
			
			list($bigtree["routed_headers"], $bigtree["routed_footers"]) = Router::getRoutedLayoutPartials($inc);
			
			// Draw the headers.
			foreach ($bigtree["routed_headers"] as $header) {
				include $header;
			}
			
			// Draw the main page.
			include $bigtree["routed_inc"];
			
			// Draw the footers.
			foreach ($bigtree["routed_footers"] as $footer) {
				include $footer;
			}
		}
	}
	
	$bigtree["content"] = ob_get_clean();
	
	include Router::getIncludePath("admin/layouts/".$bigtree["layout"].".php");
	