<?php
	namespace BigTree;
	
	/*
	 	Function: extensions/delete
			Uninstalls an extension and all of its components.
		
		Method: POST
	 
		Parameters:
	 		id - The ID for the extension (required)
	*/
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters(["id" => "string"]);
	
	$id = $_POST["id"];
	$cache_actions = [];
	$cache_actions["extensions"]["delete"] = [$id];
	
	if (!DB::exists("extensions", $id)) {
		API::triggerError("Extension was not found.", "extension:missing", "missing");
	}
	
	$extension = new Extension($id);
	
	if (!empty($extension->Manifest["components"]) && is_array($extension->Manifest["components"])) {
		foreach ($extension->Manifest["components"] as $type => $list) {
			$type = str_replace("_", "-", $type);
			
			if ($type != "tables") {
				foreach ($list as $item) {
					$cache_actions[$type]["delete"][] = $item["id"];
				}
			}
		}
	}
	
	$extension->delete();
	
	API::sendResponse([
		"deleted" => true,
		"cache" => $cache_actions
	], "Uninstalled Extension");
