<?php
	namespace BigTree;
	
	/*
	 	Function: indexed-db/settings
			Returns an array of IndexedDB commands for either caching a new set of settings data or updating an existing data set.
		
		Method: GET
	 
		Parameters:
	 		since - An optional timestamp to return updated data since.
	 	
		Returns:
			An array of IndexedDB commands
	*/
	
	$actions = [];
	$general_access_level = Auth::user()->Level ? "p" : null;
	$get_record = function($item) use ($general_access_level) {
		if ($item["locked"]) {
			if (Auth::user()->Level > 1) {
				$access_level = "p";
			} else {
				$access_level = null;
			}
		} else {
			$access_level = $general_access_level;
		}
		
		$record = [
			"id" => $item["id"],
			"title" => $item["name"],
			"locked" => $item["locked"],
			"value" => null,
			"access_level" => $access_level
		];
		
		if ($access_level) {
			$value_data = SQL::fetch("SELECT encrypted, value FROM bigtree_settings WHERE id = ?", $item["id"]);
			
			if ($value_data) {
				if ($value_data["encrypted"]) {
					$record["value"] = Text::translate("-- Encrypted --");
				} else {
					$value = json_decode($value_data["value"], true);
					
					if (is_array($value)) {
						$record["value"] = Text::translate("-- Array --");
					} else {
						$record["value"] = Text::trimLength(Text::htmlEncode(strip_tags(Link::decode($value))), 100);
					}
				}
			}
		}
		
		return $record;
	};
	
	if (!defined("API_SINCE") || defined("API_PERMISSIONS_CHANGED")) {
		$all = DB::getAll("settings");
		$settings = [];
		
		foreach ($all as $item) {
			$settings[] = $get_record($item);
		}
		
		$actions["put"] = $settings;
	}
	
	// No deletes in this request
	if (!defined("API_PERMISSIONS_CHANGED")) {
		API::sendResponse($actions);
	}
	
	$deleted_records = [];
	$audit_trail_deletes = SQL::fetchAll("SELECT entry FROM bigtree_audit_trail
										  WHERE `table` = 'config:settings' AND `date` >= ? AND `type` = 'delete'
										  ORDER BY id DESC", API_SINCE);
	
	// Run deletes first, don't want to pass creates/updates for something deleted
	foreach ($audit_trail_deletes as $item) {
		$actions["delete"][] = $item["entry"];
		$deleted_records[] = $item["entry"];
	}
	
	// If permissions changed we've already done all put statements
	if (!defined("API_PERMISSIONS_CHANGED")) {
		// Creates and updates
		$audit_trail_updates = SQL::fetchAll("SELECT DISTINCT(entry) FROM bigtree_audit_trail
											  WHERE (`table` = 'config:settings' OR `table` = 'bigtree_settings`)
												AND (`type` = 'update' OR `type` = 'add')
												AND `date` >= ?
											  ORDER BY id DESC", API_SINCE);
		
		foreach ($audit_trail_updates as $item) {
			if (in_array($item["entry"], $deleted_records)) {
				continue;
			}
			
			// Internal setting
			if (!DB::exists("settings", $item["entry"])) {
				continue;
			}
			
			$setting = DB::get("settings", $item["entry"]);
			$actions["put"][$item["entry"]] = $get_record($setting);
		}
	}
	
	API::sendResponse($actions);
	