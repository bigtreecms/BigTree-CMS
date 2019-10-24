<?php
	namespace BigTree;
	
	/*
	 	Function: modules/delete
			Deletes a module.
			Also deletes the related class file and any related directory in /custom/admin/.
		
		Method: POST
	 
		Parameters:
	 		id - The ID for the module (required)
	*/
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters(["id" => "string"]);
	
	$id = $_POST["id"];
	$cache_actions = [];
	
	if (!DB::exists("modules", $id)) {
		API::triggerError("Module was not found.", "module:missing", "missing");
	}
	
	$module = new Module($id);
	$module->delete();
	$cache_actions["modules"] = ["delete" => [$id]];
	
	API::sendResponse([
		"deleted" => true,
		"cache" => $cache_actions
	], "Deleted Module");
