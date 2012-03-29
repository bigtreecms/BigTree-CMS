<?
	// Handle Javascript Minifying and Caching
	if ($path[0] == "js") {
		clearstatcache();
		// Get the latest mod time on any included js files.
		$mtime = 0;
		$js_file = str_replace(".js","",$path[1]);
		$cfile = $server_root."cache/".$js_file.".js";
		$last_modified = file_exists($cfile) ? filemtime($cfile) : 0;
		if (is_array($config["js"]["files"][$js_file])) {
			foreach ($config["js"]["files"][$js_file] as $script) {
				$m = file_exists($site_root."js/$script") ? filemtime($site_root."js/$script") : 0;
				if ($m > $mtime) {
					$mtime = $m;
				}
			}
		}
		// If we have a newer Javascript file to include or we haven't cached yet, do it now.
		if (!file_exists($cfile) || $mtime > $last_modified) {
			$data = "";
			if (is_array($config["js"]["files"][$js_file])) {
				foreach ($config["js"]["files"][$js_file] as $script) {
					$data .= file_get_contents($site_root."js/$script")."\n";
				}
			}
			// Replace www_root/ and Minify
			$data = str_replace(array('$www_root',"www_root/"),$www_root,$data);
			if (is_array($_GET)) {
				foreach ($_GET as $key => $val) {
					if ($key != "bigtree_htaccess_url") {
						$data = str_replace('$'.$key,$val,$data);
					}
				}
			}
			if (is_array($config["js"]["vars"])) {
				foreach ($config["js"]["vars"] as $key => $val) {
					$data = str_replace('$'.$key,$val,$data);
				}
			}
			if ($config["js"]["minify"]) {
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
	if ($path[0] == "css") {
		clearstatcache();
		// Get the latest mod time on any included css files.
		$mtime = 0;
		$css_file = str_replace(".css","",$path[1]);
		$cfile = $server_root."cache/".$css_file.".css";
		$last_modified = file_exists($cfile) ? filemtime($cfile) : 0;
		if (is_array($config["css"]["files"][$css_file])) {
			foreach ($config["css"]["files"][$css_file] as $style) {
				$m = (file_exists($site_root."css/$style")) ? filemtime($site_root."css/$style") : 0;
				if ($m > $mtime) {
					$mtime = $m;
				}
			}
		}
		// If we have a newer CSS file to include or we haven't cached yet, do it now.
		if (!file_exists($cfile) || $mtime > $last_modified) {
			$data = "";
			if (is_array($config["css"]["files"][$css_file])) {
				// if we need LESS
				if (strpos(implode(" ", $config["css"]["files"][$css_file]), "less") > -1) {
					require_once($server_root."core/inc/utils/less-compiler.inc.php");
					$less_compiler = new lessc();
				}
				foreach ($config["css"]["files"][$css_file] as $style_file) {
					$style = file_get_contents($site_root."css/$style_file");
					if (strpos($style_file, "less") > -1) {
						// convert LESS
						$style = $less_compiler->parse($style);
					} else {
						// normal CSS
						if ($config["css"]["prefix"]) {
							// Replace CSS3 easymode
							$style = BigTree::formatCSS3($style);
						}
					}
					$data .= $style."\n";
				}
			}
			// Should only loop once, not with every file
			if (is_array($config["css"]["vars"])) {
				foreach ($config["css"]["vars"] as $key => $val) {
					$data = str_replace('$'.$key,$val,$data);
				}
			}
			if ($config["css"]["minify"]) {
				require_once($server_root."core/inc/utils/CSSMin.php");			
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
	if ($path[0] == "ajax") {
		bigtree_setup_sql_connection();
		$x = 1;
		$ajpath = "";
		while ($x < count($path) - 1) {
			$ajpath .= $path[$x]."/";
			$x++;
		}
		if (file_exists("../templates/ajax/".$ajpath.$path[$x].".php")) {
			include "../templates/ajax/".$ajpath.$path[$x].".php";
		} else {
			$inc = "../templates/ajax/".$path[1]."/";
			$inc_dir = $inc;
			$x = 1;
			$y = 1;
			while ($x < count($path)) {
				if (is_dir($inc.$path[$x])) {
					$inc .= $path[$x]."/";
					$inc_dir .= $path[$x]."/";
					$y++;
				} elseif (file_exists($inc.$path[$x].".php")) {
					$inc .= $path[$x].".php";
					$y++;
				}
				$x++;
			}
			if (substr($inc,-4,4) != ".php") {
				if (file_exists($inc.end($path).".php")) {
					$inc .= end($path).".php";
				} else {
					$inc .= "default.php";
				}
			}
			$commands = array_slice($path,$y+1);
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
	$preview = false;
	if ($path[0] == "_preview" && $_SESSION["bigtree"]["id"]) {
		$npath = array();
		foreach ($path as $item) {
			if ($item != "_preview") {
				$npath[] = $item;
			}
		}
		$path = $npath;
		$preview = true;
		$config["cache"] = false;
	}
	
	if ($path[0] == "_preview-pending" && $_SESSION["bigtree"]["id"]) {
		$preview = true;
		$commands = array();
		$navid = $path[1];
	}
	
	// So we don't lose this.
	define("BIGTREE_PREVIEWING",$preview);
	
	// Sitemap setup
	$sitemap = false;
	if ($path[0] == "sitemap") {
		$sitemap = true;
	}
	if ($path[0] == "sitemap.xml") {
		$cms->drawXMLSitemap();
	}
	if ($path[0] == "feeds") {
		$route = $path[1];
		$feed = $cms->getFeedByRoute($route);
		if ($feed) {
			header("Content-type: text/xml");
			echo '<?xml version="1.0"?>';
			include BigTree::path("feeds/".$feed["type"].".php");
			die();
		}
	}
	
	if (!$navid) {
		list($navid,$commands,$routed) = $cms->getNavId($path);
	}
	
	// Pre-init a bunch of vars to keep away notices.
	$module_title = "";
	$css = array();
	$js = array();
	$layout = "default";
	if ($navid) {
		// If we're previewing, get pending data as well.
		if ($preview) {
			$page = $cms->getPendingPage($navid);
		} else {
			$page = $cms->getPage($navid);
		}
			
		$resources = $page["resources"];
		$callouts = $page["callouts"];

		// Quick access to resources
		if (is_array($resources)) {
			foreach ($resources as $key => $val) {
				if (substr($key,0,1) != "_") {
					$$key = &$resources[$key];
				}
			}
		}
				
		// Redirect lower if the template is !
		if ($page["template"] == "!") {
			$nav = $cms->getNavByParent($page["id"],1);
			$first = current($nav);
			header("Location: ".$first["link"]);
			die();
		}
		
		// If the template is a module, do its routing for it, otherwise just include the template.
		if ($routed) {
			// We need to figure out how far down the directory structure to route the,.	
			$inc = "../templates/routed/".$page["template"]."/";
			$inc_dir = $inc;
			$module_commands = array();
			$ended = false;
			foreach ($commands as $command) {
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
			
			$commands = $module_commands;
			
			// Include the module's header
			if (file_exists("../templates/routed/".substr($page["template"],7)."/_header.php")) {
				include_once "../templates/routed/".substr($page["template"],7)."/_header.php";
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
			if (file_exists("../templates/routed/".substr($page["template"],7)."/_footer.php")) {
				include_once "../templates/routed/".substr($page["template"],7)."/_footer.php";
			}

		} elseif ($page["template"]) {
			include "../templates/basic/".$page["template"].".php";
		} else {
			header("Location: ".$page["external"]);
		}
	} elseif (!$_GET["bigtree_htaccess_url"] || empty($path[0])) {
		$page = $cms->getPage(0);
		
		$resources = $page["resources"];
		$callouts = $page["callouts"];

		// Quick access to resources
		if (is_array($resources)) {
			foreach ($resources as $key => $val) {
				if (substr($key,0,1) != "_") {
					$$key = &$resources[$key];
				}
			}
		}
		
		include "../templates/basic/".$page["template"].".php";
	} elseif ($sitemap) {
		include "../templates/basic/_sitemap.php";
	} else {
		// Let's check if it's in the old routing table.
		$cms->checkOldRoutes($path);
		// It's not, it's a 404.
		$cms->handle404($_GET["bigtree_htaccess_url"]);		
	}
	
	$content = ob_get_clean();
	
	// Load the content again into the layout.
	ob_start();
	include "../templates/layouts/$layout.php";
	$content = ob_get_clean();
	
	// Allow for special output filter functions.
	$filter = false;
	if ($config["output_filter"]) {
		$filter = $config["output_filter"];
	}
	
	ob_start($filter);
	
	// If we're in HTTPS, make sure all Javascript, images, and CSS are pulling from HTTPS
	if ($cms->Secure) {
		$content = str_replace(array('src="http://','link href="http://'),array('src="https://','link href="https://'),$content);
	}
	
	// Load the BigTree toolbar if you're logged in to the admin.
	if ($page["id"] && !$cms->Secure && isset($_COOKIE["bigtree"]["email"]) && !$_SESSION["bigtree"]["id"]) {
		include BigTree::path("inc/bigtree/admin.php");

		if (BIGTREE_CUSTOM_ADMIN_CLASS) {
			eval('$admin = new '.BIGTREE_CUSTOM_ADMIN_CLASS.';');
		} else {
			$admin = new BigTreeAdmin;
		}
	}
	
	if (isset($page) && $_SESSION["bigtree"]["id"] && !$cms->Secure) {
		$show_bar_default = $_COOKIE["hide_bigtree_bar"] ? "false" : "true";
		$show_preview_bar = "false";
		$return_link = "";
		if ($_GET["bigtree_preview_bar"]) {
			$show_bar_default = "false";
			$show_preview_bar = "true";
			$return_link = $_SERVER["HTTP_REFERER"];
		}
				
		$content = str_replace('</body>','<script type="text/javascript">var bigtree_is_previewing = '.(BIGTREE_PREVIEWING ? "true" : "false").'; var bigtree_current_page_id = '.$page["id"].'; var bigtree_bar_show = '.$show_bar_default.'; var bigtree_user_name = "'.$_SESSION["bigtree"]["name"].'"; var bigtree_preview_bar_show = '.$show_preview_bar.'; var bigtree_return_link = "'.$return_link.'";</script><script type="text/javascript" src="'.$config["admin_root"].'js/bar.js"></script></body>',$content);
		$nocache = true;
	}
	
	echo $content;
	
	// Write to the cache
	if ($config["cache"] && !defined("BIGTREE_DO_NOT_CACHE")) {
		$cache = ob_get_flush();
		$curl = $_GET["bigtree_htaccess_url"];
		if (!$curl) {
			$curl = "home";
		}
		file_put_contents("../cache/".base64_encode($curl),$cache);
	}
?>