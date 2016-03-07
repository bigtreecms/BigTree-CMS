<?php
	/*
		Class: BigTree\Auth
			Provides an interface for user authentication.
	*/

	namespace BigTree;
	
	use BigTree;
	use BigTreeCMS;
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
			$this->UserClass = new $user_class;

			// Handle Login Session
			if (isset($_SESSION[$this->Namespace]["email"])) {
				$user = $this->UserClass->getByEmail($_SESSION[$this->Namespace]["email"]);
				if ($user) {
					$this->ID = $user["id"];
					$this->User = $user["email"];
					$this->Level = $user["level"];
					$this->Name = $user["name"];
					$this->Permissions = @json_decode($user["permissions"],true);
				}

			// Handle saved cookies
			} elseif (isset($_COOKIE[$this->Namespace]["email"])) {
				// Get chain and session broken out
				list($session,$chain) = json_decode($_COOKIE[$this->Namespace]["login"]);

				// See if this is the current chain and session
				$chain_entry = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_user_sessions WHERE email = ? AND chain = ?",
													   $_COOKIE[$this->Namespace]["email"], $chain);
				if ($chain_entry) {
					// If both chain and session are legit, log them in
					if ($chain_entry["id"] == $session) {
						$user = $this->UserClass->getByEmail($_COOKIE[$this->Namespace]["email"]);
						
						if ($user) {
							// Setup session
							$_SESSION[$this->Namespace]["id"] = $user["id"];
							$_SESSION[$this->Namespace]["email"] = $user["email"];
							$_SESSION[$this->Namespace]["name"] = $user["name"];
							$_SESSION[$this->Namespace]["level"] = $user["level"];

							// Setup auth environment
							$this->ID = $user["id"];
							$this->User = $user["email"];
							$this->Level = $user["level"];
							$this->Name = $user["name"];
							$this->Permissions = json_decode($user["permissions"],true);

							// Delete existing session
							BigTreeCMS::$DB->delete("bigtree_user_sessions",$session);
							
							// Generate a random session id
							$session = uniqid("session-",true);
							while (BigTreeCMS::$DB->exists("bigtree_user_sessions",array("id" => $session))) {
								$session = uniqid("session-",true);
							}

							// Create a new session with the same chain
							BigTreeCMS::$DB->insert("bigtree_user_sessions",array(
								"id" => $session,
								"chain" => $chain,
								"email" => $this->User
							));
							BigTree::setCookie($this->Namespace."[login]",json_encode(array($session,$chain)),"+1 month");
						}

					// Chain is legit and session isn't -- someone has taken your cookies
					} else {
						// Delete existing cookies
						BigTree::setCookie($this->Namespace."[login]","",time() - 3600);
						BigTree::setCookie($this->Namespace."[email]","",time() - 3600);
						
						// Delete all sessions for this user
						BigTreeCMS::$DB->delete("bigtree_user_sessions",array("email" => $_COOKIE[$this->Namespace]["email"]));
					}
				}

				// Clean up
				unset($user,$f,$session,$chain,$chain_entry);
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
				$ban = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_login_bans WHERE `expires` > NOW() AND `ip` = ?", $ip);
				if ($ban) {
					$bigtree["ban_expiration"] = date("F j, Y @ g:ia",strtotime($ban["expires"]));
					$bigtree["ban_is_user"] = false;
					return false;
				}
			}

			// Get user, we'll be checking against the password later
			$user = $this->UserClass->getByEmail($email);

			// If the user doesn't exist, fail immediately
			if (!$user) {
				return false;
			}

			// See if this user is banned due to failed login attempts
			if ($this->Policies) {
				$ban = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_login_bans WHERE `table` = ? AND `expires` > NOW() AND `user` = ?",
											   $user_class::$Table, $user["id"]);
				if ($ban) {
					$bigtree["ban_expiration"] = date("F j, Y @ g:ia",strtotime($ban["expires"]));
					$bigtree["ban_is_user"] = true;
					return false;
				}
			}

			// Verify password
			$phpass = new PasswordHash($bigtree["config"]["password_depth"],true);
			if ($phpass->CheckPassword(trim($password),$user["password"])) {
				// Generate random session and chain ids
				$chain = BigTreeCMS::$DB->unique("bigtree_user_sessions","chain",uniqid("chain-",true));
				$session = BigTreeCMS::$DB->unique("bigtree_user_sessions","id",uniqid("session-",true));

				// Create the new session chain
				BigTreeCMS::$DB->insert("bigtree_user_sessions",array(
					"id" => $session,
					"table" => $user_class::$Table,
					"chain" => $chain,
					"email" => $user["email"]
				));

				// We still set the email for BigTree bar usage even if they're not being "remembered"
				setcookie($this->Namespace."[email]",$user["email"],strtotime("+1 month"),str_replace(DOMAIN,"",WWW_ROOT),"",false,true);
				if ($stay_logged_in) {
					setcookie($this->Namespace."[login]",json_encode(array($session,$chain)),strtotime("+1 month"),str_replace(DOMAIN,"",WWW_ROOT),"",false,true);
				}

				$_SESSION[$this->Namespace]["id"] = $user["id"];
				$_SESSION[$this->Namespace]["email"] = $user["email"];
				$_SESSION[$this->Namespace]["level"] = $user["level"];
				$_SESSION[$this->Namespace]["name"] = $user["name"];
				$_SESSION[$this->Namespace]["permissions"] = @json_decode($user["permissions"],true);

				return true;

			// Failed login attempt, log it.
			} elseif ($this->Policies) {

				// Log it as a failed attempt for a user if the email address matched
				BigTreeCMS::$DB->insert("bigtree_login_attempts",array(
					"ip" => $ip,
					"table" => $user_class::$Table,
					"user" => $user ? "'".$user["id"]."'" : null
				));

				// See if this attempt earns the user a ban - first verify the policy is completely filled out (3 parts)
				if ($user["id"] && count(array_filter((array)$bigtree["security-policy"]["user_fails"])) == 3) {
					$policy = $bigtree["security-policy"]["user_fails"];
					$attempts = BigTreeCMS::$DB->fetchSingle("SELECT COUNT(*) FROM bigtree_login_attempts 
															  WHERE `user` = ? AND 
														 			`timestamp` >= DATE_SUB(NOW(),INTERVAL ".$policy["time"]." MINUTE)", $user);
					// Earned a ban
					if ($attempts >= $policy["count"]) {
						// See if they have an existing ban that hasn't expired, if so, extend it
						$existing_ban = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_login_bans WHERE `user` = ? AND `expires` >= NOW()", $user);
						if ($existing_ban) {
							BigTreeCMS::$DB->query("UPDATE bigtree_login_bans 
													SET `expires` = DATE_ADD(NOW(),INTERVAL ".$policy["ban"]." MINUTE) 
													WHERE `id` = ?", $existing_ban["id"]);
						} else {
							BigTreeCMS::$DB->query("INSERT INTO bigtree_login_bans (`ip`,`user`,`expires`) 
													VALUES (?, ?, DATE_ADD(NOW(),INTERVAL ".$policy["ban"]." MINUTE))", $ip, $user);
						}
						$bigtree["ban_expiration"] = date("F j, Y @ g:ia",strtotime("+".$policy["ban"]." minutes"));
						$bigtree["ban_is_user"] = true;
					}
				}

				// See if this attempt earns the IP as a whole a ban - first verify the policy is completely filled out (3 parts)
				if (count(array_filter((array)$bigtree["security-policy"]["ip_fails"])) == 3) {
					$policy = $bigtree["security-policy"]["ip_fails"];
					$attempts = BigTreeCMS::$DB->fetchSingle("SELECT COUNT(*) FROM bigtree_login_attempts 
															  WHERE `ip` = ? AND 
																	`timestamp` >= DATE_SUB(NOW(),INTERVAL ".$policy["time"]." MINUTE)", $ip);
					// Earned a ban
					if ($attempts >= $policy["count"]) {
						$existing_ban = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_login_bans WHERE `ip` = ? AND `expires` >= NOW()", $ip);
						if ($existing_ban) {
							BigTreeCMS::$DB->query("UPDATE bigtree_login_bans 
													SET `expires` = DATE_ADD(NOW(),INTERVAL ".$policy["ban"]." HOUR) 
													WHERE `id` = ?", $existing_ban["id"]);
						} else {
							BigTreeCMS::$DB->query("INSERT INTO bigtree_login_bans (`ip`,`expires`) 
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

	}
