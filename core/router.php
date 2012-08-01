<?
	// Handle Javascript Minifying and Caching
	if ($bigtree["path"][0] == "js") {
		clearstatcache();
		// Get the latest mod time on any included js files.
		$mtime = 0;
		$js_file = str_replace(".js","",$bigtree["path"][1]);
		$cfile = SERVER_ROOT."cache/".$js_file.".js";
		$last_modified = file_exists($cfile) ? filemtime($cfile) : 0;
		if (is_array($bigtree["config"]["js"]["files"][$js_file])) {
			foreach ($bigtree["config"]["js"]["files"][$js_file] as $script) {
				$m = file_exists(SITE_ROOT."js/$script") ? filemtime(SITE_ROOT."js/$script") : 0;
				if ($m > $mtime) {
					$mtime = $m;
				}
			}
		}
		// If we have a newer Javascript file to include or we haven't cached yet, do it now.
		if (!file_exists($cfile) || $mtime > $last_modified) {
			$data = "";
			if (is_array($bigtree["config"]["js"]["files"][$js_file])) {
				foreach ($bigtree["config"]["js"]["files"][$js_file] as $script) {
					$data .= file_get_contents(SITE_ROOT."js/$script")."\n";
				}
			}
			// Replace www_root/ and Minify
			$data = str_replace(array('$www_root','www_root/','$static_root','static_root/','$admin_root/','admin_root/'),array(WWW_ROOT,WWW_ROOT,STATIC_ROOT,STATIC_ROOT,ADMIN_ROOT,ADMIN_ROOT),$data);
			if (is_array($_GET)) {
				foreach ($_GET as $key => $val) {
					if ($key != "bigtree_htaccess_url") {
						$data = str_replace('$'.$key,$val,$data);
					}
				}
			}
			if (is_array($bigtree["config"]["js"]["vars"])) {
				foreach ($bigtree["config"]["js"]["vars"] as $key => $val) {
					$data = str_replace('$'.$key,$val,$data);
				}
			}
			if ($bigtree["config"]["js"]["minify"]) {
				$data = JSMin::minify($data);
			}
			file_put_contents($cfile,$data);
			header("Content-type: text/javascript");
			die($data);
		} else {
			// Added a line to .htaccess to hopefully give us IF_MODIFIED_SINCE when running as CGI
			if (function_exists("apache_request_headers")) {
				$headers = apache_request_headers();
				$ims = $headers["If-Modified-Since"];
			} else {
				$ims = $_SERVER["HTTP_IF_MODIFIED_SINCE"];
			}
			
			if (!$ims) {
				header("Content-type: text/javascript");
				die(file_get_contents($cfile));
			} elseif (strtotime($ims) == $last_modified) {
				header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified).' GMT', true, 304);
				die();
			} else {
				header("Content-type: text/javascript");
				header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified).' GMT', true, 200);
				die(file_get_contents($cfile));
			}
		}
	}

	// Handle CSS Shortcuts and Minifying
	if ($bigtree["path"][0] == "css") {
		clearstatcache();
		// Get the latest mod time on any included css files.
		$mtime = 0;
		$css_file = str_replace(".css","",$bigtree["path"][1]);
		$cfile = SERVER_ROOT."cache/".$css_file.".css";
		$last_modified = file_exists($cfile) ? filemtime($cfile) : 0;
		if (is_array($bigtree["config"]["css"]["files"][$css_file])) {
			foreach ($bigtree["config"]["css"]["files"][$css_file] as $style) {
				$m = (file_exists(SITE_ROOT."css/$style")) ? filemtime(SITE_ROOT."css/$style") : 0;
				if ($m > $mtime) {
					$mtime = $m;
				}
			}
		}
		// If we have a newer CSS file to include or we haven't cached yet, do it now.
		if (!file_exists($cfile) || $mtime > $last_modified) {
			$data = "";
			if (is_array($bigtree["config"]["css"]["files"][$css_file])) {
				// if we need LESS
				if (strpos(implode(" ", $bigtree["config"]["css"]["files"][$css_file]), "less") > -1) {
					require_once(SERVER_ROOT."core/inc/utils/less-compiler.inc.php");
					$less_compiler = new lessc();
				}
				foreach ($bigtree["config"]["css"]["files"][$css_file] as $style_file) {
					$style = file_get_contents(SITE_ROOT."css/$style_file");
					if (strpos($style_file, "less") > -1) {
						// convert LESS
						$style = $less_compiler->parse($style);
					} else {
						// normal CSS
						if ($bigtree["config"]["css"]["prefix"]) {
							// Replace CSS3 easymode
							$style = BigTree::formatCSS3($style);
						}
					}
					$data .= $style."\n";
				}
			}
			// Should only loop once, not with every file
			if (is_array($bigtree["config"]["css"]["vars"])) {
				foreach ($bigtree["config"]["css"]["vars"] as $key => $val) {
					$data = str_replace('$'.$key,$val,$data);
				}
			}
			// Replace roots
			$data = str_replace(array('$www_root','www_root/','$static_root','static_root/','$admin_root/','admin_root/'),array(WWW_ROOT,WWW_ROOT,STATIC_ROOT,STATIC_ROOT,ADMIN_ROOT,ADMIN_ROOT),$data);
			if ($bigtree["config"]["css"]["minify"]) {
				require_once(SERVER_ROOT."core/inc/utils/CSSMin.php");			
				$minifier = new CSSMin;
				$data = $minifier->run($data);
			}	
			file_put_contents($cfile,$data);
			header("Content-type: text/css");
			die($data);
		} else {
			// Added a line to .htaccess to hopefully give us IF_MODIFIED_SINCE when running as CGI
			if (function_exists("apache_request_headers")) {
				$headers = apache_request_headers();
				$ims = $headers["If-Modified-Since"];
			} else {
				$ims = $_SERVER["HTTP_IF_MODIFIED_SINCE"];
			}
			
			if (!$ims) {
				header("Content-type: text/css");
				die(file_get_contents($cfile));
			} elseif (strtotime($ims) == $last_modified) {
				header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified).' GMT', true, 304);
				die();
			} else {
				header("Content-type: text/css");
				header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified).' GMT', true, 200);
				die(file_get_contents($cfile));
			}
		}
	}
	
	// Start output buffering and sessions
	ob_start();
	session_start();
	
	// Handle AJAX calls.
	if ($bigtree["path"][0] == "ajax") {
		bigtree_setup_sql_connection();
		$x = 1;
		$ajax_path = "";
		while ($x < count($bigtree["path"]) - 1) {
			$ajax_path .= $bigtree["path"][$x]."/";
			$x++;
		}
		if (file_exists("../templates/ajax/".$ajax_path.$bigtree["path"][$x].".php")) {
			$bigtree["commands"] = array();
			$commands = array(); // Backwards compatibility
			include "../templates/ajax/".$ajax_path.$bigtree["path"][$x].".php";
		} else {
			$inc = "../templates/ajax/".$bigtree["path"][1]."/";
			$inc_dir = $inc;
			$x = 1;
			$y = 1;
			while ($x < count($bigtree["path"])) {
				if (is_dir($inc.$bigtree["path"][$x])) {
					$inc .= $bigtree["path"][$x]."/";
					$inc_dir .= $bigtree["path"][$x]."/";
					$y++;
				} elseif (file_exists($inc.$bigtree["path"][$x].".php")) {
					$inc .= $bigtree["path"][$x].".php";
					$y++;
				}
				$x++;
			}
			if (substr($inc,-4,4) != ".php") {
				if (file_exists($inc.end($bigtree["path"]).".php")) {
					$inc .= end($bigtree["path"]).".php";
				} else {
					$inc .= "default.php";
				}
			}
			
			// Clean up
			unset($inc_dir,$ajax_path,$x);
			
			$bigtree["commands"] = array_slice($bigtree["path"],$y+1);
			$commands = $bigtree["commands"]; // Backwards compatibility
			if (file_exists($inc)) {
				include $inc;
			} else {
				include str_replace("/default.php",".php",$inc);
			}
		}
		die();
	}

	// Tell the browser we're serving HTML
	header("Content-type: text/html");

	// See if we're previewing changes.
	$bigtree["preview"] = false;
	if ($bigtree["path"][0] == "_preview" && $_SESSION["bigtree"]["id"]) {
		$npath = array();
		foreach ($bigtree["path"] as $item) {
			if ($item != "_preview") {
				$npath[] = $item;
			}
		}
		$bigtree["path"] = $npath;
		$bigtree["preview"] = true;
		$bigtree["config"]["cache"] = false;
		
		// Clean up
		unset($npath);
	}
	
	if ($bigtree["path"][0] == "_preview-pending" && $_SESSION["bigtree"]["id"]) {
		$bigtree["preview"] = true;
		$bigtree["commands"] = array();
		$commands = $bigtree["commands"]; // Backwards compatibility
		$navid = $bigtree["path"][1];
	}
	
	// So we don't lose this.
	define("BIGTREE_PREVIEWING",$bigtree["preview"]);
	
	// Sitemap setup
	if ($bigtree["path"][0] == "sitemap.xml") {
		$cms->drawXMLSitemap();
	}
	if ($bigtree["path"][0] == "feeds") {
		bigtree_setup_sql_connection();
		$route = $bigtree["path"][1];
		$feed = $cms->getFeedByRoute($route);
		if ($feed) {
			header("Content-type: text/xml");
			echo '<?xml version="1.0"?>';
			include BigTree::path("feeds/".$feed["type"].".php");
			die();
		}
	}
	
	if (!$navid) {
		list($navid,$bigtree["commands"],$routed) = $cms->getNavId($bigtree["path"]);
		$commands = $bigtree["commands"]; // Backwards compatibility
	}
	
	// Pre-init a bunch of vars to keep away notices.
	$bigtree["layout"] = "default";
	if ($navid) {
		// If we're previewing, get pending data as well.
		if ($bigtree["preview"]) {
			$bigtree["page"] = $cms->getPendingPage($navid);
		} else {
			$bigtree["page"] = $cms->getPage($navid);
		}
		
		$bigtree["resources"] = $bigtree["page"]["resources"];
		$bigtree["callouts"] = $bigtree["page"]["callouts"];
		
		/* Backwards Compatibility */
		$page = $bigtree["page"];
		$resources = $bigtree["resources"];
		$callouts = $bigtree["callouts"];

		// Quick access to resources
		if (is_array($bigtree["resources"])) {
			foreach ($bigtree["resources"] as $key => $val) {
				if (substr($key,0,1) != "_" && $key != "bigtree") { // Don't allow for SESSION or COOKIE injection and don't overwrite $bigtree
					$$key = $bigtree["resources"][$key];
				}
			}
		}
				
		// Redirect lower if the template is !
		if ($bigtree["page"]["template"] == "!") {
			$nav = $cms->getNavByParent($bigtree["page"]["id"],1);
			$first = current($nav);
			BigTree::redirect($first["link"], 303);
		}
		
		// If the template is a module, do its routing for it, otherwise just include the template.
		if ($routed) {
			// We need to figure out how far down the directory structure to route the,.	
			$inc = "../templates/routed/".$bigtree["page"]["template"]."/";
			$inc_dir = $inc;
			$module_commands = array();
			$ended = false;
			foreach ($bigtree["commands"] as $command) {
				if (!$ended && is_dir($inc.$command)) {
					$inc = $inc.$command."/";
				} elseif (!$ended && file_exists($inc.$command.".php")) {
					$inc_dir = $inc;
					$inc = $inc.$command.".php";
					$ended = true;
				} elseif (!$ended) {
					$ended = true;
					$module_commands[] = $command;
					$inc_dir = $inc;
					$inc = $inc."default.php";
				} else {
					$module_commands[] = $command;
				}
			}
			if (!$ended) {
				$inc_dir = $inc;
				$inc = $inc."default.php";
			}
			
			$bigtree["commands"] = $module_commands;
			$commands = $bigtree["commands"]; // Backwards compatibility
			
			// Include the module's header
			if (file_exists("../templates/routed/".substr($bigtree["page"]["template"],7)."/_header.php")) {
				include_once "../templates/routed/".substr($bigtree["page"]["template"],7)."/_header.php";
			}
			
			// Include the sub-module's header if it exists.
			if (file_exists($inc_dir."_header.php")) {
				include_once $inc_dir."_header.php";
			}
			
			include $inc;

			// Include the sub-module's footer if it exists.
			if (file_exists($inc_dir."_footer.php")) {
				include_once $inc_dir."_footer.php";
			}
			
			// Include the module's footer
			if (file_exists("../templates/routed/".substr($bigtree["page"]["template"],7)."/_footer.php")) {
				include_once "../templates/routed/".substr($bigtree["page"]["template"],7)."/_footer.php";
			}

		} elseif ($bigtree["page"]["template"]) {
			include "../templates/basic/".$bigtree["page"]["template"].".php";
		} else {
			BigTree::redirect($bigtree["page"]["external"]);
		}
	// Load the home page if there are no routes.
	} elseif (!$_GET["bigtree_htaccess_url"] || empty($bigtree["path"][0])) {
		$bigtree["page"] = $cms->getPage(0);
		$bigtree["resources"] = $bigtree["page"]["resources"];
		$bigtree["callouts"] = $bigtree["page"]["callouts"];
		
		/* Backwards Compatibility */
		$page = $bigtree["page"];
		$resources = $bigtree["resources"];
		$callouts = $bigtree["callouts"];

		// Quick access to resources
		if (is_array($bigtree["resources"])) {
			foreach ($bigtree["resources"] as $key => $val) {
				if (substr($key,0,1) != "_" && $key != "bigtree") { // Don't allow for SESSION or COOKIE injection and don't overwrite $bigtree
					$$key = $bigtree["resources"][$key];
				}
			}
		}
		
		include "../templates/basic/".$bigtree["page"]["template"].".php";
	// Check for standard sitemap
	} else if ($bigtree["path"][0] == "sitemap" && !$bigtree["path"][1]) {
		include "../templates/basic/_sitemap.php";
	// We've got a 404, check for old routes or throw one.
	} else {
		// Let's check if it's in the old routing table.
		$cms->checkOldRoutes($bigtree["path"]);
		// It's not, it's a 404.
		if ($cms->handle404($_GET["bigtree_htaccess_url"])) {
			include "../templates/basic/_404.php";
		}
	}
	
	$bigtree["content"] = ob_get_clean();
	
	// Load the content again into the layout.
	ob_start();
	include "../templates/layouts/".$bigtree["layout"].".php";
	$bigtree["content"] = ob_get_clean();
	
	// Allow for special output filter functions.
	$filter = null;
	if ($bigtree["config"]["output_filter"]) {
		$filter = $bigtree["config"]["output_filter"];
	}
	
	ob_start($filter);
	
	// If we're in HTTPS, make sure all Javascript, images, and CSS are pulling from HTTPS
	if ($cms->Secure) {
		// Replace CSS includes
		$bigtree["content"] = preg_replace_callback('/<link [^>]*href="([^"]*)"/',create_function('$matches','
			return str_replace(\'href="http://\',\'href="https://\',$matches[0]);
		'),$bigtree["content"]);
		// Replace script and image tags.
		$bigtree["content"] = str_replace('src="http://','src="https://',$bigtree["content"]);
	}
	
	// Load the BigTree toolbar if you're logged in to the admin.
	if ($bigtree["page"]["id"] && !$cms->Secure && isset($_COOKIE["bigtree"]["email"]) && !$_SESSION["bigtree"]["id"]) {
		include BigTree::path("inc/bigtree/admin.php");

		if (BIGTREE_CUSTOM_ADMIN_CLASS) {
			eval('$admin = new '.BIGTREE_CUSTOM_ADMIN_CLASS.';');
		} else {
			$admin = new BigTreeAdmin;
		}
	}
	
	if (isset($bigtree["page"]) && $_SESSION["bigtree"]["id"] && !$cms->Secure) {
		$show_bar_default = $_COOKIE["hide_bigtree_bar"] ? "false" : "true";
		$show_preview_bar = "false";
		$return_link = "";
		if ($_GET["bigtree_preview_bar"]) {
			$show_bar_default = "false";
			$show_preview_bar = "true";
			$return_link = $_SERVER["HTTP_REFERER"];
		}
				
		$bigtree["content"] = str_replace('</body>','<script type="text/javascript">var bigtree_is_previewing = '.(BIGTREE_PREVIEWING ? "true" : "false").'; var bigtree_current_page_id = '.$bigtree["page"]["id"].'; var bigtree_bar_show = '.$show_bar_default.'; var bigtree_user_name = "'.$_SESSION["bigtree"]["name"].'"; var bigtree_preview_bar_show = '.$show_preview_bar.'; var bigtree_return_link = "'.$return_link.'";</script><script type="text/javascript" src="'.$bigtree["config"]["admin_root"].'js/bar.js"></script></body>',$bigtree["content"]);
		$bigtree["config"]["cache"] = false;
	}
	
	echo $bigtree["content"];
	
	// Write to the cache
	if ($bigtree["config"]["cache"] && !defined("BIGTREE_DO_NOT_CACHE")) {
		$cache = ob_get_flush();
		$curl = $_GET["bigtree_htaccess_url"];
		if (!$curl) {
			$curl = "home";
		}
		file_put_contents("../cache/".base64_encode($curl),$cache);
	}
?>