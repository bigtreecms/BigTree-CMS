<?php
	namespace BigTree;
	
	/**
	 * @global array $author
	 * @global array $available_licenses
	 * @global array $callouts
	 * @global array $extension_settings
	 * @global array $feeds
	 * @global array $field_types
	 * @global array $files
	 * @global array $licenses
	 * @global array $module_groups
	 * @global array $modules
	 * @global array $settings
	 * @global array $tables
	 * @global array $templates
	 * @global string $compatibility
	 * @global string $description
	 * @global string $id
	 * @global string $keywords
	 * @global string $license
	 * @global string $license_name
	 * @global string $license_url
	 * @global string $title
	 * @global string $version
	 */
	
	// First we need to package the file so they can download it manually if they wish.
	if (!is_writable(SERVER_ROOT."cache/") || !FileSystem::getDirectoryWritability(SERVER_ROOT."extensions/$id/")) {
		Auth::stop("Your /cache/ and /extensions/$id/ directories must be writable.",
					Router::getIncludePath("admin/layouts/_error.php"));
	}
	
	// Fix keywords into an array
	$keywords = explode(",", $keywords);
	$keywords = array_map("trim", $keywords);
	
	// Fix licenses into an array
	$license_array = array();
	
	if (array_filter((array) $licenses)) {
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
		FileSystem::createDirectory($extension_root);
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
	SQL::query("SET foreign_key_checks = 0");
	
	$extension = SQL::escape($id);
	
	foreach (array_filter((array) $module_groups) as $group_id) {
		$group = new ModuleGroup($group_id);
		$package["components"]["module_groups"][] = $group->Array;
	}
	
	foreach (array_filter((array) $callouts) as $callout_id) {
		if (strpos($callout_id, "*") === false) {
			SQL::query("UPDATE bigtree_callouts SET id = CONCAT('$extension*',id), extension = '$extension' WHERE id = ?", $callout_id);
			$callout_id = "$id*$callout_id";
		}
		
		$callout = new Callout($callout_id);
		$package["components"]["callouts"][] = $callout->Array;
	}
	
	foreach (array_filter((array) $feeds) as $feed_id) {
		SQL::query("UPDATE bigtree_feeds SET route = CONCAT('$extension/',route), extension = '$extension' WHERE id = ?", $feed_id);

		$feed = new Feed($feed_id);
		$package["components"]["feeds"][] = $feed->Array;
	}
	
	foreach (array_filter((array) $settings) as $setting_id) {
		if (strpos($setting_id, "*") === false) {
			SQL::query("UPDATE bigtree_settings SET id = CONCAT('$extension*',id), extension = '$extension' WHERE id = ?", $setting_id);
			$setting_id = "$id*$setting_id";
		}
		
		$setting = new Setting($setting_id);
		$package["components"]["settings"][] = $setting->Array;
	}
	
	// Setup anonymous function for converting old field type IDs to new ones
	$field_type_converter = function ($table, $field) {
		global $id, $type;
		
		$q = SQL::query("SELECT * FROM `$table` 
						 WHERE `$field` LIKE '%\"type\":\"".SQL::escape($type)."\"%' 
						    OR `$field` LIKE '%\"type\": \"".SQL::escape($type)."\"%'");
		
		while ($f = $q->fetch()) {
			if ($field == "settings") {
				$settings = json_decode($f["settings"]);
				$array = $settings["fields"];
			} else {
				$array = json_decode($f[$field], true);
			}
			
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
			
			if ($field == "settings") {
				$settings["fields"] = $array;
				SQL::update($table, $f["id"], array("settings" => $settings));
			} else {
				SQL::update($table, $f["id"], array($field => $array));
			}
		}
	};
	
	foreach (array_filter((array) $field_types) as $type_id) {
		// Currently non-extension field type becoming an extension one
		if (strpos($type_id, "*") === false) {
			SQL::query("UPDATE bigtree_field_types SET extension = '$extension', id = CONCAT('$extension*',id) WHERE id = ?", $type_id);
			
			// Convert old usage of field type ID to extension usage
			$field_type_converter("bigtree_templates", "resources");
			$field_type_converter("bigtree_callouts", "resources");
			$field_type_converter("bigtree_module_interfaces", "settings");
			SQL::query("UPDATE bigtree_settings SET `type` = CONCAT('$extension*',type)  WHERE `type` = ?", $type_id);
			
			// Move files into new format
			FileSystem::moveFile(SERVER_ROOT."custom/admin/form-field-types/draw/$type_id.php", $extension_root."field-types/$type_id/draw.php");
			FileSystem::moveFile(SERVER_ROOT."custom/admin/form-field-types/process/$type_id.php", $extension_root."field-types/$type_id/process.php");
			FileSystem::moveFile(SERVER_ROOT."custom/admin/ajax/developer/field-options/$type_id.php", $extension_root."field-types/$type_id/options.php");
			
			// Change type ID
			$type_id = "$id*$type_id";
		}
		
		$type = new FieldType($type_id);
		$package["components"]["field_types"][] = $type->Array;
	}
	
	foreach (array_filter((array) $templates) as $template_id) {
		if (strpos($template_id, "*") === false) {
			SQL::query("UPDATE bigtree_templates SET extension = '$extension', id = CONCAT('$extension*',id) WHERE id = ?", $template_id);
			$template_id = "$id*$template_id";
		}
		
		$template = new Template($template_id);
		$package["components"]["templates"][] = $template->Array;
	}
	
	foreach (array_filter((array) $modules) as $module_id) {
		$module = new Module($module_id);
		
		// If the module isn't namespaced yet, namespace it
		if (strpos($module->Route, "*") === false) {
			SQL::query("UPDATE bigtree_modules SET route = CONCAT('$extension*', route), extension = '$extension' 
						WHERE id = ?", $module->ID);
			$new_route = $extension."*".$module->Route;
		} else {
			SQL::query("UPDATE bigtree_modules SET extension = '$extension' WHERE id = ?", $module->ID);
			$new_route = false;
		}
		
		$module = $module->Array;
		$module["actions"] = ModuleAction::allByModule($module["id"], "position DESC, id ASC", true);

		// Loop through actions to update URLs for preview / return if we've moved this module into an extension namespace
		if ($new_route) {
			foreach ($module["actions"] as $action) {
				if ($action["interface"]) {
					$interface = SQL::fetch("SELECT * FROM bigtree_module_interfaces WHERE id = ?", $action["interface"]);
					$settings = json_decode($interface["settings"], true);
					
					if ($settings["return_url"]) {
						$settings["return_url"] = str_replace("{adminroot}".$module["route"]."/", "{adminroot}$new_route/", $settings["return_url"]);
					}
					
					if ($settings["preview_url"]) {
						$settings["preview_url"] = str_replace("{adminroot}".$module["route"]."/", "{adminroot}$new_route/", $settings["preview_url"]);
					}
					
					SQL::update("bigtree_module_interfaces", $interface["id"], array("settings" => $settings));
				}
			}
		}
		
		$module["views"] = ModuleView::allByModule($module["id"], "title ASC", true);
		$module["forms"] = ModuleForm::allByModule($module["id"], "title ASC", true);
		$module["embed_forms"] = ModuleEmbedForm::allByModule($module["id"], "title ASC", true);
		$module["reports"] = ModuleReport::allByModule($module["id"], "title ASC", true);
		
		$package["components"]["modules"][] = $module;
	}
	
	foreach (array_filter((array) $tables) as $table) {
		// Set the table to the create statement
		$f = SQL::fetch("SHOW CREATE TABLE `$table`");
		$create_statement = str_replace(array("\r", "\n"), " ", end($f));
		
		// Drop auto increments and constraint names
		$create_statement = preg_replace('/(AUTO_INCREMENT\=\d*\s)/', "", $create_statement);
		$create_statement = preg_replace("/CONSTRAINT `([^`]*)`/i", "", $create_statement);
		
		$package["components"]["tables"][$table] = $create_statement;
	}
	
	// Move all the files into the extensions directory
	foreach (array_filter((array) $files) as $file) {
		$file = Text::replaceServerRoot($file);
		if (substr($file, 0, 11) != "extensions/") {
			$d = false;
			
			// We need to determine where files should be moved to based on their original file structure
			if (substr($file, 0, 18) == "custom/admin/ajax/") {
				$d = "ajax/".substr($file, 18);
			} elseif (substr($file, 0, 17) == "custom/admin/css/") {
				$d = "css/".substr($file, 17);
			} elseif (substr($file, 0, 16) == "custom/admin/js/") {
				$d = "js/".substr($file, 16);
			} elseif (substr($file, 0, 20) == "custom/admin/images/") {
				$d = "images/".substr($file, 20);
			} elseif (substr($file, 0, 21) == "custom/admin/modules/") {
				$d = "modules/".substr($file, 21);
			} elseif (substr($file, 0, 19) == "custom/inc/modules/") {
				$d = "classes/".substr($file, 19);
			} elseif (substr($file, 0, 10) == "templates/") {
				$d = $file;
			} elseif (substr($file, 0, 5) == "site/") {
				// Already in the proper directory, should be copied to public, not moved
				if (strpos($file, "site/extensions/$id/") === 0) {
					FileSystem::copyFile(SERVER_ROOT.$file, SERVER_ROOT."extensions/$id/public/".str_replace("site/extensions/$id/", "", $file));
					// Move into the site/extensions/ folder and then copy into /public/
				} else {
					FileSystem::moveFile(SERVER_ROOT.$file, SITE_ROOT."extensions/$id/".substr($file, 5));
					FileSystem::copyFile(SITE_ROOT."extensions/$id/".substr($file, 5), SERVER_ROOT."extensions/$id/public/".substr($file, 5));
				}
			}
			
			// If we have a place to move it to, move it.
			if ($d) {
				FileSystem::moveFile(SERVER_ROOT.$file, SERVER_ROOT."extensions/$id/".$d);
			}
		}
	}
	
	// If this package already exists, we need to do a diff of the tables, increment revision numbers, and add SQL statements.
	$existing = SQL::fetch("SELECT * FROM bigtree_extensions WHERE id = ? AND type = 'extension'", $id);
	if (!empty($existing)) {
		$existing_json = json_decode($existing["manifest"], true);
		
		// Increment revision numbers
		$revision = $package["revision"] = intval($existing_json["revision"]) + 1;
		$package["sql_revisions"] = (array) $existing_json["sql_revisions"];
		$package["sql_revisions"][$revision] = array();
		
		// Diff the old tables
		foreach ($existing_json["components"]["tables"] as $table => $create_statement) {
			// If the table exists in the new manifest, we're going to see if they're identical
			if (isset($package["components"]["tables"][$table])) {
				// We're going to create a temporary table of the old structure to compare to the current table
				$create_statement = preg_replace("/CREATE TABLE `([^`]*)`/i", "CREATE TABLE `bigtree_extension_temp`", $create_statement);
				$create_statement = preg_replace("/CONSTRAINT `([^`]*)`/i", "", $create_statement);
				SQL::query("DROP TABLE IF EXISTS `bigtree_extension_temp`");
				SQL::query($create_statement);
				
				// Compare the tables, if we have changes to make, store them in a SQL revisions portion of the manifest
				$transition_statements = SQL::compareTables("bigtree_extension_temp", $table);
				foreach ($transition_statements as $statement) {
					// Don't include changes to auto increment
					if (stripos($statement, "auto_increment = ") === false) {
						$package["sql_revisions"][$revision][] = str_replace("`bigtree_extension_temp`", "`$table`", $statement);
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
	
	// Store it in the database for future updates -- existing packages might be replaced
	if (SQL::exists("bigtree_extensions", $id)) {
		// Grab existing manifest and get its plugin list since this is handled manually
		$existing_manifest = json_decode(file_get_contents(SERVER_ROOT."extensions/$id/manifest.json"), true);
		$package["plugins"] = $existing_manifest["plugins"];
		SQL::update("bigtree_extensions", $id, array(
			"type" => "extension",
			"name" => $title,
			"version" => $version,
			"manifest" => $package
		));
	} else {
		SQL::insert("bigtree_extensions", array(
			"id" => $id,
			"type" => "extension",
			"name" => $title,
			"version" => $version,
			"manifest" => $package
		));
	}
	
	// Turn foreign key checks back on
	SQL::query("SET foreign_key_checks = 1");
	
	// Write the manifest file
	FileSystem::createFile(SERVER_ROOT."extensions/$id/manifest.json", JSON::encode($package));
	
	// Create the zip, clear caches since we may have moved the routes of field types and modules
	FileSystem::deleteFile(SERVER_ROOT."cache/package.zip");
	FileSystem::deleteFile(SERVER_ROOT."cache/bigtree-form-field-types.json");
	FileSystem::deleteFile(SERVER_ROOT."cache/bigtree-module-cache.json");
	include Router::getIncludePath("inc/lib/pclzip.php");
	
	$zip = new \PclZip(SERVER_ROOT."cache/package.zip");
	$zip->create(FileSystem::getDirectoryContents(SERVER_ROOT."extensions/$id/"), PCLZIP_OPT_REMOVE_PATH, SERVER_ROOT."extensions/$id/");
?>
<div class="container">
	<section>
		<p><?=Text::translate("Extension created successfully.")?></p>
	</section>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>extensions/build/download/" class="button blue"><?=Text::translate("Download")?></a>
	</footer>
</div>