<?php
	namespace BigTree;
	
	/*
	 	Function: users/reset-password
			Resets a user's password.
		
		Method: POST
	 
		Parameters:
	 		hash - Password Reset Hash
			password - New Password
	*/
	
	API::requireMethod("POST");
	API::requireParameters([
		"hash" => "string",
		"password" => "string"
	]);
	
	$user = User::getByChangePasswordHash($_POST["hash"]);
	
	if (!$user) {
		API::sendResponse([
			"password_updated" => false,
			"reason" => Text::translate("The reset token provided was invalid. Try using the forgot password option again.")
		], null, "failure:credentials");
	}
	
	if (!User::validatePassword($_POST["password"])) {
		API::sendResponse([
			"password_updated" => false,
			"reason" => Text::translate("Your chosen password does not conform to the password requirements.")
		], null, "failure:credentials");
	}
	
	$user->ChangePasswordHash = "";
	$user->Password = $_POST["password"];
	$user->save();
	$user->removeBans();

	API::sendResponse(["password_updated" => true]);
	