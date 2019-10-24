<?php
	namespace BigTree;
	
	/*
	 	Function: indexed-db/callout-groups
			Returns an array of IndexedDB commands for either caching a new set of callout group data or updating an existing data set.
		
		Method: GET
	 
		Parameters:
	 		since - An optional timestamp to return updated data since.
	 	
		Returns:
			An array of IndexedDB commands
	*/
	
	$actions = [];
	
	if (!defined("API_SINCE") || defined("API_PERMISSIONS_CHANGED")) {
		$actions["put"] = DB::getAll("callout-groups");
	}
	
	// No deletes in this request
	if (!defined("API_SINCE")) {
		API::sendResponse(["cache" => ["callout-groups" => $actions]]);
	}
	
	$deleted_records = [];
	$audit_trail_deletes = SQL::fetchAll("SELECT entry FROM bigtree_audit_trail
										  WHERE `table` = 'config:callout-groups' AND `date` >= ? AND `type` = 'delete'
										  ORDER BY id DESC", API_SINCE);
	
	// Run deletes first, don't want to pass creates/updates for something deleted
	foreach ($audit_trail_deletes as $item) {
		$actions["delete"][] = $item["entry"];
		$deleted_records[] = $item["entry"];
	}
	
	// If permissions changed we've already done all put statements
	if (!defined("API_PERMISSIONS_CHANGED")) {
		// Creates / updates
		$audit_trail_updates = SQL::fetchAll("SELECT DISTINCT(entry) FROM bigtree_audit_trail
											  WHERE `table` = 'config:callout-groups' AND `date` >= ?
												AND (`type` = 'update' OR `type` = 'add')
											  ORDER BY id DESC", API_SINCE);
		
		foreach ($audit_trail_updates as $item) {
			if (in_array($item["entry"], $deleted_records)) {
				continue;
			}
			
			$group = DB::get("callout-groups", $item["entry"]);
			
			if ($group) {
				$actions["put"][$item["entry"]] = $group;
			}
		}
	}
	
	API::sendResponse(["cache" => ["callout-groups" => $actions]]);
	