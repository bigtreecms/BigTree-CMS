<?php
	namespace BigTree;
	
	/*
	 	Function: module-groups/update
			Updates the name of a module group.
		
		Method: POST
	 
		Parameters:
			id - The ID of the module group to update
	 		name - The name of the module group
	*/
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters(["id" => "string", "name" => "string"]);
	
	$group = new ModuleGroup($_POST["id"], function() {
		API::triggerError("The setting was not found.", "setting:missing", "missing");
	});
	
	$group->Name = $_POST["name"];
	$group->save();
	$cache = DB::get("module-groups", $group->ID);
	
	API::sendResponse(["updated" => true, "cache" => ["module-groups" => ["put" => [$cache]]]]);
