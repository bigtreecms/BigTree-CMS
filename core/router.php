<?php
	namespace BigTree;
	
	// Handle Javascript Minifying and Caching
	if (Router::$Path[0] == "js") {
		clearstatcache();
		
		// Get the latest mod time on any included js files.
		$mtime = 0;
		$js_file = str_replace(".js", "", Router::$Path[1]);
		$cache_file = BIGTREE_CACHE_DIRECTORY.$js_file.".js";
		$last_modified = file_exists($cache_file) ? filemtime($cache_file) : 0;
		
		if (is_array(Router::$Config["js"]["files"][$js_file])) {
			foreach (Router::$Config["js"]["files"][$js_file] as $script) {
				$m = file_exists(SITE_ROOT."js/$script") ? filemtime(SITE_ROOT."js/$script") : 0;
				
				if ($m > $mtime) {
					$mtime = $m;
				}
			}
			
			// If we have a newer Javascript file to include or we haven't cached yet, do it now.
			if (!file_exists($cache_file) || $mtime > $last_modified) {
				$data = "";
				
				if (is_array(Router::$Config["js"]["files"][$js_file])) {
					foreach (Router::$Config["js"]["files"][$js_file] as $script) {
						$data .= file_get_contents(SITE_ROOT."js/$script")."\n";
					}
				}
				
				// Replace www_root/ and Minify
				$data = str_replace(array('$www_root', 'www_root/', '$static_root', 'static_root/', '$admin_root', 'admin_root/'), array(WWW_ROOT, WWW_ROOT, STATIC_ROOT, STATIC_ROOT, ADMIN_ROOT, ADMIN_ROOT), $data);
				
				if (is_array($_GET)) {
					foreach ($_GET as $key => $val) {
						if ($key != "bigtree_htaccess_url") {
							$data = str_replace('$'.$key, $val, $data);
						}
					}
				}
				
				if (is_array(Router::$Config["js"]["vars"])) {
					foreach (Router::$Config["js"]["vars"] as $key => $val) {
						$data = str_replace('$'.$key, $val, $data);
					}
				}
				
				if (Router::$Config["js"]["minify"]) {
					include_once SERVER_ROOT."core/inc/lib/JShrink/src/JShrink/Minifier.php";
					$data = \JShrink\Minifier::minify($data);
				}
				
				FileSystem::createFile($cache_file, $data);
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
					readfile($cache_file);
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
	if (Router::$Path[0] == "css") {
		clearstatcache();
		
		// Get the latest mod time on any included css files.
		$mtime = 0;
		$css_file = str_replace(".css", "", Router::$Path[1]);
		$cache_file = BIGTREE_CACHE_DIRECTORY.$css_file.".css";
		$last_modified = file_exists($cache_file) ? filemtime($cache_file) : 0;
		
		if (is_array(Router::$Config["css"]["files"][$css_file])) {
			// Check modification times on each included CSS file
			foreach (Router::$Config["css"]["files"][$css_file] as $style) {
				$m = (file_exists(SITE_ROOT."css/$style")) ? filemtime(SITE_ROOT."css/$style") : 0;
				if ($m > $mtime) {
					$mtime = $m;
				}
			}
			
			// If we have a newer CSS file to include or we haven't cached yet, do it now.
			if (!file_exists($cache_file) || $mtime > $last_modified) {
				$data = "";
				
				if (is_array(Router::$Config["css"]["files"][$css_file])) {
					// if we need LESS
					if (strpos(implode(" ", Router::$Config["css"]["files"][$css_file]), "less") > -1) {
						require_once SERVER_ROOT."core/inc/lib/less.php/lib/Less/Autoloader.php";
						\Less_Autoloader::register();
					}
					
					foreach (Router::$Config["css"]["files"][$css_file] as $style_file) {
						if (strpos($style_file, "less") > -1) {
							// LESS
							$less_compiler = new Less_Parser;
							$less_compiler->parseFile(SITE_ROOT."css/".$style_file);
							$style = $less_compiler->getCss();
						} else {
							$style = file_get_contents(SITE_ROOT."css/$style_file");
						}
						
						$data .= $style."\n";
					}
				}
				
				// Should only loop once, not with every file
				if (is_array(Router::$Config["css"]["vars"])) {
					foreach (Router::$Config["css"]["vars"] as $key => $val) {
						$data = str_replace('$'.$key, $val, $data);
					}
				}
				
				// Replace roots
				$data = str_replace(array('$www_root', 'www_root/', '$static_root', 'static_root/', '$admin_root/', 'admin_root/'), array(WWW_ROOT, WWW_ROOT, STATIC_ROOT, STATIC_ROOT, ADMIN_ROOT, ADMIN_ROOT), $data);
				
				// Minify a little bit if requested
				if (Router::$Config["css"]["minify"]) {
					// Courtesy of http://www.lateralcode.com/css-minifier/
					$data = preg_replace('#\s+#', ' ', $data);
					$data = preg_replace('#/\*.*?\*/#s', '', $data);
					$data = str_replace("; ", ";", $data);
					$data = str_replace(": ", ":", $data);
					$data = str_replace(" {", "{", $data);
					$data = str_replace("{ ", "{", $data);
					$data = str_replace(", ", ",", $data);
					$data = str_replace("} ", "}", $data);
					$data = str_replace(";}", "}", $data);
					$data = trim($data);
				}
				
				// Cache
				FileSystem::createFile($cache_file, $data);
				
				// Return
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
					readfile($cache_file);
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
	
	// Start output buffering and sessions
	ob_start();
	SessionHandler::start();
	
	// Check to see if we're in maintenance mode
	if (Router::$Config["maintenance_url"] && (empty($_SESSION["bigtree_admin"]["level"]) || $_SESSION["bigtree_admin"]["level"] < 2)) {
		// See if we're at the URL
		if (implode("/", Router::$Path) != trim(str_replace(WWW_ROOT, "", Router::$Config["maintenance_url"]), "/")) {
			$_SESSION["bigtree_referring_url"] = DOMAIN.$_SERVER["REQUEST_URI"];
			Router::redirect(Router::$Config["maintenance_url"], "307");
		} else {
			header("X-Robots-Tag: noindex");
			include SERVER_ROOT."templates/basic/_maintenance.php";
			$bigtree["content"] = ob_get_clean();
			include SERVER_ROOT."templates/layouts/".($bigtree["layout"] ? $bigtree["layout"] : "default").".php";
			die();
		}
	}
	
	// Handle AJAX calls.
	if (Router::$Path[0] == "ajax" || (Router::$Path[0] == "*" && Router::$Path[2] == "ajax")) {
		if (Router::$Path[0] == "*") {
			$bigtree["extension_context"] = Router::$Path[1];
			define("EXTENSION_ROOT", SERVER_ROOT."extensions/".Router::$Path[1]."/");
			Router::run("extensions/".Router::$Path[1]."/templates/ajax/", array_slice(Router::$Path, 3));
		} else {
			Router::run("templates/ajax/", array_slice(Router::$Path, 1));
		}
		
		die();
	}
	
	// API
	if (Router::$Path[0] == "api") {
		Router::run("core/api/", array_slice(Router::$Path, 1));
		die();
	}
	
	// Sitemap setup
	if (Router::$Path[0] == "sitemap.xml") {
		header("Content-type: text/xml");
		Sitemap::drawXML();
		die();
	}
	
	// Tell the browser we're serving HTML
	header("Content-type: text/html");
	
	// See if we're previewing changes.
	$bigtree["preview"] = false;
	$navid = false;
	
	if (Router::$Path[0] == "_preview" && $_SESSION["bigtree_admin"]["id"]) {
		$npath = array();
		
		foreach (Router::$Path as $item) {
			if ($item != "_preview") {
				$npath[] = $item;
			}
		}
		
		Router::$Path = $npath;
		$bigtree["preview"] = true;
		Router::$Config["cache"] = false;
		header("X-Robots-Tag: noindex");
		
		// Clean up
		unset($npath);
	}
	
	if (Router::$Path[0] == "_preview-pending" && $_SESSION["bigtree_admin"]["id"]) {
		$bigtree["preview"] = true;
		Router::$Commands = [];
		$navid = Router::$Path[1];
		
		define("BIGTREE_PREVIEWING_PENDING", true);
		header("X-Robots-Tag: noindex");
	}
	
	// So we don't lose this.
	define("BIGTREE_PREVIEWING", $bigtree["preview"]);
	
	if (Router::$Path[0] == "feeds") {
		$route = Router::$Path[1];
		$feed = Feed::getByRoute($route);
		
		if ($feed) {
			if ($feed->Type != "json") {
				header("Content-type: text/xml");
				echo '<?xml version="1.0" encoding="UTF-8" ?>';
			}
			
			include Router::getIncludePath("feeds/".$feed->Type.".php");
			die();
		}
	}
	
	// Check route registry if we're not previewing
	$registry_found = $registry_rule = $registry_commands = false;

	if (!$navid) {
		foreach (Router::$Registry["public"] as $registration) {
			if (!$registry_found) {
				$registry_commands = Router::getRegistryCommands("/".implode("/", Router::$Path), $registration["pattern"]);
				
				if ($registry_commands !== false) {
					$registry_found = true;
					$registry_rule = $registration;
				}
			}
		}
	}
	
	// Not in route registry, check BigTree pages
	if (!$registry_found) {
		list($navid, Router::$Commands, $routed) = Router::routeToPage(Router::$Path, $bigtree["preview"]);
	}
	
	// Pre-init a bunch of vars to keep away notices.
	$bigtree["layout"] = "default";
	
	// Loading a route registry entry
	if ($registry_found) {
		if ($registry_rule["file"]) {
			
			// Emulate commands at indexes as well as with requested variable keys
			Router::$Commands = [];
			$x = 0;
			
			foreach ($registry_commands as $key => $value) {
				Router::$Commands[$x] = Router::$Commands[$key] = $value;
				$x++;
			}
			
			Router::$PrimaryFile = $registry_rule["file"];
			Router::setRoutedLayoutPartials();
			
			// Draw the headers.
			foreach (Router::$HeaderFiles as $header) {
				include $header;
			}
			
			// Draw the main page.
			include Router::$PrimaryFile;
			
			// Draw the footers.
			foreach (Router::$FooterFiles as $footer) {
				include $footer;
			}
			
		} elseif ($registry_rule["function"]) {
			call_user_func_array($registry_rule["function"], $registry_commands);
		}
	// Nav ID found means we're loading a page
	} elseif ($navid !== false) {
		// If we're previewing, get pending data as well.
		if ($bigtree["preview"]) {
			$page = Page::getPageDraft($navid);
			$bigtree["page"] = $page->Array;
			
			// If we're previewing pending changes, the template's routed-ness may have changed.
			$template = new Template($bigtree["page"]["template"]);
			$routed = $template->Routed;
		} else {
			$page = new Page($navid);
			$bigtree["page"] = $page->Array;
		}
		
		Router::$CurrentPage = $page;

		// If we're in multi-site and the path contains a different site, 301 away
		if (defined("BIGTREE_SITE_KEY")) {
			foreach (Router::$SiteRoots as $site_path => $site_data) {
				if ($site_path == BIGTREE_SITE_PATH && (!$site_path || strpos($bigtree["page"]["path"], $site_path) === 0)) {
					break;
				}
				
				if ($site_path == "" || strpos($bigtree["page"]["path"], $site_path) === 0) {
					if ($site_path) {
						$bigtree["page"]["path"] = substr($bigtree["page"]["path"], strlen($site_path));
					}
					
					if (Router::$Config["trailing_slash_behavior"] == "remove") {
						Router::redirect($site_data["domain"].$bigtree["page"]["path"], "301");
					}
					
					Router::redirect($site_data["domain"].$bigtree["page"]["path"]."/", "301");
				}
			}
		}
		
		$bigtree["page"]["link"] = WWW_ROOT.$bigtree["page"]["path"]."/";
		$bigtree["resources"] = $bigtree["page"]["resources"];
		$bigtree["callouts"] = $bigtree["page"]["callouts"];
		
		// If this page should not be indexed, pass headers
		if ($bigtree["page"]["seo_invisible"]) {
			header("X-Robots-Tag: noindex");
		}
		
		// Quick access to resources
		if (is_array($bigtree["resources"])) {
			foreach ($bigtree["resources"] as $key => $val) {
				if (substr($key, 0, 1) != "_" && $key != "bigtree") { // Don't allow for SESSION or COOKIE injection and don't overwrite $bigtree
					$$key = $bigtree["resources"][$key];
				}
			}
		}
		
		// Redirect lower if the template is !
		if ($bigtree["page"]["template"] == "!") {
			Router::redirectLower($page);
		}
		
		// Setup extension handler for templates
		if (strpos($bigtree["page"]["template"], "*") !== false) {
			list($extension, $extension_template) = explode("*", $bigtree["page"]["template"]);
			
			$bigtree["extension_context"] = $extension;
			define("EXTENSION_ROOT", SERVER_ROOT."extensions/$extension/");
		} else {
			$extension = false;
		}
		
		// If the template is a module, do its routing for it, otherwise just include the template.
		if ($routed) {
			// See if a module has hooked this template routing
			$registry_found = false;
			
			foreach (Router::$Registry["template"] as $registration) {
				if ($registration["template"] == $bigtree["page"]["template"]) {
					$registry_commands = Router::getRegistryCommands(implode("/", Router::$Commands), $registration["pattern"]);
					
					if ($registry_commands !== false) {
						$registry_found = true;
						$registry_rule = $registration;
					}
				}
			}
			
			// Module successfully grabbed the routing
			if ($registry_found) {
				// Emulate commands at indexes as well as with requested variable keys
				Router::$Commands = [];
				$x = 0;
				
				foreach ($registry_commands as $key => $value) {
					Router::$Commands[$x] = Router::$Commands[$key] = $value;
					$x++;
				}
				
				// Set the include file
				$bigtree["routed_inc"] = $inc = SERVER_ROOT."templates/routed/".$bigtree["page"]["template"]."/".ltrim($registry_rule["file"], "/");
				
			// Use BigTree's routing to find the page
			} else {
				// Allow the homepage to be routed
				if (!$bigtree["page"]["path"]) {
					Router::$Commands = Router::$Path;
				}

				if ($extension) {
					Router::setRoutedFileAndCommands(SERVER_ROOT."extensions/$extension/templates/routed/$extension_template/", array_filter(Router::$Commands));
				} else {
					Router::setRoutedFileAndCommands(SERVER_ROOT."templates/routed/".$bigtree["page"]["template"]."/", array_filter(Router::$Commands));
				}

				$command_count = count(Router::$Commands);

				if ($command_count) {
					$bigtree["routed_path"] = array_slice(Router::$Commands, 0, $command_count * -1);
				} else {
					$bigtree["routed_path"] = Router::$Commands;
				}
			}
			
			Router::setRoutedLayoutPartials();
			
			foreach (Router::$HeaderFiles as $header) {
				include $header;
			}
			
			include Router::$PrimaryFile;
			
			foreach (Router::$FooterFiles as $footer) {
				include $footer;
			}
		} elseif ($bigtree["page"]["template"]) {
			if ($extension) {
				include SERVER_ROOT."extensions/$extension/templates/basic/$extension_template.php";
			} else {
				include SERVER_ROOT."templates/basic/".$bigtree["page"]["template"].".php";
			}
		} else {
			Router::redirect($bigtree["page"]["external"]);
		}
	// Check for standard sitemap
	} else if (Router::$Path[0] == "sitemap" && !Router::$Path[1]) {
		include SERVER_ROOT."templates/basic/_sitemap.php";
	// We've got a 404, check for old routes or throw one.
	} else {
		// Let's check if it's in the old routing table.
		Router::checkPathHistory(Router::$Path);
		
		// It's not, it's a 404.
		if (Redirect::handle404($_GET["bigtree_htaccess_url"])) {
			include SERVER_ROOT."templates/basic/_404.php";
		}
	}
	
	// If we have a specific URL trailing slash behavior specified, ensure it's applied to the current request
	if (array_filter(Router::$Path)) {
		// Prevent notices before output buffering
		if (empty(Router::$Config["trailing_slash_behavior"])) {
			Router::$Config["trailing_slash_behavior"] = "";
		}
		
		$last_path_element = Router::$Path[count(Router::$Path) - 1];
		
		// If this is a "file", ignore the fact that there is or isn't a trailing slash
		if (strpos($last_path_element, ".") === false) {
			if (strtolower(Router::$Config["trailing_slash_behavior"]) == "append" && !$bigtree["trailing_slash_present"]) {
				Router::redirect(WWW_ROOT.implode(Router::$Path, "/")."/", "301");
			} elseif (strtolower(Router::$Config["trailing_slash_behavior"]) == "remove" && $bigtree["trailing_slash_present"]) {
				Router::redirect(WWW_ROOT.implode(Router::$Path, "/"), "301");
			}
		}
	}
	
	$bigtree["content"] = ob_get_clean();
	
	// Load the content again into the layout.
	ob_start();
	
	if ($bigtree["extension_layout"]) {
		include SERVER_ROOT."extensions/".$bigtree["extension_layout"]."/templates/layouts/".$bigtree["layout"].".php";
	} else {
		include SERVER_ROOT."templates/layouts/".$bigtree["layout"].".php";
	}
	
	$bigtree["content"] = ob_get_clean();
	
	// Allow for special output filter functions.
	$filter = null;
	
	if (Router::$Config["output_filter"]) {
		$filter = Router::$Config["output_filter"];
	}
	
	ob_start($filter);
	
	// If we're in HTTPS, make sure all Javascript, images, and CSS are pulling from HTTPS
	if (Router::$Secure) {
		// Replace CSS includes
		$secure_replace_callback = function ($matches) {
			return str_replace('href="http://', 'href="https://', $matches[0]);
		};
		$bigtree["content"] = preg_replace_callback('/<link [^>]*href="([^"]*)"/', $secure_replace_callback, $bigtree["content"]);
		
		// Replace script and image tags.
		$bigtree["content"] = str_replace('src="http://', 'src="https://', $bigtree["content"]);
		
		// Replace inline background images
		$bigtree["content"] = preg_replace(
			array("/url\('http:\/\//", '/url\("http:\/\//', '/url\(http:\/\//'),
			array("url('https://", 'url("https://', "url(https://"),
			$bigtree["content"]
		);
	}
	
	// Load the BigTree toolbar if you're logged in to the admin via cookies but not yet via session.
	if (isset($bigtree["page"]) && isset($_COOKIE["bigtree_admin"]["email"]) && !$_SESSION["bigtree_admin"]["id"]) {
		include_once Router::getIncludePath("inc/bigtree/admin.php");
		
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
	   - User is logged BigTree admin
	   - User is logged into the BigTree admin FOR THIS PAGE
	   - Developer mode is either disabled OR the logged in user is a Developer
	*/
	if ($_SESSION["bigtree_admin"]["id"] &&
		$_COOKIE["bigtree_admin"]["email"] &&
		(empty(Router::$Config["developer_mode"]) || $_SESSION["bigtree_admin"]["level"] > 1)
	) {
		$show_bar_default = $_COOKIE["hide_bigtree_bar"] ? false : true;
		$show_preview_bar = false;
		$return_link = "";
		$bar_edit_link = "";
		
		if (!empty($_GET["bigtree_preview_return"])) {
			$show_bar_default = false;
			$show_preview_bar = true;
			$return_link = Text::htmlEncode(urlencode($_GET["bigtree_preview_return"]));
		}
		
		if (!empty($bigtree["bar_edit_link"])) {
			$bar_edit_link_query = parse_url($bigtree["bar_edit_link"], PHP_URL_QUERY);
			
			if (!empty($bar_edit_link_query)) {
				$bar_edit_link_query_parts = explode("&", $bar_edit_link_query);
				$has_return_link = false;
				
				foreach ($bar_edit_link_query_parts as $bar_edit_link_query_part) {
					list($bar_edit_link_query_param, $bar_edit_link_query_value) = explode("=", $bar_edit_link_query_part);
					
					if (strtolower($bar_edit_link_query_param) == "return_link") {
						$has_return_link = true;
					}
				}
				
				if (!$has_return_link) {
					$bigtree["bar_edit_link"] .= "&return_link=".Text::htmlEncode(urlencode(Link::currentURL()));
				}
			} else {
				$bigtree["bar_edit_link"] .= "?return_link=".Text::htmlEncode(urlencode(Link::currentURL()));
			}
			
			$bar_edit_link = Text::htmlEncode(urlencode($bigtree["bar_edit_link"]));
		}
		
		// Pending Pages don't have their ID set.
		if (!isset($bigtree["page"]["id"])) {
			$bigtree["page"]["id"] = $bigtree["page"]["page"];
		}
		
		if (defined("BIGTREE_URL_IS_404")) {
			$bigtree["content"] = str_ireplace('</body>','<script type="text/javascript" src="'.str_replace(array("http://","https://"),"//",Router::$Config["admin_root"]).'ajax/bar.js/?show_bar='.$show_bar_default.'&amp;username='.$_SESSION["bigtree_admin"]["name"].'&amp;is_404=true"></script></body>',$bigtree["content"]);
		} else {
			$bigtree["content"] = str_ireplace('</body>','<script type="text/javascript" src="'.str_replace(array("http://","https://"),"//",Router::$Config["admin_root"]).'ajax/bar.js/?previewing='.BIGTREE_PREVIEWING.'&amp;current_page_id='.$bigtree["page"]["id"].'&amp;show_bar='.$show_bar_default.'&amp;username='.$_SESSION["bigtree_admin"]["name"].'&amp;show_preview='.$show_preview_bar.'&amp;return_link='.$return_link.'&amp;custom_edit_link='.$bar_edit_link.'"></script></body>',$bigtree["content"]);
		}
		
		// Don't cache the page with the BigTree bar
		Router::$Config["cache"] = false;
	}
	
	echo $bigtree["content"];
	
	// Write to the cache
	if (Router::$Config["cache"] && !defined("BIGTREE_DO_NOT_CACHE") && !count($_POST)) {
		$cache = ob_get_flush();
		
		if (!$bigtree["page"]["path"]) {
			$bigtree["page"]["path"] = "!";
		}
		
		FileSystem::createFile(BIGTREE_CACHE_DIRECTORY.md5(json_encode($_GET)).".page", $cache);
	}
