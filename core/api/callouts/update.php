<?php
	namespace BigTree;
	
	/*
	 	Function: callouts/update
			Updates a callout.
		
		Method: POST
	 
		Parameters:
	 		id - The ID for the callout to update (required)
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
	
	if (!DB::exists("callouts", $id)) {
		API::triggerError("Callout was not found.", "callout:missing", "missing");
	}
	
	$callout = new Callout($id);
	$callout->update($_POST["name"], $_POST["description"], $_POST["level"], $_POST["fields"]);
	
	API::sendResponse([
		"updated" => true,
		"cache" => ["callouts" => ["put" => API::getCalloutsCacheObject($id)]]
	], "Updated Callout");
