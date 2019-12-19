<?php
	namespace BigTree;
	
	/*
	 	Function: settings/update
			Updates a setting.
		
		Method: POST
	 
		Parameters:
	 		id - The current ID for the setting that is to be updated (required)
			new_id - The new ID for the setting (leave null to use existing ID)
			name - Name
			description - Description / instructions for the user editing the setting
			type - Field Type
			settings - An array of settings for the field type
			extension - Related extension ID (defaults to none unless an extension is calling createSetting)
			system - Whether to hide this from the Settings tab (defaults to false)
			encrypted - Whether to encrypt this setting's value in the database (defaults to false)
			locked - Whether to lock this setting to only developers (defaults to false)
			value - The new value for the setting
	*/
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters(["id" => "string"]);
	API::validateParameters([
		"new_id" => "string",
		"name" => "string",
		"description" => "string",
		"type" => "string",
		"settings" => "array",
		"extension" => "string",
		"encrypted" => "bool",
		"locked" => "bool"
	]);
	
	$setting = new Setting($_POST["id"], function() {
		API::triggerError("The setting was not found.", "setting:missing", "missing");
	});
	
	if (isset($_POST["value"])) {
		$setting->Value = $_POST["value"];
	}
	
	$new_id = !empty($_POST["new_id"]) ? $_POST["new_id"] : $setting->ID;
	$updated = $setting->update($new_id, $_POST["type"], $_POST["settings"],$_POST["name"], $_POST["description"],
								$_POST["locked"], $_POST["encrypted"]);
	
	if (!$updated) {
		API::sendResponse([
			"updated" => false,
			"reason" => Setting::$Error
		]);
	}
	
	// Switching IDs we create one and delete the other in the cache
	if ($new_id != $_POST["id"]) {
		$cache = [
			"delete" => [$_POST["id"]],
			"put" => [API::getSettingsCacheObject($setting->ID)]
		];
	} else {
		$cache = ["put" => [API::getSettingsCacheObject($setting->ID)]];
	}
	
	API::sendResponse([
		"updated" => true,
		"cache" => ["settings" => $cache]
	], "Updated Setting");
	