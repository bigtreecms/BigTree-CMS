<?php
	namespace BigTree;
	
	/*
	 	Function: module-groups/delete
			Deletes a module group.
			Modules within this group will become ungrouped.
		
		Method: POST
	 
		Parameters:
	 		id - The ID for the module group (required)
	*/
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters(["id" => "string"]);
	
	$id = $_POST["id"];
	$cache_actions = [];
	
	if (!DB::exists("module-groups", $id)) {
		API::triggerError("Module group was not found.", "module-group:missing", "missing");
	}
	
	// Delete the group
	DB::delete("module-groups", $id);
	AuditTrail::track("config:module-groups", $id, "delete", "deleted");
	$cache_actions["module-groups"] = ["delete" => [$id]];
	
	// Remove all the modules inside it from the group
	$modules = DB::getAll("modules");
	
	foreach ($modules as $module) {
		if ($module["group"] == $id) {
			DB::update("modules", $module["id"], ["group" => null]);
			AuditTrail::track("config:modules", $module["id"], "update", "removed from deleted group");
			$cache_actions["modules"]["update"][] = API::getModulesCacheObject($module["id"]);
		}
	}
	
	API::sendResponse([
		"deleted" => true,
		"cache" => $cache_actions
	], "Deleted Module Group");
