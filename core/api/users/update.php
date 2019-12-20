<?php
	namespace BigTree;
	
	/*
	 	Function: users/update
			Updates a user.
		
		Method: POST
	 
		Parameters:
			id - The ID of the user to update (required)
			email - Email Address (required)
			password - Password (required if password invitations are not on)
			name - Name (required)
			company - Company
			level - User Level (0 for regular, 1 for admin, 2 for developer, defaults to 0)
			permissions - Array of permissions data (defaults to [])
			alerts - Array of alerts data (defaults to [])
			daily_digest - Whether the user wishes to receive the daily digest email (defaults to false)
	*/
	
	API::requireLevel(1);
	API::requireMethod("POST");
	API::requireParameters([
		"id" => "int",
		"email" => "string",
		"name" => "string"
	]);
	API::validateParameters([
		"password" => "string",
		"company" => "string",
		"level" => "int",
		"permissions" => "array",
		"alerts" => "array",
		"daily_digest" => "bool"
	]);
	
	$id = $_POST["id"];
	$email = $_POST["email"];
	$level = $_POST["level"];
	
	if (!SQL::exists("bigtree_users", $id)) {
		API::triggerError("User was not found.", "user:missing", "missing");
	}
	
	$user = new User($id);
	
	if (API::$AuthenticatedUser->Level < $level || $user->Level > API::$AuthenticatedUser->Level) {
		API::triggerError("You may not update a user with a higher access level than yours.", "user:invalid",
						  "invalid");
	}
	
	if (strtolower($user->Email) != strtolower($_POST["email"]) &&
		SQL::exists("bigtree_users", ["email" => $email]))
	{
		API::triggerError("A user with the provided email address already exists.", "user:invalid", "invalid");
	}
	
	// Validate password
	if (!empty($_POST["password"]) && !User::validatePassword($_POST["password"])) {
		API::triggerError("The provided password does not conform to the password requirements.", "user:invalid",
						  "invalid");
	}
	
	$user->update($email, $_POST["password"] ?: null, $_POST["name"], $_POST["company"], $level, $_POST["permissions"],
				  $_POST["alerts"], $_POST["daily_digest"]);
	
	API::sendResponse([
		"updated" => true,
		"cache" => ["users" => ["put" => API::getUsersCacheObject($user->ID)]]
	], "Updated User");
