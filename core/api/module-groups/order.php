<?php
	namespace BigTree;
	
	/*
	 	Function: module-groups/order
			Sets the position of module groups.
		
		Method: POST
	 
		Parameters:
	 		groups - An array of module group IDs in their new positions
	*/
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters(["groups" => "array"]);
	
	$cache = [];
	$position = count($_POST["groups"]);
	
	foreach ($_POST["groups"] as $group_id) {
		DB::update("module-groups", $group_id, ["position" => $position]);
		AuditTrail::track("config:module-groups", $group_id, "update", "changed position");
		
		$cache[] = DB::get("module-groups", $group_id);
		$position--;
	}
	
	API::sendResponse([
		"updated" => true,
		"cache" => ["module-groups" => ["put" => $cache]]
	], "Ordered Module Groups");
