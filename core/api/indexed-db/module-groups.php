<?php
	namespace BigTree;
	
	/*
	 	Function: indexed-db/module-groups
			Returns an array of IndexedDB commands for either caching a new set of module groups data or updating an existing data set.
		
		Method: GET
	 
		Parameters:
	 		since - An optional timestamp to return updated data since.
	 	
		Returns:
			An array of IndexedDB commands
	*/
	
	if (!defined("API_SINCE")) {
		$groups = DB::getAll("module-groups");
		
		API::sendResponse(["cache" => ["module-groups" => ["put" => $groups]]]);
	}
	
	$actions = [];
	$deleted_records = [];
	
	$audit_trail_deletes = SQL::fetchAll("SELECT entry FROM bigtree_audit_trail
										  WHERE `table` = 'config:module-groups' AND `date` >= ? AND `type` = 'delete'
										  ORDER BY id DESC", API_SINCE);
	
	// Run deletes first, don't want to pass creates/updates for something deleted
	foreach ($audit_trail_deletes as $item) {
		$actions["delete"][] = $item["entry"];
		$deleted_records[] = $item["entry"];
	}
	
	// Creates and updates but only the latest, so a distinct ID
	$audit_trail_updates = SQL::fetchAll("SELECT DISTINCT(entry) FROM bigtree_audit_trail
										  WHERE `table` = 'config:module-groups' AND `date` >= ?
										    AND (`type` = 'update' OR `type` = 'add')
										  ORDER BY id DESC", API_SINCE);
	
	foreach ($audit_trail_updates as $item) {
		if (in_array($item["entry"], $deleted_records)) {
			continue;
		}
		
		$group = DB::get("module-groups", $item["entry"]);
		
		if ($group) {
			$actions["put"][] = $group;
		}
	}
	
	API::sendResponse(["cache" => ["module-groups" => $actions]]);
	