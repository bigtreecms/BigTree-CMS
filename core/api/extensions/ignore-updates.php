<?php
	namespace BigTree;
	
	/*
	 	Function: extensions/ignore-updates
			Ignores updates for the given extension ID.
		
		Method: POST
	 
		Parameters:
	 		id - The ID for the extension (required)
	*/
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters(["id" => "string"]);
	
	$id = $_POST["id"];
	
	if (!DB::exists("extensions", $id)) {
		API::triggerError("Extension was not found.", "extension:missing", "missing");
	}
	
	DB::update("extensions", $id, ["updates_ignored" => true]);
	AuditTrail::track("config:extensions", $id, "update", "ignored updates");
	
	API::sendResponse([
		"ignored" => true,
	], "Ignored Updates for Extension");
