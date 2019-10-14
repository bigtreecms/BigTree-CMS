<?php
	namespace BigTree;
	
	/*
	 	Function: settings/get-value
			Returns a setting's value.
		
		Method: GET
	 
		Parameters:
	 		id - The ID for the setting
	*/
	
	API::requireMethod("GET");
	API::requireParameters(["id" => "string"]);
	
	$id = $_GET["id"];
	
	if (!SQL::exists("bigtree_settings", $id)) {
		API::triggerError("The setting was not found.", "setting:missing", "missing");
	}
	
	$encrypted = SQL::fetchSingle("SELECT encrypted FROM bigtree_settings WHERE id = ?", $id);
	
	if ($encrypted) {
		$value = SQL::fetchSingle("SELECT AES_DECRYPT(`value`,?) AS `value` FROM bigtree_settings
								   WHERE id = ?", Router::$Config["settings_key"], $id);
	} else {
		$value = SQL::fetchSingle("SELECT `value` FROM bigtree_settings WHERE id = ?", $id);
	}

	API::sendResponse(["value" => Link::decode(json_decode($value, true))]);
