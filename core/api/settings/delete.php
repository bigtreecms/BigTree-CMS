<?php
	namespace BigTree;
	
	/*
	 	Function: settings/delete
			Deletes a setting.
		
		Method: POST
	 
		Parameters:
	 		id - The ID for the setting (required)
	*/
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters(["id" => "string"]);
	
	$id = $_POST["id"];
	
	if (!DB::exists("settings", $id) && !SQL::exists("bigtree_settings", $id)) {
		API::triggerError("Setting was not found.", "setting:missing", "missing");
	}

	if (DB::exists("settings", $id)) {
		$setting = new Setting($id);
		$setting->delete();
	} else {
		SQL::delete("bigtree_settings", $id);
		AuditTrail::track("bigtree_settings", $this->ID, "delete", "deleted");
	}
	
	API::sendResponse([
		"deleted" => true,
		"cache" => [
			"settings" => [
				"delete" => [$id]
			]
		]
	], "Deleted Setting");
