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
		"sql" => array()
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
				BigTree::copyFile($file,str_replace(SERVER_ROOT,SERVER_ROOT."cache/package/",$file));
				$package["files"][] = str_replace(SERVER_ROOT,"",$file);
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
		$package["field_types"][] = $admin->getFieldType($type);
	}
	
	// We need to rearrange the tables array so that ones that have foreign keys fall at the end.
	$rearranged_tables = array();
	$pending_tables = array();
	$table_info = array();
	foreach ((array)$tables as $t) {
		list($table,$type) = explode("#",$t);
		$i = BigTree::describeTable($table);
		if (!count($i["foreign_keys"])) {
			$rearranged_tables[] = $t;
		} else {
			// See if the other tables are all bigtree_ ones or the key points to itself.
			$just_bigtree_keys = true;
			foreach ($i["foreign_keys"] as $key) {
				if (substr($key["other_table"],0,8) != "bigtree_" && $key["other_table"] != $table) {
					$just_bigtree_keys = false;
				}
			}
			if ($just_bigtree_keys) {
				$rearranged_tables[] = $t;
			} else {
				$table_info[$t] = $i;
				$pending_tables[] = $t;
			}
		}
	}
	// We're going to loop the number of times there are tables so we don't loop forever
	for ($i = 0; $i < count($pending_tables); $i++) {
		$t = $pending_tables[$i];
		$keys = $table_info[$t]["foreign_keys"];
		$ok = true;
		foreach ($keys as $key) {
			// If we haven't already found this foreign key table and it's not related to BigTree's core tables, we're not including it yet.
			if (!in_array($key["other_table"]."#data",$rearranged_tables) &&!in_array($key["other_table"]."#structure",$rearranged_tables) && substr($key["other_table"],0,8) != "bigtree_") {
				$ok = false;
			}
		}
		// If we've already got the table for this one's foreign keys, add it to the rearranged tables if we haven't already.
		if ($ok && !in_array($t,$rearranged_tables)) {
			$rearranged_tables[] = $t;
		}
	}
	
	// If we have less rearranged tables than pending tables we're missing a table dependancy.
	if (count($rearranged_tables) != count((array)$tables)) {
		$failed_tables = array();
		foreach ((array)$tables as $t) {
			if (!in_array($t,$rearranged_tables)) {
				list($table,$type) = explode("#",$t);
				$failed_tables[] = $table;
			}
		}
		$admin->stop('<div class="container"><section><div class="alert">
		<span></span><h3>Creation Failed</h3></div><p>The following tables have missing foreign key constraints: '.implode(", ",$failed_tables).'</p></section></div>');
	}
	
	foreach ($rearranged_tables as $t) {
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