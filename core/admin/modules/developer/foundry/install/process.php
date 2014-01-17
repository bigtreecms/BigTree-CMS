<?
	$bigtree["group_match"] = $bigtree["module_match"] = $bigtree["route_match"] = $bigtree["class_name_match"] = $bigtree["form_id_match"] = $bigtree["view_id_match"] = $bigtree["report_id_match"] = array();
	
	$json = json_decode(file_get_contents(SERVER_ROOT."cache/package/manifest.json"),true);

	// Import module groups
	foreach ($json["module_groups"] as $group) {
		$bigtree["group_match"][$group["id"]] = $admin->createModuleGroup($group["name"]);
	}

	// Import modules
	foreach ($json["modules"] as $module) {
		$group = $module["group"] ? $bigtree["group_match"][$module["group"]] : "NULL";
		// Find a unique route
		$oroute = $route = $module["route"];
		$x = 2;
		while (sqlrows(sqlquery("SELECT * FROM bigtree_modules WHERE route = '".sqlescape($route)."'"))) {
			$route = $oroute."-$x";
			$x++;
		}
		// Create the module
		sqlquery("INSERT INTO bigtree_modules (`name`,`route`,`class`,`icon`,`group`,`gbp`) VALUES ('".sqlescape($module["name"])."','".sqlescape($route)."','".sqlescape($module["class"])."','".sqlescape($module["icon"])."',$group,'".sqlescape($module["gbp"])."')");
		$module_id = sqlid();
		$bigtree["module_match"][$module["id"]] = $module_id;
		$bigtree["route_match"][$module["route"]] = $route;
		// Create the embed forms
		foreach ((array)$module["embed_forms"] as $form) {
			$admin->createModuleEmbedForm($module_id,$form["title"],$form["table"],(is_array($form["fields"]) ? $form["fields"] : json_decode($form["fields"],true)),$form["preprocess"],$form["callback"],$form["default_position"],$form["default_pending"],$form["css"],$form["redirect_url"],$form["thank_you_message"]);
		}
		// Create views
		foreach ((array)$module["views"] as $view) {
			$bigtree["view_id_match"][$view["id"]] = $admin->createModuleView($view["title"],$view["description"],$view["table"],$view["type"],(is_array($view["options"]) ? $view["options"] : json_decode($view["options"],true)),(is_array($view["fields"]) ? $view["fields"] : json_decode($view["fields"],true)),(is_array($view["actions"]) ? $view["actions"] : json_decode($view["actions"],true)),$view["suffix"],$view["preview_url"]);
		}
		// Create regular forms
		foreach ((array)$module["forms"] as $form) {
			$bigtree["form_id_match"][$form["id"]] = $admin->createModuleForm($form["title"],$form["table"],(is_array($form["fields"]) ? $form["fields"] : json_decode($form["fields"],true)),$form["preprocess"],$form["callback"],$form["default_position"],($form["return_view"] ? $bigtree["view_id_match"][$form["return_view"]] : false),$form["return_url"],$form["tagging"]);
		}
		// Create reports
		foreach ((array)$module["reports"] as $report) {
			$bigtree["report_id_match"][$report["id"]] = $admin->createModuleReport($report["title"],$report["table"],$report["type"],(is_array($report["filters"]) ? $report["filters"] : json_decode($report["filters"],true)),(is_array($report["fields"]) ? $report["fields"] : json_decode($report["fields"],true)),$report["parser"],($report["view"] ? $bigtree["view_id_match"][$report["view"]] : false));
		}
		// Create actions
		foreach ((array)$module["actions"] as $action) {
			$admin->createModuleAction($module_id,$action["name"],$action["route"],$action["in_nav"],$action["class"],$action["form"],$action["view"],$action["report"],$action["level"],$action["position"]);
		}
	}

	// Import templates
	foreach ((array)$json["templates"] as $template) {
		$resources = sqlescape(is_array($template["resources"]) ? json_encode($template["resources"]) : $template["resources"]);
		sqlquery("INSERT INTO bigtree_templates (`id`,`name`,`module`,`resources`,`level`,`routed`) VALUES ('".sqlescape($template["id"])."','".sqlescape($template["name"])."','".$bigtree["module_match"][$template["module"]]."','$resources','".sqlescape($template["level"])."','".sqlescape($template["routed"])."')");
	}

	// Import callouts
	foreach ((array)$json["callouts"] as $callout) {
		$resources = sqlescape(is_array($callout["resources"]) ? json_encode($callout["resources"]) : $callout["resources"]);
		sqlquery("INSERT INTO bigtree_callouts (`id`,`name`,`description`,`display_default`,`display_field`,`resources`,`level`,`position`) VALUES ('".sqlescape($callout["id"])."','".sqlescape($callout["name"])."','".sqlescape($callout["description"])."','".sqlescape($callout["display_default"])."','".sqlescape($callout["display_field"])."','$resources','".sqlescape($callout["level"])."','".sqlescape($callout["position"])."')");	
	}

	// Import Field Types
	foreach ((array)$json["field_types"] as $type) {
		sqlquery("INSERT INTO bigtree_field_types (`id`,`name`,`pages`,`modules`,`callouts`,`settings`) VALUES ('".sqlescape($type["id"])."','".sqlescape($type["name"])."','".sqlescape($type["pages"])."','".sqlescape($type["modules"])."','".sqlescape($type["callouts"])."','".sqlescape($type["settings"])."')");
	}

	// Import Settings
	foreach ((array)$json["settings"] as $setting) {
		$admin->createSetting($setting);
	}

	// Import Feeds
	foreach ((array)$json["feeds"] as $feed) {
		$fields = sqlescape(is_array($feed["fields"]) ? json_encode($feed["fields"]) : $feed["fields"]);
		$options = sqlescape(is_array($feed["options"]) ? json_encode($feed["options"]) : $feed["options"]);
		sqlquery("INSERT INTO bigtree_feeds (`route`,`name`,`description`,`type`,`table`,`fields`,`options`) VALUES ('".sqlescape($feed["route"])."','".sqlescape($feed["name"])."','".sqlescape($feed["description"])."','".sqlescape($feed["type"])."','".sqlescape($feed["table"])."','$fields','$options')");
	}

	// Import files
	foreach ((array)$json["files"] as $file) {
		BigTree::copyFile(SERVER_ROOT."cache/package/$file",SERVER_ROOT.$file);
	}

	// Run SQL
	foreach ((array)$json["sql"] as $sql) {
		sqlquery($sql);
	}

	// Remove the package directory, we do it backwards because the "deepest" files are last
	$contents = @array_reverse(BigTree::directoryContents(SERVER_ROOT."cache/package/"));
	foreach ((array)$contents as $file) {
		@unlink($file);
		@rmdir($file);
	}
	@rmdir(SERVER_ROOT."cache/package/");

	// Clear module class cache and field type cache.
	unlink(SERVER_ROOT."cache/module-class-list.btc");
	unlink(SERVER_ROOT."cache/form-field-types.btc");
	
	
	if ($json["install_code"]) {
		try {
			eval(ltrim(rtrim(base64_decode($json["install_code"]),"?>"),"<?"));
		} catch (Exception $e) {
			$_SESSION["bigtree_admin"]["package_code"] = ltrim(rtrim(base64_decode($json["install_code"]),"?>"),"<?");
			$_SESSION["bigtree_admin"]["package_error"] = $e;
		}
	}
	
	if (count($json["instructions"]) && $json["instructions"]["post"]) {
		$_SESSION["bigtree_admin"]["package_instructions"] = $json["instructions"]["post"];
	}
	
	$admin->growl("Developer","Installed Package");
	BigTree::redirect(ADMIN_ROOT."developer/foundry/install/complete/");
?>