<?php
	namespace BigTree;
	use BigTree\Auth\GoogleAuthenticator;
	
	/*
	 	Function: users/login
			Logs a user in.
		
		Method: POST
	 
		Parameters:
	 		email - Email Address
			password - Password
			stay_logged_in - Stay Logged In (optional)
	*/
	
	API::requireMethod("POST", false);
	API::requireParameters([
		"email" => "string",
		"password" => "string"
	]);
	
	$ban_error = "You are temporarily banned due to failed login attempts.\n".
				 "You may try logging in again after :ban_expiration:.";
	$common_error = Text::translate("You have entered an incorrect email address or password.");
	$ip = ip2long(Router::getRemoteIP());
	$email = trim(strtolower($_POST["email"]));
	$password = trim($_POST["password"]);

	if (Auth::getIsIPBanned($ip)) {
		$message = Text::translate($ban_error, false, [":ban_expiration:" => Auth::$BanExpiration]);
		API::sendResponse(["logged_in" => false, "reason" => $message], null, "failure:ipban");
	}
	
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
	
	// Get user, we'll be checking against the password later
	$user = User::getByEmail($email);
		
	if (!$user) {
		API::sendResponse(["logged_in" => false, "reason" => $common_error], null, "failure:credentials");
	}
	
	// User specific ban
	if ($user->IsBanned) {
		$message = Text::translate($ban_error, false, [":ban_expiration:" => Auth::$BanExpiration]);
		API::sendResponse(["logged_in" => false, "reason" => $message], null, "failure:userban");
	}
		
	$login_validated = password_verify($password, $user->Password);
	
	if (!$login_validated) {
		// Log the failed attempt
		SQL::insert("bigtree_login_attempts", [
			"ip" => $ip,
			"table" => User::$Table,
			"user" => $user ? $user->ID : null
		]);
		
		// See if this attempt earns the user a ban - first verify the policy is completely filled out (3 parts)
		if ($user->ID && count(array_filter((array) $security_policy["user_fails"])) == 3) {
			$policy = $security_policy["user_fails"];
			$attempts = SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_login_attempts
										  WHERE `user` = ? AND
												`timestamp` >= DATE_SUB(NOW(), INTERVAL ".$policy["time"]." MINUTE)",
										 $user);
			// Earned a ban
			if ($attempts >= $policy["count"]) {
				// See if they have an existing ban that hasn't expired, if so, extend it
				$existing_ban = SQL::fetch("SELECT * FROM bigtree_login_bans WHERE `user` = ? AND `expires` >= NOW()",
										   $user);
				
				if (!empty($existing_ban)) {
					SQL::query("UPDATE bigtree_login_bans
								SET `expires` = DATE_ADD(NOW(), INTERVAL ".$policy["ban"]." MINUTE)
								WHERE `id` = ?", $existing_ban["id"]);
				} else {
					SQL::query("INSERT INTO bigtree_login_bans (`ip`,`user`,`expires`)
								VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ".$policy["ban"]." MINUTE))", $ip, $user);
				}
				
				Auth::$BanExpiration = date("F j, Y @ g:ia", strtotime("+".$policy["ban"]." minutes"));
				$message = Text::translate($ban_error, false, [":ban_expiration:" => Auth::$BanExpiration]);

				API::sendResponse(["logged_in" => false, "reason" => $message], null, "failure:userban");
			}
		}
		
		// See if this attempt earns the IP as a whole a ban - first verify the policy is completely filled out (3 parts)
		if (count(array_filter((array) $security_policy["ip_fails"])) == 3) {
			$policy = $security_policy["ip_fails"];
			$attempts = SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_login_attempts
										  WHERE `ip` = ? AND
												`timestamp` >= DATE_SUB(NOW(), INTERVAL ".$policy["time"]." MINUTE)",
										 $ip);
			// Earned a ban
			if ($attempts >= $policy["count"]) {
				$existing_ban = SQL::fetch("SELECT * FROM bigtree_login_bans WHERE `ip` = ? AND `expires` >= NOW()",
										   $ip);
				
				if (!empty($existing_ban)) {
					SQL::query("UPDATE bigtree_login_bans
								SET `expires` = DATE_ADD(NOW(), INTERVAL ".$policy["ban"]." HOUR)
								WHERE `id` = ?", $existing_ban["id"]);
				} else {
					SQL::query("INSERT INTO bigtree_login_bans (`ip`,`expires`)
								VALUES (?, DATE_ADD(NOW(), INTERVAL ".$policy["ban"]." HOUR))", $ip);
				}
				
				Auth::$BanExpiration = date("F j, Y @ g:ia", strtotime("+".$policy["ban"]." hours"));
				$message = Text::translate($ban_error, false, [":ban_expiration:" => Auth::$BanExpiration]);

				API::sendResponse(["logged_in" => false, "reason" => $message], null, "failure:ipban");
			}
		}
		
		API::sendResponse(["logged_in" => false, "reason" => $common_error], null, "failure:credentials");
	}
	
	// Successful login but the password needs to be rehashed
	if (password_needs_rehash($user->Password, PASSWORD_DEFAULT)) {
		SQL::update("bigtree_users", $user->ID, ["password" => password_hash($password, PASSWORD_DEFAULT)]);
	}
	
	// User is using two factor auth, we need to create a unique login token for them and request their code
	if ($user->TwoFactorSecret) {
		API::sendResponse([
			"logged_in" => false,
			"two_factor_auth" => true,
			"user" => $user->ID,
			"token" => $user->setTwoFactorToken()
		]);
	} elseif ($security_policy["two_factor"]) {
		$site = new Page(0, null, false);
		$secret = GoogleAuthenticator::generateSecret();
		
		API::sendResponse([
			"logged_in" => false,
			"two_factor_setup" => true,
			"user" => $user->ID,
			"secret" => $secret,
			"qr_code" => GoogleAuthenticator::getQRCode($site->NavigationTitle, $secret),
			"token" => $user->setTwoFactorToken()
		]);
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
