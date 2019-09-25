<?php
	namespace BigTree;
	
	/*
	 	Function: indexed-db/files
			Returns an array of IndexedDB commands for either caching a new set of files data or updating an existing data set.
		
		Method: GET
	 
		Parameters:
	 		since - An optional timestamp to return updated data since.
	 	
		Returns:
			An array of IndexedDB commands
	*/
	
	$actions = [];
	$get_record = function($file) {
		if ($file["is_image"]) {
			$file["image"] = FileSystem::getPrefixedFile(Link::decode($file["file"]), "list-preview/");
		}
		
		unset($file["is_image"]);
		unset($file["file"]);
		
		return $file;
	};
	
	if (!defined("API_SINCE") || defined("API_PERMISSIONS_CHANGED")) {
		$files = SQL::fetchAll("SELECT id, folder, name, type, size, is_image, file FROM bigtree_resources");
		
		foreach ($files as $index => $file) {
			$files[$index] = $get_record($file);
		}
		
		$actions["put"] = $files;
	}
	
	// No deletes in this request
	if (!defined("API_SINCE")) {
		API::sendResponse($actions);
	}
	
	$deleted_records = [];
	$audit_trail_deletes = SQL::fetchAll("SELECT entry FROM bigtree_audit_trail
										  WHERE `table` = 'bigtree_resources' AND `date` >= ? AND `type` = 'delete'
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
											  WHERE `table` = 'bigtree_resources' AND `date` >= ?
												AND (`type` = 'update' OR `type` = 'add')
											  ORDER BY id DESC", API_SINCE);
		
		foreach ($audit_trail_updates as $item) {
			if (in_array($item["entry"], $deleted_records)) {
				continue;
			}
			
			$file = SQL::fetch("SELECT id, folder, name, type, size, is_image, file FROM bigtree_resources
							WHERE id = ?", $item["entry"]);
			
			if ($file) {
				$actions["put"][$item["entry"]] = $get_record($file);
			}
		}
	}
	
	API::sendResponse($actions);
	