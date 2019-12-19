<?php
	namespace BigTree;
	
	/*
	 	Function: callouts/create
			Creates a callout.
			Also creates the callout template file.
		
		Method: POST
	 
		Parameters:
	 		id - The ID for the callout (required)
			name - The name of the callout (required)
			description - A description of the callout
			level - Required access level for using the callout (defaults to 0)
			fields - An array of fields (defaults to an empty array)
	*/
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters([
		"id" => "string",
		"name" => "string"
	]);
	API::validateParameters([
		"description" => "string",
		"level" => "int",
		"fields" => "array"
	]);
	
	$id = $_POST["id"];
	
	if (DB::exists("callouts", $id)) {
		API::triggerError("A callout with the chosen ID already exists.", "callout:invalid", "invalid");
	}
	
	Callout::create($id, $_POST["name"], $_POST["description"], $_POST["level"], $_POST["fields"]);
	
	API::sendResponse([
		"created" => true,
		"cache" => ["callouts" => ["put" => API::getCalloutsCacheObject($id)]]
	], "Created Callout");
