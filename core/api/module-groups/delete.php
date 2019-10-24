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
	
	if (!DB::exists("module-groups", $id)) {
		API::triggerError("Module group was not found.", "module-group:missing", "missing");
	}
	
	// Generate cache actions
	$cache_actions = [];
	$cache_actions["module-groups"] = ["delete" => [$id]];
	$modules = DB::getAll("modules");
	
	foreach ($modules as $module) {
		if ($module["group"] == $id) {
			$cache_object = API::getModulesCacheObject($module["id"]);
			$cache_object["group"] = null;
			$cache_actions["modules"]["update"][] = $cache_object;
		}
	}
	
	// Delete the group
	$group = new ModuleGroup($id);
	$group->delete();
	
	API::sendResponse([
		"deleted" => true,
		"cache" => $cache_actions
	], "Deleted Module Group");
