<?php
	/*
		Class: BigTree\Auth
			Provides an interface for user authentication.
	*/

	namespace BigTree;
	
	use PasswordHash;

	class Auth {

		var $Namespace = "";
		var $Policies = false;
		var $UserClass = "";

		/*
			Constructor:
				Sets up the user class and cookie/session namespace.
				Initiates a user environment.

			Parameters:
				user_class - The user class that represents your users
				namespace - The cookie and session namespace to store login credentials in
				enforce_policies - Whether to enforce password/login policies
		*/

		function __construct($user_class = 'BigTree\User',$namespace = "bigtree_admin",$enforce_policies = true) {
			$this->Namespace = $namespace;
			$this->Policies = $enforce_policies;
			$this->UserClass = $user_class;

			// Handle Login Session
			if (isset($_SESSION[$this->Namespace]["email"])) {
				$user = $user_class::getByEmail($_SESSION[$this->Namespace]["email"]);
				if ($user) {
					$this->ID = $user->ID;
					$this->User = $user->Email;
					$this->Level = $user->Level;
					$this->Name = $user->Name;
					$this->Permissions = $user->Permissions;
				}

			// Handle saved cookies
			} elseif (isset($_COOKIE[$this->Namespace]["email"])) {
				// Get chain and session broken out
				list($session,$chain) = json_decode($_COOKIE[$this->Namespace]["login"]);

				// See if this is the current chain and session
				$chain_entry = SQL::fetch("SELECT * FROM bigtree_user_sessions WHERE email = ? AND chain = ?",
													   $_COOKIE[$this->Namespace]["email"], $chain);
				if ($chain_entry) {
					// If both chain and session are legit, log them in
					if ($chain_entry["id"] == $session) {
						$user = $user_class::getByEmail($_COOKIE[$this->Namespace]["email"]);
						
						if ($user) {
							// Setup session
							$_SESSION[$this->Namespace]["id"] = $user->ID;
							$_SESSION[$this->Namespace]["email"] = $user->Email;
							$_SESSION[$this->Namespace]["name"] = $user->Name;
							$_SESSION[$this->Namespace]["level"] = $user->Level;

							// Setup auth environment
							$this->ID = $user->ID;
							$this->User = $user->Email;
							$this->Level = $user->Level;
							$this->Name = $user->Name;
							$this->Permissions = $user->Permissions;

							// Delete existing session
							SQL::delete("bigtree_user_sessions",$session);
							
							// Generate a random session id
							$session = uniqid("session-",true);
							while (SQL::exists("bigtree_user_sessions",array("id" => $session))) {
								$session = uniqid("session-",true);
							}

							// Create a new session with the same chain
							SQL::insert("bigtree_user_sessions",array(
								"id" => $session,
								"chain" => $chain,
								"email" => $this->User
							));
							Cookie::set($this->Namespace."[login]",json_encode(array($session,$chain)),"+1 month");
						}

					// Chain is legit and session isn't -- someone has taken your cookies
					} else {
						// Delete existing cookies
						Cookie::set($this->Namespace."[login]","",time() - 3600);
						Cookie::set($this->Namespace."[email]","",time() - 3600);
						
						// Delete all sessions for this user
						SQL::delete("bigtree_user_sessions",array("email" => $_COOKIE[$this->Namespace]["email"]));
					}
				}

				// Clean up
				unset($user,$f,$session,$chain,$chain_entry);
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
				$banned = explode("\n",$policy["banned_ips"]);
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
				$list = explode("\n",$policy["allowed_ips"]);
				foreach ($list as $item) {
					list($begin,$end) = explode(",",$item);
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

		function login($email,$password,$stay_logged_in = false) {
			global $bigtree;
			$user_class = $this->UserClass;

			// Check to see if this IP is already banned from logging in.
			if ($this->Policies) {
				$ip = ip2long($_SERVER["REMOTE_ADDR"]);
				$ban = SQL::fetch("SELECT * FROM bigtree_login_bans WHERE `expires` > NOW() AND `ip` = ?", $ip);
				if ($ban) {
					$bigtree["ban_expiration"] = date("F j, Y @ g:ia",strtotime($ban["expires"]));
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
			if ($this->Policies) {
				$ban = SQL::fetch("SELECT * FROM bigtree_login_bans WHERE `table` = ? AND `expires` > NOW() AND `user` = ?",
											   $user_class::$Table, $user->ID);
				if ($ban) {
					$bigtree["ban_expiration"] = date("F j, Y @ g:ia",strtotime($ban["expires"]));
					$bigtree["ban_is_user"] = true;
					return false;
				}
			}

			// Verify password
			$phpass = new PasswordHash($bigtree["config"]["password_depth"],true);
			if ($phpass->CheckPassword(trim($password),$user->Password)) {
				// Generate random session and chain ids
				$chain = SQL::unique("bigtree_user_sessions","chain",uniqid("chain-",true));
				$session = SQL::unique("bigtree_user_sessions","id",uniqid("session-",true));

				// Create the new session chain
				SQL::insert("bigtree_user_sessions",array(
					"id" => $session,
					"table" => $user_class::$Table,
					"chain" => $chain,
					"email" => $user->Email
				));

				// We still set the email for BigTree bar usage even if they're not being "remembered"
				setcookie($this->Namespace."[email]",$user->Email,strtotime("+1 month"),str_replace(DOMAIN,"",WWW_ROOT),"",false,true);
				if ($stay_logged_in) {
					setcookie($this->Namespace."[login]",json_encode(array($session,$chain)),strtotime("+1 month"),str_replace(DOMAIN,"",WWW_ROOT),"",false,true);
				}

				$_SESSION[$this->Namespace]["id"] = $user->ID;
				$_SESSION[$this->Namespace]["email"] = $user->Email;
				$_SESSION[$this->Namespace]["level"] = $user->Level;
				$_SESSION[$this->Namespace]["name"] = $user->Name;
				$_SESSION[$this->Namespace]["permissions"] = $user->Permissions;

				return true;

			// Failed login attempt, log it.
			} elseif ($this->Policies) {

				// Log it as a failed attempt for a user if the email address matched
				SQL::insert("bigtree_login_attempts",array(
					"ip" => $ip,
					"table" => $user_class::$Table,
					"user" => $user ? "'".$user->ID."'" : null
				));

				// See if this attempt earns the user a ban - first verify the policy is completely filled out (3 parts)
				if ($user->ID && count(array_filter((array)$bigtree["security-policy"]["user_fails"])) == 3) {
					$policy = $bigtree["security-policy"]["user_fails"];
					$attempts = SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_login_attempts 
															  WHERE `user` = ? AND 
														 			`timestamp` >= DATE_SUB(NOW(),INTERVAL ".$policy["time"]." MINUTE)", $user);
					// Earned a ban
					if ($attempts >= $policy["count"]) {
						// See if they have an existing ban that hasn't expired, if so, extend it
						$existing_ban = SQL::fetch("SELECT * FROM bigtree_login_bans WHERE `user` = ? AND `expires` >= NOW()", $user);
						if ($existing_ban) {
							SQL::query("UPDATE bigtree_login_bans 
													SET `expires` = DATE_ADD(NOW(),INTERVAL ".$policy["ban"]." MINUTE) 
													WHERE `id` = ?", $existing_ban["id"]);
						} else {
							SQL::query("INSERT INTO bigtree_login_bans (`ip`,`user`,`expires`) 
													VALUES (?, ?, DATE_ADD(NOW(),INTERVAL ".$policy["ban"]." MINUTE))", $ip, $user);
						}
						$bigtree["ban_expiration"] = date("F j, Y @ g:ia",strtotime("+".$policy["ban"]." minutes"));
						$bigtree["ban_is_user"] = true;
					}
				}

				// See if this attempt earns the IP as a whole a ban - first verify the policy is completely filled out (3 parts)
				if (count(array_filter((array)$bigtree["security-policy"]["ip_fails"])) == 3) {
					$policy = $bigtree["security-policy"]["ip_fails"];
					$attempts = SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_login_attempts 
															  WHERE `ip` = ? AND 
																	`timestamp` >= DATE_SUB(NOW(),INTERVAL ".$policy["time"]." MINUTE)", $ip);
					// Earned a ban
					if ($attempts >= $policy["count"]) {
						$existing_ban = SQL::fetch("SELECT * FROM bigtree_login_bans WHERE `ip` = ? AND `expires` >= NOW()", $ip);
						if ($existing_ban) {
							SQL::query("UPDATE bigtree_login_bans 
													SET `expires` = DATE_ADD(NOW(),INTERVAL ".$policy["ban"]." HOUR) 
													WHERE `id` = ?", $existing_ban["id"]);
						} else {
							SQL::query("INSERT INTO bigtree_login_bans (`ip`,`expires`) 
													VALUES (?, DATE_ADD(NOW(),INTERVAL ".$policy["ban"]." HOUR))", $ip);
						}
						$bigtree["ban_expiration"] = date("F j, Y @ g:ia",strtotime("+".$policy["ban"]." hours"));
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
			setcookie($this->Namespace."[email]","",time() - 3600,str_replace(DOMAIN,"",WWW_ROOT));
			setcookie($this->Namespace."[login]","",time() - 3600,str_replace(DOMAIN,"",WWW_ROOT));
			unset($_COOKIE[$this->Namespace]);
			unset($_SESSION[$this->Namespace]);
		}

		/*
			Function: requireLevel
				Requires the logged in user to have a certain access level to continue.
				Throws a permission denied page and stops page execution if the user doesn't have access.

			Parameters:
				level - An access level (0 being normal user, 1 being administrator, 2 being developer)
				error_path - Path (relative to SERVER_ROOT) of the error page to serve.
		*/

		function requireLevel($level, $error_path = "admin/pages/_denied.php") {
			global $admin,$bigtree,$cms,$db;

			// If we aren't logged in or the logged in level is less than required, denied.
			if (!isset($this->Level) || $this->Level < $level) {
				define("BIGTREE_ACCESS_DENIED",true);
				$this->stop(file_get_contents(Router::getIncludePath($error_path)));
			}

			return true;
		}

		/*
			Function: stop
				Stops processing of the Admin area and shows a message in the default layout.

			Parameters:
				message - Content to show (error, permission denied, etc)
				file - A file to load (optional, replaces message but $message will be available in the file)
				layout_directory - The base directory for the layout to load (defaults to "admin/layouts/")
		*/

		static function stop($message = "",$file = "",$layout_directory = "admin/layouts/") {
			global $admin,$bigtree,$cms,$db;

			if ($file) {
				include $file;
			} else {
				echo $message;
			}

			$bigtree["content"] = ob_get_clean();

			include Router::getIncludePath($layout_directory.$bigtree["layout"].".php");

			die();
		}

	}
