<?php
	namespace BigTree;
	
	/*
	 	Function: users/create
			Creates a user.
		
		Method: POST
	 
		Parameters:
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
	
	$email = $_POST["email"];
	$level = $_POST["level"];
	
	if (SQL::exists("bigtree_users", ["email" => $email])) {
		API::triggerError("A user with the provided email address already exists.", "user:invalid", "invalid");
	}
	
	if (API::$AuthenticatedUser->Level < $level) {
		API::triggerError("You may not create a user with a higher access level than yours.", "user:invalid", "invalid");
	}
	
	// Validate password
	if (empty(Admin::$SecurityPolicy["password"]["invitations"])) {
		if (empty($_POST["password"])) {
			API::triggerError("A password is required.", "user:invalid", "invalid");
		} else if (!User::validatePassword($_POST["password"])) {
			API::triggerError("The provided password does not conform to the password requirements.", "user:invalid",
							  "invalid");
		}
	}
	
	$user = User::create($email, $_POST["password"], $_POST["name"], $_POST["company"], $level, $_POST["permissions"],
						 $_POST["alerts"], $_POST["daily_digest"]);
	
	API::sendResponse([
		"created" => true,
		"cache" => ["users" => ["put" => API::getUsersCacheObject($user->ID)]]
	], "Created User");
