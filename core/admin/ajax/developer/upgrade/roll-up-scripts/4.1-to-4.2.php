<?php
	// BigTree 4.1 -> 4.2

	sqlquery("ALTER TABLE bigtree_caches CHANGE `key` `key` VARCHAR(10000)");
	$storage = new BigTreeStorage;
	
	if (is_array($storage->Settings->Files)) {
		foreach ($storage->Settings->Files as $file) {
			sqlquery("INSERT INTO bigtree_caches (`identifier`,`key`,`value`) VALUES ('org.bigtreecms.cloudfiles','".sqlescape($file["path"])."','".sqlescape(json_encode($file))."')");
		}
	}
	
	unset($storage->Settings->Files);

	sqlquery("ALTER TABLE bigtree_field_types ADD COLUMN `use_cases` TEXT NOT NULL AFTER `name`");
	sqlquery("ALTER TABLE bigtree_field_types ADD COLUMN `self_draw` CHAR(2) NULL AFTER `use_cases`");
	$q = sqlquery("SELECT * FROM bigtree_field_types");
	
	while ($f = sqlfetch($q)) {
		$use_cases = sqlescape(json_encode(array(
			"templates" => $f["pages"],
			"modules" => $f["modules"],
			"callouts" => $f["callouts"],
			"settings" => $f["settings"]
		)));
		sqlquery("UPDATE bigtree_field_types SET use_cases = '$use_cases' WHERE id = '".sqlescape($f["id"])."'");
	}
	
	sqlquery("ALTER TABLE bigtree_field_types DROP `pages`, DROP `modules`, DROP `callouts`, DROP `settings`");
	
	// Converting resource thumbnail sizes to a properly editable feature and naming it better.
	$current = $cms->getSetting("resource-thumbnail-sizes");
	$thumbs = json_decode($current,true);
	$value = array();
	
	foreach (array_filter((array)$thumbs) as $title => $info) {
		$value[] = array("title" => $title,"prefix" => $info["prefix"],"width" => $info["width"],"height" => $info["height"]);
	}
	
	sqlquery("INSERT INTO bigtree_settings (`id`,`value`,`type`,`options`,`name`,`locked`) VALUES ('bigtree-file-manager-thumbnail-sizes','".sqlescape(json_encode($value))."','array','".sqlescape('{"fields":[{"key":"title","title":"Title","type":"text"},{"key":"prefix","title":"File Prefix (i.e. thumb_)","type":"text"},{"key":"width","title":"Width","type":"text"},{"key":"height","title":"Height","type":"text"}]}')."','File Manager Thumbnail Sizes','on')");
	sqlquery("DELETE FROM bigtree_settings WHERE id = 'resource-thumbnail-sizes'");
	
	// Drop unused comments column
	sqlquery("ALTER TABLE bigtree_pending_changes DROP COLUMN `comments`");

	// Add extension columns
	sqlquery("ALTER TABLE bigtree_callouts ADD COLUMN `extension` VARCHAR(255)");
	sqlquery("ALTER TABLE bigtree_callouts ADD FOREIGN KEY (extension) REFERENCES `bigtree_extensions` (id) ON DELETE CASCADE");
	sqlquery("ALTER TABLE bigtree_feeds ADD COLUMN `extension` VARCHAR(255)");
	sqlquery("ALTER TABLE bigtree_feeds ADD FOREIGN KEY (extension) REFERENCES `bigtree_extensions` (id) ON DELETE CASCADE");
	sqlquery("ALTER TABLE bigtree_field_types ADD COLUMN `extension` VARCHAR(255)");
	sqlquery("ALTER TABLE bigtree_field_types ADD FOREIGN KEY (extension) REFERENCES `bigtree_extensions` (id) ON DELETE CASCADE");
	sqlquery("ALTER TABLE bigtree_modules ADD COLUMN `extension` VARCHAR(255)");
	sqlquery("ALTER TABLE bigtree_modules ADD FOREIGN KEY (extension) REFERENCES `bigtree_extensions` (id) ON DELETE CASCADE");
	sqlquery("ALTER TABLE bigtree_module_groups ADD COLUMN `extension` VARCHAR(255)");
	sqlquery("ALTER TABLE bigtree_module_groups ADD FOREIGN KEY (extension) REFERENCES `bigtree_extensions` (id) ON DELETE CASCADE");
	sqlquery("ALTER TABLE bigtree_settings ADD COLUMN `extension` VARCHAR(255)");
	sqlquery("ALTER TABLE bigtree_settings ADD FOREIGN KEY (extension) REFERENCES `bigtree_extensions` (id) ON DELETE CASCADE");
	sqlquery("ALTER TABLE bigtree_templates ADD COLUMN `extension` VARCHAR(255)");
	sqlquery("ALTER TABLE bigtree_templates ADD FOREIGN KEY (extension) REFERENCES `bigtree_extensions` (id) ON DELETE CASCADE");

	// New publish_hook column, consolidate other hooks into one column
	sqlquery("ALTER TABLE bigtree_pending_changes ADD COLUMN `publish_hook` VARCHAR(255)");
	sqlquery("ALTER TABLE bigtree_module_forms ADD COLUMN `hooks` TEXT");
	sqlquery("ALTER TABLE bigtree_module_embeds ADD COLUMN `hooks` TEXT");
	$q = sqlquery("SELECT * FROM bigtree_module_forms");
	
	while ($f = sqlfetch($q)) {
		$hooks = array();
		$hooks["pre"] = $f["preprocess"];
		$hooks["post"] = $f["callback"];
		$hooks["publish"] = "";
		sqlquery("UPDATE bigtree_module_forms SET hooks = '".BigTree::json($hooks,true)."' WHERE id = '".$f["id"]."'");
	}
	
	$q = sqlquery("SELECT * FROM bigtree_module_embeds");
	
	while ($f = sqlfetch($q)) {
		$hooks = array();
		$hooks["pre"] = $f["preprocess"];
		$hooks["post"] = $f["callback"];
		$hooks["publish"] = "";
		sqlquery("UPDATE bigtree_module_embeds SET hooks = '".BigTree::json($hooks,true)."' WHERE id = '".$f["id"]."'");
	}
	
	sqlquery("ALTER TABLE bigtree_module_forms DROP COLUMN `preprocess`");
	sqlquery("ALTER TABLE bigtree_module_forms DROP COLUMN `callback`");
	sqlquery("ALTER TABLE bigtree_module_embeds DROP COLUMN `preprocess`");
	sqlquery("ALTER TABLE bigtree_module_embeds DROP COLUMN `callback`");

	// Adjust groups/callouts for multi-support -- first we drop the foreign key
	$table_desc = BigTree::describeTable("bigtree_callouts");
	
	foreach ($table_desc["foreign_keys"] as $name => $definition) {
		if ($definition["local_columns"][0] === "group") {
			sqlquery("ALTER TABLE bigtree_callouts DROP FOREIGN KEY `$name`");
		}
	}
	
	// Add the field to the groups
	sqlquery("ALTER TABLE bigtree_callout_groups ADD COLUMN `callouts` TEXT AFTER `name`");
	// Find all the callouts in each group
	$q = sqlquery("SELECT * FROM bigtree_callout_groups");
	
	while ($f = sqlfetch($q)) {
		$callouts = array();
		$qq = sqlquery("SELECT * FROM bigtree_callouts WHERE `group` = '".$f["id"]."' ORDER BY position DESC, id ASC");
	
		while ($ff = sqlfetch($qq)) {
			$callouts[] = $ff["id"];
		}

		sqlquery("UPDATE bigtree_callout_groups SET `callouts` = '".BigTree::json($callouts,true)."' WHERE id = '".$f["id"]."'");
	}

	// Drop the group column
	sqlquery("ALTER TABLE bigtree_callouts DROP COLUMN `group`");

	// Security policy setting
	sqlquery("INSERT INTO `bigtree_settings` (`id`,`value`,`system`) VALUES ('bigtree-internal-security-policy','{}','on')");
	sqlquery("CREATE TABLE `bigtree_login_attempts` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `ip` int(11) DEFAULT NULL, `user` int(11) DEFAULT NULL, `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
	sqlquery("CREATE TABLE `bigtree_login_bans` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `ip` int(11) DEFAULT NULL, `user` int(11) DEFAULT NULL, `created` timestamp NULL DEFAULT CURRENT_TIMESTAMP, `expires` datetime DEFAULT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8");

	// Media settings
	sqlquery("INSERT INTO `bigtree_settings` (`id`,`value`,`system`) VALUES ('bigtree-internal-media-settings','{}','on')");

	// New field types
	@unlink(SERVER_ROOT."cache/bigtree-form-field-types.json");

	// Setup an anonymous function for converting a resource set
	$resource_converter = function($resources) {
		$new_resources = array();
		
		foreach ($resources as $item) {
			// Array of Items no longer exists, switching to Matrix
			if ($item["type"] == "array") {
				$item["type"] = "matrix";
				$item["columns"] = array();
				$x = 0;
		
				foreach ($item["fields"] as $field) {
					$x++;
					$item["columns"][] = array(
						"id" => $field["key"],
						"type" => $field["type"],
						"title" => $field["title"],
						"display_title" => ($x == 1) ? "on" : ""
					);
		
				}
				unset($item["fields"]);
			}
		
			$r = array(
				"id" => $item["id"],
				"type" => $item["type"],
				"title" => $item["title"],
				"subtitle" => $item["subtitle"],
				"options" => array()
			);

			foreach ($item as $key => $val) {
				if ($key != "id" && $key != "title" && $key != "subtitle" && $key != "type") {
					$r["options"][$key] = $val;
				}
			}

			$new_resources[] = $r;
		}
		return BigTree::json($new_resources,true);
	};

	$field_converter = function($fields) {
		$new_fields = array();
		
		foreach ($fields as $id => $field) {
			// Array of Items no longer exists, switching to Matrix
			if ($field["type"] == "array") {
				$field["type"] = "matrix";
				$field["columns"] = array();
				$x = 0;
		
				foreach ($field["fields"] as $subfield) {
					$x++;
					$field["columns"][] = array(
						"id" => $subfield["key"],
						"type" => $subfield["type"],
						"title" => $subfield["title"],
						"display_title" => ($x == 1) ? "on" : ""
					);
				}
		
				unset($field["fields"]);
			}
		
			$r = array(
				"column" => $id,
				"type" => $field["type"],
				"title" => $field["title"],
				"subtitle" => $field["subtitle"],
				"options" => array()
			);
		
			foreach ($field as $key => $val) {
				if ($key != "id" && $key != "title" && $key != "subtitle" && $key != "type") {
					$r["options"][$key] = $val;
				}
			}
		
			$new_fields[] = $r;
		}
		
		return $new_fields;
	};

	// New resource format to be less restrictive on option names
	$q = sqlquery("SELECT * FROM bigtree_callouts");
	
	while ($f = sqlfetch($q)) {
		$resources = $resource_converter(json_decode($f["resources"],true));
		sqlquery("UPDATE bigtree_callouts SET resources = '$resources' WHERE id = '".$f["id"]."'");
	}

	$q = sqlquery("SELECT * FROM bigtree_templates");
	
	while ($f = sqlfetch($q)) {
		$resources = $resource_converter(json_decode($f["resources"],true));
		sqlquery("UPDATE bigtree_templates SET resources = '$resources' WHERE id = '".$f["id"]."'");
	}
	
	// Forms and Embedded Forms
	$q = sqlquery("SELECT * FROM bigtree_module_forms");
	
	while ($f = sqlfetch($q)) {
		$fields = $field_converter(json_decode($f["fields"],true));
		sqlquery("UPDATE bigtree_module_forms SET fields = '".BigTree::json($fields,true)."' WHERE id = '".$f["id"]."'");
	}
	
	$q = sqlquery("SELECT * FROM bigtree_module_embeds");
	
	while ($f = sqlfetch($q)) {
		$fields = $field_converter(json_decode($f["fields"],true));
		sqlquery("UPDATE bigtree_module_embeds SET fields = '".BigTree::json($fields,true)."' WHERE id = '".$f["id"]."'");
	}
	
	// Settings
	$q = sqlquery("SELECT * FROM bigtree_settings WHERE type = 'array'");
	
	while ($f = sqlfetch($q)) {
		// Update settings options to turn array into matrix
		$options = json_decode($f["options"],true);
		$options["columns"] = array();
		$x = 0;
	
		foreach ($options["fields"] as $field) {
			$x++;
			$options["columns"][] = array(
				"id" => $field["key"],
				"type" => $field["type"],
				"title" => $field["title"],
				"display_title" => ($x == 1) ? "on" : ""
			);
			if ($x == 1) {
				$display_key = $field["key"];
			}
		}
	
		unset($options["fields"]);

		// Update the value to set an internal title key
		$value = BigTreeCMS::getSetting($f["id"]);
	
		foreach ($value as &$entry) {
			$entry["__internal-title"] = $entry[$display_key];
		}
	
		unset($entry);

		// Update type/options
		sqlquery("UPDATE bigtree_settings SET type = 'matrix', options = '".BigTree::json($options,true)."' WHERE id = '".$f["id"]."'");
		// Update value separately
		BigTreeAdmin::updateSettingValue($f["id"],$value);
	}
	
	$admin->updateInternalSettingValue("bigtree-internal-revision", 200);
