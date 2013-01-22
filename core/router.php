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
				
				if (!$ims || strtotime($ims) != $last_modified) {
					header("Content-type: text/javascript");
					header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified).' GMT', true, 200);
					die(file_get_contents($cfile));
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
						require_once(SERVER_ROOT."core/inc/lib/less-compiler.inc.php");
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
					require_once(SERVER_ROOT."core/inc/lib/CSSMin.php");			
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
					die(file_get_contents($cfile));
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
	
	// Handle AJAX calls.
	if ($bigtree["path"][0] == "ajax") {
		$bigtree["mysql_read_connection"] = bigtree_setup_sql_connection();

		list($inc,$commands) = BigTree::route(SERVER_ROOT."templates/ajax/",array_slice($bigtree["path"],1));
		if (!file_exists($inc)) {
			header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
			die("File not found.");
		}
		$bigtree["commands"] = $commands;

		// Get the pieces of the location so we can get header and footers. Take away the first 2 routes since they're templates/ajax.
		$pieces = array_slice(explode("/",str_replace(SERVER_ROOT,"",$inc)),2);
		// Include all headers in the module directory in the order they occur.
		$inc_path = "";
		$headers = $footers = array();
		foreach ($pieces as $piece) {
			if (substr($piece,-4,4) != ".php") {
				$inc_path .= $piece."/";
				$header = SERVER_ROOT."templates/ajax/".$inc_path."_header.php";
				$footer = SERVER_ROOT."templates/ajax/".$inc_path."_footer.php";
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
	
	// Sitemap setup
	if ($bigtree["path"][0] == "sitemap.xml") {
		$cms->drawXMLSitemap();
	}
	if ($bigtree["path"][0] == "feeds") {
		$bigtree["mysql_read_connection"] = bigtree_setup_sql_connection();
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
		list($navid,$bigtree["commands"],$routed) = $cms->getNavId($bigtree["path"],$bigtree["preview"]);
		$commands = $bigtree["commands"]; // Backwards compatibility
	}
	
	// Pre-init a bunch of vars to keep away notices.
	$bigtree["layout"] = "default";
	if ($navid !== false) {
		// If we're previewing, get pending data as well.
		if ($bigtree["preview"]) {
			$bigtree["page"] = $cms->getPendingPage($navid);
		} else {
			$bigtree["page"] = $cms->getPage($navid);
		}
		$bigtree["page"]["link"] = WWW_ROOT.$bigtree["page"]["path"]."/";
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
			$path_components = explode("/",str_replace($bigtree["page"]["path"]."/","",implode("/",$bigtree["path"])."/"));
			if (end($path_components) === "") {
				array_pop($path_components);
			}
			list($inc,$commands) = BigTree::route(SERVER_ROOT."templates/routed/".$bigtree["page"]["template"]."/",$path_components);
			$bigtree["commands"] = $commands;
			if (count($commands)) {
				$bigtree["module_path"] = array_slice($path_components,0,-1 * count($commands));
			} else {
				$bigtree["module_path"] = array_slice($path_components,0);
			}
			
			// Get the pieces of the location so we can get header and footers. Take away the first 2 routes since they're templates/routed/.
			$pieces = array_slice(explode("/",str_replace(SERVER_ROOT,"",$inc)),2);
			// Include all headers in the module directory in the order they occur.
			$inc_path = "";
			$headers = $footers = array();
			foreach ($pieces as $piece) {
				if (substr($piece,-4,4) != ".php") {
					$inc_path .= $piece."/";
					$header = SERVER_ROOT."templates/routed/".$inc_path."_header.php";
					$footer = SERVER_ROOT."templates/routed/".$inc_path."_footer.php";
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
			eval('$admin = new '.BIGTREE_CUSTOM_ADMIN_CLASS.';');
		} else {
			$admin = new BigTreeAdmin;
		}
	}
	
	if (isset($bigtree["page"]) && !$cms->Secure && $_SESSION["bigtree_admin"]["id"]) {
		$show_bar_default = $_COOKIE["hide_bigtree_bar"] ? false : true;
		$show_preview_bar = false;
		$return_link = "";
		if ($_GET["bigtree_preview_return"]) {
			$show_bar_default = false;
			$show_preview_bar = true;
			$return_link = $_GET["bigtree_preview_return"];
		}
				
		$bigtree["content"] = str_ireplace('</body>','<script type="text/javascript" src="'.$bigtree["config"]["admin_root"].'ajax/bar.js/?previewing='.BIGTREE_PREVIEWING.'&current_page_id='.$bigtree["page"]["id"].'&show_bar='.$show_bar_default.'&username='.$_SESSION["bigtree_admin"]["name"].'&show_preview='.$show_preview_bar.'&return_link='.$return_link.'"></script></body>',$bigtree["content"]);
		$bigtree["config"]["cache"] = false;
	}
	
	echo $bigtree["content"];
	
	// Write to the cache
	if ($bigtree["config"]["cache"] && !defined("BIGTREE_DO_NOT_CACHE")) {
		$cache = ob_get_flush();
		if (!$bigtree["page"]["path"]) {
			$bigtree["page"]["path"] = "!";
		}
		file_put_contents("../cache/".base64_encode($bigtree["page"]["path"]).".page",$cache);
	}
?>