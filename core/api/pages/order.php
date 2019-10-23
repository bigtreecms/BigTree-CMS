<?php
	namespace BigTree;
	
	/*
	 	Function: pages/order
			Updates the order of the children of a page.
		
		Method: POST
	 
		Parameters:
	 		parent - The ID for the parent page
			positioned_children - An array of child page IDs in their new order
	*/
	
	API::requireMethod("POST");
	API::requireParameters([
		"parent" => "int",
		"positioned_children" => "array"
	]);
	
	if (!Page::exists($_POST["parent"])) {
		API::triggerError("The parent page was not found.", "page:missing", "missing");
	}
	
	$page = new Page($_POST["parent"]);
	$cache = ["put" => []];
	
	if ($page->UserAccessLevel != "p" || !$page->UserCanModifyChildren) {
		API::triggerError("You are not allowed to order the requested page's children.", "page:notallowed", "permissions");
	}
	
	$position = count($_POST["positioned_children"]);
	
	foreach ($_POST["positioned_children"] as $child) {
		if (intval($child) != $child || !Page::exists($child)) {
			API::triggerError("Invalid page ID provided in parameter 'positioned_child'", "parameters:invalid", "parameters");
		}
		
		$page = new Page($child);
		
		// Make sure the page is actually a child of the parent we're updating
		if ($page->Parent == $_POST["parent"]) {
			$page->updatePosition($position);
		}
		
		$position--;
		$cache["put"][] = API::getPagesCacheObject($child);
	}
	
	API::sendResponse(["updated" => true, "cache" => ["pages" => $cache]], "Updated Page Order");
