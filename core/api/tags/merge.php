<?php
	namespace BigTree;
	
	/*
	 	Function: tags/merge
			Merges a group of tags into a single tag and updates references.
		
		Method: POST
	 
		Parameters:
	 		id - A tag ID which will consume the other tags (required)
			to_merge - An array of other tag IDs to merge into the main tag (required)
	*/
	
	API::requireLevel(1);
	API::requireMethod("POST");
	API::requireParameters(["tag" => "int", "to_merge" => "array"]);
	
	$tag = new Tag($_POST["tag"], function() {
		API::triggerError("Tag was not found.", "tag:missing", "missing");
	});
	
	// Sanitize merge
	foreach ($_POST["to_merge"] as $id) {
		$id = intval($id);
			
		if (!SQL::exists("bigtree_tags", $id)) {
			API::triggerError("Invalid merge tag ID: ".intval($id), "tag:invalid", "invalid");
		}
	}
	
	$cache["delete"] = [];
	
	foreach ($_POST["to_merge"] as $id) {
		$tag->merge($id);
		$cache["delete"][] = $id;
	}

	$cache["put"] = [API::getTagsCacheObject($tag->ID)];
	
	API::sendResponse([
		"updated" => true,
		"cache" => ["tags" => $cache]
	], "Merged Tags");
