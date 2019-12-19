<?php
	namespace BigTree;
	
	/*
	 	Function: feeds/create
			Creates a feed.
		
		Method: POST
	 
		Parameters:
	 		name - A name for the feed (required)
			description - A description for the feed
			table - A SQL data table for the feed (required)
			type - The feed type (valid options are "custom", "json", "rss", "rss2")
			settings - An array of settings for the given feed type
			fields - An array of fields to include in the feed (for "custom" and "json" feeds)
	*/
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters([
		"name" => "string",
		"type" => "string",
		"table" => "string"
	]);
	API::validateParameters([
		"description" => "string",
		"settings" => "array",
		"fields" => "array"
	]);
	
	$type = trim(strtolower($_POST["type"]));
	$valid_types = ["custom", "json", "rss", "rss2"];
	
	if (!in_array($type, $valid_types)) {
		API::triggerError("An invalid feed type was provided. Valid types are: ".implode(", ", $valid_types),
						  "feed:invalid", "invalid");
	}
	
	$feed = Feed::create($_POST["name"], $_POST["description"], $_POST["table"], $type, $_POST["settings"],
						 $_POST["fields"]);
	
	API::sendResponse([
		"created" => true,
		"cache" => ["feeds" => ["put" => [API::getFeedsCacheObject($feed->ID)]]]
	], "Created Feed");
