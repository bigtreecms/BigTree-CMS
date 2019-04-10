<?php
	namespace BigTree;
	
	$bigtree["group_match"] = $bigtree["module_match"] = $bigtree["route_match"] = $bigtree["class_name_match"] = $bigtree["form_id_match"] = $bigtree["view_id_match"] = $bigtree["report_id_match"] = array();
	
	$json = json_decode(file_get_contents(SERVER_ROOT."cache/package/manifest.json"), true);
	
	// Run SQL
	foreach ($json["sql"] as $sql) {
		SQL::query($sql);
	}
	
	SQL::query("SET foreign_key_checks = 0");
	
	// Import module groups
	foreach ($json["components"]["module_groups"] as &$group) {
		if ($group) {
			$module_group = ModuleGroup::create($group["name"]);
			$bigtree["group_match"][$group["id"]] = $module_group->ID;
			
			// Update the group ID since we're going to save this manifest locally for uninstalling
			$group["id"] = $module_group->ID;
		}
	}
	
	// Import modules
	foreach ($json["components"]["modules"] as &$module) {
		if ($module) {
			$group = ($module["group"] && isset($bigtree["group_match"][$module["group"]])) ? $bigtree["group_match"][$module["group"]] : null;
			$route = SQL::unique("bigtree_modules", "route", $module["route"]);
			
			// Create the module
			$module_id = SQL::insert("bigtree_modules", array(
				"name" => $module["name"],
				"route" => $route,
				"class" => $module["class"],
				"icon" => $module["icon"],
				"group" => $group,
				"gbp" => $module["gbp"]
			));
			
			$bigtree["module_match"][$module["id"]] = $module_id;
			$bigtree["route_match"][$module["route"]] = $route;

			// Update the module ID since we're going to save this manifest locally for uninstalling
			$module["id"] = $module_id;
			
			// Create views
			$views_to_update = array();
			
			foreach ($module["views"] as $view) {
				$module_view = ModuleView::create($module_id, $view["title"], $view["description"], $view["table"], $view["type"], Utils::arrayValue($view["options"]), Utils::arrayValue($view["fields"]), Utils::arrayValue($view["actions"]), $view["related_form"], $view["preview_url"]);
				$bigtree["view_id_match"][$view["id"]] = $module_view->ID;
				
				if ($view["related_form"]) {
					$views_to_update[] = $module_view->ID;
				}
			}
			
			// Create regular forms
			foreach ($module["forms"] as $form) {
				// 4.1 package compatibility
				if (!is_array($form["hooks"])) {
					$form["hooks"] = array("pre" => $form["preprocess"], "post" => $form["callback"], "publish" => false);
				}
				
				$module_form = ModuleForm::create($module_id, $form["title"], $form["table"], Utils::arrayValue($form["fields"]), $form["hooks"], $form["default_position"], ($form["return_view"] ? $bigtree["view_id_match"][$form["return_view"]] : false), $form["return_url"], $form["tagging"]);
				$bigtree["form_id_match"][$form["id"]] = $module_form->ID;
			}
			
			// Update views with their new related form value
			foreach ($views_to_update as $id) {
				$settings = json_decode(SQL::fetchSingle("SELECT settings FROM bigtree_module_interfaces WHERE id = ?", $id), true);
				$settings["related_form"] = $bigtree["form_id_match"][$settings["related_form"]];
				SQL::update("bigtree_module_interfaces", $id, array("settings" => $settings));
			}
			
			// Create reports
			foreach ($module["reports"] as $report) {
				$module_report = ModuleReport::create($module_id, $report["title"], $report["table"], $report["type"], Utils::arrayValue($report["filters"]), Utils::arrayValue($report["fields"]), $report["parser"], ($report["view"] ? $bigtree["view_id_match"][$report["view"]] : false));
				$bigtree["report_id_match"][$report["id"]] = $module_report->ID;
			}
			
			// Create actions
			foreach ($module["actions"] as $action) {
				// 4.1 and 4.2 compatibility
				if ($action["report"]) {
					$action["interface"] = $bigtree["report_id_match"][$action["report"]];
				} elseif ($action["form"]) {
					$action["interface"] = $bigtree["form_id_match"][$action["form"]];
				} elseif ($action["view"]) {
					$action["interface"] = $bigtree["view_id_match"][$action["view"]];
				}
				
				ModuleAction::create($module_id, $action["name"], $action["route"], $action["in_nav"], $action["class"], $action["interface"], $action["level"], $action["position"]);
			}
		}
	}
	
	// Import templates
	foreach ($json["components"]["templates"] as $template) {
		if ($template) {
			$resources = is_array($template["resources"]) ? $template["resources"] : json_decode($template["resources"], true);
			
			if (Template::exists($template["id"])) {
				$existing_template = new Template($template["id"]);
				$existing_template->delete();
			}

			Template::create($template["id"], $template["name"], $template["routed"], $template["level"], $bigtree["module_match"][$template["module"]], $resources);
		}
	}
	
	// Import callouts
	foreach ($json["components"]["callouts"] as $callout) {
		if ($callout) {
			$resources = is_array($callout["resources"]) ? $callout["resources"] : json_decode($callout["resources"], true);
			
			if (Callout::exists($callout["id"])) {
				$existing_callout = new Callout($callout["id"]);
				$existing_callout->delete();
			}

			Callout::create($callout["id"], $callout["name"], $callout["description"], $callout["level"], $resources, $callout["display_field"], $callout["display_default"]);
		}
	}
	
	// Import Settings
	foreach ($json["components"]["settings"] as $setting) {
		if ($setting) {
			SQL::delete("bigtree_settings", $setting["id"]);
			Setting::create($setting["id"], $setting["name"], $setting["description"], $setting["type"], $setting["settings"],
							$setting["extension"], $setting["system"], $setting["encrypted"], $setting["locked"]);
		}
	}
	
	// Import Feeds
	foreach ($json["components"]["feeds"] as $feed) {
		if ($feed) {
			SQL::delete("bigtree_feeds", array("route" => $feed["route"]));
			SQL::insert("bigtree_feeds", array(
				"route" => $feed["route"],
				"name" => $feed["name"],
				"description" => $feed["description"],
				"type" => $feed["type"],
				"table" => $feed["table"],
				"fields" => $feed["fields"],
				"options" => $feed["options"]
			));
		}
	}
	
	// Import Field Types
	foreach ($json["components"]["field_types"] as $type) {
		if ($type) {
			// Backwards compatibility with field types packaged for 4.1
			if (!isset($type["use_cases"])) {
				$type["use_cases"] = array(
					"templates" => $type["pages"],
					"modules" => $type["modules"],
					"callouts" => $type["callouts"],
					"settings" => $type["settings"]
				);
			}
			
			SQL::delete("bigtree_field_types", $type["id"]);
			SQL::insert("bigtree_field_types", array(
				"id" => $type["id"],
				"name" => $type["name"],
				"use_cases" => $type["use_cases"],
				"self_draw" => $type["self_draw"] ? "on" : null
			));
		}
	}
	
	// Import files
	foreach ($json["files"] as $file) {
		FileSystem::copyFile(SERVER_ROOT."cache/package/$file", SERVER_ROOT.$file);
	}
	
	// Empty view cache
	SQL::query("DELETE FROM bigtree_module_view_cache");
	
	// Remove the package directory
	FileSystem::deleteDirectory(SERVER_ROOT."cache/package/");
	
	// Clear module class cache and field type cache.
	FileSystem::deleteFile(SERVER_ROOT."cache/bigtree-module-cache.json");
	FileSystem::deleteFile(SERVER_ROOT."cache/bigtree-form-field-types.json");
	
	// Create package
	SQL::insert("bigtree_extensions", array(
		"id" => $json["id"],
		"type" => "package",
		"name" => $json["title"],
		"version" => $json["version"],
		"manifest" => $json
	));
	
	// Turn key checks back on
	SQL::query("SET foreign_key_checks = 1");
	
	Utils::growl("Developer", "Installed Package");
	Router::redirect(DEVELOPER_ROOT."packages/install/complete/");
	