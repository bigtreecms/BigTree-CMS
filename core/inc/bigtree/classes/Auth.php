<?php
	/*
		Class: BigTree\Auth
			Provides an interface for user authentication.
	*/

	namespace BigTree;
	
	use PasswordHash;

	class Auth {

		public static $ID;
		public static $Level = 0;
		public static $Name;
		public static $Permissions = array();
		public static $User;

		private static $Namespace = "";
		private static $Policies = false;
		private static $UserClass = "";

		/*
			Constructor:
				Sets up the user class and cookie/session namespace.
				Initiates a user environment.

			Parameters:
				user_class - The user class that represents your users
				namespace - The cookie and session namespace to store login credentials in
				enforce_policies - Whether to enforce password/login policies
		*/

		function __construct($user_class = 'BigTree\User', $namespace = "bigtree_admin", $enforce_policies = true) {
			static::$Namespace = $namespace;
			static::$Policies = $enforce_policies;
			static::$UserClass = $user_class;

			// Handle Login Session
			if (isset($_SESSION[static::$Namespace]["email"])) {
				$user = $user_class::getByEmail($_SESSION[static::$Namespace]["email"]);
				
				if ($user) {
					static::$ID = $user->ID;
					static::$User = $user->Email;
					static::$Level = $user->Level;
					static::$Name = $user->Name;
					static::$Permissions = $user->Permissions;
				}

			// Handle saved cookies
			} elseif (isset($_COOKIE[static::$Namespace]["email"])) {
				// Get chain and session broken out
				list($session, $chain) = json_decode($_COOKIE[static::$Namespace]["login"]);

				// See if this is the current chain and session
				$chain_entry = SQL::fetch("SELECT * FROM bigtree_user_sessions WHERE email = ? AND chain = ?",
										   $_COOKIE[static::$Namespace]["email"], $chain);
				if (!empty($chain_entry)) {
					// If both chain and session are legit, log them in
					if ($chain_entry["id"] == $session) {
						$user = $user_class::getByEmail($_COOKIE[static::$Namespace]["email"]);
						
						if ($user) {
							// Setup session
							$_SESSION[static::$Namespace]["id"] = $user->ID;
							$_SESSION[static::$Namespace]["email"] = $user->Email;
							$_SESSION[static::$Namespace]["name"] = $user->Name;
							$_SESSION[static::$Namespace]["level"] = $user->Level;

							// Setup auth environment
							static::$ID = $user->ID;
							static::$User = $user->Email;
							static::$Level = $user->Level;
							static::$Name = $user->Name;
							static::$Permissions = $user->Permissions;

							// Delete existing session
							SQL::delete("bigtree_user_sessions", $session);
							
							// Generate a random session id
							$session = uniqid("session-", true);
							while (SQL::exists("bigtree_user_sessions", array("id" => $session))) {
								$session = uniqid("session-", true);
							}

							// Create a new session with the same chain
							SQL::insert("bigtree_user_sessions", array(
								"id" => $session,
								"chain" => $chain,
								"email" => static::$User
							));

							Cookie::create(static::$Namespace."[login]", json_encode(array($session, $chain)), "+1 month");
						}

						// Chain is legit and session isn't -- someone has taken your cookies
					} else {
						// Delete existing cookies
						Cookie::create(static::$Namespace."[login]", "", time() - 3600);
						Cookie::create(static::$Namespace."[email]", "", time() - 3600);
						
						// Delete all sessions for this user
						SQL::delete("bigtree_user_sessions", array("email" => $_COOKIE[static::$Namespace]["email"]));
					}
				}

				// Clean up
				unset($user, $f, $session, $chain, $chain_entry);
			}
		}

		/*
			Function: initSecurity
				Sets up security environment variables and runs white/blacklists for IP checks.
		*/

		static function initSecurity() {
			global $bigtree;

			$ip = ip2long($_SERVER["REMOTE_ADDR"]);
			$bigtree["security-policy"] = $policy = Setting::value("bigtree-internal-security-policy");

			// Check banned IPs list for the user's IP
			if (!empty($policy["banned_ips"])) {
				$banned = explode("\n", $policy["banned_ips"]);
				
				foreach ($banned as $address) {
					if (ip2long(trim($address)) == $ip) {
						$bigtree["layout"] = "login";
						static::stop(file_get_contents(Router::getIncludePath("admin/pages/ip-restriction.php")));
					}
				}
			}

			// Check allowed IP ranges list for user's IP
			if (!empty($policy["allowed_ips"])) {
				$allowed = false;
				
				// Go through the list and see if our IP address is allowed
				$list = explode("\n", $policy["allowed_ips"]);
				foreach ($list as $item) {
					list($begin, $end) = explode(",", $item);
					$begin = ip2long(trim($begin));
					$end = ip2long(trim($end));
					if ($begin <= $ip && $end >= $ip) {
						$allowed = true;
					}
				}

				if (!$allowed) {
					$bigtree["layout"] = "login";
					static::stop(file_get_contents(Router::getIncludePath("admin/pages/ip-restriction.php")));
				}
			}
		}

		/*
			Function: login
				Attempts to log a user into to the CMS.

			Parameters:
				email - The email address of the user.
				password - The password of the user.
				stay_logged_in - Whether to set a cookie to keep the user logged in.

			Returns:
				false if login failed, otherwise redirects back to the page the person requested.
		*/

		function login($email, $password, $stay_logged_in = false) {
			global $bigtree;

			$user_class = static::$UserClass;
			$ip = ip2long($_SERVER["REMOTE_ADDR"]);

			// Check to see if this IP is already banned from logging in.
			if (!empty(static::$Policies)) {
				$ban = SQL::fetch("SELECT * FROM bigtree_login_bans WHERE `expires` > NOW() AND `ip` = ?", $ip);
				
				if (!empty($ban)) {
					$bigtree["ban_expiration"] = date("F j, Y @ g:ia", strtotime($ban["expires"]));
					$bigtree["ban_is_user"] = false;

					return false;
				}
			}

			// Get user, we'll be checking against the password later
			$user = $user_class::getByEmail($email);

			// If the user doesn't exist, fail immediately
			if (!$user) {
				return false;
			}

			// See if this user is banned due to failed login attempts
			if (!empty(static::$Policies)) {
				$ban = SQL::fetch("SELECT * FROM bigtree_login_bans WHERE `table` = ? AND `expires` > NOW() AND `user` = ?",
								   $user_class::$Table, $user->ID);
				if (!empty($ban)) {
					$bigtree["ban_expiration"] = date("F j, Y @ g:ia", strtotime($ban["expires"]));
					$bigtree["ban_is_user"] = true;

					return false;
				}
			}

			// Verify password
			$phpass = new PasswordHash($bigtree["config"]["password_depth"], true);

			if ($phpass->CheckPassword(trim($password), $user->Password)) {
				// Generate random session and chain ids
				$chain = SQL::unique("bigtree_user_sessions", "chain", uniqid("chain-", true));
				$session = SQL::unique("bigtree_user_sessions", "id", uniqid("session-", true));

				// Create the new session chain
				SQL::insert("bigtree_user_sessions", array(
					"id" => $session,
					"table" => $user_class::$Table,
					"chain" => $chain,
					"email" => $user->Email
				));

				// We still set the email for BigTree bar usage even if they're not being "remembered"
				Cookie::create(static::$Namespace."[email]", $user->Email, "+1 month");
				if ($stay_logged_in) {
					Cookie::create(static::$Namespace."[login]", json_encode(array($session, $chain)), "+1 month");
				}

				$_SESSION[static::$Namespace]["id"] = $user->ID;
				$_SESSION[static::$Namespace]["email"] = $user->Email;
				$_SESSION[static::$Namespace]["level"] = $user->Level;
				$_SESSION[static::$Namespace]["name"] = $user->Name;
				$_SESSION[static::$Namespace]["permissions"] = $user->Permissions;

				return true;

			// Failed login attempt, log it.
			} elseif (!empty(static::$Policies)) {

				// Log it as a failed attempt for a user if the email address matched
				SQL::insert("bigtree_login_attempts", array(
					"ip" => $ip,
					"table" => $user_class::$Table,
					"user" => $user ? "'".$user->ID."'" : null
				));

				// See if this attempt earns the user a ban - first verify the policy is completely filled out (3 parts)
				if ($user->ID && count(array_filter((array) $bigtree["security-policy"]["user_fails"])) == 3) {
					$policy = $bigtree["security-policy"]["user_fails"];
					$attempts = SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_login_attempts 
												  WHERE `user` = ? AND 
														`timestamp` >= DATE_SUB(NOW(),INTERVAL ".$policy["time"]." MINUTE)", $user);
					// Earned a ban
					if ($attempts >= $policy["count"]) {
						// See if they have an existing ban that hasn't expired, if so, extend it
						$existing_ban = SQL::fetch("SELECT * FROM bigtree_login_bans WHERE `user` = ? AND `expires` >= NOW()", $user);
						
						if (!empty($existing_ban)) {
							SQL::query("UPDATE bigtree_login_bans 
										SET `expires` = DATE_ADD(NOW(),INTERVAL ".$policy["ban"]." MINUTE) 
										WHERE `id` = ?", $existing_ban["id"]);
						} else {
							SQL::query("INSERT INTO bigtree_login_bans (`ip`,`user`,`expires`) 
										VALUES (?, ?, DATE_ADD(NOW(),INTERVAL ".$policy["ban"]." MINUTE))", $ip, $user);
						}

						$bigtree["ban_expiration"] = date("F j, Y @ g:ia", strtotime("+".$policy["ban"]." minutes"));
						$bigtree["ban_is_user"] = true;
					}
				}

				// See if this attempt earns the IP as a whole a ban - first verify the policy is completely filled out (3 parts)
				if (count(array_filter((array) $bigtree["security-policy"]["ip_fails"])) == 3) {
					$policy = $bigtree["security-policy"]["ip_fails"];
					$attempts = SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_login_attempts 
												  WHERE `ip` = ? AND 
														`timestamp` >= DATE_SUB(NOW(),INTERVAL ".$policy["time"]." MINUTE)", $ip);
					// Earned a ban
					if ($attempts >= $policy["count"]) {
						$existing_ban = SQL::fetch("SELECT * FROM bigtree_login_bans WHERE `ip` = ? AND `expires` >= NOW()", $ip);
						
						if (!empty($existing_ban)) {
							SQL::query("UPDATE bigtree_login_bans 
										SET `expires` = DATE_ADD(NOW(),INTERVAL ".$policy["ban"]." HOUR) 
										WHERE `id` = ?", $existing_ban["id"]);
						} else {
							SQL::query("INSERT INTO bigtree_login_bans (`ip`,`expires`) 
										VALUES (?, DATE_ADD(NOW(),INTERVAL ".$policy["ban"]." HOUR))", $ip);
						}
						
						$bigtree["ban_expiration"] = date("F j, Y @ g:ia", strtotime("+".$policy["ban"]." hours"));
						$bigtree["ban_is_user"] = false;
					}
				}

				return false;
			}

			return false;
		}

		/*
			Function: logout
				Destroys the user's session and unsets the login cookies.
		*/

		function logout() {
			// If the user asked to be remembered, drop their chain from the legit sessions and remove cookies
			if ($login = Cookie::get(static::$Namespace."[login]")) {
				list($session, $chain) = $login;

				// Make sure this session/chain is legit before removing everything with the given chain
				if (SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_user_sessions WHERE id = ? AND chain = ?", $session, $chain)) {
					SQL::delete("bigtree_user_sessions", array("chain" => $chain));
				}

				Cookie::delete(static::$Namespace."[email]");
				Cookie::delete(static::$Namespace."[login]");
			}
			
			unset($_COOKIE[static::$Namespace]);
			unset($_SESSION[static::$Namespace]);
		}

		/*
			Function: stop
				Stops processing of the Admin area and shows a message in the default layout.

			Parameters:
				message - Content to show (error, permission denied, etc)
				file - A file to load (optional, replaces message but $message will be available in the file)
				layout_directory - The base directory for the layout to load (defaults to "admin/layouts/")
		*/

		static function stop($message = "", $file = "", $layout_directory = "admin/layouts/") {
			global $admin, $bigtree, $cms, $db;

			if ($file) {
				include $file;
			} else {
				echo Text::translate($message);
			}

			$bigtree["content"] = ob_get_clean();

			include Router::getIncludePath($layout_directory.$bigtree["layout"].".php");

			die();
		}

		/*
		    Function: user
				Returns a BigTree\Auth\AuthenticatedUser object.

			Parameters:
				user - Either a user ID, BigTree\User object, or false to use the currently logged in user.

			Returns:
				A BigTree\Auth\AuthenticatedUser object.
		*/

		static function user($user = false) {
			if ($user !== false) {
				if (is_object($user)) {
					return new Auth\AuthenticatedUser($user->Level, $user->Permissions);
				}

				$user = SQL::fetch("SELECT level, permissions FROM bigtree_users WHERE id = ?", $user);

				// Return a -1 level of anonymous user
				if (!$user) {
					return new Auth\AuthenticatedUser(-1, array());
				} else {
					return new Auth\AuthenticatedUser($user["level"], (array) json_decode($user["permissions"], true));
				}
			} else {
				if (static::$ID) {
					return new Auth\AuthenticatedUser(static::$Level, static::$Permissions);
				} else {
					return new Auth\AuthenticatedUser(-1, array());
				}
			}
		}

	}
