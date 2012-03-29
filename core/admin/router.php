<?
	// BigTree Version
	$GLOBALS["bigtree"]["version"] = "4.0b1";		
	$GLOBALS["wiki"] = "http://wiki.bigtreecms.com/index.php/";
	
	// If they're requesting images, css, or js, just give it to them.
	if ($path[1] == "images") {
		$x = 2;
		$ipath = "";
		while ($x < count($path) - 1) {
			$ipath .= $path[$x]."/";
			$x++;
		}
		
		$ifile = (file_exists("../custom/admin/images/".$ipath.$path[$x])) ? "../custom/admin/images/".$ipath.$path[$x] : "../core/admin/images/".$ipath.$path[$x];
		
		if (function_exists("apache_request_headers")) {
			$headers = apache_request_headers();
			$ims = $headers["If-Modified-Since"];
		} else {
			$ims = $_SERVER["HTTP_IF_MODIFIED_SINCE"];
		}
		
		$last_modified = filemtime($ifile);
		if ($ims && strtotime($ims) == $last_modified) {
			header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified).' GMT', true, 304);
			die();
		}
		$type = explode(".",$path[$x]);
		$type = strtolower($type[count($type)-1]);
		if ($type == "gif") {
			header("Content-type: image/gif");
		} elseif ($type == "jpg") {
			header("Content-type: image/jpeg");
		} elseif ($type == "png") {
			header("Content-type: image/png");
		}
		
		echo file_get_contents($ifile);
		header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified).' GMT', true, 200);
		die();
	}
	
	if ($path[1] == "css") {
		if (file_exists("../custom/inc/bigtree/utils.php")) {
			include "../custom/inc/bigtree/utils.php";		
		} else {
			include "../core/inc/bigtree/utils.php";
		}
		$x = 2;
		$ipath = "";
		while ($x < count($path) - 1) {
			$ipath .= $path[$x]."/";
			$x++;
		}
		
		$ifile = (file_exists("../custom/admin/css/".$ipath.$path[$x])) ? "../custom/admin/css/".$ipath.$path[$x] : "../core/admin/css/".$ipath.$path[$x];
		
		if (function_exists("apache_request_headers")) {
			$headers = apache_request_headers();
			$ims = $headers["If-Modified-Since"];
		} else {
			$ims = $_SERVER["HTTP_IF_MODIFIED_SINCE"];
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
	
	if ($path[1] == "js") {
		include "../templates/config.php";
		
		$pms = ini_get('post_max_size');
		$mul = substr($pms,-1);
		$mul = ($mul == 'M' ? 1048576 : ($mul == 'K' ? 1024 : ($mul == 'G' ? 1073741824 : 1)));
		$max_file_size = $mul * (int)$pms;
		
		$x = 2;
		$ipath = "";
		while ($x < count($path) - 1) {
			$ipath .= $path[$x]."/";
			$x++;
		}
		
		$ifile = (file_exists("../custom/admin/js/".$ipath.$path[$x])) ? "../custom/admin/js/".$ipath.$path[$x] : "../core/admin/js/".$ipath.$path[$x];
		
		if (substr($ifile,-4,4) == ".php") {
			include $ifile;
			die();
		}
		
		if (function_exists("apache_request_headers")) {
			$headers = apache_request_headers();
			$ims = $headers["If-Modified-Since"];
		} else {
			$ims = $_SERVER["HTTP_IF_MODIFIED_SINCE"];
		}
		
		$last_modified = filemtime($ifile);
		if ($ims && strtotime($ims) == $last_modified) {
			header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified).' GMT', true, 304);
			die();
		}
		if (substr($path[$x],-3,3) == "css") {
			header("Content-type: text/css");
		} elseif (substr($path[$x],-3,3) == "htm" || substr($path[$x],-4,4) == "html") {
			header("Content-type: text/html");
		} else {
			header("Content-type: text/javascript");
		}
		header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified).' GMT', true, 200);
		
		echo str_replace(array("{max_file_size}","www_root/","admin_root/"),array($max_file_size,$config["www_root"],$config["admin_root"]),file_get_contents($ifile));
		die();
	}
	
	// Otherwise start the admin routing
	
	if (file_exists("../custom/bootstrap.php")) {
		include "../custom/bootstrap.php";
	} else {
		include "../core/bootstrap.php";
	}
	$GLOBALS["admin_root"] = $config["admin_root"];
	bigtree_setup_sql_connection();
	ob_start();
	session_start();
	include BigTree::path("inc/bigtree/admin.php");
	include BigTree::path("inc/bigtree/auto-modules.php");
	
	if (BIGTREE_CUSTOM_ADMIN_CLASS) {
		eval('$admin = new '.BIGTREE_CUSTOM_ADMIN_CLASS.';');
	} else {
		$admin = new BigTreeAdmin;
	}
		
	if (!isset($path[1])) {
		$path[1] = "";
	}
	
	$css = array();
	$js = array();
	$layout = "default";
	if (!$admin->ID && $path[1] != "login") {
		header("Location: ".$admin_root."login/");
		die();
	} else {
		// We're logged in, let's go somewhere.
		if (!$path[1]) {
			header("Location: ".$admin_root."dashboard/");
			die();
		// We're hitting an ajax page.
		} elseif ($path[1] == "ajax") {
			$x = 2;
			$ajpath = "";
			while ($x < count($path) - 1) {
				$ajpath .= $path[$x]."/";
				$x++;
			}
			
			// Permissions!
			$module = $admin->getModuleByRoute($path[2]);
			if ($module && !$admin->checkAccess($module["id"])) {
				include BigTree::path("admin/ajax/login.php");
				die();
			}

			$autoModule = new BigTreeAutoModule;
			
			$path[$x] = str_replace(".php","",$path[$x]);

			include BigTree::path("admin/ajax/".$ajpath.$path[$x].".php");
			die();
		// We've actually chosen a section now.
		} else {
			$ispage = false;
			$inc = false;
			// Check if it's a module or a normal page.
			if (is_dir("../custom/admin/modules/".$path[1])) {
				if (!isset($path[2])) {
					$inc = "../custom/admin/modules/".$path[1]."/default.php";
				} else {
					$inc = "../custom/admin/modules/".$path[1]."/";
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
				}
			}
			if (($inc && !file_exists($inc)) || (!$inc && is_dir("../core/admin/modules/".$path[1]))) {
				if (!isset($path[2])) {
					$inc = "../core/admin/modules/".$path[1]."/default.php";
				} else {
					$inc = "../core/admin/modules/".$path[1]."/";
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
				}
			// It's a normal page.
			} elseif (!$inc) {
				if (file_exists("../custom/admin/pages/".$path[1].".php")) {
					$inc = "../custom/admin/pages/".$path[1].".php";
				} elseif (file_exists("../core/admin/pages/".$path[1].".php")) {
					$inc = "../core/admin/pages/".$path[1].".php";
				}
				$ispage = true;
			}
			
			// Permissions!
			if (!$ispage || !$inc) {
				$module = $admin->getModuleByRoute($path[1]);
				$module_title = $module["name"];
				if ($module && !$admin->checkAccess($module["id"])) {
					ob_clean();
					include BigTree::path("admin/pages/_denied.php");
					$content = ob_get_clean();
					include BigTree::path("admin/layouts/".$layout.".php");
					die();
				}
			}
			
			// Ok, if this inc is real, let's include it -- otherwise see if it's an auto-module action.
			if (isset($path[1])) {
				$module = $admin->getModuleByRoute($path[1]);
			}
			if (!isset($path[2])) {
				$path[2] = "";
			}
			
			$action = $admin->getModuleActionByRoute($module["id"],$path[2]);
			
			$inc_dir = str_replace("../",$server_root,$inc_dir);
			
			if ($module && ($action["view"] || $action["form"])) {
				if ($action["form"]) {
					$edit_id = isset($path[3]) ? $path[3] : "";
					include BigTree::path("admin/auto-modules/form.php");
				} else {
					include BigTree::path("admin/auto-modules/view.php");
				}
			} elseif (file_exists($inc)) {
				// Include the top level module header.
				if (!$ispage && file_exists(BigTree::path("admin/modules/".$path[1]."/_header.php"))) {
					include BigTree::path("admin/modules/".$path[1]."/_header.php");
				}
				
				// Include the routed directory's module header if it's not the same one.
				if (!$ispage && file_exists($inc_dir."_header.php") && BigTree::path("admin/modules/".$path[1]."/_header.php") != ($inc_dir."_header.php")) {
					include $inc_dir."_header.php";
				}
				
				// Include the routed file.
				include $inc;
				
				// Include the routed directory's footer if it's not the same as the top level footer.
				if (!$ispage && file_exists($inc_dir."_footer.php") && BigTree::path("admin/modules/".$path[1]."/_footer.php") != ($inc_dir."_footer.php")) {
					include $inc_dir."_footer.php";
				}
				
				// Include the top level module footer.
				if (!$ispage && file_exists(BigTree::path("admin/modules/".$path[1]."/_footer.php"))) {
					include BigTree::path("admin/modules/".$path[1]."/_footer.php");
				}
			} else {
				include BigTree::path("admin/pages/_404.php");
			}
		}
	}
	
	$content = ob_get_clean();
	
	include BigTree::path("admin/layouts/".$layout.".php");
	
	// Execute cron tab functions if they haven't been run in 24 hours
	if (!$admin->settingExists("bigtree-internal-cron-last-run")) {
		$admin->createSetting(array(
			"id" => "bigtree-internal-cron-last-run",
			"system" => "on"
		));
	}
	
	$last_check = strtotime($cms->getSetting("bigtree-internal-cron-last-run"));
	// It's been more than 24 hours since we last ran cron.
	if ((time() - $last_check) < (24 * 60 * 60)) {
		// Email the daily digest
		$admin->emailDailyDigest();
		// Cache google analytics
		$ga = new BigTreeGoogleAnalytics;
		if ($ga->AuthToken) {
			$ga->cacheInformation();
		}
		// Update the setting.
		$admin->updateSettingValue("bigtree-internal-cron-last-run",time());	
	}
?>