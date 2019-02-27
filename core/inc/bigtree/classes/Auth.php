<?php
	/*
		Class: BigTree\Auth
			Provides an interface for user authentication.
	*/
	
	namespace BigTree;
	
	use BigTree\Auth\AuthenticatedUser;
	use Hautelook\Phpass\PasswordHash;
	
	class Auth {
		
		public static $BanExpiration;
		public static $Email;
		public static $ID;
		public static $Level = 0;
		public static $Name;
		public static $PagesTabHidden = false;
		public static $Permissions = [];
		
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
		
		function __construct(string $user_class = 'BigTree\User', string $namespace = "bigtree_admin", bool $enforce_policies = true) {
			static::$Namespace = $namespace;
			static::$Policies = $enforce_policies;
			static::$UserClass = $user_class;
			
			// Handle Login Session
			if (isset($_SESSION[static::$Namespace]["email"])) {
				$user = $user_class::getByEmail($_SESSION[static::$Namespace]["email"]);
				
				if ($user) {
					static::$Email = $user->Email;
					static::$ID = $user->ID;
					static::$Level = $user->Level;
					static::$Name = $user->Name;
					static::$Permissions = $user->Permissions;

					CSRF::setup($_SESSION[static::$Namespace]["csrf_token_field"], $_SESSION[static::$Namespace]["csrf_token"]);
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
							CSRF::generate();
							
							// Regenerate session ID on user state change
							$old_session_id = session_id();
							session_regenerate_id();
							
							if (!empty($bigtree["config"]["session_handler"]) && $bigtree["config"]["session_handler"] == "db") {
								SQL::update("bigtree_sessions", $old_session_id, [
									"id" => session_id(),
									"is_login" => "on",
									"logged_in_user" => $user->ID
								]);
							}
							
							$_SESSION[static::$Namespace]["id"] = $user->ID;
							$_SESSION[static::$Namespace]["email"] = $user->Email;
							$_SESSION[static::$Namespace]["name"] = $user->Name;
							$_SESSION[static::$Namespace]["level"] = $user->Level;
							$_SESSION[static::$Namespace]["csrf_token"] = CSRF::$Token;
							$_SESSION[static::$Namespace]["csrf_token_field"] = CSRF::$Field;
							
							// Setup auth environment
							static::$Email = $user->Email;
							static::$ID = $user->ID;
							static::$Level = $user->Level;
							static::$Name = $user->Name;
							static::$Permissions = $user->Permissions;
							
							// Delete existing session
							SQL::delete("bigtree_user_sessions", $session);
							
							// Generate a random session id
							$session = uniqid("session-", true);
							while (SQL::exists("bigtree_user_sessions", ["id" => $session])) {
								$session = uniqid("session-", true);
							}
							
							// Create a new session with the same chain
							SQL::insert("bigtree_user_sessions", [
								"id" => $session,
								"chain" => $chain,
								"email" => static::$Email,
								"csrf_token" => CSRF::$Token,
								"csrf_token_field" => CSRF::$Field
							]);
							
							Cookie::create(static::$Namespace."[login]", json_encode([$session, $chain]), "+1 month");
						}
						
					// Chain is legit and session isn't -- someone has taken your cookies
					} else {
						// Delete existing cookies
						Cookie::create(static::$Namespace."[login]", "", time() - 3600);
						Cookie::create(static::$Namespace."[email]", "", time() - 3600);
						
						// Delete all sessions for this user
						SQL::delete("bigtree_user_sessions", ["email" => $_COOKIE[static::$Namespace]["email"]]);
					}
				}
				
				// Check the permissions to see if we should show the pages tab.
				if (!static::$Level) {
					static::$PagesTabHidden = true;
					
					if (is_array(static::$Permissions["page"])) {
						foreach (static::$Permissions["page"] as $page_id => $permission) {
							if ($permission != "n" && $permission != "i") {
								static::$PagesTabHidden = false;
							}
						}
					}
				} else {
					static::$PagesTabHidden = false;
				}
				
				// Clean up
				unset($user, $f, $session, $chain, $chain_entry);
			}
		}
		
		/*
			Function: assign2FASecret
				Assigns a two factor auth token to a user and then logs them in.

			Parameters:
				secret - A Google Authenticator secret
		*/
		
		static function assign2FASecret(string $secret): void {
			$user = sqlfetch(sqlquery("SELECT 2fa_login_token FROM bigtree_users WHERE id = '".$_SESSION["bigtree_admin"]["2fa_id"]."'"));
			
			if ($user["2fa_login_token"] == $_SESSION["bigtree_admin"]["2fa_login_token"]) {
				sqlquery("UPDATE bigtree_users SET 2fa_secret = '".sqlescape($secret)."' WHERE id = '".$_SESSION["bigtree_admin"]["2fa_id"]."'");
			}
			
			static::login2FA(null, true);
		}
		
		/*
			Function: getIsIPBanned
				Checks to see if the requesting IP address is banned and should not be allowed to attempt login.

			Returns:
				true if the IP is banned
		*/
		
		static function getIsIPBanned($ip) {
			global $bigtree;
			
			if (!empty(static::$Policies)) {
				// Check to see if this IP is already banned from logging in.
				$ban = SQL::fetch("SELECT * FROM bigtree_login_bans WHERE expires > NOW() AND ip = ?", $ip);
				
				if ($ban) {
					$bigtree["ban_expiration"] = date("F j, Y @ g:ia", strtotime($ban["expires"]));
					$bigtree["ban_is_user"] = false;
					
					return true;
				}
			}
			
			return false;
		}
		
		/*
			Function: initSecurity
				Sets up security environment variables and runs white/blacklists for IP checks.
		*/
		
		static function initSecurity(): void {
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
				domain - A secondary domain to set login cookies for (used for multi-site).
				two_factor_token - A token for a login that is already in progress with 2FA.

			Returns:
				false if login failed, otherwise redirects back to the page the person requested.
		*/
		
		static function login(string $email, string $password, bool $stay_logged_in = false, ?string $domain = null,
							  string $two_factor_token = null): bool
		{
			global $bigtree;
			
			$user_class = static::$UserClass;
			$ip = ip2long($_SERVER["REMOTE_ADDR"]);
			
			if ($two_factor_token) {
				$user = $user_class::process2FAToken($two_factor_token);
				
				if ($user) {
					$login_validated = true;
				} else {
					$login_validated = false;
				}
			} else {
				if (static::getIsIPBanned($ip)) {
					return false;
				}
				
				// Get user, we'll be checking against the password later
				$email = trim(strtolower($email));
				$user = $user_class::getByEmail($email);
				
				// If the user doesn't exist, fail immediately
				if (!$user) {
					return false;
				}
				
				if ($user->IsBanned) {
					return false;
				}
				
				// BigTree 4.3+ switch to password_hash
				if ($user["new_hash"]) {
					$login_validated = password_verify($password, $user["password"]);
					
					// New algorithm
					if ($login_validated && password_needs_rehash($user["password"], PASSWORD_DEFAULT)) {
						SQL::update("bigtree_users", $user["id"], ["password" => password_hash($password, PASSWORD_DEFAULT)]);
					}
				} else {
					$phpass = new PasswordHash($bigtree["config"]["password_depth"], true);
					$login_validated = $phpass->CheckPassword($password, $user["password"]);
					
					// Switch to password_hash
					if ($login_validated) {
						SQL::update("bigtree_users", $user["id"], [
							"password" => password_hash($password, PASSWORD_DEFAULT),
							"new_hash" => "on"
						]);
					}
				}
			}
			
			if ($login_validated) {
				// Generate random session and chain ids
				$chain = SQL::unique("bigtree_user_sessions", "chain", uniqid("chain-", true));
				$session = SQL::unique("bigtree_user_sessions", "id", uniqid("session-", true));

				// Setup CSRF token
				CSRF::generate();
				
				// Create the new session chain
				SQL::insert("bigtree_user_sessions", [
					"id" => $session,
					"table" => $user_class::$Table,
					"chain" => $chain,
					"email" => $user->Email,
					"csrf_token" => CSRF::$Token,
					"csrf_token_field" => CSRF::$Field
				]);
				
				if (is_array($bigtree["config"]["sites"]) && count($bigtree["config"]["sites"])) {
					// Create another unique cache session for logins across domains
					$cache_data = [
						"user_id" => $user->ID,
						"session" => $session,
						"chain" => $chain,
						"stay_logged_in" => $stay_logged_in,
						"login_redirect" => isset($_SESSION["bigtree_login_redirect"]) ? $_SESSION["bigtree_login_redirect"] : false,
						"remaining_sites" => [],
						"csrf_token" => CSRF::$Token,
						"csrf_token_field" => CSRF::$Field
					];
					
					// If we have less than 4 other sites, browsers aren't going to freak out with the redirects
					$all_ssl = true;
					
					foreach ($bigtree["config"]["sites"] as $site_key => $site_configuration) {
						$cache_data["remaining_sites"][$site_key] = $site_configuration["www_root"];
						
						if (strpos($site_configuration["www_root"], "https://") !== 0) {
							$all_ssl = false;
						}
					}
					
					$cache_session_key = Cache::putUnique("org.bigtreecms.login-session", $cache_data);
					
					// Start the login chain
					if (strpos(ADMIN_ROOT, "https://") === 0 && !$all_ssl) {
						Router::redirect(str_replace("https://", "http://", ADMIN_ROOT)."login/cors/?key=".$cache_session_key);
					} else {
						Router::redirect(ADMIN_ROOT."login/cors/?key=".$cache_session_key);
					}
				} else {
					// We still set the email for BigTree bar usage even if they're not being "remembered"
					Cookie::create(static::$Namespace."[email]", $user->Email, "+1 month");
					
					if ($stay_logged_in) {
						Cookie::create(static::$Namespace."[login]", json_encode([$session, $chain]), "+1 month");
					}
					
					// Regenerate session ID on user state change
					$old_session_id = session_id();
					session_regenerate_id();
					
					if (!empty($bigtree["config"]["session_handler"]) && $bigtree["config"]["session_handler"] == "db") {
						SQL::update("bigtree_sessions", $old_session_id, [
							"id" => session_id(),
							"is_login" => "on",
							"logged_in_user" => $user->ID
						]);
					}
					
					$_SESSION[static::$Namespace]["id"] = $user->ID;
					$_SESSION[static::$Namespace]["email"] = $user->Email;
					$_SESSION[static::$Namespace]["level"] = $user->Level;
					$_SESSION[static::$Namespace]["name"] = $user->Name;
					$_SESSION[static::$Namespace]["permissions"] = $user->Permissions;
					$_SESSION[static::$Namespace]["csrf_token"] = CSRF::$Token;
					$_SESSION[static::$Namespace]["csrf_token_field"] = CSRF::$Field;
					
					if (isset($_SESSION["bigtree_login_redirect"])) {
						Router::redirect($_SESSION["bigtree_login_redirect"]);
					} else {
						Router::redirect(ADMIN_ROOT);
					}
				}
				
				return true;
				
			// Failed login attempt, log it.
			} elseif (!empty(static::$Policies)) {
				
				// Log it as a failed attempt for a user if the email address matched
				SQL::insert("bigtree_login_attempts", [
					"ip" => $ip,
					"table" => $user_class::$Table,
					"user" => $user ? "'".$user->ID."'" : null
				]);
				
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
		 	Function: loginSessionChain
				Begins or continues the login process for a login session chain logging in a user across all domains.
			
			Parameters:
				session_key - The session key created by the login method
		*/
		
		static function loginChainSession(string $session_key): void {
			$cache_data = Cache::get("org.bigtreecms.login-session", $session_key);
			
			if (empty($cache_data)) {
				die();
			}
			
			$admin_parts = parse_url(ADMIN_ROOT);
			
			if (isset($_GET["no_ssl"])) {
				$admin_parts["scheme"] = "http";
			}
			
			// Allow setting cookies and sessions
			header("Access-Control-Allow-Origin: ".$admin_parts["scheme"]."://".$admin_parts["host"]);
			header("Access-Control-Allow-Credentials: true");
			session_start(array("gc_maxlifetime" => 24 * 60 * 60));
			
			$user = SQL::fetch("SELECT * FROM bigtree_users WHERE id = ?", $cache_data["user_id"]);
			
			foreach ($cache_data["remaining_sites"] as $site_key => $www_root) {
				if ($site_key == BIGTREE_SITE_KEY) {
					$cookie_domain = str_replace(DOMAIN, "", WWW_ROOT);
					$cookie_value = json_encode(array($cache_data["session"], $cache_data["chain"]));
					
					// We still set the email for BigTree bar usage even if they're not being "remembered"
					setcookie('bigtree_admin[email]', $user["email"], strtotime("+1 month"), $cookie_domain, "", false, true);
					
					if ($cache_data["stay_logged_in"]) {
						setcookie('bigtree_admin[login]', $cookie_value, strtotime("+1 month"), $cookie_domain, "", false, true);
					}
					
					// Regenerate session ID on user state change
					$old_session_id = session_id();
					session_regenerate_id();
					
					if (!empty($bigtree["config"]["session_handler"]) && $bigtree["config"]["session_handler"] == "db") {
						SQL::update("bigtree_sessions", $old_session_id, [
							"id" => session_id(),
							"is_login" => "on",
							"logged_in_user" => $user["id"]
						]);
					}
					
					$_SESSION[static::$Namespace]["id"] = $user["id"];
					$_SESSION[static::$Namespace]["email"] = $user["email"];
					$_SESSION[static::$Namespace]["level"] = $user["level"];
					$_SESSION[static::$Namespace]["name"] = $user["name"];
					$_SESSION[static::$Namespace]["permissions"] = json_decode($user["permissions"], true);
					$_SESSION[static::$Namespace]["csrf_token"] = $cache_data["csrf_token"];
					$_SESSION[static::$Namespace]["csrf_token_field"] = $cache_data["csrf_token_field"];
				}
			}
		}
		
		/*
			Function: logout
				Destroys the user's session and unsets the login cookies.
		*/
		
		static function logout(): void {
			global $bigtree;
			
			// If the user asked to be remembered, drop their chain from the legit sessions and remove cookies
			if ($login = Cookie::get(static::$Namespace."[login]")) {
				list($session, $chain) = $login;
				
				// Make sure this session/chain is legit before removing everything with the given chain
				if (SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_user_sessions WHERE id = ? AND chain = ?", $session, $chain)) {
					SQL::delete("bigtree_user_sessions", ["chain" => $chain]);
				}
				
				Cookie::delete(static::$Namespace."[email]");
				Cookie::delete(static::$Namespace."[login]");
			}
			
			// Determine whether we should log out all instances of this user
			if (!empty($bigtree["config"]["session_handler"]) && $bigtree["config"]["session_handler"] == "db") {
				$security_policy = Setting::value("bigtree-internal-security-policy");
				
				if (!empty($security_policy["logout_all"])) {
					SQL::delete("bigtree_sessions", ["logged_in_user" => $_SESSION["bigtree_admin"]["id"]]);
					SQL::delete("bigtree_user_sessions", ["email" => $_SESSION["bigtree_admin"]["email"]]);
				}
			}
			
			unset($_COOKIE[static::$Namespace]);
			unset($_SESSION[static::$Namespace]);
		}
		
		/*
			Function: logoutAllUsers
				Logs all users out of the CMS.
				Requires the "db" state for sessions.
		*/
		
		public function logoutAllUsers(): void {
			SQL::query("DELETE FROM bigtree_sessions");
			SQL::query("DELETE FROM bigtree_user_sessions");
		}
		
		/*
			Function: stop
				Stops processing of the Admin area and shows a message in the default layout.

			Parameters:
				message - Content to show (error, permission denied, etc)
				file - A file to load (optional, replaces message but $message will be available in the file)
				layout_directory - The base directory for the layout to load (defaults to "admin/layouts/")
		*/
		
		static function stop(?string $message = null, ?string $file = null, string $layout_directory = "admin/layouts/"): void {
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
		
		static function user($user = null): AuthenticatedUser {
			if (is_null($user)) {
				if (static::$ID) {
					return new AuthenticatedUser(static::$ID, static::$Level, static::$Permissions);
				} else {
					return new AuthenticatedUser(null, -1, []);
				}
			} else {
				if (is_object($user)) {
					return new AuthenticatedUser($user->ID, $user->Level, $user->Permissions);
				}
				
				$user = SQL::fetch("SELECT id, level, permissions FROM bigtree_users WHERE id = ?", $user);
				
				// Return a -1 level of anonymous user
				if (empty($user)) {
					return new AuthenticatedUser(null, -1, []);
				} else {
					return new AuthenticatedUser($user["id"], $user["level"], (array) json_decode($user["permissions"], true));
				}
			}
		}
		
		/*
			Function: remove2FASecret
				Removes two factor authentication from a user.
		
			Parameters:
				user - A user ID
		*/
		
		static function remove2FASecret(int $user): void {
			SQL::update("bigtree_users", $user, ["2fa_secret" => ""]);
		}
		
		/*
			Function: verifyLogin2FA
				Verifies a username and password and returns the two factor auth secret.
			
			Parameters:
				email - Email address
				password - Password

			Returns:
				The two factor auth secret for the user or null if login failed.
		*/
		
		static function verifyLogin2FA(string $email, string $password): ?string {
			global $bigtree;
			
			$ip = ip2long($_SERVER["REMOTE_ADDR"]);
			
			if (static::getIsIPBanned($ip)) {
				return null;
			}
			
			// Get rid of whitespace on user input
			$email = trim($email);
			$password = trim($password);
			
			$user = $user_class::getByEmail($email);
			
			if (!$user) {
				return null;
			}
			
			if ($user_class::getIsUserBanned($user["id"])) {
				return null;
			}
			
			$phpass = new PasswordHash($bigtree["config"]["password_depth"], true);
			
			if ($phpass->CheckPassword($password, $user["password"])) {
				$token = $phpass->HashPassword(Text::getRandomString(64).trim($password).Text::getRandomString(64));
				$_SESSION["bigtree_admin"]["2fa_id"] = intval($user["id"]);
				$_SESSION["bigtree_admin"]["2fa_login_token"] = $token;
				SQL::update("bigtree_users", $user["id"], ["2fa_login_token" => $token]);
				
				return $user["2fa_secret"];
			}
			
			return null;
		}
		
	}
