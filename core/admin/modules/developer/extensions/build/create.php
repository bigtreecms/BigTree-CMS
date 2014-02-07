<?
	// First we need to package the file so they can download it manually if they wish.
	if (!is_writable(SERVER_ROOT."cache/") || !BigTree::isDirectoryWritable(SERVER_ROOT."extensions/$id/")) {
?>
<div class="container">
	<section>
		<h3>Error</h3>
		<p>Your cache/ and extesions/<?=$id?>/ directories must be writable.</p>
	</section>
</div>
<?
		$admin->stop();
	}
	
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
		foreach ($licenses as $license) {
			$license_array[$license] = $available_licenses["Open Source"][$license];
		}
	}
	
	// Setup JSON manifest
	$package = array(
		"type" => "extension",
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
		"sql" => array("SET foreign_key_checks = 0")
	);

	$used_forms = array();
	$used_views = array();
	$used_reports = array();

	foreach ((array)$module_groups as $group) {
		$package["components"]["module_groups"][] = $admin->getModuleGroup($group);
	}
	
	foreach ((array)$modules as $module) {
		$module = $admin->getModule($module);
		$module["actions"] = $admin->getModuleActions($module["id"]);
		foreach ($module["actions"] as $a) {
			// If there's an auto module, include it as well.
			if ($a["form"] && !in_array($a["form"],$used_forms)) {
				$module["forms"][] = BigTreeAutoModule::getForm($a["form"]);
				$used_forms[] = $a["form"];
			} elseif ($a["view"] && !in_array($a["view"],$used_views)) {
				$module["views"][] = BigTreeAutoModule::getView($a["view"]);
				$used_views[] = $a["view"];
			} elseif ($a["report"] && !in_array($a["report"],$used_reports)) {
				$module["reports"][] = BigTreeAutoModule::getReport($a["report"]);
				$used_reports[] = $a["report"];
			}
		}
		$module["embed_forms"] = $admin->getModuleEmbedForms("title",$module["id"]);
		$package["components"]["modules"][] = $module;
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
	
	// Move all the files into the extensions directory
	if (!file_exists(SERVER_ROOT."extensions/$id/")) {
		mkdir(SERVER_ROOT."extensions/$id/");
		chmod(SERVER_ROOT."extensions/$id/",0777);
	}
	foreach ((array)$files as $file) {
		$file = str_replace(SERVER_ROOT,"",$file);
		if (substr($file,0,11) != "extensions/") {
			$d = false;
			// We need to determine where files should be moved to based on their original file structure
			if (substr($file,0,18) == "custom/admin/ajax/") {
				$d = "ajax/".substr($file,18);
			} elseif (substr($file,0,17) == "custom/admin/css/") {
				$d = "css/".substr($file,17);
			} elseif (substr($file,0,16) == "custom/admin/js/") {
				$d = "js/".substr($file,16);
			} elseif (substr($file,0,20) == "custom/admin/images/") {
				$d = "images/".substr($file,20);
			} elseif (substr($file,0,21) == "custom/admin/modules/") {
				$d = "modules/".substr($file,21);
			} elseif (substr($file,0,19) == "custom/inc/modules/") {
				$d = "classes/".substr($file,19);
			} elseif (substr($file,0,30) == "custom/admin/form-field-types/") {
				$d = "field-types/".substr($file,30);
			} elseif (substr($file,0,10) == "templates/") {
				$d = $file;
			} elseif (substr($file,0,5) == "site/") {
				$d = $file;
			}
			if ($d) {
				BigTree::moveFile(SERVER_ROOT.$file,SERVER_ROOT."extensions/$id/".$d);
			}
		}
	}
	
	// Write the manifest file
	$json = (version_compare(PHP_VERSION,"5.4.0") >= 0) ? json_encode($package,JSON_PRETTY_PRINT |  JSON_UNESCAPED_SLASHES) : json_encode($package);
	file_put_contents(SERVER_ROOT."extensions/$id/manifest.json",$json);
	
	// Create the zip
	@unlink(SERVER_ROOT."cache/package.zip");
	include BigTree::path("inc/lib/pclzip.php");
	$zip = new PclZip(SERVER_ROOT."cache/package.zip");
	$zip->create(BigTree::directoryContents(SERVER_ROOT."extensions/$id/"),PCLZIP_OPT_REMOVE_PATH,SERVER_ROOT."extensions/$id/");

	// Store it in the database for future updates
	if (sqlrows(sqlquery("SELECT * FROM bigtree_extensions WHERE id = '".sqlescape($id)."'"))) {
		sqlquery("UPDATE bigtree_extensions SET name = '".sqlescape($title)."', version = '".sqlescape($version)."', last_updated = NOW(), manifest = '".sqlescape($json)."' WHERE id = '".sqlescape($id)."'");
	} else {
		sqlquery("INSERT INTO bigtree_extensions (`id`,`type`,`name`,`version`,`last_updated`,`manifest`) VALUES ('".sqlescape($id)."','extension','".sqlescape($title)."','".sqlescape($version)."',NOW(),'".sqlescape($json)."')");
	}
?>
<div class="container">
	<section>
		<p>Extension created successfully.</p>
	</section>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>extensions/build/download/" class="button blue">Download</a>
	</footer>
</div>