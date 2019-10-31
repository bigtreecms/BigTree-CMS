<?php
	namespace BigTree;
	
	/*
	 	Function: users/remove-login-session
			Removes a user's login session cache after finishing cross origin login
		
		Method: POST
	 
		Parameters:
	 		session - Session cache ID
	*/
	
	API::requireMethod("POST", false);
	API::requireParameters(["session" => "string"]);
	
	$login_session = Cache::get("org.bigtreecms.login-session", $_POST["session"]);
	
	if (intval($login_session["user_id"]) === Auth::user()->ID) {
		Cache::delete("org.bigtreecms.login-session", $_POST["session"]);
	} else {
		API::triggerError("Invalid session key.");
	}
	