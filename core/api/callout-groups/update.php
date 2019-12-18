<?php
	namespace BigTree;
	
	/*
	 	Function: callout-groups/update
			Updates a callout group.
		
		Method: POST
	 
		Parameters:
			id - The ID of the callout group to update (required)
	 		name - The name of the callout group (required)
			callouts - An array of callout IDs to assign to the group
	*/
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters([
		"id" => "string",
		"name" => "string"
	]);
	API::validateParameters(["callouts" => "array"]);
	
	$id = $_POST["id"];
	$callouts = [];
	
	if (!DB::exists("callout-groups", $id)) {
		API::triggerError("Callout group was not found.", "callout-group:missing", "missing");
	}
	
	// Validate callouts exist before assigning to the group
	foreach ($_POST["callouts"] as $callout_id) {
		if (Callout::exists($callout_id)) {
			$callouts[] = $callout_id;
		}
	}
	
	$group = new CalloutGroup($id);
	$group->update($_POST["name"], $callouts);
	
	API::sendResponse([
		"updated" => true,
		"cache" => ["callout-groups" => ["put" => DB::get("callout-groups", $group->ID)]]
	], "Updated Callout Group");
