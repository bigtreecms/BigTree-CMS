<?php
	namespace BigTree;
	
	/*
	 	Function: templates/update
			Updates a template.
		
		Method: POST
	 
		Parameters:
	 		id - ID for the template to update (required)
			name - Name of the template (required)
			level - Access level (0 for everyone, 1 for administrators, 2 for developers, defaults to 0)
			module - Related Module id (defaults to null)
			fields - An array of fields (defaults to [])
			hooks - An array of hooks ("pre", "post", "edit", and "publish" keys, defaults to none)
	*/
	
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters([
		"id" => "string",
		"name" => "string"
	]);
	API::validateParameters([
		"level" => "int",
		"module" => "string",
		"fields" => "array",
		"hooks" => "array"
	]);
	
	$id = $_POST["id"];
	
	if (!DB::exists("templates", $id)) {
		API::triggerError("Template was not found.", "template:missing", "missing");
	}
	
	$template = new Template($id);
	$template->update($_POST["name"], $_POST["level"], $_POST["module"], $_POST["fields"], $_POST["hooks"]);
	
	API::sendResponse([
		"updated" => true,
		"cache" => ["templates" => ["put" => API::getTemplatesCacheObject($template->ID)]]
	], "Updated Template");
