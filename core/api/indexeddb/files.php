<?php
	namespace BigTree;
	
	/*
	 	Function: indexeddb/files
			Returns an array of IndexedDB commands for either caching a new set of files data or updating an existing data set.
		
		Method: GET
	 
		Parameters:
	 		since - An optional timestamp to return updated data since.
	 	
		Returns:
			An array of IndexedDB commands
	*/
	
	$get_imaged_file = function($file) {
		if ($file["is_image"]) {
			$file["image"] = FileSystem::getPrefixedFile(Link::decode($file["file"]), "list-preview/");
		}
		
		unset($file["is_image"]);
		unset($file["file"]);
		
		return $file;
	};
	
	if (empty($_GET["since"])) {
		$files = SQL::fetchAll("SELECT id, folder, name, type, size, is_image, file FROM bigtree_resources");
		
		foreach ($files as $index => $file) {
			$files[$index] = $get_imaged_file($file);
		}
		
		API::sendResponse(["insert" => ["files" => $files]]);
	}
	
	$actions = [];
	$deleted_records = [];
	$created_records = [];
	$since = date("Y-m-d H:i:s", strtotime($_GET["since"]));
	
	$audit_trail_deletes = SQL::fetchAll("SELECT entry FROM bigtree_audit_trail
										  WHERE `table` = 'bigtree_resources' AND `date` >= ? AND `type` = 'delete'
										  ORDER BY id DESC", $since);
	
	// Run deletes first, don't want to pass creates/updates for something deleted
	foreach ($audit_trail_deletes as $item) {
		$actions["delete"]["files"][] = $item["entry"];
		$deleted_records[] = $item["entry"];
	}
	
	// Creates next, if we have the latest data we don't need to run updates on it
	$audit_trail_creates = SQL::fetchAll("SELECT entry, type FROM bigtree_audit_trail
										  WHERE `table` = 'bigtree_resources' AND `date` >= ? AND `type` = 'add'
										  ORDER BY id DESC", $since);
	
	foreach ($audit_trail_creates as $item) {
		if (in_array($item["entry"], $deleted_records)) {
			continue;
		}
		
		$file = SQL::fetch("SELECT id, folder, name, type, size, is_image, file FROM bigtree_resources
							WHERE id = ?", $item["entry"]);
		
		if ($file) {
			$actions["insert"]["files"][] = $get_imaged_file($file);
			$created_records[] = $item["entry"];
		}
	}
	
	// Finally, updates, but only the latest, so a distinct ID
	$audit_trail_updates = SQL::fetchAll("SELECT DISTINCT(entry) FROM bigtree_audit_trail
										  WHERE `table` = 'bigtree_resources' AND `date` >= ? AND `type` = 'update'
										  ORDER BY id DESC", $since);
	
	foreach ($audit_trail_updates as $item) {
		if (in_array($item["entry"], $deleted_records) || in_array($item["entry"], $created_records)) {
			continue;
		}
		
		$file = SQL::fetch("SELECT id, folder, name, type, size, is_image, file FROM bigtree_resources
							WHERE id = ?", $item["entry"]);
		
		if ($file) {
			$actions["update"]["files"][$item["entry"]] = $get_imaged_file($file);
		}
	}
	
	API::sendResponse($actions);
	