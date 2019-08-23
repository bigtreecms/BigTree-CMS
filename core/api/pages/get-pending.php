<?php
	namespace BigTree;
	
	/*
	 	Function: pages/get-pending
			Returns a page from the bigtree_pages table (with pending changes applied) or a pending page from the bigtree_pending_changes table.
		
		Method: GET
	 
		Parameters:
	 		id - The ID for the requested page (prefixed with "p" if this is a pending page ID)
	 	
		Returns:
			An array of page data
	*/
	
	API::requireMethod("GET");
	API::requireParameters(["id" => "string_int"]);
	
	$id = $_GET["id"];
	
	if (is_int($id)) {
		if (!Page::exists($id)) {
			API::triggerError("The requested page was not found.", "page:missing", "missing");
		}
	} else {
		if (SQL::exists("bigtree_pending_changes", ["table" => "bigtree_pages", "id" => substr($id, 1)])) {
			API::triggerError("The requested page was not found.", "page:missing", "missing");
		}
	}
	
	$page = Page::getPageDraft($id);
	
	if (empty($page->UserAccessLevel)) {
		API::triggerError("You are not allowed to access the requested page.", "page:notallowed", "permissions");
	} else {
		API::sendResponse($page->Array);
	}
	