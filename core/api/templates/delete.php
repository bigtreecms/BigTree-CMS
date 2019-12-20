<?php
	namespace BigTree;
	
	/*
	 	Function: templates/delete
			Deletes a template.
			Also deletes related files / directories for this template.
		
		Method: POST
	 
		Parameters:
	 		id - The ID for the template (required)
	*/
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters(["id" => "string"]);
	
	$id = $_POST["id"];
	
	if (!DB::exists("templates", $id)) {
		API::triggerError("Template was not found.", "template:missing", "missing");
	}
	
	$template = new Template($id);
	$template->delete();
	
	API::sendResponse([
		"deleted" => true,
		"cache" => ["templates" => ["delete" => [$id]]]
	], "Deleted Template");
