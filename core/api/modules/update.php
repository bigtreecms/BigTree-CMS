<?php
	namespace BigTree;
	
	/*
	 	Function: modules/update
			Updates a module.
		
		Method: POST
	 
		Parameters:
			id - The ID of the module to update (required).
			name - The name of the module (required).
			group - The group for the module.
			class - The module class to create.
			permissions - An array of group-based permissions settings.
			developer_only - Sets the module to be only accessible/visible to developers (defaults to false).
	*/
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters([
		"id" => "string",
		"name" => "string"
	]);
	API::validateParameters([
		"group" => "string",
		"class" => "string",
		"permissions" => "array",
		"developer_only" => "bool"
	]);
	
	$id = $_POST["id"];
	$group = $_POST["group"];
	
	if (!DB::exists("modules", $id)) {
		API::triggerError("Module was not found.", "module:missing", "missing");
	}
	
	if (!empty($group) && !DB::exists("module-groups", $group)) {
		API::triggerError("The chosen module group does not exist.", "module:invalid", "invalid");
	}
	
	$module = new Module($id);
	$module->update($_POST["name"], $group, $_POST["class"], $_POST["permissions"] ?: null,
					!empty($_POST["developer_only"]));
	
	API::sendResponse([
		"updated" => true,
		"cache" => ["modules" => ["put" => API::getModulesCacheObject($module->ID)]]
	], "Updated Module");
