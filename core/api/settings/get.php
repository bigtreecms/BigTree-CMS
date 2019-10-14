<?php
	namespace BigTree;
	
	/*
	 	Function: settings/get
			Returns a setting's configuration and value.
		
		Method: GET
	 
		Parameters:
	 		id - The ID for the setting
	*/
	
	API::requireMethod("GET");
	API::requireParameters(["id" => "string"]);
	
	$id = $_GET["id"];
	
	if (!Setting::exists($id)) {
		API::triggerError("The setting was not found.", "setting:missing", "missing");
	}
	
	$setting = new Setting($id);
	
	API::sendResponse([
		"description" => $setting->Description,
		"encrypted" => $setting->Encrypted,
		"id" => $setting->ID,
		"locked" => $setting->Locked,
		"name" => $setting->Name,
		"settings" => $setting->Settings,
		"type" => $setting->Type,
		"value" => $setting->Value
	]);
