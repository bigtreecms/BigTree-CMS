<?php
	namespace BigTree;
	
	/*
	 	Function: indexeddb/tags
			Returns an array of IndexedDB commands for either caching a new set of tags data or updating an existing data set.
		
		Method: GET
	 
		Parameters:
	 		since - An optional timestamp to return updated data since.
	 	
		Returns:
			An array of IndexedDB commands
	*/
	
	if (empty($_GET["since"])) {
		$tags = SQL::fetchAll("SELECT id, tag, usage_count FROM bigtree_tags");
		
		API::sendResponse(["insert" => $tags]);
	}
	
	$actions = [];
	$deleted_records = [];
	$created_records = [];
	$since = date("Y-m-d H:i:s", strtotime($_GET["since"]));
	
	$audit_trail_deletes = SQL::fetchAll("SELECT entry FROM bigtree_audit_trail
										  WHERE `table` = 'bigtree_tags' AND `date` >= ? AND `type` = 'delete'
										  ORDER BY id DESC", $since);
	
	// Run deletes first, don't want to pass creates/updates for something deleted
	foreach ($audit_trail_deletes as $item) {
		$actions["delete"][] = $item["entry"];
		$deleted_records[] = $item["entry"];
	}
	
	// Creates next, if we have the latest data we don't need to run updates on it
	$audit_trail_creates = SQL::fetchAll("SELECT entry, type FROM bigtree_audit_trail
										  WHERE `table` = 'bigtree_tags' AND `date` >= ? AND `type` = 'add'
										  ORDER BY id DESC", $since);
	
	foreach ($audit_trail_creates as $item) {
		if (in_array($item["entry"], $deleted_records)) {
			continue;
		}
		
		$tag = SQL::fetch("SELECT id, tag, usage_count FROM bigtree_tags WHERE id = ?", $item["entry"]);
		
		if ($tag) {
			$actions["insert"][] = $tag;
			$created_records[] = $item["entry"];
		}
	}
	
	// Finally, updates, but only the latest, so a distinct ID
	$audit_trail_updates = SQL::fetchAll("SELECT DISTINCT(entry) FROM bigtree_audit_trail
										  WHERE `table` = 'bigtree_tags' AND `date` >= ? AND `type` = 'update'
										  ORDER BY id DESC", $since);
	
	foreach ($audit_trail_updates as $item) {
		if (in_array($item["entry"], $deleted_records) || in_array($item["entry"], $created_records)) {
			continue;
		}
		
		$tag = SQL::fetch("SELECT id, tag, usage_count FROM bigtree_tags WHERE id = ?", $item["entry"]);
		
		if ($tag) {
			$actions["update"][$item["entry"]] = $tag;
		}
	}
	
	API::sendResponse($actions);
	