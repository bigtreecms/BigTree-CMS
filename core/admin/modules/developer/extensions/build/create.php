<?php
	// First we need to package the file so they can download it manually if they wish.
	if (!is_writable(SERVER_ROOT."cache/") || !BigTree::isDirectoryWritable(SERVER_ROOT."extensions/$id/")) {
?>
<div class="container">
	<section>
		<h3>Error</h3>
		<p>Your /cache/ and /extensions/<?=$id?>/ directories must be writable.</p>
	</section>
</div>
<?php
		$admin->stop();
	}
	
	// Fix keywords into an array
	$keywords = explode(",", $keywords);
	foreach ($keywords as &$word) {
		$word = trim($word);
	}
	
	// Fix licenses into an array
	if (array_filter((array) $licenses)) {
		$license_array = [];
		foreach ($licenses as $license) {
			$license_array[$license] = $available_licenses["Open Source"][$license];
		}
	} elseif ($license_name) {
		$license_array = [$license_name => $license_url];
	} elseif ($license) {
		$license_array = [$license => $available_licenses["Closed Source"][$license]];
	}
	
	// Create extension directory if it doesn't exist
	$extension_root = SERVER_ROOT."extensions/$id/";
	if (!file_exists($extension_root)) {
		BigTree::makeDirectory($extension_root);
	}
	
	// Setup JSON manifest
	$package = [
		"type" => "extension",
		"id" => $id,
		"version" => $version,
		"revision" => 1,
		"compatibility" => $compatibility,
		"title" => $title,
		"description" => $description,
		"keywords" => $keywords,
		"author" => $author,
		"licenses" => $license_array ?? [],
		"components" => [
			"module_groups" => [],
			"modules" => [],
			"templates" => [],
			"callouts" => [],
			"settings" => [],
			"feeds" => [],
			"field_types" => [],
			"tables" => []
		]
	];
	
	// We're going to be associating things to the extension before creating it
	sqlquery("SET foreign_key_checks = 0");
	
	$used_forms = [];
	$used_views = [];
	$used_reports = [];
	$extension = $id;
	
	foreach (array_filter((array) $module_groups) as $group) {
		$package["components"]["module_groups"][] = $admin->getModuleGroup($group);
	}
	
	foreach (array_filter((array) $callouts) as $callout) {
		if (strpos($callout, "*") === false) {
			BigTreeJSONDB::update("callouts", $callout, [
				"extension" => $id,
				"id" => $id."*".$callout
			]);
			
			$callout = $id."*".$callout;
		}
		$package["components"]["callouts"][] = $admin->getCallout($callout);
	}
	
	foreach (array_filter((array) $feeds) as $feed) {
		$feed = BigTreeJSONDB::get("feeds", $feed);
		
		if (empty($feed["extension"])) {
			BigTreeJSONDB::update("feeds", $feed["id"], ["route" => $extension."/".$feed["route"], "extension" => $extension]);
		}
		
		$package["components"]["feeds"][] = $feed;
	}
	
	foreach (array_filter((array) $settings) as $setting) {
		if (strpos($setting, "*") === false) {
			BigTreeJSONDB::update("settings", $setting, ["id" => $extension."*".$setting, "extension" => $extension]);
			$setting = "$id*$setting";
		}
		
		$package["components"]["settings"][] = $admin->getSetting($setting);
	}
	
	// Setup anonymous function for converting old field type IDs to new ones
	$field_type_converter = function ($table, $field, $subset = "") {
		global $id, $type;
		
		if ($subset) {
			$modules = BigTreeJSONDB::getAll("modules");
			
			foreach ($modules as $module) {
				$context = BigTreeJSONDB::getSubset("modules", $module["id"]);
				$rows = $context->getAll($subset);
				
				foreach ($rows as $row) {
					$array = $row[$field];
					
					foreach ($array as &$item) {
						if ($item["type"] == $type) {
							$item["type"] = $id."*".$type;
						} elseif ($item["type"] == "matrix") {
							if (empty($item["settings"])) {
								$item["settings"] = $item["options"];
							}
							
							foreach ($item["settings"]["columns"] as &$column) {
								if ($column["type"] == $type) {
									$column["type"] = $id."*".$type;
								}
							}
						}
					}
					
					$context->update($subset, $row["id"], [$field => $array]);
				}
			}
		} else {
			$rows = BigTreeJSONDB::getAll($table);
			
			foreach ($rows as $row) {
				$array = $row[$field];
				
				foreach ($array as &$item) {
					if ($item["type"] == $type) {
						$item["type"] = $id."*".$type;
					} elseif ($item["type"] == "matrix") {
						if (empty($item["settings"])) {
							$item["settings"] = $item["options"];
						}
						
						foreach ($item["settings"]["columns"] as &$column) {
							if ($column["type"] == $type) {
								$column["type"] = $id."*".$type;
							}
						}
					}
				}
				
				BigTreeJSONDB::update($table, $row["id"], [$field => $array]);
			}
		}
	};
	
	foreach (array_filter((array) $field_types) as $type) {
		// Currently non-extension field type becoming an extension one
		if (strpos($type, "*") === false) {
			BigTreeJSONDB::update("field-types", $type, [
				"extension" => $extension,
				"id" => $extension."*".$type
			]);
			
			// Convert old usage of field type ID to extension usage
			$field_type_converter("templates", "resources");
			$field_type_converter("callouts", "resources");
			$field_type_converter("modules", "fields", "forms");
			$field_type_converter("modules", "fields", "embeddable-forms");
			
			$settings = BigTreeJSONDB::getAll("settings");
			
			foreach ($settings as $setting) {
				if ($setting["type"] == $type) {
					BigTreeJSONDB::update("settings", $setting["id"], ["type" => $extension."*".$type]);
				}
			}
			
			// Move files into new location
			if (file_exists(SERVER_ROOT."custom/admin/field-types/$type/")) {
				BigTree::moveFile(SERVER_ROOT."custom/admin/field-types/$type/draw.php", $extension_root."field-types/$type/draw.php");
				BigTree::moveFile(SERVER_ROOT."custom/admin/field-types/$type/process.php", $extension_root."field-types/$type/process.php");
				BigTree::moveFile(SERVER_ROOT."custom/admin/field-types/$type/settings.php", $extension_root."field-types/$type/settings.php");
				@unlink(SERVER_ROOT."custom/admin/field-types/$type/");
			} else {
				BigTree::moveFile(SERVER_ROOT."custom/admin/ajax/developer/field-options/$type.php", $extension_root."field-types/$type/settings.php");
				BigTree::moveFile(SERVER_ROOT."custom/admin/form-field-types/draw/$type.php", $extension_root."field-types/$type/draw.php");
				BigTree::moveFile(SERVER_ROOT."custom/admin/form-field-types/process/$type.php", $extension_root."field-types/$type/process.php");
			}
			
			// Change type ID
			$type = "$id*$type";
		}
		$package["components"]["field_types"][] = $admin->getFieldType($type);
	}
	
	foreach (array_filter((array) $templates) as $template) {
		if (strpos($template, "*") === false) {
			BigTreeJSONDB::update("templates", $template, [
				"extension" => $extension,
				"id" => $extension."*".$template
			]);
			$template = "$id*$template";
		}
		
		$package["components"]["templates"][] = $cms->getTemplate($template);
	}
	
	foreach (array_filter((array) $modules) as $module) {
		$module = $admin->getModule($module);
		
		// If the module isn't namespaced yet, namespace it
		if (empty($module["extension"])) {
			$new_route = $extension."*".$module["route"];
			BigTreeJSONDB::update("modules", $module["id"], [
				"route" => $new_route,
				"extension" => $extension
			]);
		} else {
			$new_route = false;
		}
		
		$module["actions"] = $admin->getModuleActions($module["id"]);
		
		// Loop through actions to update URLs for preview / return if we've moved this module into an extension namespace
		if ($new_route) {
			foreach ($module["actions"] as $a) {
				// Adjust return view URLs for forms
				if ($a["form"]) {
					$form = BigTreeAutoModule::getForm($a["form"]);
					
					if ($form && $form["return_url"]) {
						$context = BigTreeJSONDB::getSubset("modules", $module["id"]);
						$context->update("forms", $a["form"], ["return_url" => str_replace($module["route"]."/", $new_route."/", $form["return_url"])]);
					}
					// Adjust preview URLs for views
				} elseif ($a["view"]) {
					$view = BigTreeAutoModule::getView($a["view"]);
					
					if ($view && $view["preview_url"]) {
						$context = BigTreeJSONDB::getSubset("modules", $module["id"]);
						$context->update("views", $a["view"], ["preview_url" => str_replace($module["route"]."/", $new_route."/", $view["preview_url"])]);
					}
				}
			}
		}
		
		$module["views"] = $admin->getModuleViews("title", $module["id"]);
		$module["forms"] = $admin->getModuleForms("title", $module["id"]);
		$module["embed_forms"] = $admin->getModuleEmbedForms("title", $module["id"]);
		$module["reports"] = $admin->getModuleReports("title", $module["id"]);
		
		$package["components"]["modules"][] = $module;
	}
	
	foreach (array_filter((array) $tables) as $table) {
		// Set the table to the create statement
		$f = sqlfetch(sqlquery("SHOW CREATE TABLE `$table`"));
		$create_statement = str_replace(["\r", "\n"], " ", end($f));
		
		// Drop auto increments and constraint names
		$create_statement = preg_replace('/(AUTO_INCREMENT\=\d*\s)/', "", $create_statement);
		$create_statement = preg_replace("/CONSTRAINT `([^`]*)`/i", "", $create_statement);
		
		$package["components"]["tables"][$table] = $create_statement;
	}
	
	// Move all the files into the extensions directory
	foreach ((array) $files as $file) {
		$file = str_replace(SERVER_ROOT, "", $file);
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
					BigTree::copyFile(SERVER_ROOT.$file, SERVER_ROOT."extensions/$id/public/".str_replace("site/extensions/$id/", "", $file));
					// Move into the site/extensions/ folder and then copy into /public/
				} else {
					BigTree::moveFile(SERVER_ROOT.$file, SITE_ROOT."extensions/$id/".substr($file, 5));
					BigTree::copyFile(SITE_ROOT."extensions/$id/".substr($file, 5), SERVER_ROOT."extensions/$id/public/".substr($file, 5));
				}
			}
			
			// If we have a place to move it to, move it.
			if ($d) {
				BigTree::moveFile(SERVER_ROOT.$file, SERVER_ROOT."extensions/$id/".$d);
			}
		}
	}
	
	// If this package already exists, we need to do a diff of the tables, increment revision numbers, and add SQL statements.
	$existing = BigTreeJSONDB::get("extensions", $id);
	
	if ($existing) {
		$existing_json = $existing["manifest"];
		
		// Increment revision numbers
		$revision = $package["revision"] = intval($existing_json["revision"]) + 1;
		$package["sql_revisions"] = (array) $existing_json["sql_revisions"] ?? [];
		$package["sql_revisions"][$revision] = [];
		
		// Diff the old tables
		foreach ($existing_json["components"]["tables"] as $table => $create_statement) {
			// If the table exists in the new manifest, we're going to see if they're identical
			if (isset($package["components"]["tables"][$table])) {
				// We're going to create a temporary table of the old structure to compare to the current table
				$create_statement = preg_replace("/CREATE TABLE `([^`]*)`/i", "CREATE TABLE `bigtree_extension_temp`", $create_statement);
				$create_statement = preg_replace("/CONSTRAINT `([^`]*)`/i", "", $create_statement);
				sqlquery("DROP TABLE IF EXISTS `bigtree_extension_temp`");
				sqlquery($create_statement);
				
				// Compare the tables, if we have changes to make, store them in a SQL revisions portion of the manifest
				$transition_statements = BigTree::tableCompare("bigtree_extension_temp", $table);
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
	
	// Write the manifest file
	$json = BigTree::json($package);
	BigTree::putFile(SERVER_ROOT."extensions/$id/manifest.json", $json);
	
	// Create the zip, clear caches since we may have moved the routes of field types and modules
	@unlink(SERVER_ROOT."cache/package.zip");
	@unlink(SERVER_ROOT."cache/bigtree-form-field-types.json");
	@unlink(SERVER_ROOT."cache/bigtree-module-class-list.json");
	
	include BigTree::path("inc/lib/pclzip.php");
	
	$zip = new PclZip(SERVER_ROOT."cache/package.zip");
	$zip->create(BigTree::directoryContents(SERVER_ROOT."extensions/$id/"), PCLZIP_OPT_REMOVE_PATH, SERVER_ROOT."extensions/$id/");
	
	// Store it in the database for future updates -- existing packages might be replaced
	if (BigTreeJSONDB::exists("extensions", $id)) {
		BigTreeJSONDB::update("extensions", $id, [
			"name" => $title,
			"version" => $version,
			"last_updated" => date("Y-m-d H:i:s"),
			"manifest" => $package
		]);
	} else {
		BigTreeJSONDB::insert("extensions", [
			"id" => $id,
			"name" => $title,
			"version" => $version,
			"last_updated" => date("Y-m-d H:i:s"),
			"manifest" => $package
		]);
	}
	
	// Turn foreign key checks back on
	sqlquery("SET foreign_key_checks = 1");
	
	$admin->cacheHooks();
?>
<div class="container">
	<section>
		<p>Extension created successfully.</p>
	</section>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>extensions/build/download/" class="button blue">Download</a>
	</footer>
</div>