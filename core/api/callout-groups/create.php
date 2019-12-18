<?php
	namespace BigTree;
	
	/*
	 	Function: callout-groups/create
			Creates a callout group.
		
		Method: POST
	 
		Parameters:
	 		name - The name of the callout group (required)
			callouts - An array of callout IDs to assign to the group
	*/
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters(["name" => "string"]);
	API::validateParameters(["callouts" => "array"]);
	
	$callouts = [];
	
	// Validate callouts exist before assigning to the group
	foreach ($_POST["callouts"] as $callout_id) {
		if (Callout::exists($callout_id)) {
			$callouts[] = $callout_id;
		}
	}
	
	$group = CalloutGroup::create($_POST["name"], $callouts);
	
	API::sendResponse([
		"created" => true,
		"cache" => ["callout-groups" => ["put" => DB::get("callout-groups", $group->ID)]]
	], "Created Callout Group");
