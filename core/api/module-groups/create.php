<?php
	namespace BigTree;
	
	/*
	 	Function: module-groups/create
			Creates a new module group.
		
		Method: POST
	 
		Parameters:
	 		name - The name of the module group
	*/
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters(["name" => "string"]);
	
	$group = ModuleGroup::create($_POST["name"]);
	$cache = DB::get("module-groups", $group->ID);

	API::sendResponse(["created" => true, "cache" => ["module-groups" => ["put" => [$cache]]]]);
