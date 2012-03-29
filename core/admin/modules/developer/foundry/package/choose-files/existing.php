<?
	include "_file_chooser_header.php";
	
	$package = sqlfetch(sqlquery("SELECT * FROM bigtree_module_packages WHERE id = '".mysql_real_escape_string(end($commands))."'"));
	
	if ($package["module_id"]) {
		gatherModuleInformation($package["module_id"]);
	} elseif ($package["group_id"]) {
		$group = $admin->getModuleGroup($package["group_id"]);
		$modules = $admin->getModulesByGroup($group);
		foreach ($modules as $m) {
			gatherModuleInformation($m["id"]);
		}
		if (file_exists($server_root."custom/inc/required/".$cms->urlify($group["name"]).".php")) {
			$required_files[] = "custom/inc/required/".$cms->urlify($group["name"]).".php";
		}
	}
	
	$details = unserialize($package["details"]);
	foreach ($details as $key => $val) {
		// Overwrite the defaults for any table that was already included.
		if ($key == "tables") {
			foreach ($val as $t) {
				list($table,$type) = explode("#",$t);
				$found = false;
				foreach ($tables as &$titem) {
					list($otable,$otype) = explode("#",$titem);
					if ($otable == $table) {
						$titem = $t;
						$found = true;
					}
				}
				if (!$found)
					$tables[] = $t;
			}
		} elseif (substr($key,0,1) != "_") {
			if (is_array($val)) {
				$$key = array_unique(array_merge($$key,$val));
			}
		}
	}
	
	$removed = array();
	
	// Remove tables that no longer exist
	if (is_array($tables)) {
		foreach ($tables as $key => $tval) {
			list($table,$type) = explode("#",$tval);
			if (!sqlrows(sqlquery("SELECT table_name FROM information_schema.tables WHERE table_schema = '".$config["db"]["name"]."' AND table_name = '$table'"))) {
				unset($tables[$key]);
				$removed[] = "Table: $table";
			}
		}
	}
	
	// Remove templates that don't exist anymore.
	if (is_array($templates)) {
		foreach ($templates as $key => $t) {
			if (!sqlrows(sqlquery("SELECT id FROM bigtree_templates WHERE id = '$t'"))) {
				unset($templates[$key]);
				$removed[] = "Template: $t";
			}
		}
	}
	
	// Remove sidelets that don't exist anymore.
	if (is_array($sidelets)) {
		foreach ($sidelets as $key => $t) {
			if (!sqlrows(sqlquery("SELECT id FROM bigtree_sidelets WHERE id = '$t'"))) {
				unset($sidelets[$key]);
				$removed[] = "Sidelet: $t";
			}
		}
	}
	
	// Remove feeds that don't exist anymore.
	if (is_array($feeds)) {
		foreach ($feeds as $key => $t) {
			if (!sqlrows(sqlquery("SELECT id FROM bigtree_feeds WHERE id = '$t'"))) {
				unset($feeds[$key]);
				$removed[] = "Feed: $t";
			}
		}
	}
	
	// Remove settings that don't exist anymore.
	if (is_array($settings)) {
		foreach ($settings as $key => $t) {
			if (!sqlrows(sqlquery("SELECT id FROM bigtree_settings WHERE id = '$t'"))) {
				unset($settings[$key]);
				$removed[] = "Setting: $t";
			}
		}
	}
	
	// Remove files that don't exist anymore
	if (is_array($files)) {
		foreach ($files as $key => $f) {
			if (!file_exists($server_root.$f)) {
				unset($files[$key]);
				$removed[] = "File: $f";
			}
		}
	}
	if (is_array($required_files)) {
		foreach ($required_files as $key => $f) {
			if (!file_exists($server_root.$f)) {
				unset($files[$key]);
				$removed[] = "File: $f";
			}
		}
	}
	if (is_array($class_files)) {
		foreach ($class_files as $key => $f) {
			if (!file_exists($server_root.$f)) {
				unset($files[$key]);
				$removed[] = "File: $f";
			}
		}
	}
	
	// We need to update the list to see if there are any new things.
?>
<h3 class="foundry">Update Package: Choose Files</h3>
<p>Please select all the files required for the Package &ldquo;<?=$package["name"]?>&rdquo;</p>
<? if (count($removed)) { ?>
<p><strong>Some items from this package were removed because they no longer exist.</strong></p>
<? } ?>
<br />
<h4>Package Information</h4>
<form method="post" action="<?=$aroot?>developer/foundry/package/release-notes/module/" class="module">
	<input type="hidden" name="package" value="<?=$package["id"]?>" />
	<? include "_file_chooser_footer.php" ?>