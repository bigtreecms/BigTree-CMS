<?php
	namespace BigTree;
	
	/*
	 	Function: module/order
			Sets the position of modules.
		
		Method: POST
	 
		Parameters:
	 		modules - An array of module IDs in their new positions
	*/
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters(["modules" => "array"]);
	
	$cache = [];
	$position = count($_POST["modules"]);
	
	foreach ($_POST["modules"] as $module_id) {
		DB::update("modules", $module_id, ["position" => $position]);
		AuditTrail::track("config:modules", $module_id, "update", "changed position");
		
		$cache[] = API::getModulesCacheObject($module_id);
		$position--;
	}
	
	API::sendResponse([
		"updated" => true,
		"cache" => ["modules" => ["put" => $cache]]
	], "Ordered Modules");
