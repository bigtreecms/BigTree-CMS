<?
	BigTree::globalizePOSTVars();

	$p = &$_SESSION["bigtree_admin"]["developer"]["package"];
	$p["module_groups"] = array();
	$p["modules"] = $modules;
	$p["templates"] = $templates;
	$p["callouts"] = $callouts;
	$p["settings"] = $settings;
	$p["feeds"] = $feeds;
	$p["field_types"] = $field_types;

	// Get a list of custom field types so we can auto include any that modules rely on
	$ft = $admin->getFieldTypes();
	foreach ($ft as $type) {
		$custom_field_types[$type["id"]] = true;
	}

	// Infer tables/files to include from the modules
	foreach ((array)$modules as $module_id) {
		if ($module_id) {
			$module = $admin->getModule($module_id);
			$actions = $admin->getModuleActions($module_id);
			// Get all the tables of the module's actions.
			foreach ($actions as $action) {
				if ($action["form"] || $action["view"]) {
					if ($action["form"]) {
						$auto = BigTreeAutoModule::getForm($action["form"]);
						// Figure out what tables and field types the form uses and automatically add them.
						foreach ($auto["fields"] as $field) {
							// Database populated list? Include the table it pulls from.
							if ($field["type"] == "list" && $field["list_type"] == "db") {
								if (!in_array($field["pop-table"]."#structure",$p["tables"]) && substr($field["pop-table"],0,8) != "bigtree_") {
									$p["tables"][] = $field["pop-table"]."#structure";
								}
							}
							// Many to many? Include the connecting and "other" table.
							if ($field["type"] == "many-to-many") {
								if (!in_array($field["mtm-connecting-table"]."#structure",$p["tables"]) && substr($field["mtm-connecting-table"],0,8) != "bigtree_") {
									$p["tables"][] = $field["mtm-connecting-table"]."#structure";
								}
								if (!in_array($field["mtm-other-table"]."#structure",$p["tables"]) && substr($field["mtm-other-table"],0,8) != "bigtree_") {
									$p["tables"][] = $field["mtm-other-table"]."#structure";
								}
							}
							// Include the custom field type if it was forgotten.
							if (isset($custom_field_types[$field["type"]])) {
								if (!in_array($field["type"],$p["field_types"])) {
									$p["field_types"][] = $field["type"];
								}
							}
						}
					// For views/reports we just care about what table it's from
					} else {
						if ($action["view"]) {
							$auto = BigTreeAutoModule::getView($action["view"]);
						} elseif ($action["report"]) {
							$auto = BigTreeAutoModule::getReport($action["report"]);
						}
					}
	
					if (!in_array($auto["table"]."#structure",$p["tables"])) {
						$p["tables"][] = $auto["table"]."#structure";
					}
				}
				if ($module["group"]) {
					$p["module_groups"][] = $module["group"];
				}
			}
			
			
			// Search the class files directory to see if one exists in there with our route.
			if (file_exists(SERVER_ROOT."custom/inc/modules/".$module["route"].".php")) {
				$p["files"][] = SERVER_ROOT."custom/inc/modules/".$module["route"].".php";
			}
			
			// Search the class files directory to see if one exists in there with our route.
			if (file_exists(SERVER_ROOT."custom/inc/required/".$module["route"].".php")) {
				$p["files"][] = SERVER_ROOT."custom/inc/required/".$module["route"].".php";
			}
			
			// Check the custom/admin/modules/{module}/ directory, related ajax directory, related images directory
			$contents = array_merge((array)BigTree::directoryContents(SERVER_ROOT."custom/admin/modules/".$module["route"]."/"),(array)BigTree::directoryContents(SERVER_ROOT."custom/admin/ajax/".$module["route"]."/"),(array)BigTree::directoryContents(SERVER_ROOT."custom/admin/images/".$module["route"]."/"));
			foreach ($contents as $file) {
				if (is_file($file)) {
					$p["files"][] = $file;
				}
			}
			// Include related CSS/JS files if named properly
			if (file_exists(SERVER_ROOT."custom/admin/css/".$module["route"].".css")) {
				$p["files"][] = SERVER_ROOT."custom/admin/css/".$module["route"].".css";
			}
			if (file_exists(SERVER_ROOT."custom/admin/js/".$module["route"].".js")) {
				$p["files"][] = SERVER_ROOT."custom/admin/js/".$module["route"].".js";
			}
		}
	}
	// Bring in files for templates
	foreach ((array)$templates as $template) {
		if ($template) {
			if (is_dir(SERVER_ROOT."templates/routed/$template/")) {
				$contents = BigTree::directoryContents(SERVER_ROOT."templates/routed/$template/");
				foreach ($contents as $c) {
					if (is_file($c)) {
						$p["files"][] = $c;					
					}
				}
			} elseif (file_exists(SERVER_ROOT."templates/basic/$template.php")) {
				$p["files"][] = SERVER_ROOT."templates/basic/$template.php";
			}
		}
	}
	// Files for callouts
	foreach ((array)$callouts as $callout) {
		if ($callout) {
			if (file_exists(SERVER_ROOT."templates/callouts/$callout.php")) {
				$p["files"][] = SERVER_ROOT."templates/callouts/$callout.php";
			}
		}
	}
	// Files for field types -- we use the $p version here because we may have added some when checking the module
	foreach ((array)$p["field_types"] as $type) {
		if ($type) {
			if (file_exists(SERVER_ROOT."custom/admin/form-field-types/draw/$type.php")) {
				$p["files"][] = SERVER_ROOT."custom/admin/form-field-types/draw/$type.php";
			}
			if (file_exists(SERVER_ROOT."custom/admin/form-field-types/process/$type.php")) {
				$p["files"][] = SERVER_ROOT."custom/admin/form-field-types/process/$type.php";
			}
			if (file_exists(SERVER_ROOT."custom/admin/ajax/developer/field-options/$type.php")) {
				$p["files"][] = SERVER_ROOT."custom/admin/ajax/developer/field-options/$type.php";
			}
		}
	}	

	// Make sure we have no dupes
	$p["module_groups"] = array_unique($p["module_groups"]);
	$p["modules"] = array_unique($p["modules"]);
	$p["templates"] = array_unique($p["templates"]);
	$p["callouts"] = array_unique($p["callouts"]);
	$p["settings"] = array_unique($p["settings"]);
	$p["feeds"] = array_unique($p["feeds"]);
	$p["field_types"] = array_unique($p["field_types"]);
	$p["files"] = array_unique($p["files"]);
	$p["tables"] = array_unique($p["tables"]);
	// Sort them to make them easier to read
	foreach ($p as &$part) {
		if (is_array($part)) {
			asort($part);
		}
	}

	BigTree::redirect(DEVELOPER_ROOT."packages/build/files/");
?>