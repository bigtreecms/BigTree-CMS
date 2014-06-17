<?
	$bigtree["group_match"] = $bigtree["module_match"] = $bigtree["route_match"] = $bigtree["class_name_match"] = $bigtree["form_id_match"] = $bigtree["view_id_match"] = $bigtree["report_id_match"] = array();

	sqlquery("SET foreign_key_checks = 0");
	
	$json = json_decode(file_get_contents(SERVER_ROOT."cache/package/manifest.json"),true);

	// Import module groups
	foreach ($json["components"]["module_groups"] as &$group) {
		if ($group) {
			$bigtree["group_match"][$group["id"]] = $admin->createModuleGroup($group["name"]);
			// Update the group ID since we're going to save this manifest locally for uninstalling
			$group["id"] = $bigtree["group_match"][$group["id"]];
		}
	}

	// Import modules
	foreach ($json["components"]["modules"] as &$module) {
		if ($module) {
			$group = ($module["group"] && isset($bigtree["group_match"][$module["group"]])) ? $bigtree["group_match"][$module["group"]] : "NULL";
			$gbp = sqlescape(is_array($module["gbp"]) ? json_encode($module["gbp"]) : $module["gbp"]);
			// Find a unique route
			$oroute = $route = $module["route"];
			$x = 2;
			while (sqlrows(sqlquery("SELECT * FROM bigtree_modules WHERE route = '".sqlescape($route)."'"))) {
				$route = $oroute."-$x";
				$x++;
			}
			// Create the module
			sqlquery("INSERT INTO bigtree_modules (`name`,`route`,`class`,`icon`,`group`,`gbp`) VALUES ('".sqlescape($module["name"])."','".sqlescape($route)."','".sqlescape($module["class"])."','".sqlescape($module["icon"])."',$group,'$gbp')");
			$module_id = sqlid();
			$bigtree["module_match"][$module["id"]] = $module_id;
			$bigtree["route_match"][$module["route"]] = $route;
			// Update the module ID since we're going to save this manifest locally for uninstalling
			$module["id"] = $module_id;
	
			// Create the embed forms
			foreach ($module["embed_forms"] as $form) {
				$admin->createModuleEmbedForm($module_id,$form["title"],$form["table"],(is_array($form["fields"]) ? $form["fields"] : json_decode($form["fields"],true)),$form["preprocess"],$form["callback"],$form["default_position"],$form["default_pending"],$form["css"],$form["redirect_url"],$form["thank_you_message"]);
			}
			// Create views
			foreach ($module["views"] as $view) {
				$bigtree["view_id_match"][$view["id"]] = $admin->createModuleView($module_id,$view["title"],$view["description"],$view["table"],$view["type"],(is_array($view["options"]) ? $view["options"] : json_decode($view["options"],true)),(is_array($view["fields"]) ? $view["fields"] : json_decode($view["fields"],true)),(is_array($view["actions"]) ? $view["actions"] : json_decode($view["actions"],true)),$view["related_form"],$view["preview_url"]);
			}
			// Create regular forms
			foreach ($module["forms"] as $form) {
				$bigtree["form_id_match"][$form["id"]] = $admin->createModuleForm($module_id,$form["title"],$form["table"],(is_array($form["fields"]) ? $form["fields"] : json_decode($form["fields"],true)),$form["preprocess"],$form["callback"],$form["default_position"],($form["return_view"] ? $bigtree["view_id_match"][$form["return_view"]] : false),$form["return_url"],$form["tagging"]);
				// Update related form values
				foreach ($bigtree["view_id_match"] as $view_id) {
					sqlquery("UPDATE bigtree_module_views SET related_form = '".$bigtree["form_id_match"][$form["id"]]."' WHERE related_form = '".$form["id"]."' AND id = '$view_id'");
				}
			}
			// Create reports
			foreach ($module["reports"] as $report) {
				$bigtree["report_id_match"][$report["id"]] = $admin->createModuleReport($module_id,$report["title"],$report["table"],$report["type"],(is_array($report["filters"]) ? $report["filters"] : json_decode($report["filters"],true)),(is_array($report["fields"]) ? $report["fields"] : json_decode($report["fields"],true)),$report["parser"],($report["view"] ? $bigtree["view_id_match"][$report["view"]] : false));
			}
			// Create actions
			foreach ($module["actions"] as $action) {
				$admin->createModuleAction($module_id,$action["name"],$action["route"],$action["in_nav"],$action["class"],$bigtree["form_id_match"][$action["form"]],$bigtree["view_id_match"][$action["view"]],$bigtree["report_id_match"][$action["report"]],$action["level"],$action["position"]);
			}
		}
	}

	// Import templates
	foreach ($json["components"]["templates"] as $template) {
		if ($template) {
			$resources = sqlescape(is_array($template["resources"]) ? json_encode($template["resources"]) : $template["resources"]);
			sqlquery("DELETE FROM bigtree_templates WHERE id = '".sqlescape($template["id"])."'");
			sqlquery("INSERT INTO bigtree_templates (`id`,`name`,`module`,`resources`,`level`,`routed`) VALUES ('".sqlescape($template["id"])."','".sqlescape($template["name"])."','".$bigtree["module_match"][$template["module"]]."','$resources','".sqlescape($template["level"])."','".sqlescape($template["routed"])."')");
		}
	}

	// Import callouts
	foreach ($json["components"]["callouts"] as $callout) {
		if ($callout) {
			$resources = sqlescape(is_array($callout["resources"]) ? json_encode($callout["resources"]) : $callout["resources"]);
			sqlquery("DELETE FROM bigtree_callouts WHERE id = '".sqlescape($callout["id"])."'");
			sqlquery("INSERT INTO bigtree_callouts (`id`,`name`,`description`,`display_default`,`display_field`,`resources`,`level`,`position`) VALUES ('".sqlescape($callout["id"])."','".sqlescape($callout["name"])."','".sqlescape($callout["description"])."','".sqlescape($callout["display_default"])."','".sqlescape($callout["display_field"])."','$resources','".sqlescape($callout["level"])."','".sqlescape($callout["position"])."')");	
		}
	}

	// Import Settings
	foreach ($json["components"]["settings"] as $setting) {
		if ($setting) {
			sqlquery("DELETE FROM bigtree_settings WHERE id = '".sqlescape($setting["id"])."'");
			$admin->createSetting($setting);
		}
	}

	// Import Feeds
	foreach ($json["components"]["feeds"] as $feed) {
		if ($feed) {
			$fields = sqlescape(is_array($feed["fields"]) ? json_encode($feed["fields"]) : $feed["fields"]);
			$options = sqlescape(is_array($feed["options"]) ? json_encode($feed["options"]) : $feed["options"]);
			sqlquery("DELETE FROM bigtree_feeds WHERE route = '".sqlescape($feed["route"])."'");
			sqlquery("INSERT INTO bigtree_feeds (`route`,`name`,`description`,`type`,`table`,`fields`,`options`) VALUES ('".sqlescape($feed["route"])."','".sqlescape($feed["name"])."','".sqlescape($feed["description"])."','".sqlescape($feed["type"])."','".sqlescape($feed["table"])."','$fields','$options')");
		}
	}

	// Import Field Types
	foreach ($json["components"]["field_types"] as $type) {
		if ($type) {
			sqlquery("DELETE FROM bigtree_field_types WHERE id = '".sqlescape($type["id"])."'");
			// Backwards compatibility with field types packaged for 4.1
			if (!isset($type["use_cases"])) {
				$type["use_cases"] = array(
					"templates" => $type["pages"],
					"modules" => $type["modules"],
					"callouts" => $type["callouts"],
					"settings" => $type["settings"]
				);
			}
			$use_cases = is_array($type["use_cases"]) ? sqlescape(json_encode($type["use_cases"])) : sqlescape($type["use_cases"]);
			$self_draw = $type["self_draw"] ? "'on'" : "NULL";
			sqlquery("INSERT INTO bigtree_field_types (`id`,`name`,`use_cases`,`self_draw`) VALUES ('".sqlescape($type["id"])."','".sqlescape($type["name"])."','$use_cases',$self_draw)");
		}
	}

	// Import files
	foreach ($json["files"] as $file) {
		BigTree::copyFile(SERVER_ROOT."cache/package/$file",SERVER_ROOT.$file);
	}

	// Run SQL
	foreach ($json["sql"] as $sql) {
		sqlquery($sql);
	}
	// Empty view cache
	sqlquery("DELETE FROM bigtree_module_view_cache");

	// Remove the package directory, we do it backwards because the "deepest" files are last
	$contents = @array_reverse(BigTree::directoryContents(SERVER_ROOT."cache/package/"));
	foreach ($contents as $file) {
		@unlink($file);
		@rmdir($file);
	}
	@rmdir(SERVER_ROOT."cache/package/");

	// Clear module class cache and field type cache.
	@unlink(SERVER_ROOT."cache/bigtree-module-class-list.json");
	@unlink(SERVER_ROOT."cache/bigtree-form-field-types.json");

	sqlquery("INSERT INTO bigtree_extensions (`id`,`type`,`name`,`version`,`last_updated`,`manifest`) VALUES ('".sqlescape($json["id"])."','package','".sqlescape($json["title"])."','".sqlescape($json["version"])."',NOW(),'".sqlescape(json_encode($json))."')");

	sqlquery("SET foreign_key_checks = 1");
	
	$admin->growl("Developer","Installed Package");
	BigTree::redirect(DEVELOPER_ROOT."packages/install/complete/");
?>