<?php
	namespace BigTree;
	
	/*
	 	Function: module/order
			Sets the position of modules.
		
		Method: POST
	 
		Parameters:
	 		positions - A key => value array of module IDs as keys and positions as values
	*/
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters(["positions" => "array"]);
	
	$cache = [];
	
	foreach ($_POST["positions"] as $module_id => $position) {
		DB::update("modules", $module_id, ["position" => intval($position)]);
		AuditTrail::track("config:modules", $module_id, "update", "changed position");
		
		$cache[] = API::getModulesCacheObject($module_id);
	}
	
	API::sendResponse(["updated" => true, "cache" => ["modules" => ["put" => $cache]]]);
