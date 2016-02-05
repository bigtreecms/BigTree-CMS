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
	if (array_filter((array)$licenses)) {
		$license_array = array();
		foreach ($licenses as $license) {
			$license_array[$license] = $available_licenses["Open Source"][$license];
		}
	} elseif ($license_name) {
		$license_array = array($license_name => $license_url);
	} elseif ($license) {
		$license_array = array($license => $available_licenses["Closed Source"][$license]);
	}

	// Create extension directory if it doesn't exist
	$extension_root = SERVER_ROOT."extensions/$id/";
	if (!file_exists($extension_root)) {
		BigTree::makeDirectory($extension_root);
	}
	
	// Setup JSON manifest
	$package = array(
		"type" => "extension",
		"id" => $id,
		"version" => $version,
		"revision" => 1,
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
		)
	);

	// We're going to be associating things to the extension before creating it
	sqlquery("SET foreign_key_checks = 0");

	$used_forms = array();
	$used_views = array();
	$used_reports = array();
	$extension = sqlescape($id);

	foreach (array_filter((array)$module_groups) as $group) {
		$package["components"]["module_groups"][] = $admin->getModuleGroup($group);
	}
	
	foreach (array_filter((array)$callouts) as $callout) {
		if (strpos($callout,"*") === false) {
			sqlquery("UPDATE bigtree_callouts SET extension = '$extension', id = '$extension*".sqlescape($callout)."' WHERE id = '".sqlescape($callout)."'");
			$callout = "$id*$callout";
		}
		$package["components"]["callouts"][] = $admin->getCallout($callout);
	}
	
	foreach (array_filter((array)$feeds) as $feed) {
		sqlquery("UPDATE bigtree_feeds SET route = CONCAT('$extension/',route), extension = '$extension' WHERE id = '".sqlescape($feed)."'");
		$package["components"]["feeds"][] = $cms->getFeed($feed);
	}
	
	foreach (array_filter((array)$settings) as $setting) {
		if (strpos($setting,"*") === false) {
			sqlquery("UPDATE bigtree_settings SET id = CONCAT('$extension*',id), extension = '$extension' WHERE id = '".sqlescape($setting)."'");
			$setting = "$id*$setting";
		}
		$package["components"]["settings"][] = $admin->getSetting($setting);
	}

	// Setup anonymous function for converting old field type IDs to new ones
	$field_type_converter = function($table,$field) {
		global $id,$type;
		$q = sqlquery("SELECT * FROM `$table` WHERE `$field` LIKE '%\"type\":\"".sqlescape($type)."\"%' OR `$field` LIKE '%\"type\": \"".sqlescape($type)."\"%'");
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

	foreach (array_filter((array)$field_types) as $type) {
		// Currently non-extension field type becoming an extension one
		if (strpos($type,"*") === false) {
			sqlquery("UPDATE bigtree_field_types SET extension = '$extension', id = CONCAT('$extension*',id) WHERE id = '".sqlescape($type)."'");
			// Convert old usage of field type ID to extension usage
			$field_type_converter("bigtree_templates","resources");
			$field_type_converter("bigtree_callouts","resources");
			$field_type_converter("bigtree_module_forms","fields");
			$field_type_converter("bigtree_module_embeds","fields");
			sqlquery("UPDATE bigtree_settings SET `type` = '".sqlescape($id."*".$type)."' WHERE `type` = '".sqlescape($type)."'");

			// Move files into new format
			BigTree::moveFile(SERVER_ROOT."custom/admin/form-field-types/draw/$type.php",$extension_root."field-types/$type/draw.php");
			BigTree::moveFile(SERVER_ROOT."custom/admin/form-field-types/process/$type.php",$extension_root."field-types/$type/process.php");
			BigTree::moveFile(SERVER_ROOT."custom/admin/ajax/developer/field-options/$type.php",$extension_root."field-types/$type/options.php");
			
			// Change type ID
			$type = "$id*$type";
		}
		$package["components"]["field_types"][] = $admin->getFieldType($type);
	}

	foreach (array_filter((array)$templates) as $template) {
		if (strpos($template,"*") === false) {
			sqlquery("UPDATE bigtree_templates SET extension = '$extension', id = CONCAT('$extension*',id) WHERE id = '".sqlescape($template)."'");
			$template = "$id*$template";
		}
		$package["components"]["templates"][] = $cms->getTemplate($template);
	}

	foreach (array_filter((array)$modules) as $module) {
		$module = $admin->getModule($module);
		
		// If the module isn't namespaced yet, namespace it
		if (strpos($module["route"],"*") === false) {
			sqlquery("UPDATE bigtree_modules SET route = CONCAT('$extension*',route), extension = '$extension' WHERE id = '".sqlescape($module["id"])."'");
			$new_route = $extension."*".$module["route"];
		} else {
			sqlquery("UPDATE bigtree_modules SET extension = '$extension' WHERE id = '".sqlescape($module["id"])."'");
			$new_route = false;
		}
		
		$module["actions"] = $admin->getModuleActions($module["id"]);
		// Loop through actions to update URLs for preview / return if we've moved this module into an extension namespace
		if ($new_route) {
			foreach ($module["actions"] as $a) {
				// Adjust return view URLs for forms
				if ($a["form"]) {
					sqlquery("UPDATE bigtree_module_forms SET return_url = REPLACE(return_url,'{adminroot}".$module["route"]."/','{adminroot}$new_route/') WHERE id = '".$a["form"]."'");
				// Adjust preview URLs for views
				} elseif ($a["view"]) {
					sqlquery("UPDATE bigtree_module_views SET preview_url = REPLACE(preview_url,'{adminroot}".$module["route"]."/','{adminroot}$new_route/') WHERE id = '".$a["view"]."'");
				}
			}
		}

		$module["views"] = $admin->getModuleViews("title",$module["id"]);
		$module["forms"] = $admin->getModuleForms("title",$module["id"]);
		$module["embed_forms"] = $admin->getModuleEmbedForms("title",$module["id"]);
		$module["reports"] = $admin->getModuleReports("title",$module["id"]);

		$package["components"]["modules"][] = $module;
	}
	
	foreach (array_filter((array)$tables) as $table) {
		// Set the table to the create statement
		$f = sqlfetch(sqlquery("SHOW CREATE TABLE `$table`"));
		$create_statement = str_replace(array("\r","\n")," ",end($f));

		// Drop auto increments and constraint names
		$create_statement = preg_replace('/(AUTO_INCREMENT\=\d*\s)/',"",$create_statement);
		$create_statement = preg_replace("/CONSTRAINT `([^`]*)`/i","",$create_statement);

		$package["components"]["tables"][$table] = $create_statement;
	}
	
	// Move all the files into the extensions directory
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
			} elseif (substr($file,0,10) == "templates/") {
				$d = $file;
			} elseif (substr($file,0,5) == "site/") {
				// Already in the proper directory, should be copied to public, not moved
				if (strpos($file,"site/extensions/$id/") === 0) {
					BigTree::copyFile(SERVER_ROOT.$file,SERVER_ROOT."extensions/$id/public/".str_replace("site/extensions/$id/","",$file));
				// Move into the site/extensions/ folder and then copy into /public/
				} else {
					BigTree::moveFile(SERVER_ROOT.$file,SITE_ROOT."extensions/$id/".substr($file,5));
					BigTree::copyFile(SITE_ROOT."extensions/$id/".substr($file,5),SERVER_ROOT."extensions/$id/public/".substr($file,5));
				}
			}

			// If we have a place to move it to, move it.
			if ($d) {
				BigTree::moveFile(SERVER_ROOT.$file,SERVER_ROOT."extensions/$id/".$d);
			}
		}
	}

	// If this package already exists, we need to do a diff of the tables, increment revision numbers, and add SQL statements.
	$existing = sqlfetch(sqlquery("SELECT * FROM bigtree_extensions WHERE id = '".sqlescape($id)."' AND type = 'extension'"));
	if ($existing) {
		$existing_json = json_decode($existing["manifest"],true);

		// Increment revision numbers
		$revision = $package["revision"] = intval($existing_json["revision"]) + 1;
		$package["sql_revisions"] = (array)$existing_json["sql_revisions"];
		$package["sql_revisions"][$revision] = array();

		// Diff the old tables
		foreach ($existing_json["components"]["tables"] as $table => $create_statement) {
			// If the table exists in the new manifest, we're going to see if they're identical
			if (isset($package["components"]["tables"][$table])) {
				// We're going to create a temporary table of the old structure to compare to the current table
				$create_statement = preg_replace("/CREATE TABLE `([^`]*)`/i","CREATE TABLE `bigtree_extension_temp`",$create_statement);
				$create_statement = preg_replace("/CONSTRAINT `([^`]*)`/i","",$create_statement);
				sqlquery("DROP TABLE IF EXISTS `bigtree_extension_temp`");
				sqlquery($create_statement);

				// Compare the tables, if we have changes to make, store them in a SQL revisions portion of the manifest
				$transition_statements = BigTree::tableCompare("bigtree_extension_temp",$table);
				foreach ($transition_statements as $statement) {
					// Don't include changes to auto increment
					if (stripos($statement,"auto_increment = ") === false) {
						$package["sql_revisions"][$revision][] = str_replace("`bigtree_extension_temp`","`$table`",$statement);
					}
				}
			// Table doesn't exist in the new manifest, so we're going to drop it
			} else {
				$package["sql_revisions"][$revision][] = "DROP TABLE IF EXISTS `$table`";
			}
		}

		// Add new tables that don't exist in the old manifest
		foreach ($package["components"]["tables"] as $table => $create_statement) {
			if (!isset($existing_json["components"]["tables"][$table])) {
				$package["sql_revisions"][$revision][] = $create_statement;
			}
		}

		// Clean up the revisions (if we don't have any)
		$package["sql_revisions"] = array_filter($package["sql_revisions"]);
	}
	
	// Write the manifest file
	$json = BigTree::json($package);
	BigTree::putFile(SERVER_ROOT."extensions/$id/manifest.json",$json);
	
	// Create the zip, clear caches since we may have moved the routes of field types and modules
	@unlink(SERVER_ROOT."cache/package.zip");
	@unlink(SERVER_ROOT."cache/bigtree-form-field-types.json");
	@unlink(SERVER_ROOT."cache/bigtree-module-class-list.json");
	include BigTree::path("inc/lib/pclzip.php");
	$zip = new PclZip(SERVER_ROOT."cache/package.zip");
	$zip->create(BigTree::directoryContents(SERVER_ROOT."extensions/$id/"),PCLZIP_OPT_REMOVE_PATH,SERVER_ROOT."extensions/$id/");

	// Store it in the database for future updates -- existing packages might be replaced
	if (sqlrows(sqlquery("SELECT id FROM bigtree_extensions WHERE id = '".sqlescape($id)."'"))) {
		sqlquery("UPDATE bigtree_extensions SET type = 'extension', name = '".sqlescape($title)."', version = '".sqlescape($version)."', last_updated = NOW(), manifest = '".sqlescape($json)."' WHERE id = '".sqlescape($id)."'");
	} else {
		sqlquery("INSERT INTO bigtree_extensions (`id`,`type`,`name`,`version`,`last_updated`,`manifest`) VALUES ('".sqlescape($id)."','extension','".sqlescape($title)."','".sqlescape($version)."',NOW(),'".sqlescape($json)."')");
	}

	// Turn foreign key checks back on
	sqlquery("SET foreign_key_checks = 1");
?>
<div class="container">
	<section>
		<p>Extension created successfully.</p>
	</section>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>extensions/build/download/" class="button blue">Download</a>
	</footer>
</div>