<?php
	namespace BigTree;
	
	/*
	 	Function: settings/update-value
			Updates the value of a setting.
		
		Method: POST
	 
		Parameters:
	 		id - The ID for the setting
			value - The value to set the setting to
	*/
	
	API::requireMethod("POST");
	API::requireParameters([
		"id" => "string",
		"value" => true
	]);
	
	$id = $_POST["id"];
	
	if (!Setting::exists($id)) {
		API::triggerError("The setting was not found.", "setting:missing", "missing");
	}
	
	$setting = DB::get("settings", $id);
	$encrypted = SQL::fetchSingle("SELECT encrypted FROM bigtree_settings WHERE id = ?", $id);
	
	if ((!$setting || !empty($setting["locked"])) && Auth::$Level < 2) {
		API::triggerError("You do not have permission to update this setting's value.",
						  "setting:notallowed", "permissions");
	}
	
	Setting::updateValue($id, $_POST["value"], ($encrypted || $setting["encrypted"]));
	API::sendResponse(["cache" => API::getSettingsCacheObject($id)], "Updated Setting Value");
