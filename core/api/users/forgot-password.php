<?php
	namespace BigTree;
	
	/*
	 	Function: users/forgot-password
			Initiates a password reset for a user.
		
		Method: POST
	 
		Parameters:
	 		email - Email Address
	*/
	
	API::requireMethod("POST", false);
	API::requireParameters(["email" => "string"]);
	
	$user = User::getByEmail($_POST["email"]);
	
	if ($user) {
		$user->initPasswordReset();
	}
	