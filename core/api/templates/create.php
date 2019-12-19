<?php
	namespace BigTree;
	
	/*
	 	Function: templates/create
			Creates a template.
			Also creates related files / directories for this template.
		
		Method: POST
	 
		Parameters:
	 		id - ID for the template (required)
			name - Name of the template (required)
			routed - An empty value for Basic, a non-empty value for Routed (defaults to false)
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
		"routed" => "bool",
		"level" => "int",
		"module" => "string",
		"fields" => "array",
		"hooks" => "array"
	]);
	
	$id = $_POST["id"];
	
	if (DB::exists("templates", $id)) {
		API::triggerError("A template with the provided ID already exists.", "template:invalid", "invalid");
	}
	
	if (!ctype_alnum(str_replace("-", "", $id))) {
		API::triggerError("The chosen ID is invalid. Only alphanumeric characters and dashes are allowed.",
						  "module:invalid", "invalid");
	} else if (strlen($id) > 127) {
		API::triggerError("The chosen ID is invalid. The maximum number of characters for an ID is 127.",
						  "module:invalid", "invalid");
	}
	
	$template = Template::create($id, $_POST["name"], $_POST["routed"], $_POST["level"], $_POST["module"],
								 $_POST["fields"], $_POST["hooks"]);
	
	API::sendResponse([
		"created" => true,
		"cache" => ["templates" => ["put" => API::getTemplatesCacheObject($template->ID)]]
	], "Created Template");
