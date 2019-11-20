<?php
	namespace BigTree;
	
	/*
	 	Function: modules/get-action-url
			Returns the URL for a module action that uses a custom function for determining location.
		
		Method: GET
	 
		Parameters:
			module - The ID for the module (required)
	 		view - The ID for the module's view (required)
			entry - The ID for the entry being acted upon (required)
			action - The index of the action being taken (required)
	*/
	
	/**
	 * @global Module $module
	 * @global ModuleView $view
	 */
	
	API::requireMethod("GET");
	
	include "_common.php";
	
	API::requireParameters([
		"entry" => "string_int",
		"action" => "string_int"
	]);
	
	if (!isset($view->Actions[$_GET["action"]])) {
		API::triggerError("Action was not found.", "module-view-action:missing", "missing");
	}
	
	$entry = SQL::fetch("SELECT * FROM bigtree_module_view_cache WHERE view = ? AND id = ?", $view->ID, $_GET["entry"]);
	
	if (!$entry) {
		API::triggerError("Entry was not found.", "module-view-entry:missing", "missing");
	}
	
	$action = json_decode($view->Actions[$_GET["action"]], true);
	$url = @call_user_func($action["function"], $entry);
	
	API::sendResponse(["url" => $url]);
