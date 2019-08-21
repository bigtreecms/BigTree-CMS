<?php
	namespace BigTree;
	
	/*
	 	Function: indexed-db/modules
			Returns an array of IndexedDB commands for either caching a new set of module data or updating an existing data set.
		
		Method: GET
	 
		Parameters:
	 		since - An optional timestamp to return updated data since.
	 	
		Returns:
			An array of IndexedDB commands
	*/
	
	$actions = [];
	$get_record = function($item) {
		$module = new Module($item);
		
		return [
			"id" => $item["id"],
			"group" => $item["group"],
			"name" => $item["name"],
			"position" => $item["position"] ?: 0,
			"actions" => $item["actions"],
			"route" => $item["route"],
			"access_level" => $module->UserAccessLevel
		];
	};
	
	if (!defined("API_SINCE") || defined("API_PERMISSIONS_CHANGED")) {
		$all = DB::getAll("modules");
		$modules = [];
		
		foreach ($all as $module) {
			$modules[] = $get_record($module);
		}
		
		$actions["put"] = $modules;
	}
	
	// No deletes in this request
	if (!defined("API_SINCE")) {
		API::sendResponse($actions);
	}
	
	$deleted_records = [];
	$audit_trail_deletes = SQL::fetchAll("SELECT entry FROM bigtree_audit_trail
										  WHERE `table` = 'config:modules' AND `date` >= ? AND `type` = 'delete'
										  ORDER BY id DESC", API_SINCE);
	
	// Run deletes first, don't want to pass creates/updates for something deleted
	foreach ($audit_trail_deletes as $item) {
		$actions["delete"][] = $item["entry"];
		$deleted_records[] = $item["entry"];
	}
	
	// If permissions changed we've already done all put statements
	if (!defined("API_PERMISSIONS_CHANGED")) {
		// Finally, updates, but only the latest, so a distinct ID
		$audit_trail_updates = SQL::fetchAll("SELECT DISTINCT(entry) FROM bigtree_audit_trail
											  WHERE `table` = 'config:module-groups' AND `date` >= ?
												AND (`type` = 'update' OR `type` = 'add')
											  ORDER BY id DESC", API_SINCE);
		
		foreach ($audit_trail_updates as $item) {
			if (in_array($item["entry"], $deleted_records)) {
				continue;
			}
			
			$module = DB::get("modules", $item["entry"]);
			
			if ($module) {
				$actions["put"][$item["entry"]] = $get_record($module);
			}
		}
	}
	
	API::sendResponse($actions);
	