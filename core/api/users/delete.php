<?php
	namespace BigTree;
	
	/*
	 	Function: users/delete
			Deletes a user.
		
		Method: POST
	 
		Parameters:
	 		id - The ID for the user (required)
	*/
	
	API::requireLevel(1);
	API::requireMethod("POST");
	API::requireParameters(["id" => "int"]);
	
	$id = $_POST["id"];
	
	if (!SQL::exists("bigtree_users", $id)) {
		API::triggerError("User was not found.", "user:missing", "missing");
	}
	
	$user = new User($id);
	
	if ($user->Level > API::$AuthenticatedUser->Level) {
		API::triggerError("You may not delete a user with a higher access level than yours.", "user:invalid",
						  "invalid");
	}
	
	$user->delete();
	
	API::sendResponse([
		"deleted" => true,
		"cache" => ["users" => ["delete" => [$id]]]
	], "Deleted User");
