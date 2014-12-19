<?
	$bigtree["group_match"] = $bigtree["module_match"] = $bigtree["route_match"] = $bigtree["class_name_match"] = $bigtree["form_id_match"] = $bigtree["view_id_match"] = $bigtree["report_id_match"] = array();
	
	$json = json_decode(file_get_contents(SERVER_ROOT."cache/package/manifest.json"),true);
	$extension = sqlescape($json["id"]);

	// Turn off foreign key checks so we can reference the extension before creating it
	sqlquery("SET foreign_key_checks = 0");

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
			$gbp = sqlescape(is_array($module["gbp"]) ? BigTree::json($module["gbp"]) : $module["gbp"]);
			// Find a unique route
			$oroute = $route = $module["route"];
			$x = 2;
			while (sqlrows(sqlquery("SELECT * FROM bigtree_modules WHERE route = '".sqlescape($route)."'"))) {
				$route = $oroute."-$x";
				$x++;
			}
			// Create the module
			sqlquery("INSERT INTO bigtree_modules (`name`,`route`,`class`,`icon`,`group`,`gbp`,`extension`) VALUES ('".sqlescape($module["name"])."','".sqlescape($route)."','".sqlescape($module["class"])."','".sqlescape($module["icon"])."',$group,'$gbp','$extension')");
			$module_id = sqlid();
			$bigtree["module_match"][$module["id"]] = $module_id;
			$bigtree["route_match"][$module["route"]] = $route;
			// Update the module ID since we're going to save this manifest locally for uninstalling
			$module["id"] = $module_id;
	
			// Create the embed forms
			foreach ($module["embed_forms"] as $form) {
				$admin->createModuleEmbedForm($module_id,$form["title"],$form["table"],(is_array($form["fields"]) ? $form["fields"] : json_decode($form["fields"],true)),$form["hooks"],$form["default_position"],$form["default_pending"],$form["css"],$form["redirect_url"],$form["thank_you_message"]);
			}
			// Create views
			foreach ($module["views"] as $view) {
				$bigtree["view_id_match"][$view["id"]] = $admin->createModuleView($module_id,$view["title"],$view["description"],$view["table"],$view["type"],(is_array($view["options"]) ? $view["options"] : json_decode($view["options"],true)),(is_array($view["fields"]) ? $view["fields"] : json_decode($view["fields"],true)),(is_array($view["actions"]) ? $view["actions"] : json_decode($view["actions"],true)),$view["suffix"],$view["preview_url"]);
			}
			// Create regular forms
			foreach ($module["forms"] as $form) {
				$bigtree["form_id_match"][$form["id"]] = $admin->createModuleForm($module_id,$form["title"],$form["table"],(is_array($form["fields"]) ? $form["fields"] : json_decode($form["fields"],true)),$form["hooks"],$form["default_position"],($form["return_view"] ? $bigtree["view_id_match"][$form["return_view"]] : false),$form["return_url"],$form["tagging"]);
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
			$resources = sqlescape(is_array($template["resources"]) ? BigTree::json($template["resources"]) : $template["resources"]);
			sqlquery("INSERT INTO bigtree_templates (`id`,`name`,`module`,`resources`,`level`,`routed`,`extension`) VALUES ('".sqlescape($template["id"])."','".sqlescape($template["name"])."','".$bigtree["module_match"][$template["module"]]."','$resources','".sqlescape($template["level"])."','".sqlescape($template["routed"])."','$extension')");
		}
	}

	// Import callouts
	foreach ($json["components"]["callouts"] as $callout) {
		if ($callout) {
			$resources = sqlescape(is_array($callout["resources"]) ? BigTree::json($callout["resources"]) : $callout["resources"]);
			sqlquery("INSERT INTO bigtree_callouts (`id`,`name`,`description`,`display_default`,`display_field`,`resources`,`level`,`position`,`extension`) VALUES ('".sqlescape($callout["id"])."','".sqlescape($callout["name"])."','".sqlescape($callout["description"])."','".sqlescape($callout["display_default"])."','".sqlescape($callout["display_field"])."','$resources','".sqlescape($callout["level"])."','".sqlescape($callout["position"])."','$extension')");	
		}
	}

	// Import Settings
	foreach ($json["components"]["settings"] as $setting) {
		if ($setting) {
			$admin->createSetting($setting);
			sqlquery("UPDATE bigtree_settings SET extension = '$extension' WHERE id = '".sqlescape($setting["id"])."'");
		}
	}

	// Import Feeds
	foreach ($json["components"]["feeds"] as $feed) {
		if ($feed) {
			$fields = sqlescape(is_array($feed["fields"]) ? BigTree::json($feed["fields"]) : $feed["fields"]);
			$options = sqlescape(is_array($feed["options"]) ? BigTree::json($feed["options"]) : $feed["options"]);
			sqlquery("INSERT INTO bigtree_feeds (`route`,`name`,`description`,`type`,`table`,`fields`,`options`,`extension`) VALUES ('".sqlescape($feed["route"])."','".sqlescape($feed["name"])."','".sqlescape($feed["description"])."','".sqlescape($feed["type"])."','".sqlescape($feed["table"])."','$fields','$options','$extension')");
		}
	}

	// Import Field Types
	foreach ($json["components"]["field_types"] as $type) {
		if ($type) {
			sqlquery("INSERT INTO bigtree_field_types (`id`,`name`,`pages`,`modules`,`callouts`,`settings`,`extension`) VALUES ('".sqlescape($type["id"])."','".sqlescape($type["name"])."','".sqlescape($type["pages"])."','".sqlescape($type["modules"])."','".sqlescape($type["callouts"])."','".sqlescape($type["settings"])."','$extension')");
		}
	}

	// Import files into the extension directory
	$contents = BigTree::directoryContents(SERVER_ROOT."cache/package/");
	foreach ($contents as $file) {
		BigTree::copyFile($file,str_replace(SERVER_ROOT."cache/package/",SERVER_ROOT."extensions/".$json["id"]."/",$file));
	}
	// Move site related files into the site directory
	$site_contents = file_exists(SERVER_ROOT."extensions/".$json["id"]."/site/") ? BigTree::directoryContents(SERVER_ROOT."extensions/".$json["id"]."/site/") : array();
	foreach ($site_contents as $file) {
		BigTree::copyFile($file,str_replace(SERVER_ROOT,SERVER_ROOT."site/",$file));
	}

	// Import Tables
	foreach ($json["components"]["tables"] as $table_name => $sql_statement) {
		sqlquery("DROP TABLE IF EXISTS `$table_name`");
		sqlquery($sql_statement);
	}
	
	sqlquery("SET foreign_key_checks = 1");

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
	@unlink(SERVER_ROOT."cache/module-class-list.btc");
	@unlink(SERVER_ROOT."cache/form-field-types.btc");

	sqlquery("INSERT INTO bigtree_extensions (`id`,`type`,`name`,`version`,`last_updated`,`manifest`) VALUES ('".sqlescape($json["id"])."','extension','".sqlescape($json["title"])."','".sqlescape($json["version"])."',NOW(),'".BigTree::json($json,true)."')");
	
	$admin->growl("Developer","Installed Extension");
	BigTree::redirect(DEVELOPER_ROOT."extensions/install/complete/");
?>