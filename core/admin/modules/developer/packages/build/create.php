<?
	// First we need to package the file so they can download it manually if they wish.
	if (!BigTree::isDirectoryWritable(SERVER_ROOT."cache/package/")) {
?>
<div class="container">
	<section>
		<h3>Error</h3>
		<p>Your cache/ and cache/package/ directories must be writable.</p>
	</section>
</div>
<?
		$admin->stop();
	}

	@mkdir(SERVER_ROOT."cache/package/");
	
	// Fix keywords into an array
	$keywords = explode(",",$keywords);
	foreach ($keywords as &$word) {
		$word = trim($word);
	}
	// Fix licenses into an array
	if ($license_name) {
		$license_array = array($license_name => $license_url);
	} elseif ($license) {
		$license_array = array($license => $available_licenses["Closed Source"][$license]);
	} else {
		$license_array = array();
		if (is_array($licenses)) {
			foreach ($licenses as $license) {
				$license_array[$license] = $available_licenses["Open Source"][$license];
			}
		}
	}
	
	// Setup JSON manifest
	$package = array(
		"type" => "package",
		"id" => $id,
		"version" => $version,
		"compatibility" => $compatibility,
		"title" => $title,
		"description" => $description,
		"keywords" => $keywords,
		"author" => $author,
		"licenses" => $license_array,
		"components" => array(
			"module_groups" => array(),
			"modules" => array(),
			"templates" => array(),
			"callouts" => array(),
			"settings" => array(),
			"feeds" => array(),
			"field_types" => array(),
			"tables" => array()
		),
		"sql" => array("SET foreign_key_checks = 0"),
		"files" => array()
	);

	$used_forms = array();
	$used_views = array();
	$used_reports = array();

	foreach ((array)$module_groups as $group) {
		$package["components"]["module_groups"][] = $admin->getModuleGroup($group);
	}
	
	foreach ((array)$modules as $module) {
		$module = $admin->getModule($module);
		if ($module) {
			$module["actions"] = $admin->getModuleActions($module["id"]);
			foreach ($module["actions"] as $a) {
				// If there's an auto module, include it as well.
				if ($a["form"] && !in_array($a["form"],$used_forms)) {
					$module["forms"][] = BigTreeAutoModule::getForm($a["form"],false);
					$used_forms[] = $a["form"];
				} elseif ($a["view"] && !in_array($a["view"],$used_views)) {
					$module["views"][] = BigTreeAutoModule::getView($a["view"],false);
					$used_views[] = $a["view"];
				} elseif ($a["report"] && !in_array($a["report"],$used_reports)) {
					$module["reports"][] = BigTreeAutoModule::getReport($a["report"]);
					$used_reports[] = $a["report"];
				}
			}
			$module["embed_forms"] = $admin->getModuleEmbedForms("title",$module["id"]);
			$package["components"]["modules"][] = $module;
		}
	}
	
	foreach ((array)$templates as $template) {
		$package["components"]["templates"][] = $cms->getTemplate($template);
	}
	
	foreach ((array)$callouts as $callout) {
		$package["components"]["callouts"][] = $admin->getCallout($callout);
	}
	
	foreach ((array)$feeds as $feed) {
		$package["components"]["feeds"][] = $cms->getFeed($feed);
	}
	
	foreach ((array)$settings as $setting) {
		$package["components"]["settings"][] = $admin->getSetting($setting);
	}
	
	foreach ((array)$field_types as $type) {
		$package["components"]["field_types"][] = $admin->getFieldType($type);
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
				$package["sql"][] = "INSERT INTO `$table` (".implode(",",$fields).") VALUES (".implode(",",$values).")";
			}
		}
		$package["components"]["tables"][] = $table;
	}
	$package["sql"][] = "SET foreign_key_checks = 1";
	
	foreach ((array)$files as $file) {
		$file = str_replace(SERVER_ROOT,"",$file);
		BigTree::copyFile(SERVER_ROOT.$file,SERVER_ROOT."cache/package/".$file);
		$package["files"][] = $file;
	}
	
	// Write the manifest file
	$json = (version_compare(PHP_VERSION,"5.4.0") >= 0) ? json_encode($package,JSON_PRETTY_PRINT |  JSON_UNESCAPED_SLASHES) : json_encode($package);
	file_put_contents(SERVER_ROOT."cache/package/manifest.json",$json);
	
	// Create the zip
	@unlink(SERVER_ROOT."cache/package.zip");
	include BigTree::path("inc/lib/pclzip.php");
	$zip = new PclZip(SERVER_ROOT."cache/package.zip");
	$zip->create(BigTree::directoryContents(SERVER_ROOT."cache/package/"),PCLZIP_OPT_REMOVE_PATH,SERVER_ROOT."cache/package/");

	// Remove the package directory, we do it backwards because the "deepest" files are last
	$contents = array_reverse(BigTree::directoryContents(SERVER_ROOT."cache/package/"));
	foreach ($contents as $file) {
		@unlink($file);
		@rmdir($file);
	}
	@rmdir(SERVER_ROOT."cache/package/");

	// Store it in the database for future updates
	if (sqlrows(sqlquery("SELECT * FROM bigtree_extensions WHERE id = '".sqlescape($id)."'"))) {
		sqlquery("UPDATE bigtree_extensions SET name = '".sqlescape($title)."', version = '".sqlescape($version)."', last_updated = NOW(), manifest = '".sqlescape($json)."' WHERE id = '".sqlescape($id)."'");
	} else {
		sqlquery("INSERT INTO bigtree_extensions (`id`,`type`,`name`,`version`,`last_updated`,`manifest`) VALUES ('".sqlescape($id)."','package','".sqlescape($title)."','".sqlescape($version)."',NOW(),'".sqlescape($json)."')");
	}
?>
<div class="container">
	<section>
		<p>Package created successfully.</p>
	</section>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>packages/build/download/" class="button blue">Download</a>
	</footer>
</div>