<?php
	namespace BigTree;
	
	/*
	 	Function: indexeddb/pages
			Returns an array of IndexedDB commands for either caching a new set of pages data or updating an existing data set.
		
		Method: GET
	 
		Parameters:
	 		since - An optional timestamp to return updated data since.
	 	
		Returns:
			An array of IndexedDB commands
	*/
	
	if (empty($_GET["since"])) {
		$pages = SQL::fetchAll("SELECT id, parent, nav_title, path, archived, in_nav, position, publish_at, expire_at
								FROM bigtree_pages
								ORDER BY id");
		
		foreach ($pages as $index => $page) {
			$pending = SQL::fetch("SELECT * FROM bigtree_pending_changes
								   WHERE `table` = 'bigtree_pages' AND `item_id` = ?", $page["id"]);
			
			if ($pending) {
				$changes = json_decode($pending["changes"], true);
				
				foreach ($changes as $key => $value) {
					if (isset($pages[$index][$key])) {
						$pages[$index][$key] = $value;
					}
				}

				$status = "changed";
			} elseif (strtotime($page["publish_at"]) > time()) {
				$status = "scheduled";
			} elseif ($page["expire_at"] != "" && strtotime($page["expire_at"]) < time()) {
				$status = "expired";
			} else {
				$status = "published";
			}
			
			$pages[$index]["status"] = $status;
			unset($pages[$index]["expire_at"]);
			unset($pages[$index]["publish_at"]);
		}
		
		
		$pending = SQL::fetchAll("SELECT * FROM bigtree_pending_changes
								  WHERE `table` = 'bigtree_pages' AND `item_id` IS NULL");
		
		foreach ($pending as $item) {
			$changes = json_decode($item["changes"], true);
			$pages[] = [
				"id" => "p".$item["id"],
				"parent" => $item["pending_page_parent"],
				"nav_title" => $changes["nav_title"],
				"path" => "_preview/p".$item["id"],
				"archived" => false,
				"in_nav" => $changes["in_nav"],
				"position" => false,
				"status" => "pending"
			];
		}
			
		API::sendResponse(["insert" => $pages]);
	}
	
	$actions = [];
	$deleted_records = [];
	$created_records = [];
	$since = date("Y-m-d H:i:s", strtotime($_GET["since"]));
	
	$audit_trail_deletes = SQL::fetchAll("SELECT entry FROM bigtree_audit_trail
										  WHERE `table` = 'bigtree_pages' AND `date` >= ? AND `type` = 'delete'
										  ORDER BY id DESC", $since);
	
	// Run deletes first, don't want to pass creates/updates for something deleted
	foreach ($audit_trail_deletes as $item) {
		$actions["delete"][] = $item["entry"];
		$deleted_records[] = $item["entry"];
	}
	
	// Functions for grabbing the latest records, will be used by both the created and updated methods to get the latest
	$get_latest_record = function($id) {
		$record = SQL::fetch("SELECT id, parent, nav_title, path, archived, in_nav, position, publish_at, expire_at
							  FROM bigtree_pages WHERE id = ?", $id);
		
		if ($record) {
			$pending = SQL::fetch("SELECT * FROM bigtree_pending_changes
								   WHERE `table` = 'bigtree_pages' AND `item_id` = ?", $id);
			
			if ($pending) {
				$changes = json_decode($pending["changes"], true);
				
				foreach ($changes as $key => $value) {
					if (isset($record[$key])) {
						$record[$key] = $value;
					}
				}
				
				$record["status"] = "changed";
			} elseif (strtotime($record["publish_at"]) > time()) {
				$record["status"] = "scheduled";
			} elseif ($record["expire_at"] != "" && strtotime($record["expire_at"]) < time()) {
				$record["status"] = "expired";
			} else {
				$record["status"] = "published";
			}
			
			return $record;
		}
		
		return null;
	};
	
	$get_latest_pending_record = function($id) {
		$id = substr($id, 1);
		$item = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $id);
		
		if (!$item) {
			return null;
		}
		
		$changes = json_decode($item["changes"], true);
		
		return [
			"id" => "p".$item["id"],
			"parent" => $item["pending_page_parent"],
			"nav_title" => $changes["nav_title"],
			"path" => "_preview/p".$item["id"],
			"archived" => false,
			"in_nav" => $changes["in_nav"],
			"position" => false,
			"status" => "pending"
		];
	};
	
	// Creates next, if we have the latest data we don't need to run updates on it
	$audit_trail_creates = SQL::fetchAll("SELECT entry, type, action FROM bigtree_audit_trail
										  WHERE `table` = 'bigtree_pages' AND `date` >= ? AND `type` = 'add'
										  ORDER BY id DESC", $since);
	
	foreach ($audit_trail_creates as $item) {
		if (in_array($item["entry"], $deleted_records)) {
			continue;
		}
	
		if (is_numeric($item["entry"])) {
			$record = $get_latest_record($item["entry"]);
			
			if ($record) {
				$actions["insert"][] = $record;
			}
		} else {
			$record = $get_latest_pending_record($item["entry"]);
			
			if ($record) {
				$actions["insert"][] = $record;
			}
		}
		
		$created_records[] = $item["entry"];
	}
	
	// Finally, updates, but only the latest, so a distinct ID
	$audit_trail_updates = SQL::fetchAll("SELECT DISTINCT(entry) FROM bigtree_audit_trail
										  WHERE `table` = 'bigtree_pages' AND `date` >= ? AND `type` = 'update'
										  ORDER BY id DESC", $since);
	
	foreach ($audit_trail_updates as $item) {
		if (in_array($item["entry"], $deleted_records) || in_array($item["entry"], $created_records)) {
			continue;
		}
		
		if (is_numeric($item["entry"])) {
			$record = $get_latest_record($item["entry"]);
			
			if ($record) {
				$actions["update"][$item["entry"]] = $record;
			}
		} else {
			$record = $get_latest_pending_record($item["entry"]);
			
			if ($record) {
				$actions["update"][$item["entry"]] = $record;
			}
		}
	}
	
	API::sendResponse($actions);
	