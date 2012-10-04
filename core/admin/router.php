<?
	// BigTree Version
	define("BIGTREE_VERSION","4.0RC2");
	define("BIGTREE_REVISION",12);

	// Set static root for those without it
	if (!isset($bigtree["config"]["static_root"])) {
		$bigtree["config"]["static_root"] = $bigtree["config"]["www_root"];
	}

	// Make sure no notice gets thrown for $bigtree["path"] being too small.
	$bigtree["path"] = array_pad($bigtree["path"],2,"");

	// If they're requesting images, css, or js, just give it to them.
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

	// Otherwise start the admin routing

	if (file_exists("../custom/bootstrap.php")) {
		include "../custom/bootstrap.php";
	} else {
		include "../core/bootstrap.php";
	}

	$bigtree["mysql_read_connection"] = bigtree_setup_sql_connection();
	ob_start();
	session_start();

	if (BIGTREE_CUSTOM_ADMIN_CLASS) {
		eval('$admin = new '.BIGTREE_CUSTOM_ADMIN_CLASS.';');
	} else {
		$admin = new BigTreeAdmin;
	}

	if (!isset($bigtree["path"][1])) {
		$bigtree["path"][1] = "";
	}

	$bigtree["layout"] = "default";
	$inc_dir = "";

	if (!isset($admin->ID) && $bigtree["path"][1] != "login") {
		$_SESSION["bigtree_login_redirect"] = DOMAIN.$_SERVER["REQUEST_URI"];
		BigTree::redirect(ADMIN_ROOT."login/");
	} else {
		// We're logged in, let's go somewhere.
		if (!$bigtree["path"][1]) {
			BigTree::redirect(ADMIN_ROOT."dashboard/");
		// We're hitting an ajax page.
		} elseif ($bigtree["path"][1] == "ajax") {
			$x = 2;
			$ajpath = "";
			while ($x < count($bigtree["path"]) - 1) {
				$ajpath .= $bigtree["path"][$x]."/";
				$x++;
			}

			// Permissions!
			$module = $admin->getModuleByRoute($bigtree["path"][2]);
			if ($module && !$admin->checkAccess($module["id"])) {
				include BigTree::path("admin/ajax/login.php");
				die();
			}

			$autoModule = new BigTreeAutoModule;

			$bigtree["path"][$x] = str_replace(".php","",$bigtree["path"][$x]);

			include BigTree::path("admin/ajax/".$ajpath.$bigtree["path"][$x].".php");
			die();
		// We've actually chosen a section now.
		} else {
			$ispage = false;
			$inc = false;
			// Check if it's a module or a normal page.
			if (is_dir("../custom/admin/modules/".$bigtree["path"][1])) {
				if (!isset($bigtree["path"][2])) {
					$inc = "../custom/admin/modules/".$bigtree["path"][1]."/default.php";
				} else {
					$inc = "../custom/admin/modules/".$bigtree["path"][1]."/";
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
					$bigtree["commands"] = array_slice($bigtree["path"],$y+1);
					$commands = $bigtree["commands"]; // Backwards compatibility
				}
			}
			if (($inc && !file_exists($inc)) || (!$inc && is_dir("../core/admin/modules/".$bigtree["path"][1]))) {
				if (!isset($bigtree["path"][2])) {
					$inc = "../core/admin/modules/".$bigtree["path"][1]."/default.php";
				} else {
					$inc = "../core/admin/modules/".$bigtree["path"][1]."/";
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
					$bigtree["commands"] = array_slice($bigtree["path"],$y+1);
					$commands = $bigtree["commands"]; // Backwards compatibility
				}
			// It's a normal page.
			} elseif (!$inc) {
				if (file_exists("../custom/admin/pages/".$bigtree["path"][1].".php")) {
					$inc = "../custom/admin/pages/".$bigtree["path"][1].".php";
				} elseif (file_exists("../core/admin/pages/".$bigtree["path"][1].".php")) {
					$inc = "../core/admin/pages/".$bigtree["path"][1].".php";
				}
				$ispage = true;
			}

			// Permissions!
			if (!$ispage || !$inc) {
				$module = $admin->getModuleByRoute($bigtree["path"][1]);
				$module_title = $module["name"];
				if ($module && !$admin->checkAccess($module["id"])) {
					ob_clean();
					include BigTree::path("admin/pages/_denied.php");
					$bigtree["content"] = ob_get_clean();
					include BigTree::path("admin/layouts/".$bigtree["layout"].".php");
					die();
				}
			}

			// Ok, if this inc is real, let's include it -- otherwise see if it's an auto-module action.
			if (isset($bigtree["path"][1])) {
				$module = $admin->getModuleByRoute($bigtree["path"][1]);
			}
			if (!isset($bigtree["path"][2])) {
				$bigtree["path"][2] = "";
			}

			$bigtree["module_action"] = $admin->getModuleActionByRoute($module["id"],array_slice($bigtree["path"],2));

			$inc_dir = str_replace("../",SERVER_ROOT,$inc_dir);

			if ($module && ($bigtree["module_action"]["view"] || $bigtree["module_action"]["form"])) {
				if ($bigtree["module_action"]["form"]) {
					$edit_id = is_numeric(end($bigtree["path"])) ? end($bigtree["path"]) : "";
					include BigTree::path("admin/auto-modules/form.php");
				} else {
					include BigTree::path("admin/auto-modules/view.php");
				}
			} elseif (file_exists($inc)) {
				// Include the top level module header.
				if (!$ispage && file_exists(BigTree::path("admin/modules/".$bigtree["path"][1]."/_header.php"))) {
					include BigTree::path("admin/modules/".$bigtree["path"][1]."/_header.php");
				}

				// Include the routed directory's module header if it's not the same one.
				if (!$ispage && file_exists($inc_dir."_header.php") && BigTree::path("admin/modules/".$bigtree["path"][1]."/_header.php") != ($inc_dir."_header.php")) {
					include $inc_dir."_header.php";
				}

				// Include the routed file.
				include $inc;

				// Include the routed directory's footer if it's not the same as the top level footer.
				if (!$ispage && file_exists($inc_dir."_footer.php") && BigTree::path("admin/modules/".$bigtree["path"][1]."/_footer.php") != ($inc_dir."_footer.php")) {
					include $inc_dir."_footer.php";
				}

				// Include the top level module footer.
				if (!$ispage && file_exists(BigTree::path("admin/modules/".$bigtree["path"][1]."/_footer.php"))) {
					include BigTree::path("admin/modules/".$bigtree["path"][1]."/_footer.php");
				}
			} else {
				include BigTree::path("admin/pages/_404.php");
			}
		}
	}

	$bigtree["content"] = ob_get_clean();

	include BigTree::path("admin/layouts/".$bigtree["layout"].".php");

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