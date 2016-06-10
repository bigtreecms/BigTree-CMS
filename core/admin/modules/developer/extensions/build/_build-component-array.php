<?php
	namespace BigTree;

	$package = &$_SESSION["bigtree_admin"]["developer"]["package"];
	$package["module_groups"] = array();
	$package["modules"] = array_filter((array) $_POST["modules"]);
	$package["templates"] = array_filter((array) $_POST["templates"]);
	$package["callouts"] = array_filter((array) $_POST["callouts"]);
	$package["settings"] = array_filter((array) $_POST["settings"]);
	$package["feeds"] = array_filter((array) $_POST["feeds"]);
	$package["field_types"] = array_filter((array) $_POST["field_types"]);

	// Get a list of custom field types so we can auto include any that modules rely on
	$field_types = FieldType::all("name ASC");

	foreach ($field_types as $type) {
		$custom_field_types[$type->ID] = true;
	}

	// Infer tables/files to include from the modules
	foreach ($package["modules"] as $module_id) {
		$module = new Module($module_id);
		$actions = $module->Actions;

		// Get all the tables of the module's actions.
		foreach ($actions as $action) {
			if ($action->Interface) {
				$interface = new ModuleInterface($action->Interface);

				// Forms we're going to lookup field types that could be used
				if ($interface->Type == "form") {
					$form = new ModuleForm($interface->Array);

					// Figure out what tables and field types the form uses and automatically add them.
					foreach ($form->Fields as $field) {
						// Database populated list? Include the table it pulls from.
						if ($field["type"] == "list" && $field["list_type"] == "db") {
							if (!in_array($field["pop-table"]."#structure", $package["tables"]) && substr($field["pop-table"], 0, 8) != "bigtree_") {
								$package["tables"][] = $field["pop-table"]."#structure";
							}
						}
						// Many to many? Include the connecting and "other" table.
						if ($field["type"] == "many-to-many") {
							if (!in_array($field["mtm-connecting-table"]."#structure", $package["tables"]) && substr($field["mtm-connecting-table"], 0, 8) != "bigtree_") {
								$package["tables"][] = $field["mtm-connecting-table"]."#structure";
							}
							if (!in_array($field["mtm-other-table"]."#structure", $package["tables"]) && substr($field["mtm-other-table"], 0, 8) != "bigtree_") {
								$package["tables"][] = $field["mtm-other-table"]."#structure";
							}
						}
						// Include the custom field type if it was forgotten.
						if (isset($custom_field_types[$field["type"]])) {
							if (!in_array($field["type"], $package["field_types"])) {
								$package["field_types"][] = $field["type"];
							}
						}
					}
				}

				if (!empty($interface->Table) && !in_array($interface->Table."#structure", $package["tables"])) {
					$package["tables"][] = $interface->Table."#structure";
				}
			}

			if ($module->Group) {
				$package["module_groups"][] = $module->Group;
			}
		}


		// Search the class files directory to see if one exists in there with our route.
		if (file_exists(SERVER_ROOT."custom/inc/modules/".$module->Route.".php")) {
			$package["files"][] = SERVER_ROOT."custom/inc/modules/".$module->Route.".php";
		}

		// Search the class files directory to see if one exists in there with our route.
		if (file_exists(SERVER_ROOT."custom/inc/required/".$module->Route.".php")) {
			$package["files"][] = SERVER_ROOT."custom/inc/required/".$module->Route.".php";
		}

		// Check the custom/admin/modules/{module}/ directory, related ajax directory, related images directory
		$contents = array_merge(
			(array) FileSystem::getDirectoryContents(SERVER_ROOT."custom/admin/modules/".$module->Route."/"),
			(array) FileSystem::getDirectoryContents(SERVER_ROOT."custom/admin/ajax/".$module->Route."/"),
			(array) FileSystem::getDirectoryContents(SERVER_ROOT."custom/admin/images/".$module->Route."/")
		);

		foreach ($contents as $file) {
			if (is_file($file)) {
				$package["files"][] = $file;
			}
		}
		// Include related CSS/JS files if named properly
		if (file_exists(SERVER_ROOT."custom/admin/css/".$module->Route.".css")) {
			$package["files"][] = SERVER_ROOT."custom/admin/css/".$module->Route.".css";
		}
		if (file_exists(SERVER_ROOT."custom/admin/js/".$module->Route.".js")) {
			$package["files"][] = SERVER_ROOT."custom/admin/js/".$module->Route.".js";
		}
	}

	// Bring in files for templates
	foreach ($package["templates"] as $template_id) {
		if (is_dir(SERVER_ROOT."templates/routed/$template_id/")) {
			$contents = FileSystem::getDirectoryContents(SERVER_ROOT."templates/routed/$template_id/");

			foreach (array_filter((array) $contents) as $c) {
				if (is_file($c)) {
					$package["files"][] = $c;
				}
			}
		} elseif (file_exists(SERVER_ROOT."templates/basic/$template_id.php")) {
			$package["files"][] = SERVER_ROOT."templates/basic/$template_id.php";
		}

		// Get template info to bring in extra field types
		$template = new Template($template_id);

		foreach ($template->Fields as $field) {
			// Include the custom field type if it was forgotten.
			if (isset($custom_field_types[$field["type"]])) {
				if (!in_array($field["type"], $package["field_types"])) {
					$package["field_types"][] = $field["type"];
				}
			}
		}
	}

	// Files for callouts
	foreach ($package["callouts"] as $callout_id) {
		if ($callout_id) {
			if (file_exists(SERVER_ROOT."templates/callouts/$callout_id.php")) {
				$package["files"][] = SERVER_ROOT."templates/callouts/$callout_id.php";
			}

			// Get callout info to bring in extra field types
			$callout = new Callout($callout_id);

			foreach ($callout->Fields as $field) {
				// Include the custom field type if it was forgotten.
				if (isset($custom_field_types[$field["type"]])) {
					if (!in_array($field["type"], $package["field_types"])) {
						$package["field_types"][] = $field["type"];
					}
				}
			}
		}
	}

	// Get settings to make sure they don't use a custom field type
	foreach ($package["settings"] as $setting_id) {
		$setting = new Setting($setting_id);

		if (isset($custom_field_types[$setting->Type])) {
			if (!in_array($setting->Type, $package["field_types"])) {
				$package["field_types"][] = $setting["type"];
			}
		}
	}

	// Files for field types -- we use the $p version here because we may have added some when checking the module
	foreach ($package["field_types"] as $type) {
		if (file_exists(SERVER_ROOT."custom/admin/form-field-types/draw/$type.php")) {
			$package["files"][] = SERVER_ROOT."custom/admin/form-field-types/draw/$type.php";
		}

		if (file_exists(SERVER_ROOT."custom/admin/form-field-types/process/$type.php")) {
			$package["files"][] = SERVER_ROOT."custom/admin/form-field-types/process/$type.php";
		}

		if (file_exists(SERVER_ROOT."custom/admin/ajax/developer/field-options/$type.php")) {
			$package["files"][] = SERVER_ROOT."custom/admin/ajax/developer/field-options/$type.php";
		}
	}

	// Add all the files in the extension directories
	$contents = array_merge(
		(array) FileSystem::getDirectoryContents(SITE_ROOT."extensions/".$package["id"]."/"),
		(array) FileSystem::getDirectoryContents(SERVER_ROOT."extensions/".$package["id"]."/")
	);

	foreach ($contents as $file) {
		if (!is_dir($file) && file_exists($file)) {
			$package["files"][] = $file;
		}
	}

	// Make sure we have no dupes
	$package["module_groups"] = array_unique($package["module_groups"]);
	$package["modules"] = array_unique($package["modules"]);
	$package["templates"] = array_unique($package["templates"]);
	$package["callouts"] = array_unique($package["callouts"]);
	$package["settings"] = array_unique($package["settings"]);
	$package["feeds"] = array_unique($package["feeds"]);
	$package["field_types"] = array_unique($package["field_types"]);
	$package["files"] = array_unique($package["files"]);
	$package["tables"] = array_unique($package["tables"]);

	// Sort them to make them easier to read
	foreach ($package as &$part) {
		if (is_array($part)) {
			asort($part);
		}
	}
	