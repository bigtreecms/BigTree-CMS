<?php
	namespace BigTree;
	
	/*
	 	Function: indexed-db/feeds
			Returns an array of IndexedDB commands for either caching a new set of feeds data or updating an existing data set.
		
		Method: GET
	 
		Parameters:
	 		since - An optional timestamp to return updated data since.
	 	
		Returns:
			An array of IndexedDB commands
	*/
	
	$actions = [];
	
	if (!defined("API_SINCE") || defined("API_PERMISSIONS_CHANGED")) {
		$raw = DB::getAll("feeds");
		$feeds = [];
		
		foreach ($raw as $index => $feed) {
			$feeds[] = API::getFeedsCacheObject($feed);
		}
		
		$actions["put"] = $feeds;
	}
	
	// No deletes in this request
	if (!defined("API_SINCE")) {
		API::sendResponse(["cache" => ["feeds" => $actions]]);
	}
	
	$deleted_records = [];
	$audit_trail_deletes = SQL::fetchAll("SELECT entry FROM bigtree_audit_trail
										  WHERE `table` = 'config:feeds' AND `date` >= ? AND `type` = 'delete'
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
											  WHERE `table` = 'config:feeds' AND `date` >= ?
												AND (`type` = 'update' OR `type` = 'add')
											  ORDER BY id DESC", API_SINCE);
		
		foreach ($audit_trail_updates as $item) {
			if (in_array($item["entry"], $deleted_records)) {
				continue;
			}
			
			$feed = DB::get("feeds", $item["entry"]);
			
			if ($feed) {
				$actions["put"][$item["entry"]] = API::getFeedsCacheObject($feed);
			}
		}
	}
	
	API::sendResponse(["cache" => ["feeds" => $actions]]);
	