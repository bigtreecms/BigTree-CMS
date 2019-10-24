<?php
	namespace BigTree;
	
	/*
	 	Function: callout-groups/delete
			Deletes a callout group.
		
		Method: POST
	 
		Parameters:
	 		id - The ID for the callout group (required)
	*/
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters(["id" => "string"]);
	
	$id = $_POST["id"];
	
	if (!DB::exists("callout-groups", $id)) {
		API::triggerError("Callout group was not found.", "callout-group:missing", "missing");
	}
	
	$group = new CalloutGroup($id);
	$group->delete();
	
	API::sendResponse([
		"deleted" => true,
		"cache" => ["callout-groups" => ["delete" => [$id]]]
	], "Deleted Callout Group");
