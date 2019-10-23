<?php
	namespace BigTree;
	
	/*
	 	Function: indexed-db/templates
			Returns an array of IndexedDB commands for either caching a new set of template data or updating an existing data set.
		
		Method: GET
	 
		Parameters:
	 		since - An optional timestamp to return updated data since.
	 	
		Returns:
			An array of IndexedDB commands
	*/
	
	$actions = [];
	$user_level = Auth::user()->Level;
	
	if (!defined("API_SINCE") || defined("API_PERMISSIONS_CHANGED")) {
		$raw = DB::getAll("templates");
		$templates = [];
		
		foreach ($raw as $index => $template) {
			$templates[] = API::getTemplatesCacheObject($template);
		}
		
		$actions["put"] = $templates;
	}
	
	// No deletes in this request
	if (!defined("API_SINCE")) {
		API::sendResponse(["cache" => ["templates" => $actions]]);
	}
	
	$deleted_records = [];
	$audit_trail_deletes = SQL::fetchAll("SELECT entry FROM bigtree_audit_trail
										  WHERE `table` = 'config:templates' AND `date` >= ? AND `type` = 'delete'
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
											  WHERE `table` = 'config:templates' AND `date` >= ?
												AND (`type` = 'update' OR `type` = 'add')
											  ORDER BY id DESC", API_SINCE);
		
		foreach ($audit_trail_updates as $item) {
			if (in_array($item["entry"], $deleted_records)) {
				continue;
			}
			
			$template = DB::get("templates", $item["entry"]);
			
			if ($template) {
				$actions["put"][$item["entry"]] = API::getTemplatesCacheObject($template);
			}
		}
	}
	
	API::sendResponse(["cache" => ["templates" => $actions]]);
	