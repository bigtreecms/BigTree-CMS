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
					$data = JShrink::minify($data);
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
				
				if (!$ims || strtotime($ims) != $last_modified) {
					header("Content-type: text/javascript");
					header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified).' GMT', true, 200);
					readfile($cfile);
					die();
				} else {
					header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified).' GMT', true, 304);
					die();
				}
			}
		} else {
			header("HTTP/1.0 404 Not Found");
			die();
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
			// If we have a newer CSS file to include or we haven't cached yet, do it now.
			if (!file_exists($cfile) || $mtime > $last_modified) {
				$data = "";
				if (is_array($bigtree["config"]["css"]["files"][$css_file])) {
					// if we need LESS
					if (strpos(implode(" ", $bigtree["config"]["css"]["files"][$css_file]), "less") > -1) {
						$less_compiler = new lessc();
						$less_compiler->setImportDir(array(SITE_ROOT."css/"));
					}
					foreach ($bigtree["config"]["css"]["files"][$css_file] as $style_file) {
						$style = file_get_contents(SITE_ROOT."css/$style_file");
						if (strpos($style_file, "less") > -1) {
							// convert LESS
							$style = $less_compiler->compile($style);
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
				
				if (!$ims || strtotime($ims) != $last_modified) {
					header("Content-type: text/css");
					header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified).' GMT', true, 200);
					readfile($cfile);
					die();
				} else {
					header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified).' GMT', true, 304);
					die();
				}
			}
		} else {
			header("HTTP/1.0 404 Not Found");
			die();
		}
	}
	
	// Serve Placeholder Image
	if ($bigtree["path"][0] == "images" && $bigtree["path"][1] == "placeholder") {
		if (is_array($bigtree["config"]["placeholder"][$bigtree["path"][2]])) {
			$style = $bigtree["config"]["placeholder"][$bigtree["path"][2]];
			$size = explode("x", strtolower($bigtree["path"][3]));
		} else {
			$style = $bigtree["config"]["placeholder"]["default"];
			$size = explode("x", strtolower($bigtree["path"][2]));
		}
		if (count($size) == 2) {
			BigTree::placeholderImage($size[0], $size[1], $style["background_color"], $style["text_color"], $style["image"], $style["text"]);
		}
	}
	
	// Start output buffering and sessions
	ob_start();
	session_start();

	// Check to see if we're in maintenance mode
	if ($bigtree["config"]["maintenance_url"] && (empty($_SESSION["bigtree_admin"]["level"]) || $_SESSION["bigtree_admin"]["level"] < 2)) {
		// See if we're at the URL
		if (implode("/",$path) != trim(str_replace(WWW_ROOT,"",$bigtree["config"]["maintenance_url"]),"/")) {
			BigTree::redirect($bigtree["config"]["maintenance_url"],"307");
		} else {
			include "../templates/basic/_maintenance.php";
			$bigtree["content"] = ob_get_clean();
			include "../templates/layouts/".($bigtree["layout"] ? $bigtree["layout"] : "default").".php";
			die();
		}
	}
	
	// Handle AJAX calls.
	if ($bigtree["path"][0] == "ajax") {
		list($inc,$commands) = BigTree::route(SERVER_ROOT."templates/ajax/",array_slice($bigtree["path"],1));
		if (!file_exists($inc)) {
			header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
			die("File not found.");
		}
		$bigtree["ajax_inc"] = $inc;
		$bigtree["commands"] = $commands;

		// Get the pieces of the location so we can get header and footers. Take away the first 2 routes since they're templates/ajax.
		$pieces = array_slice(explode("/",str_replace(SERVER_ROOT,"",$inc)),2);
		// Include all headers in the module directory in the order they occur.
		$inc_path = "";
		$bigtree["ajax_headers"] = $bigtree["ajax_footers"] = array();
		foreach ($pieces as $piece) {
			if (substr($piece,-4,4) != ".php") {
				$inc_path .= $piece."/";
				$header = SERVER_ROOT."templates/ajax/".$inc_path."_header.php";
				$footer = SERVER_ROOT."templates/ajax/".$inc_path."_footer.php";
				if (file_exists($header)) {
					$bigtree["ajax_headers"][] = $header;
				}
				if (file_exists($footer)) {
					$bigtree["ajax_footers"][] = $footer;
				}
			}
		}
		// Draw the headers.
		foreach ($bigtree["ajax_headers"] as $header) {
			include $header;
		}
		// Draw the main page.
		include $bigtree["ajax_inc"];
		// Draw the footers.
		$bigtree["ajax_footers"] = array_reverse($bigtree["ajax_footers"]);
		foreach ($bigtree["ajax_footers"] as $footer) {
			include $footer;
		}
		die();
	}

	// Sitemap setup
	if ($bigtree["path"][0] == "sitemap.xml") {
		$cms->drawXMLSitemap();
	}

	// Tell the browser we're serving HTML
	header("Content-type: text/html");

	// See if we're previewing changes.
	$bigtree["preview"] = false;
	if ($bigtree["path"][0] == "_preview" && $_SESSION["bigtree_admin"]["id"]) {
		$npath = array();
		foreach ($bigtree["path"] as $item) {
			if ($item != "_preview") {
				$npath[] = $item;
			}
		}
		$bigtree["path"] = $npath;
		$bigtree["preview"] = true;
		$bigtree["config"]["cache"] = false;
		header("X-Robots-Tag: noindex");
		
		// Clean up
		unset($npath);
	}
	if ($bigtree["path"][0] == "_preview-pending" && $_SESSION["bigtree_admin"]["id"]) {
		$bigtree["preview"] = true;
		$bigtree["commands"] = array();
		$commands = $bigtree["commands"]; // Backwards compatibility
		$navid = $bigtree["path"][1];
		header("X-Robots-Tag: noindex");
	}
	
	// So we don't lose this.
	define("BIGTREE_PREVIEWING",$bigtree["preview"]);
	
	if ($bigtree["path"][0] == "feeds") {
		$route = $bigtree["path"][1];
		$feed = $cms->getFeedByRoute($route);
		if ($feed) {
			header("Content-type: text/xml");
			echo '<?xml version="1.0" encoding="UTF-8" ?>';
			include BigTree::path("feeds/".$feed["type"].".php");
			die();
		}
	}
	
	// If we haven't already received our nav id through previewing...
	if (!$navid) {
		list($navid,$bigtree["commands"],$routed) = $cms->getNavId($bigtree["path"],$bigtree["preview"]);
		$commands = $bigtree["commands"]; // Backwards compatibility
	}
	
	// Pre-init a bunch of vars to keep away notices.
	$bigtree["layout"] = "default";
	if ($navid !== false) {
		// If we're previewing, get pending data as well.
		if ($bigtree["preview"]) {
			$bigtree["page"] = $cms->getPendingPage($navid);
			// If we're previewing pending changes, the template's routed-ness may have changed.
			$template = $cms->getTemplate($bigtree["page"]["template"]);
			$routed = $template["routed"];
		} else {
			$bigtree["page"] = $cms->getPage($navid);
		}
		$bigtree["page"]["link"] = WWW_ROOT.$bigtree["page"]["path"]."/";
		$bigtree["resources"] = $bigtree["page"]["resources"];
		$bigtree["callouts"] = $bigtree["page"]["callouts"];

		// If this page should not be indexed, pass headers
		if ($bigtree["page"]["seo_invisible"]) {
			header("X-Robots-Tag: noindex");
		}
		
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
			// Allow the homepage to be routed
			if ($bigtree["page"]["path"]) {
				$path_components = explode("/",substr(implode("/",$bigtree["path"])."/",strlen($bigtree["page"]["path"]."/")));
			} else {
				$path_components = $bigtree["path"];
			}
			if (end($path_components) === "") {
				array_pop($path_components);
			}
			list($inc,$commands) = BigTree::route(SERVER_ROOT."templates/routed/".$bigtree["page"]["template"]."/",$path_components);
			$bigtree["routed_inc"] = $inc;
			$bigtree["commands"] = $commands;
			if (count($commands)) {
				$bigtree["routed_path"] = $bigtree["module_path"] = array_slice($path_components,0,-1 * count($commands));
			} else {
				$bigtree["routed_path"] = $bigtree["module_path"] = array_slice($path_components,0);
			}
			
			// Get the pieces of the location so we can get header and footers. Take away the first 2 routes since they're templates/routed/.
			$pieces = array_slice(explode("/",str_replace(SERVER_ROOT,"",$inc)),2);
			// Include all headers in the module directory in the order they occur.
			$inc_path = "";
			$bigtree["routed_headers"] = $bigtree["routed_footers"] = array();
			foreach ($pieces as $piece) {
				if (substr($piece,-4,4) != ".php") {
					$inc_path .= $piece."/";
					$header = SERVER_ROOT."templates/routed/".$inc_path."_header.php";
					$footer = SERVER_ROOT."templates/routed/".$inc_path."_footer.php";
					if (file_exists($header)) {
						$bigtree["routed_headers"][] = $header;
					}
					if (file_exists($footer)) {
						$bigtree["routed_footers"][] = $footer;
					}
				}
			}
			// Draw the headers.
			foreach ($bigtree["routed_headers"] as $header) {
				include $header;
			}
			// Draw the main page.
			include $bigtree["routed_inc"];
			// Draw the footers.
			$bigtree["routed_footers"] = array_reverse($bigtree["routed_footers"]);
			foreach ($bigtree["routed_footers"] as $footer) {
				include $footer;
			}
		} elseif ($bigtree["page"]["template"]) {
			include "../templates/basic/".$bigtree["page"]["template"].".php";
		} else {
			BigTree::redirect($bigtree["page"]["external"]);
		}
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
	
	// Load the BigTree toolbar if you're logged in to the admin via cookies but not yet via session.
	if (isset($bigtree["page"]) && !$cms->Secure && isset($_COOKIE["bigtree_admin"]["email"]) && !$_SESSION["bigtree_admin"]["id"]) {
		include_once BigTree::path("inc/bigtree/admin.php");

		if (BIGTREE_CUSTOM_ADMIN_CLASS) {
			// Can't instantiate class from a constant name, so we use a variable then unset it.
			$c = BIGTREE_CUSTOM_ADMIN_CLASS;
			$admin = new $c;
			unset($c);
		} else {
			$admin = new BigTreeAdmin;
		}
	}
	
	/* To load the BigTree Bar, meet the following qualifications:
	   - Not a 404 page
	   - Not a forced secure page (i.e. checkout)
	   - User is logged BigTree admin
	   - User is logged into the BigTree admin FOR THIS PAGE
	   - Developer mode is either disabled OR the logged in user is a Developer
	*/
	if (isset($bigtree["page"]) && !$cms->Secure && $_SESSION["bigtree_admin"]["id"] && $_COOKIE["bigtree_admin"]["email"] && (empty($bigtree["config"]["developer_mode"]) || $_SESSION["bigtree_admin"]["level"] > 1)) {
		$show_bar_default = $_COOKIE["hide_bigtree_bar"] ? false : true;
		$show_preview_bar = false;
		$return_link = "";
		if (!empty($_GET["bigtree_preview_return"])) {
			$show_bar_default = false;
			$show_preview_bar = true;
			$return_link = $_GET["bigtree_preview_return"];
		}
		// Pending Pages don't have their ID set.
		if (!isset($bigtree["page"]["id"])) {
			$bigtree["page"]["id"] = $bigtree["page"]["page"];
		}
		$bigtree["content"] = str_ireplace('</body>','<script type="text/javascript" src="'.str_replace(array("http://","https://"),"//",$bigtree["config"]["admin_root"]).'ajax/bar.js/?previewing='.BIGTREE_PREVIEWING.'&current_page_id='.$bigtree["page"]["id"].'&show_bar='.$show_bar_default.'&username='.$_SESSION["bigtree_admin"]["name"].'&show_preview='.$show_preview_bar.'&return_link='.$return_link.'"></script></body>',$bigtree["content"]);
		// Don't cache the page with the BigTree bar
		$bigtree["config"]["cache"] = false;
	}
	
	echo $bigtree["content"];
	
	// Write to the cache
	if ($bigtree["config"]["cache"] && !defined("BIGTREE_DO_NOT_CACHE") && !count($_POST)) {
		$cache = ob_get_flush();
		if (!$bigtree["page"]["path"]) {
			$bigtree["page"]["path"] = "!";
		}
		file_put_contents("../cache/".md5(json_encode($_GET)).".page",$cache);
	}
?>