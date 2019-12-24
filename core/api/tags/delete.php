<?php
	namespace BigTree;
	
	/*
	 	Function: tags/delete
			Deletes a tag.
		
		Method: POST
	 
		Parameters:
	 		id - A tag ID (required)
	*/
	
	API::requireLevel(1);
	API::requireMethod("POST");
	API::requireParameters(["id" => "int"]);
	
	$tag = new Tag($_POST["id"], function() {
		API::triggerError("Tag was not found.", "tag:missing", "missing");
	});
	
	$tag->delete();

	API::sendResponse([
		"deleted" => true,
		"cache" => ["tags" => ["delete" => [$tag->ID]]]
	 ], "Deleted Tag");
