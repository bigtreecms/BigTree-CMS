<?php
	namespace BigTree;
	
	/*
	 	Function: indexeddb/settings
			Returns an array of IndexedDB commands for either caching a new set of settings data or updating an existing data set.
		
		Method: GET
	 
		Parameters:
	 		since - An optional timestamp to return updated data since.
	 	
		Returns:
			An array of IndexedDB commands
	*/
	
	// Function for getting setting value for cache
	$get_setting_value = function($id) {
		$value_data = SQL::fetch("SELECT encrypted, value FROM bigtree_settings WHERE id = ?", $id);
		
		if (!$value_data) {
			return null;
		}
		
		if ($value_data["encrypted"]) {
			return Text::translate("-- Encrypted --");
		} else {
			$value = json_decode($value_data["value"], true);
			
			if (is_array($value)) {
				return Text::translate("-- Array --");
			} else {
				return Text::trimLength(Text::htmlEncode(strip_tags(Link::decode($value))), 100);
			}
		}
	};
	
	if (empty($_GET["since"])) {
		$all = DB::getAll("settings");
		$settings = [];
		
		foreach ($all as $item) {
			$settings[] = [
				"id" => $item["id"],
				"title" => $item["name"],
				"value" => $get_setting_value($item["id"])
			];
		}
		
		API::sendResponse(["insert" => $settings]);
	}
	
	$actions = [];
	$deleted_records = [];
	$created_records = [];
	$since = date("Y-m-d H:i:s", is_numeric($_GET["since"]) ? $_GET["since"] : strtotime($_GET["since"]));
	
	$audit_trail_deletes = SQL::fetchAll("SELECT entry FROM bigtree_audit_trail
										  WHERE `table` = 'config:settings' AND `date` >= ? AND `type` = 'delete'
										  ORDER BY id DESC", $since);
	
	// Run deletes first, don't want to pass creates/updates for something deleted
	foreach ($audit_trail_deletes as $item) {
		$actions["delete"][] = $item["entry"];
		$deleted_records[] = $item["entry"];
	}
	
	// Creates next, if we have the latest data we don't need to run updates on it
	$audit_trail_creates = SQL::fetchAll("SELECT entry, type FROM bigtree_audit_trail
										  WHERE `table` = 'config:settings' AND `date` >= ? AND `type` = 'add'
										  ORDER BY id DESC", $since);
	
	foreach ($audit_trail_creates as $item) {
		if (in_array($item["entry"], $deleted_records)) {
			continue;
		}
		
		$setting = DB::get("settings", $item["entry"]);
		
		if ($setting) {
			$actions["insert"][] = [
				"id" => $setting["id"],
				"title" => $setting["name"],
				"value" => $get_setting_value($setting["id"])
			];
			$created_records[] = $item["entry"];
		}
	}
	
	// Finally, updates, but only the latest, so a distinct ID
	$audit_trail_updates = SQL::fetchAll("SELECT DISTINCT(entry) FROM bigtree_audit_trail
										  WHERE (`table` = 'config:settings' OR `table` = 'bigtree_settings`)
										    AND `date` >= ? AND `type` = 'update'
										  ORDER BY id DESC", $since);
	
	foreach ($audit_trail_updates as $item) {
		if (in_array($item["entry"], $deleted_records) || in_array($item["entry"], $created_records)) {
			continue;
		}
		
		// Internal setting
		if (!DB::exists("settings", $item["entry"])) {
			continue;
		}
		
		$setting = DB::get("settings", $item["entry"]);
		$actions["update"][$item["entry"]] = [
			"id" => $item["entry"],
			"title" => $setting["name"],
			"value" => $get_setting_value($item["entry"])
		];
	}
	
	API::sendResponse($actions);
	