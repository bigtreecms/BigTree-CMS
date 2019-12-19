<?php
	namespace BigTree;
	
	/*
	 	Function: settings/create
			Creates a setting.
		
		Method: POST
	 
		Parameters:
	 		id - The ID for the setting (required)
			name - Name
			description - Description / instructions for the user editing the setting
			type - Field Type
			settings - An array of settings for the field type
			extension - Related extension ID (defaults to none unless an extension is calling createSetting)
			system - Whether to hide this from the Settings tab (defaults to false)
			encrypted - Whether to encrypt this setting's value in the database (defaults to false)
			locked - Whether to lock this setting to only developers (defaults to false)
			value - The initial value
	*/
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters(["id" => "string"]);
	API::validateParameters([
		"name" => "string",
		"description" => "string",
		"type" => "string",
		"settings" => "array",
		"extension" => "string",
		"encrypted" => "bool",
		"locked" => "bool"
	]);
	
	$setting = Setting::create($_POST["id"], $_POST["name"], $_POST["description"], $_POST["type"], $_POST["settings"],
							   $_POST["extension"], $_POST["encrypted"], $_POST["locked"]);
	
	if (!$setting) {
		API::sendResponse([
			"created" => false,
			"reason" => Setting::$Error
		]);
	}
	
	API::sendResponse([
		"created" => true,
		"cache" => ["settings" => ["put" => [API::getSettingsCacheObject($setting->ID)]]]
	], "Created Setting");
	