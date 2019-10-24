<?php
	namespace BigTree;
	
	/*
	 	Function: callouts/delete
			Deletes a callout.
			Also deletes the callout template file.
		
		Method: POST
	 
		Parameters:
	 		id - The ID for the callout (required)
	*/
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters(["id" => "string"]);
	
	$id = $_POST["id"];
	
	if (!DB::exists("callouts", $id)) {
		API::triggerError("Callout was not found.", "callout:missing", "missing");
	}
	
	// Generate cache actions before deleting
	$cache_actions = [];
	$cache_actions["callouts"] = ["delete" => [$id]];
	
	$groups = DB::getAll("callout-groups");
	
	foreach ($groups as $group) {
		if (is_array($group["callouts"])) {
			$key = array_search($id, $group["callouts"]);
			
			if ($key !== false) {
				unset($group["callouts"][$key]);
				$group["callouts"] = array_values($group["callouts"]);
				$cache_actions["callout-groups"]["update"][] = $group;
			}
		}
	}
	
	// Actually delete the callout
	$callout = new Callout($id);
	$callout->delete();
	
	API::sendResponse([
		"deleted" => true,
		"cache" => $cache_actions
	], "Deleted Callout");
