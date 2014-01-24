<?
	// First we need to package the file so they can download it manually if they wish.
	if (!is_writable(SERVER_ROOT."cache/")) {
?>
<div class="container">
	<section>
		<h3>Error</h3>
		<p>Your cache/ directory must be writable.</p>
	</section>
</div>
<?
		$admin->stop();
	}
	
	// If someone accidentally added something twice, remove the duplicates.
	foreach ($_POST as $key => $val) {
		if (is_array($val)) {
			$_POST[$key] = array_unique($val);
		}
	}	
	BigTree::globalizePOSTVars();
	
	// Setup the package for the JSON
	$package = array(
		"type" => "package",
		"id" => $package_id,
		"title" => $package_name,
		"bigtree_version" => BIGTREE_VERSION,
		"author" => $created_by,
		"pre_install_instructions" => $pre_instructions,
		"post_install_instructions" => $post_instructions,
		"install_code" => $install_code,
		"module_groups" => array(),
		"modules" => array(),
		"templates" => array(),
		"callouts" => array(),
		"field_types" => array(),
		"settings" => array(),
		"feeds" => array(),
		"files" => array(),
		"sql" => array("SET foreign_key_checks = 0")
	);
	
	// If it's a single module we're exporting, do just the module
	if ($module) {
		$modules = array($admin->getModule($module));
	// Otherwise go with the whole group of modules
	} elseif ($group) {
		$group_details = $admin->getModuleGroup($group);
		$modules = $admin->getModulesByGroup($group_details["id"]);
		$package["module_groups"][] = $group_details;
	}

	$used_forms = array();
	$used_views = array();
	$used_reports = array();
	
	foreach ((array)$modules as $item) {
		$item["actions"] = $admin->getModuleActions($item["id"]);
		foreach ($item["actions"] as $a) {
			// If there's an auto module, include it as well.
			if ($a["form"] && !in_array($a["form"],$used_forms)) {
				$item["forms"][] = BigTreeAutoModule::getForm($a["form"]);
				$used_forms[] = $a["form"];
			} elseif ($a["view"] && !in_array($a["view"],$used_views)) {
				$item["views"][] = BigTreeAutoModule::getView($a["view"]);
				$used_views[] = $a["view"];
			} elseif ($a["report"] && !in_array($a["report"],$used_reports)) {
				$item["reports"][] = BigTreeAutoModule::getReport($a["report"]);
				$used_reports[] = $a["report"];
			}
		}
		$item["embed_forms"] = $admin->getModuleEmbedForms("title",$item["id"]);
		$package["modules"][] = $item;
	}
	
	foreach ((array)$templates as $template) {
		$item = $cms->getTemplate($template);
		$package["templates"][] = $item;
		// If we're bringing over a module template, copy the whole darn folder.
		if ($item["routed"]) {
			$files = BigTree::directoryContents(SERVER_ROOT."templates/routed/$template/");
			foreach ($files as $file) {
				if (!is_dir($file)) {
					BigTree::copyFile($file,str_replace(SERVER_ROOT,SERVER_ROOT."cache/package/",$file));
					$package["files"][] = str_replace(SERVER_ROOT,"",$file);
				}
			}
		} else {
			BigTree::copyFile(SERVER_ROOT."templates/basic/$template.php",SERVER_ROOT."cache/package/templates/basic/$template.php");
			$package["files"][] = "templates/basic/$template.php";
		}
	}
	
	foreach ((array)$callouts as $callout) {
		$package["callouts"][] = $admin->getCallout($callout);
		BigTree::copyFile(SERVER_ROOT."templates/callouts/$callout.php",SERVER_ROOT."cache/package/templates/callouts/$callout.php");
		$package["files"][] = "templates/callouts/$callout.php";
	}
	
	foreach ((array)$feeds as $feed) {
		$package["feeds"][] = $cms->getFeed($feed);
	}
	
	foreach ((array)$settings as $setting) {
		$package["settings"][] = $admin->getSetting($setting);
	}
	
	foreach ((array)$field_types as $type) {
		$package["files"][] = "custom/admin/form-field-types/draw/$type.php";
		$package["files"][] = "custom/admin/form-field-types/process/$type.php";
		$package["files"][] = "custom/admin/ajax/developer/field-options/$type.php";
		BigTree::copyFile(SERVER_ROOT."custom/admin/form-field-types/draw/$type.php",SERVER_ROOT."cache/package/"."custom/admin/form-field-types/draw/$type.php");
		BigTree::copyFile(SERVER_ROOT."custom/admin/form-field-types/process/$type.php",SERVER_ROOT."cache/package/"."custom/admin/form-field-types/process/$type.php");
		BigTree::copyFile(SERVER_ROOT."custom/admin/ajax/developer/field-options/$type.php",SERVER_ROOT."cache/package/"."custom/admin/ajax/developer/field-options/$type.php");
		$package["field_types"][] = $admin->getFieldType($type);
	}
	
	foreach ((array)$tables as $t) {
		$x++;
		list($table,$type) = explode("#",$t);
		$f = sqlfetch(sqlquery("SHOW CREATE TABLE `$table`"));
		$package["sql"][] = "DROP TABLE IF EXISTS `$table`";
		$package["sql"][] = str_replace(array("\r","\n")," ",end($f));
		if ($type != "structure") {
			$q = sqlquery("SELECT * FROM `$table`");
			while ($f = sqlfetch($q)) {
				$fields = array();
				$values = array();
				foreach ($f as $key => $val) {
					$fields[] = "`$key`";
					if ($val === null) {
						$values[] = "NULL";
					} else {
						$values[] = "'".sqlescape(str_replace("\n","\\n",$val))."'";
					}
				}
				$bigtree["sql"][] = "INSERT INTO `$table` (".implode(",",$fields).") VALUES (".implode(",",$values).")";
			}
		}
	}
	$bigtree["sql"][] = "SET foreign_key_checks = 1";
	
	foreach ((array)$class_files as $file) {
		BigTree::copyFile(SERVER_ROOT.$file,SERVER_ROOT."cache/package/".$file);
		$package["files"][] = $file;
	}
	
	foreach ((array)$required_files as $file) {
		BigTree::copyFile(SERVER_ROOT.$file,SERVER_ROOT."cache/package/".$file);
		$package["files"][] = $file;
	}
	
	foreach ((array)$other_files as $file) {
		BigTree::copyFile(SERVER_ROOT.$file,SERVER_ROOT."cache/package/".$file);
		$package["files"][] = $file;
	}

	// May have dupes if someone manually included things
	$package["files"] = array_unique($package["files"]);
	
	// Write the manifest file
	$json = (version_compare(PHP_VERSION,"5.4.0") >= 0) ? json_encode($package,JSON_PRETTY_PRINT |  JSON_UNESCAPED_SLASHES) : json_encode($package);
	file_put_contents(SERVER_ROOT."cache/package/manifest.json",$json);
	
	// Create the zip
	@unlink(SITE_ROOT."files/package.zip");
	include BigTree::path("inc/lib/pclzip.php");
	$zip = new PclZip(SITE_ROOT."files/package.zip");
	$zip->create(BigTree::directoryContents(SERVER_ROOT."cache/package/"),PCLZIP_OPT_REMOVE_PATH,SERVER_ROOT."cache/package/");

	// Remove the package directory, we do it backwards because the "deepest" files are last
	$contents = array_reverse(BigTree::directoryContents(SERVER_ROOT."cache/package/"));
	foreach ($contents as $file) {
		@unlink($file);
		@rmdir($file);
	}
	@rmdir(SERVER_ROOT."cache/package/");
?>
<div class="container">
	<section>
		<p>Package created successfully.  You may download it <a href="<?=WWW_ROOT?>files/package.zip">by clicking here</a>.</p>
	</section>
</div>