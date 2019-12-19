<?php
	namespace BigTree;
	
	/*
	 	Function: modules/create
			Creates a module.
			Also creates the related class file if a class name is specified.
			If the provided route has a collision it will be modified to be unique.
		
		Method: POST
	 
		Parameters:
			name - The name of the module (required).
			group - The group for the module.
			class - The module class to create.
			table - The table this module relates to.
			permissions - An array of group-based permissions settings.
			route - Desired route to use (defaults to auto generating if this is left empty).
			developer_only - Sets the module to be only accessible/visible to developers (defaults to false).
	*/
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters(["name" => "string"]);
	API::validateParameters([
		"group" => "string",
		"class" => "string",
		"table" => "string",
		"permissions" => "array",
		"route" => "string",
		"developer_only" => "bool"
	]);
	
	$group = $_POST["group"];
	$route = $_POST["route"];
	
	if (!empty($group) && !DB::exists("module-groups", $group)) {
		API::triggerError("The chosen module group does not exist.", "module:invalid", "invalid");
	}
	
	if (!empty($route)) {
		if (!ctype_alnum(str_replace("-", "", $route))) {
			API::triggerError("The chosen route is invalid. Only alphanumeric characters and dashes are allowed.",
							  "module:invalid", "invalid");
		} else if (strlen($route) > 127) {
			API::triggerError("The chosen route is invalid. The maximum number of characters for a route is 127.",
							  "module:invalid", "invalid");
		}
	}
	
	$module = Module::create($_POST["name"], $group, $_POST["class"], $_POST["table"], $_POST["permissions"] ?: null,
							 $_POST["route"], !empty($_POST["developer_only"]));
	
	API::sendResponse([
		"created" => true,
		"cache" => ["modules" => ["put" => API::getModulesCacheObject($module->ID)]]
	], "Created Module");
