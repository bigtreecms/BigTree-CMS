<?
	$cache_root = SERVER_ROOT."cache/unpack/".end($bigtree["path"])."/";
	if (!file_exists($cache_root)) {
		$cache_root = mkdir($cache_root);
		chmod($cache_root,0777);
	}
	$index = file_get_contents($cache_root."index.btx");
	$lines = explode("\n",$index);
	$module_name = $lines[0];
	$package_info = $lines[1];
	$group_id = 0;
	$package_files = array();
	$package_tables = array();
	$module_match = array();
	$route_match = array();
	
	// Instructions and Install Code defaults
	$instructions = false;
	$install_code = false;
	unset($_SESSION["bigtree_admin"]["package_error"]);
	unset($_SESSION["bigtree_admin"]["package_instructions"]);	
		
	// Saved information for managing these packages later.
	$savedData["tables"] = array();
	$savedData["class_files"] = array();
	$savedData["required_files"] = array();
	$savedData["files"] = array();
	$savedData["templates"] = array();
	$savedData["callouts"] = array();
	$savedData["settings"] = array();
	$savedData["feeds"] = array();
	
	next($lines);
	next($lines);
	foreach ($lines as $line) {
		$parts = explode("::||BTX||::",$line);
		$type = $parts[0];
		$data = json_decode($parts[1],true);
		
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
			$instructions = $data;
		}

		if ($type == "InstallCode") {
			$install_code = $data;
		}
		
		if ($type == "Group") {
			$existing = sqlfetch(sqlquery("SELECT * FROM bigtree_module_groups WHERE name = '$name'"));
			if ($existing) {
				$group_id = $existing["id"];
			} else {
				sqlquery("INSERT INTO bigtree_module_groups (`name`,`route`) VALUES ('$name','$route')");
				$group_id = sqlid();
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
				$route_match["custom/admin/$oroute/"] = "custom/admin/$route/";
			}
			$group_insert = $group_id ? "'$group_id'" : "NULL";
			sqlquery("INSERT INTO bigtree_modules (`name`,`route`,`class`,`group`,`icon`,`gbp`) VALUES ('$name','$route','$class',$group_insert,'$icon','$gbp')");
			$module_match[$id] = sqlid();
			$module_id = sqlid();
		}
		
		// Import a Module Action
		if ($type == "Action") {
			if ($form)
				$form = $last_form_id;
			if ($view)
				$view = $last_view_id;
			sqlquery("INSERT INTO bigtree_module_actions (`module`,`name`,`route`,`in_nav`,`view`,`form`,`class`,`position`) VALUES ('$module_id','$name','$route','$in_nav','$view','$form','$class','$position')");
		}
		
		// Import a Module Form
		if ($type == "ModuleForm") {
			$return_view = $return_view ? "'".$return_view."'" : "NULL";
			sqlquery("INSERT INTO bigtree_module_forms (`title`,`preprocess`,`callback`,`table`,`fields`,`positioning`,`default_position`,`return_view`,`return_url`) VALUES ('$title','$preprocess','$callback','$table','$fields','$positioning','$default_position',$return_view,'$return_url')");
			$last_form_id = sqlid();
		}
		
		// Import a Module View
		if ($type == "ModuleView") {
			sqlquery("INSERT INTO bigtree_module_views (`title`,`description`,`type`,`table`,`fields`,`options`,`actions`,`suffix`,`preview_url`) VALUES ('$title','$description','".$data["type"]."','$table','$fields','$options','$actions','$suffix','$preview_url')");
			$last_view_id = sqlid();
		}
		
		// Import a Template
		if ($type == "Template") {
			sqlquery("DELETE FROM bigtree_templates WHERE id = '$id'");
			sqlquery("INSERT INTO bigtree_templates (`id`,`name`,`image`,`module`,`resources`,`description`,`callouts_enabled`,`level`,`routed`) VALUES ('$id','$name','$image','$module','$resources','$description','$callouts_enabled','$level','$routed')");
			$savedData["templates"][] = $id;
		}
		
		// Import a Callout
		if ($type == "Callout") {
			sqlquery("DELETE FROM bigtree_callouts WHERE id = '$id'");
			sqlquery("INSERT INTO bigtree_callouts (`id`,`name`,`description`,`display_default`,`display_field`,`resources`,`level`) VALUES ('$id','$name','$description','$display_default','$display_field','$resources','$level')");
			$savedData["callouts"][] = $id;
		}
		
		// Import a Setting
		if ($type == "Setting") {
			if ($data["module"]) {
				$module = $module_match[$module];
			}
			sqlquery("DELETE FROM bigtree_settings WHERE id = '$id'");
			sqlquery("INSERT INTO bigtree_settings (`id`,`value`,`type`,`name`,`description`,`options`,`locked`,`system`,`encrypted`) VALUES ('$id','$value','".$data["type"]."','$name','$description','$options','$locked','$system','$encrypted')");
			$savedData["settings"][] = $id;
		}
		
		// Import a Feed
		if ($type == "Feed") {
			sqlquery("DELETE FROM bigtree_feeds WHERE route = '$route'");
			sqlquery("INSERT INTO bigtree_feeds (`route`,`name`,`description`,`type`,`table`,`fields`,`options`) VALUES ('$route','$name','$description','".$data["type"]."','$table','$fields','$options')");
			$savedData["feeds"][] = $route;
		}
		
		// Import a Field Type
		if ($type == "FieldType") {
			sqlquery("DELETE FROM bigtree_field_types WHERE id = '$id'");
			sqlquery("INSERT INTO bigtree_field_types (`id`,`name`,`pages`,`modules`,`callouts`,`settings`) VALUES ('$id','$name','$pages','$modules','$callouts','$settings')");
			$savedData["field_types"][] = $id;
		}
		
		// Import a File
		if ($type == "File") {
			$source = $parts[1];
			$destination = $parts[2];
			$section = $parts[3];
			foreach ($route_match as $key => $val) {
				$destination = str_replace($key,$val,$destination);
			}
			
			BigTree::copyFile($cache_root.$source,SERVER_ROOT.$destination);
			if ($section == "Other") {			
				$savedData["other_files"][] = $destination;
			} elseif ($section == "Required") {
				$savedData["required_files"][] = $destination;				
			}
			$package_files[] = $destination;
		}
		
		if ($type == "ClassFile") {
			$source = $parts[1];
			$destination = $parts[2];
			$module_id = $parts[3];
			BigTree::copyFile($cache_root.$source,SERVER_ROOT.$destination);
			file_put_contents(SERVER_ROOT.$destination,str_replace('var $Module = "'.$module_id.'";','var $Module = "'.$module_match[$module_id].'";',file_get_contents(SERVER_ROOT.$destination)));
			$savedData["class_files"][] = $destination;
			$package_files[] = $destination;
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
			$savedData["tables"][] = $table;
			$package_tables[] = $table;
		}
	}
	
	// Clear module class cache and field type cache.
	unlink(SERVER_ROOT."cache/module-class-list.btc");
	unlink(SERVER_ROOT."cache/form-field-types.btc");
	
	$data = unserialize($_POST["details"]);
	
	$admin->growl("Developer","Installed Package");
	
	if ($install_code) {
		try {
			eval(ltrim(rtrim(base64_decode($install_code),"?>"),"<?"));
		} catch (Exception $e) {
			$_SESSION["bigtree_admin"]["package_code"] = ltrim(rtrim(base64_decode($install_code),"?>"),"<?");
			$_SESSION["bigtree_admin"]["package_error"] = $e;
		}
	}
	
	if (count($instructions) && $instructions["post"]) {
		$_SESSION["bigtree_admin"]["package_instructions"] = $instructions["post"];
	}
	
	BigTree::redirect(ADMIN_ROOT."developer/foundry/install/complete/");
?>