<?php
	namespace BigTree;
	
	/*
	 	Function: indexeddb/module-views
			Returns an array of IndexedDB commands for either caching a new set of module view data or updating an existing data set.
		
		Method: GET
	 
		Parameters:
	 		since - An optional timestamp to return updated data since.
	 	
		Returns:
			An array of IndexedDB commands
	*/
	
	$record_for_view = function($view, $item) {
		unset($item["view"]);
		
		if (!is_array($view["settings"]["fields"])) {
			return array_slice($item, 0, 12);
		} else {
			return array_slice($item, 0, 12 + count($view["settings"]["fields"]));
		}
	};
	
	$modules = DB::getAll("modules");
	$views = [];
	$actions = [];
	
	foreach ($modules as $module) {
		foreach ($module["interfaces"] as $interface) {
			if ($interface["type"] == "view") {
				$interface["schema-id"] = $module["id"]."-".$interface["id"];
				$views[] = $interface;
			}
		}
	}
	
	if (empty($_GET["since"])) {
		foreach ($views as $view) {
			$rows = SQL::fetchAll("SELECT * FROM bigtree_module_view_cache WHERE view = ?", $view["id"]);
			
			foreach ($rows as $row) {
				$actions["insert"][$view["schema-id"]][] = $record_for_view($view, $row);
			}
		}
		
		API::sendResponse($actions);
	}
	
	foreach ($views as $view) {
		$deleted_records = [];
		$created_records = [];
		$since = date("Y-m-d H:i:s", strtotime($_GET["since"]));
		
		$audit_trail_deletes = SQL::fetchAll("SELECT entry FROM bigtree_audit_trail
											  WHERE `table` = ? AND `date` >= ? AND `type` = 'delete'
											  ORDER BY id DESC", $view["table"], $since);
		
		// Run deletes first, don't want to pass creates/updates for something deleted
		foreach ($audit_trail_deletes as $item) {
			$actions["delete"][$view["schema-id"]][] = $item["entry"];
			$deleted_records[] = $item["entry"];
		}
		
		// Creates next, if we have the latest data we don't need to run updates on it
		$audit_trail_creates = SQL::fetchAll("SELECT entry, type FROM bigtree_audit_trail
											  WHERE `table` = ? AND `date` >= ? AND `type` = 'add'
											  ORDER BY id DESC", $view["table"], $since);
		
		foreach ($audit_trail_creates as $item) {
			if (in_array($item["entry"], $deleted_records)) {
				continue;
			}
			
			$record = SQL::fetch("SELECT * FROM bigtree_module_view_cache WHERE view = ? AND id = ?",
								 $view["id"], $item["entry"]);
			
			if ($record) {
				$actions["insert"][$view["schema-id"]][] = $record_for_view($view, $record);
				$created_records[] = $item["entry"];
			}
		}
		
		// Finally, updates, but only the latest, so a distinct ID
		$audit_trail_updates = SQL::fetchAll("SELECT DISTINCT(entry) FROM bigtree_audit_trail
											  WHERE `table` = ? AND `date` >= ? AND `type` = 'update'
											  ORDER BY id DESC", $view["table"], $since);
		
		foreach ($audit_trail_updates as $item) {
			if (in_array($item["entry"], $deleted_records) || in_array($item["entry"], $created_records)) {
				continue;
			}
			
			$record = SQL::fetch("SELECT * FROM bigtree_module_view_cache WHERE view = ? AND id = ?",
								 $view["id"], $item["entry"]);
			
			if ($record) {
				$actions["update"][$view["schema-id"]][$item["entry"]] = $record_for_view($view, $record);
			}
		}
	}
	
	API::sendResponse($actions);
	