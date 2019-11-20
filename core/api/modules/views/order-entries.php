<?php
	namespace BigTree;
	
	/*
	 	Function: module/views/order-entries
			Sets the position of the entries of a module view.
		
		Method: POST
	 
		Parameters:
			module - Module ID
			view - View ID
	 		entries - An array of entry IDs in their new positions
	*/
	
	/**
	 * @global Module $module
	 * @global ModuleView $view
	 */
	
	API::requireMethod("POST");

	include "_common.php";

	API::requireParameters([
		"entries" => "array"
	]);
	
	$cache = [];
	$position = count($_POST["entries"]);
	
	if ($module->UserAccessLevel != "p") {
		API::triggerError("The minimum access level to call this endpoint is: publisher",
						  "invalid:level", "permissions");
	}
	
	foreach ($_POST["entries"] as $entry_id) {
		if (!SQL::exists($view->Table, $entry_id)) {
			continue;
		}
		
		SQL::update($view->Table, $entry_id, ["position" => $position]);
		AuditTrail::track($view->Table, $entry_id, "update", "changed position");
		ModuleView::cacheForAll($view->Table, $entry_id);
		
		$record = SQL::fetch("SELECT * FROM bigtree_module_view_cache WHERE id = ? AND view = ?",
							 $entry_id, $view->ID);
		$cache[] = API::getViewCacheObject($record);
		$position--;
	}
	
	API::sendResponse(["updated" => true, "cache" => ["view-cache" => ["put" => $cache]]]);
