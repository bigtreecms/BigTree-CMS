<?php
	namespace BigTree;
	
	/*
	 	Function: indexed-db/view-cache
			Returns an array of IndexedDB commands for either caching a new set of module view data or updating an existing data set.
		
		Method: GET
	 
		Parameters:
	 		since - An optional timestamp to return updated data since.
	 	
		Returns:
			An array of IndexedDB commands
	*/
	
	$modules = DB::getAll("modules");
	$views = [];
	$actions = [];
	$deleted_records = [];
	
	foreach ($modules as $module) {
		foreach ($module["interfaces"] as $interface) {
			if ($interface["type"] == "view") {
				$interface["module"] = new Module($module["id"]);
				$views[$interface["id"]] = $interface;
			}
		}
	}
	
	$get_record = function($item) use ($views) {
		if ($view = $views[$item["view"]]) {
			$item["access_level"] = Auth::user()->getCachedAccessLevel($view["module"], $item);
			$item["id"] = $item["view"]."-".$item["entry"];
			
			return $item;
		} else {
			return null;
		}
	};
	
	if (defined("API_SINCE") && empty($_GET["page"])) {
		foreach ($views as $view) {
			$audit_trail_deletes = SQL::fetchAll("SELECT entry FROM bigtree_audit_trail
												  WHERE `table` = ? AND `date` >= ? AND `type` = 'delete'
												  ORDER BY id DESC", $view["table"], API_SINCE);
			
			// Run deletes first, don't want to pass creates/updates for something deleted
			foreach ($audit_trail_deletes as $item) {
				$actions["delete"][] = $view["id"]."-".$item["entry"];
				$deleted_records[] = $view["id"]."-".$item["entry"];
			}
		}
	}
	
	if (!defined("API_SINCE") || defined("API_PERMISSIONS_CHANGED")) {
		$total = intval(SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_module_view_cache"));
		
		// Paginated response
		if ($total > 1000) {
			$current_page = isset($_GET["page"]) ? intval($_GET["page"]) : 1;
			$total_pages = ceil($total / 1000);
			$limit = ($current_page - 1) * 1000;
			
			if ($current_page < 1) {
				$current_page = 1;
			}
			
			$records = SQL::fetchAll("SELECT * FROM bigtree_module_view_cache
									  ORDER BY view ASC, id ASC
									  LIMIT $limit, 1000");
			
			foreach ($records as $record) {
				$actions["put"][] = $get_record($record);
			}
			
			if ($current_page != $total_pages) {
				API::sendResponse($actions, null, null, WWW_ROOT."api/indexed-db/view-cache/?page=".($current_page + 1));
			} else {
				API::sendResponse($actions);
			}
		} else {
			// All in one response
			$records = SQL::fetchAll("SELECT * FROM bigtree_module_view_cache");
			
			foreach ($records as $record) {
				$actions["put"][] = $get_record($record);
			}
			
			API::sendResponse($actions);
		}
		
		API::sendResponse($actions);
	}
	
	// If permissions changed we've already done all put statements
	if (!defined("API_PERMISSIONS_CHANGED")) {
		// Creates and updates for the related tables
		foreach ($views as $view) {
			$audit_trail_updates = SQL::fetchAll("SELECT DISTINCT(entry) FROM bigtree_audit_trail
												  WHERE `table` = ? AND `date` >= ?
												    AND (`type` = 'update' OR `type` = 'add')
												  ORDER BY id DESC", $view["table"], API_SINCE);
			
			foreach ($audit_trail_updates as $item) {
				$item["id"] = $view["id"]."-".$item["entry"];
				
				if (in_array($item["id"], $deleted_records)) {
					continue;
				}
				
				$record = SQL::fetch("SELECT * FROM bigtree_module_view_cache WHERE view = ? AND id = ?",
									 $view["id"], $item["entry"]);
				
				if ($record) {
					$actions["put"][] = $get_record($record);
				}
			}
		}
	}
	
	API::sendResponse($actions);
	