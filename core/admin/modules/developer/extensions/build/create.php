<?
	// First we need to package the file so they can download it manually if they wish.
	if (!is_writable(SERVER_ROOT."cache/") || !BigTree::isDirectoryWritable(SERVER_ROOT."extensions/$id/")) {
?>
<div class="container">
	<section>
		<h3>Error</h3>
		<p>Your /cache/ and /extensions/<?=$id?>/ directories must be writable.</p>
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
		if (is_array($licenses)) {
			foreach ($licenses as $license) {
				$license_array[$license] = $available_licenses["Open Source"][$license];
			}
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
	$extension = sqlescape($id);

	foreach ((array)$module_groups as $group) {
		$package["components"]["module_groups"][] = $admin->getModuleGroup($group);
	}
	
	foreach ((array)$callouts as $callout) {
		if (strpos($callout,"*") === false) {
			sqlquery("UPDATE bigtree_callouts SET extension = '$extension', id = '$extension*".sqlescape($callout)."' WHERE id = '".sqlescape($callout)."'");
		}
		$package["components"]["callouts"][] = $admin->getCallout($callout);
	}
	
	foreach ((array)$feeds as $feed) {
		sqlquery("UPDATE bigtree_feeds SET route = CONCAT('$extension/',route), extension = '$extension' WHERE id = '".sqlescape($feed)."'");
		$package["components"]["feeds"][] = $cms->getFeed($feed);
	}
	
	foreach ((array)$settings as $setting) {
		sqlquery("UPDATE bigtree_settings SET id = CONCAT('$extension*',id), extension = '$extension' WHERE id = '".sqlescape($setting)."'");
		$package["components"]["settings"][] = $admin->getSetting($setting);
	}

	// Setup anonymous function for converting old field type IDs to new ones
	$field_type_converter = function($table,$field) {
		global $id,$type;
		$q = sqlquery("SELECT * FROM `$table` WHERE `$field` LIKE '%\"type\":\"".sqlescape($type)."\"%'");
		while ($f = sqlfetch($q)) {
			$array = json_decode($f[$field],true);
			foreach ($array as &$item) {
				if ($item["type"] == $type) {
					$item["type"] = $id."*".$type;
				} elseif ($item["type"] == "matrix") {
					foreach ($item["options"]["columns"] as &$column) {
						if ($column["type"] == $type) {
							$column["type"] = $id."*".$type;
						}
					}
				}
			}
			sqlquery("UPDATE `$table` SET `$field` = '".BigTree::json($array,true)."' WHERE id = '".$f["id"]."'");
		}
	};
	
	foreach ((array)$field_types as $type) {
		// Currently non-extension field type becoming an extension one
		if (strpos($type,"*") === false) {
			sqlquery("UPDATE bigtree_field_types SET extension = '$extension', id = CONCAT('$extension*',id) WHERE id = '".sqlescape($type)."'");
			// Convert old usage of field type ID to extension usage
			$field_type_converter("bigtree_templates","resources");
			$field_type_converter("bigtree_callouts","resources");
			$field_type_converter("bigtree_module_forms","fields");
			$field_type_converter("bigtree_module_embeds","fields");
			sqlquery("UPDATE bigtree_settings SET `type` = '".sqlescape($id."*".$type)."' WHERE `type` = '".sqlescape($type)."'");
		}
		$package["components"]["field_types"][] = $admin->getFieldType($type);
	}

	foreach ((array)$templates as $template) {
		if (strpos($template,"*") === false) {
			sqlquery("UPDATE bigtree_templates SET extension = '$extension', id = CONCAT('$extension*',id) WHERE id = '".sqlescape($template)."'");
		}
		$package["components"]["templates"][] = $cms->getTemplate($template);
	}

	foreach ((array)$modules as $module) {
		$module = $admin->getModule($module);
		$new_route = false;
		if (strpos($module["route"],"*") === false) {
			sqlquery("UPDATE bigtree_modules SET route = CONCAT('$extension*',route), extension = '$extension' WHERE id = '".sqlescape($module["id"])."'");
			$new_route = $extension."*".$module["route"];
		} else {
			sqlquery("UPDATE bigtree_modules SET extension = '$extension' WHERE id = '".sqlescape($module["id"])."'");
		}
		$module["actions"] = $admin->getModuleActions($module["id"]);
		foreach ($module["actions"] as $a) {
			// If there's an auto module, include it as well.
			if ($a["form"] && !in_array($a["form"],$used_forms)) {
				// If we've changed module routes, make sure we adjust return view URLs
				if ($new_route) {
					sqlquery("UPDATE bigtree_module_forms SET return_url = REPLACE(return_url,'{adminroot}".$module["route"]."/','{adminroot}$new_route/') WHERE id = '".$a["form"]."'");
				}
				$module["forms"][] = BigTreeAutoModule::getForm($a["form"],false);
				$used_forms[] = $a["form"];
			} elseif ($a["view"] && !in_array($a["view"],$used_views)) {
				// If we've changed module routes, make sure we adjust preview URLs
				if ($new_route) {
					sqlquery("UPDATE bigtree_module_views SET preview_url = REPLACE(preview_url,'{adminroot}".$module["route"]."/','{adminroot}$new_route/') WHERE id = '".$a["view"]."'");
				}

				$view = BigTreeAutoModule::getView($a["view"],false);
				// Unset edit_url as it exposes origin URL
				unset($view["edit_url"]);
				$module["views"][] = $view;
				$used_views[] = $a["view"];
			} elseif ($a["report"] && !in_array($a["report"],$used_reports)) {
				$module["reports"][] = BigTreeAutoModule::getReport($a["report"]);
				$used_reports[] = $a["report"];
			}
		}
		$module["embed_forms"] = $admin->getModuleEmbedForms("title",$module["id"]);
		$package["components"]["modules"][] = $module;
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
	$json = BigTree::json($package);
	BigTree::putFile(SERVER_ROOT."extensions/$id/manifest.json",$json);
	
	// Create the zip
	@unlink(SERVER_ROOT."cache/package.zip");
	@unlink(SERVER_ROOT."cache/bigtree-form-field-types.json");
	@unlink(SERVER_ROOT."cache/bigtree-module-class-list.json");
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