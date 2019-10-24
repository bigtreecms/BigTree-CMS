<?php
	namespace BigTree;
	
	/*
	 	Function: indexed-db/field-types
			Returns an array of IndexedDB commands for either caching a new set of field types data or updating an existing data set.
			If the requesting user is not a developer, an empty response is returned.
		
		Method: GET
	 
		Parameters:
	 		since - An optional timestamp to return updated data since.
	 	
		Returns:
			An array of IndexedDB commands
	*/
	
	$actions = [];
	
	if (Auth::user()->Level < 2) {
		API::sendResponse();
	}
	
	if (!defined("API_SINCE") || defined("API_PERMISSIONS_CHANGED")) {
		$raw = DB::getAll("field-types");
		$field_types = [];
		
		foreach ($raw as $index => $field_type) {
			$field_types[] = API::getFieldTypesCacheObject($field_type);
		}
		
		$actions["put"] = $field_types;
	}
	
	// No deletes in this request
	if (!defined("API_SINCE")) {
		API::sendResponse(["cache" => ["field-types" => $actions]]);
	}
	
	$deleted_records = [];
	$audit_trail_deletes = SQL::fetchAll("SELECT entry FROM bigtree_audit_trail
										  WHERE `table` = 'config:field-types' AND `date` >= ? AND `type` = 'delete'
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
											  WHERE `table` = 'config:field-types' AND `date` >= ?
												AND (`type` = 'update' OR `type` = 'add')
											  ORDER BY id DESC", API_SINCE);
		
		foreach ($audit_trail_updates as $item) {
			if (in_array($item["entry"], $deleted_records)) {
				continue;
			}
			
			$field_type = DB::get("field-types", $item["entry"]);
			
			if ($field_type) {
				$actions["put"][$item["entry"]] = API::getFieldTypesCacheObject($field_type);
			}
		}
	}
	
	API::sendResponse(["cache" => ["field-types" => $actions]]);
	