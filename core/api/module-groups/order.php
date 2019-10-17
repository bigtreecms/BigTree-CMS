<?php
	namespace BigTree;
	
	/*
	 	Function: module-groups/order
			Sets the position of module groups.
		
		Method: POST
	 
		Parameters:
	 		positions - A key => value array of modul group IDs as keys and positions as values
	*/
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters(["positions" => "array"]);
	
	$cache = [];
	
	foreach ($_POST["positions"] as $group_id => $position) {
		DB::update("module-groups", $group_id, ["position" => intval($position)]);
		AuditTrail::track("config:module-groups", $group_id, "update", "changed position");
		
		$cache[] = DB::get("module-groups", $group_id);
	}
	
	API::sendResponse(["updated" => true, "cache" => ["module-groups" => ["put" => $cache]]]);
