<?php
	namespace BigTree;
	
	/*
	 	Function: indexed-db/pages
			Returns an array of IndexedDB commands for either caching a new set of pages data or updating an existing data set.
		
		Method: GET
	 
		Parameters:
	 		since - An optional timestamp to return updated data since.
	 	
		Returns:
			An array of IndexedDB commands
	*/
	
	$actions = [];
	$deleted_records = [];

	// We're doing deletes first since puts might be paginated
	if (defined("API_SINCE") && empty($_GET["page"])) {
		$audit_trail_deletes = SQL::fetchAll("SELECT entry FROM bigtree_audit_trail
											  WHERE `table` = 'bigtree_pages' AND `date` >= ? AND `type` = 'delete'
											  ORDER BY id DESC", API_SINCE);
		
		// Run deletes first, don't want to pass creates/updates for something deleted
		foreach ($audit_trail_deletes as $item) {
			$actions["delete"][] = $item["entry"];
			$deleted_records[] = $item["entry"];
		}
	}
	
	if (!defined("API_SINCE") || defined("API_PERMISSIONS_CHANGED")) {
		$pages_total = intval(SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_pages"));
		$pending_total = intval(SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_pending_changes
												  WHERE `table` = 'bigtree_pages' AND `item_id` IS NULL"));
		
		// Paginated response
		if ($pages_total + $pending_total > 1000) {
			$current_page = isset($_GET["page"]) ? intval($_GET["page"]) : 1;
			$total_pages = ceil(($pages_total + $pending_total) / 1000);
			$limit = ($current_page - 1) * 1000;
			
			if ($current_page < 1) {
				$current_page = 1;
			}
			
			$pages = [];
			$page_ids = SQL::fetchAllSingle("SELECT id FROM bigtree_pages ORDER BY id
											 LIMIT $limit, 1000");
			
			foreach ($page_ids as $id) {
				$pages[] = API::getPagesCacheObject($id);
			}
			
			$page_id_count = count($page_ids);
			
			if ($page_id_count < 1000) {
				$limit = $limit - $page_id_count;
				$pending_ids = SQL::fetchAllSingle("SELECT id FROM bigtree_pending_changes
													WHERE `table` = 'bigtree_pages' AND `item_id` IS NULL
													ORDER BY id LIMIT $limit, 1000");
				
				foreach ($pending_ids as $id) {
					$pages[] = API::getPagesCacheObject("p".$id);
				}
			}
			
			$actions["put"] = $pages;
			
			if ($current_page != $total_pages) {
				API::sendResponse(["cache" => ["pages" => $actions]], null, null, WWW_ROOT."api/indexed-db/pages/?page=".($current_page + 1));
			} else {
				API::sendResponse(["cache" => ["pages" => $actions]]);
			}
		} else {
			// All in one response
			$pages = [];
			$page_ids = SQL::fetchAllSingle("SELECT id FROM bigtree_pages");
			
			foreach ($page_ids as $id) {
				$pages[] = API::getPagesCacheObject($id);
			}
			
			$pending_ids = SQL::fetchAllSingle("SELECT id FROM bigtree_pending_changes
												WHERE `table` = 'bigtree_pages' AND `item_id` IS NULL");
			
			foreach ($pending_ids as $id) {
				$pages[] = API::getPagesCacheObject("p".$id);
			}
			
			$actions["put"] = $pages;
			
			API::sendResponse(["cache" => ["pages" => $actions]]);
		}
	}
	
	// If permissions changed we've already done all put statements
	if (!defined("API_PERMISSIONS_CHANGED")) {
		// Creates and updates since last request
		$audit_trail_updates = SQL::fetchAll("SELECT DISTINCT(entry) FROM bigtree_audit_trail
											  WHERE `table` = 'bigtree_pages' AND `date` >= ?
												AND (`type` = 'update' OR `type` = 'add')
											  ORDER BY id DESC", API_SINCE);
		
		foreach ($audit_trail_updates as $item) {
			if (in_array($item["entry"], $deleted_records)) {
				continue;
			}
			
			$record = API::getPagesCacheObject($item["entry"]);
			
			if ($record) {
				$actions["put"][$item["entry"]] = $record;
			}
		}
	}
	
	API::sendResponse(["cache" => ["pages" => $actions]]);
	