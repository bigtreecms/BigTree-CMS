<?php
	namespace BigTree;
	
	/*
	 	Function: tags/create
			Creates a tag.
			If a tag with a sanitized name match already exists, an error will be thrown.
			If to_merge is passed, this call requires administrator level access.
		
		Method: POST
	 
		Parameters:
	 		tag - A tag name (required)
			to_merge - An array of other tag IDs to merge into the newly created tag.
	*/
	
	API::requireMethod("POST");
	API::requireParameters(["tag" => "string"]);
	API::validateParameters(["to_merge" => "array"]);
	
	$name = strtolower(html_entity_decode(trim($_POST["tag"])));
	$to_merge = array_filter($_POST["to_merge"]);
	$cache = [];
	
	if (SQL::exists("bigtree_tags", ["tag" => $name])) {
		API::triggerError("A tag with that name already exists.", "tag:invalid", "invalid");
	}
	
	// Sanitize merge
	if (count($to_merge)) {
		API::requireLevel(1);
		
		foreach ($_POST["to_merge"] as $id) {
			$id = intval($id);
			
			if (!SQL::exists("bigtree_tags", $id)) {
				API::triggerError("Invalid merge tag ID: ".intval($id), "tag:invalid", "invalid");
			}
		}
	}

	$tag = Tag::create($_POST["tag"]);
	$cache["delete"] = [];
	
	foreach ($to_merge as $id) {
		$tag->merge($id);
		$cache["delete"][] = $id;
	}
	
	$cache["put"] = [API::getTagsCacheObject($tag->ID)];
	
	API::sendResponse([
		"created" => true,
		"cache" => ["tags" => $cache]
	], "Created Tag");
