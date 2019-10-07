<?php
	namespace BigTree;
	use BigTree\Auth\GoogleAuthenticator;
	
	/*
	 	Function: users/two-factor-auth
			Authenticates a user via two factor authentication AFTER having authenticated via email & password.
		
		Method: POST
	 
		Parameters:
	 		user - User ID
			token - Two Factor Auth Token
			code - Authenticator code
			stay_logged_in - Stay Logged In (optional)
	*/
	
	API::requireMethod("POST");
	API::requireParameters([
		"user" => "int",
		"token" => "string",
		"code" => "int"
	]);
	
	$user = new User($_POST["user"], function() {
		API::triggerError("Invalid user.");
	});
	
	if ($user->TwoFactorLoginToken !== $_POST["token"]) {
		API::triggerError("Invalid token.");
	}
	
	$code = trim($_POST["code"]);
	$valid = GoogleAuthenticator::verifyCode($user->TwoFactorSecret, $code);
	
	if (!$valid) {
		$message = Text::translate("The authenticator code entered is expired or invalid.");
		API::sendResponse(["logged_in" => false, "reason" => $message], null, "failure:twofactor");
	}
	
	SQL::update("bigtree_users", $user->ID, ["2fa_login_token" => ""]);
	
	// See if remembering the user is disabled
	$security_policy = Setting::value("bigtree-internal-security-policy");
	
	if ($security_policy["remember_disabled"]) {
		$stay_logged_in = false;
	} else {
		$stay_logged_in = !empty($_POST["stay_logged_in"]);
		
		if ($stay_logged_in === "false") {
			$stay_logged_in = false;
		}
	}
	
	$multi_domain_key = Auth::login($user, $stay_logged_in);
	$login_session = null;
	
	if ($multi_domain_key) {
		$login_session = Cache::get("org.bigtreecms.login-session", $multi_domain_key);
	}
	
	API::sendResponse([
		"logged_in" => true,
		"redirect" => $_SESSION["bigtree_login_redirect"],
		"multi_domain_key" => $multi_domain_key,
		"domains" => $login_session ? $login_session["domains"] : []
	]);
