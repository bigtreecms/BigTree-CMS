<?php
	namespace BigTree;
	
	/*
	 	Function: indexed-db/users
			Returns an array of IndexedDB commands for either caching a new set of users data or updating an existing data set.
		
		Method: GET
	 
		Parameters:
	 		since - An optional timestamp to return updated data since.
	 	
		Returns:
			An array of IndexedDB commands
	*/
	
	$actions = [];
	$user_level = Auth::user()->Level;
	$get_record = function($record) use ($user_level) {
		if (!$user_level) {
			$record["access_level"] = null;
		} elseif ($user_level < $record["level"]) {
			$record["access_level"] = null;
		} else {
			$record["access_level" ] = "p";
		}
		
		return $record;
	};
	
	if (!defined("API_SINCE") || defined("API_PERMISSIONS_CHANGED")) {
		$users = SQL::fetchAll("SELECT id, name, email, company, level FROM bigtree_users");
		
		foreach ($users as $index => $user) {
			$users[$index] = $get_record($user);
		}
		
		$actions["put"] = $users;
	}
	
	// No deletes in this request
	if (!defined("API_SINCE")) {
		API::sendResponse($actions);
	}
	
	$deleted_records = [];
	$audit_trail_deletes = SQL::fetchAll("SELECT entry FROM bigtree_audit_trail
										  WHERE `table` = 'bigtree_users' AND `date` >= ? AND `type` = 'delete'
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
											  WHERE `table` = 'bigtree_users' AND `date` >= ?
												AND (`type` = 'update' OR `type` = 'add')
											  ORDER BY id DESC", API_SINCE);
		
		foreach ($audit_trail_updates as $item) {
			if (in_array($item["entry"], $deleted_records)) {
				continue;
			}
			
			$user = SQL::fetch("SELECT id, name, email, company, level FROM bigtree_users WHERE id = ?", $item["entry"]);
			
			if ($user) {
				$actions["put"][$item["entry"]] = $get_record($user);
			}
		}
	}
	
	API::sendResponse($actions);
	