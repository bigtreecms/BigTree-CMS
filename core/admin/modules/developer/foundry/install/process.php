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
		
	}

	// We're putting it in function scope so that when we globalize the $data array it doesn't cross-contaminate when importing older data that's missing fields.
	function _local_import_line($type,$data) {
		global $bigtree;

		if (is_array($data)) {
			foreach ($data as $key => $val) {
				if (substr($key,0,1) != "_") {
					if ($key != "type") {
						if (is_array($val)) {
							$$key = sqlescape(json_encode($val,true));
						} else {
							$$key = sqlescape($val);
						}
					}
				}
			}
		}
		
		if ($type == "Instructions") {
			$bigtree["instructions"] = $data;
		}

		if ($type == "InstallCode") {
			$bigtree["install_code"] = $data;
		}
		
		if ($type == "Group") {
			$existing = sqlfetch(sqlquery("SELECT * FROM bigtree_module_groups WHERE name = '$name'"));
			if ($existing) {
				$bigtree["group_id"] = $existing["id"];
			} else {
				sqlquery("INSERT INTO bigtree_module_groups (`name`,`route`) VALUES ('$name','$route')");
				$bigtree["group_id"] = sqlid();
			}
		}
		
		// Import the Module
		if ($type == "Module") {
			// Get a unique route
			$oroute = $route;
			$x = 2;
			while (sqlrows(sqlquery("SELECT * FROM bigtree_modules WHERE route = '$route'"))) {
				$route = $oroute."-".$x;
				$x++;
			}
			if ($route != $oroute) {
				$bigtree["route_match"]["custom/admin/$oroute/"] = "custom/admin/$route/";
				$bigtree["class_name_match"][$oroute] = $route;
			}
			$group_insert = $bigtree["group_id"] ? "'".$bigtree["group_id"]."'" : "NULL";
			sqlquery("INSERT INTO bigtree_modules (`group`,`name`,`route`,`class`,`icon`,`gbp`) VALUES ($group_insert,'$name','$route','$class','$icon','$gbp')");
			$bigtree["module_match"][$id] = sqlid();
			$bigtree["last_module_id"] = sqlid();
		}
		
		// Import a Module Action
		if ($type == "Action") {
			if ($form) {
				$form = $bigtree["last_form_id"];
			}
			if ($view) {
				$view = $bigtree["last_view_id"];
			}
			if ($report) {
				$report = $bigtree["last_report_id"];
			}
			sqlquery("INSERT INTO bigtree_module_actions (`module`,`name`,`route`,`in_nav`,`view`,`form`,`report`,`class`,`level`,`position`) VALUES ('".$bigtree["last_module_id"]."','$name','$route','$in_nav','$view','$form','$class','$position')");
		}

		// Import a Module Form
		if ($type == "ModuleForm") {
			$return_view = $return_view ? "'".$return_view."'" : "NULL";
			sqlquery("INSERT INTO bigtree_module_forms (`title`,`preprocess`,`callback`,`table`,`fields`,`default_position`,`return_view`,`return_url`,`tagging`) VALUES ('$title','$preprocess','$callback','$table','$fields','$positioning','$default_position',$return_view,'$return_url','$tagging')");
			$bigtree["last_form_id"] = sqlid();
		}
		
		// Import a Module View
		if ($type == "ModuleView") {
			sqlquery("INSERT INTO bigtree_module_views (`title`,`description`,`type`,`table`,`fields`,`options`,`actions`,`suffix`,`preview_url`) VALUES ('$title','$description','".$data["type"]."','$table','$fields','$options','$actions','$suffix','$preview_url')");
			$bigtree["last_view_id"] = sqlid();
		}
		
		// Import a Template
		if ($type == "Template") {
			sqlquery("DELETE FROM bigtree_templates WHERE id = '$id'");
			sqlquery("INSERT INTO bigtree_templates (`id`,`name`,`module`,`resources`,`description`,`level`,`routed`) VALUES ('$id','$name','$module','$resources','$description','$level','$routed')");
		}
		
		// Import a Callout
		if ($type == "Callout") {
			sqlquery("DELETE FROM bigtree_callouts WHERE id = '$id'");
			sqlquery("INSERT INTO bigtree_callouts (`id`,`name`,`description`,`display_default`,`display_field`,`resources`,`level`) VALUES ('$id','$name','$description','$display_default','$display_field','$resources','$level')");
		}
		
		// Import a Setting
		if ($type == "Setting") {
			if ($data["module"]) {
				$module = $bigtree["module_match"][$module];
			}
			sqlquery("DELETE FROM bigtree_settings WHERE id = '$id'");
			sqlquery("INSERT INTO bigtree_settings (`id`,`value`,`type`,`name`,`description`,`options`,`locked`,`system`,`encrypted`) VALUES ('$id','$value','".$data["type"]."','$name','$description','$options','$locked','$system','$encrypted')");
		}
		
		// Import a Feed
		if ($type == "Feed") {
			sqlquery("DELETE FROM bigtree_feeds WHERE route = '$route'");
			sqlquery("INSERT INTO bigtree_feeds (`route`,`name`,`description`,`type`,`table`,`fields`,`options`) VALUES ('$route','$name','$description','".$data["type"]."','$table','$fields','$options')");
		}
		
		// Import a Field Type
		if ($type == "FieldType") {
			sqlquery("DELETE FROM bigtree_field_types WHERE id = '$id'");
			sqlquery("INSERT INTO bigtree_field_types (`id`,`name`,`pages`,`modules`,`callouts`,`settings`) VALUES ('$id','$name','$pages','$modules','$callouts','$settings')");
		}
		
		// Import a File
		if ($type == "File") {
			$source = $parts[1];
			$destination = $parts[2];
			$section = $parts[3];
			foreach ($bigtree["route_match"] as $key => $val) {
				$destination = str_replace($key,$val,$destination);
			}
			
			BigTree::copyFile($cache_root.$source,SERVER_ROOT.$destination);
		}
		
		if ($type == "ClassFile") {
			$source = $parts[1];
			$destination = $parts[2];
			$module_id = $parts[3];
			BigTree::copyFile($cache_root.$source,SERVER_ROOT.$destination);
			foreach ($bigtree["class_name_match"] as $key => $val) {
				$destination = str_replace("/$key.php","/$val.php",$destination);
			}
			file_put_contents(SERVER_ROOT.$destination,str_replace('var $Module = "'.$module_id.'";','var $Module = "'.$bigtree["module_match"][$module_id].'";',file_get_contents(SERVER_ROOT.$destination)));
		}
		
		// Import a SQL file
		if ($type == "SQL") {
			$table = $parts[1];
			$file = $cache_root.$parts[2];
			$queries = explode("\n",file_get_contents($file));
			foreach ($queries as $query) {
				if ($query) {
					sqlquery($query);
				}
			}
		}
	}
	
	// Clear module class cache and field type cache.
	unlink(SERVER_ROOT."cache/module-class-list.btc");
	unlink(SERVER_ROOT."cache/form-field-types.btc");
	
	$data = unserialize($_POST["details"]);
	
	$admin->growl("Developer","Installed Package");
	
	if ($bigtree["install_code"]) {
		try {
			eval(ltrim(rtrim(base64_decode($bigtree["install_code"]),"?>"),"<?"));
		} catch (Exception $e) {
			$_SESSION["bigtree_admin"]["package_code"] = ltrim(rtrim(base64_decode($bigtree["install_code"]),"?>"),"<?");
			$_SESSION["bigtree_admin"]["package_error"] = $e;
		}
	}
	
	if (count($bigtree["instructions"]) && $bigtree["instructions"]["post"]) {
		$_SESSION["bigtree_admin"]["package_instructions"] = $bigtree["instructions"]["post"];
	}
	
	BigTree::redirect(ADMIN_ROOT."developer/foundry/install/complete/");
?>