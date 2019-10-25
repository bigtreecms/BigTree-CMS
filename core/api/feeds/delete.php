<?php
	namespace BigTree;
	
	/*
	 	Function: feeds/delete
			Deletes a feed.
		
		Method: POST
	 
		Parameters:
	 		id - The ID for the feed (required)
	*/
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters(["id" => "string"]);
	
	$id = $_POST["id"];
	
	if (!DB::exists("feeds", $id)) {
		API::triggerError("Feed was not found.", "feed:missing", "missing");
	}
	
	$feed = new Feed($id);
	$feed->delete();
	
	API::sendResponse([
		"deleted" => true,
		"cache" => ["feeds" => ["delete" => [$id]]]
	], "Deleted Feed");
