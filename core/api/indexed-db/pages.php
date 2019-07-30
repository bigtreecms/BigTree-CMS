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
	
	// Functions for grabbing the latest records, will be used by both the created and updated methods to get the latest
	$get_latest_record = function($id) {
		$data = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $id);
		
		if ($data) {
			$record = [
				"id" => $data["id"],
				"parent" => $data["parent"],
				"nav_title" => $data["nav_title"],
				"path" => $data["path"],
				"archived" => $data["archived"],
				"in_nav" => $data["in_nav"],
				"position" => $data["position"],
				"max_age" => $data["max_age"] ?: 365,
				"age" => ceil((time() - strtotime($data["updated_at"])) / 24 / 60 / 60),
				"expires" => null,
				"seo_score" => $data["seo_score"],
				"seo_recommendations" => $data["seo_recommendations"]
			];
			
			if ($data["expire_at"]) {
				$record["expires"] = date(Router::$Config["date_format"] ?: "m/d/Y", strtotime($data["expire_at"]));
			}
			
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
			} elseif (strtotime($data["publish_at"]) > time()) {
				$record["status"] = "scheduled";
			} elseif ($data["expire_at"] != "" && strtotime($data["expire_at"]) < time()) {
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
			"status" => "pending",
			"age" => floor((time() - strtotime($item["date"])) / 24 / 60 / 60),
			"max_age" => intval($changes["max_age"]),
			"expires" => null
		];
	};
	
	if (empty($_GET["since"])) {
		$pages = [];
		$page_ids = SQL::fetchAllSingle("SELECT id FROM bigtree_pages");
		
		foreach ($page_ids as $id) {
			$pages[] = $get_latest_record($id);
		}
		
		$pending_ids = SQL::fetchAllSingle("SELECT id FROM bigtree_pending_changes
											WHERE `table` = 'bigtree_pages' AND `item_id` IS NULL");
		
		foreach ($pending_ids as $id) {
			$pages[] = $get_latest_pending_record("p".$id);
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
	