<?php /** @noinspection Annotator */
	/** @noinspection ALL */
	/** @noinspection Annotator */
	
	/*
		Class: BigTree\Auth
			Provides an interface for user authentication.
	*/
	
	namespace BigTree;
	
	use BigTree\Auth\AuthenticatedUser;
	use Hautelook\Phpass\PasswordHash;
	
	class Auth
	{
		
		public static $BanExpiration;
		public static $BannedIP = false;
		public static $Email;
		public static $ID;
		public static $Level = 0;
		public static $Name;
		public static $PagesTabHidden = false;
		public static $Permissions = [];
		public static $Timezone = "";
		
		private static $Namespace = "";
		private static $Policies = false;
		private static $UserClass = "";
		
		/*
			Function: assign2FASecret
				Assigns a two factor auth token to a user and then logs them in.

			Parameters:
				secret - A Google Authenticator secret
		*/
		
		public static function assign2FASecret(string $secret): void
		{
			$token = SQL::fetchSingle("SELECT 2fa_login_token FROM bigtree_users
									   WHERE id = ?", $_SESSION["bigtree_admin"]["2fa_id"]);
			
			if ($token == $_SESSION["bigtree_admin"]["2fa_login_token"]) {
				SQL::update("bigtree_users", $_SESSION["bigtree_admin"]["2fa_id"], ["2fa_secret" => $secret]);
			}
			
			static::login2FA(null, true);
		}
		
		/*
			Function: authenticate
				Sets up the user class and cookie/session namespace.
				Initiates a user environment.

			Parameters:
				user_class - The user class that represents your users
				namespace - The cookie and session namespace to store login credentials in
				enforce_policies - Whether to enforce password/login policies
		*/
		
		public static function authenticate(string $user_class = 'BigTree\User', string $namespace = "bigtree_admin",
											bool $enforce_policies = true)
		{
			/** @var User $user_class */
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
					static::$Timezone = $user->Timezone;
					
					CSRF::setup($_SESSION[static::$Namespace]["csrf_token_field"], $_SESSION[static::$Namespace]["csrf_token"]);
				}
				
				// Handle saved cookies
			} elseif (isset($_COOKIE[static::$Namespace]["email"])) {
				// Get chain and session broken out
				list($session, $chain) = Cookie::get(static::$Namespace.'["login"]');
				$email = Cookie::get(static::$Namespace.'["email"]');
				
				// See if this is the current chain and session
				$chain_entry = SQL::fetch("SELECT * FROM bigtree_user_sessions WHERE email = ? AND chain = ?",
										  $email, $chain);
				
				if (!empty($chain_entry)) {
					// If both chain and session are legit, log them in
					if ($chain_entry["id"] == $session) {
						$user = $user_class::getByEmail($email);
						
						if ($user) {
							CSRF::generate();
							
							// Regenerate session ID on user state change
							$old_session_id = session_id();
							session_regenerate_id();
							
							if (!empty(Router::$Config["session_handler"]) && Router::$Config["session_handler"] == "db") {
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
							static::$Timezone = $user->Timezone;
							
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
						SQL::delete("bigtree_user_sessions", ["email" => $email]);
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
			Function: getIsIPBanned
				Checks to see if the requesting IP address is banned and should not be allowed to attempt login.

			Returns:
				true if the IP is banned
		*/
		
		public static function getIsIPBanned($ip)
		{
			global $bigtree;
			
			if (!empty(static::$Policies)) {
				// Check to see if this IP is already banned from logging in.
				$ban = SQL::fetch("SELECT * FROM bigtree_login_bans WHERE expires > NOW() AND ip = ?", $ip);
				
				if ($ban) {
					static::$BanExpiration = date("F j, Y @ g:ia", strtotime($ban["expires"]));
					static::$BannedIP = true;
					
					return true;
				}
			}
			
			return false;
		}
		
		/*
			Function: initSecurity
				Sets up security environment variables and runs white/blacklists for IP checks.
		*/
		
		public static function initSecurity(): void
		{
			global $bigtree;
			
			$ip = ip2long(Router::getRemoteIP());
			$bigtree["security-policy"] = $policy = Setting::value("bigtree-internal-security-policy");
			
			// Check banned IPs list for the user's IP
			if (!empty($policy["banned_ips"])) {
				$banned = explode("\n", $policy["banned_ips"]);
				
				foreach ($banned as $address) {
					if (ip2long(trim($address)) == $ip) {
						Router::setLayout("login");
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
					Router::setLayout("login");
					static::stop(file_get_contents(Router::getIncludePath("admin/pages/ip-restriction.php")));
				}
			}
		}
		
		/*
			Function: login
				Logs a user into BigTree

			Parameters:
				user - A BigTree\User object
				stay_logged_in - Whether to stay logged in when closing the browser
		*/
		
		public static function login(User $user, bool $stay_logged_in = false,
									 string $namespace = "bigtree_admin"): ?string
		{
			static::$Namespace = $namespace;
			
			// Generate random session and chain ids
			$chain = SQL::unique("bigtree_user_sessions", "chain", uniqid("chain-", true));
			$session = SQL::unique("bigtree_user_sessions", "id", uniqid("session-", true));
			
			// Setup CSRF token
			CSRF::generate();
			
			// Create the new session chain
			SQL::insert("bigtree_user_sessions", [
				"id" => $session,
				"table" => User::$Table,
				"chain" => $chain,
				"email" => $user->Email,
				"csrf_token" => CSRF::$Token,
				"csrf_token_field" => CSRF::$Field
			]);
			
			// Multi-domain setup needs a chain redirect
			if (is_array(Router::$Config["sites"]) && count(Router::$Config["sites"])) {
				// Create another unique cache session for logins across domains
				$cache_data = [
					"user_id" => $user->ID,
					"session" => $session,
					"chain" => $chain,
					"stay_logged_in" => $stay_logged_in,
					"login_redirect" => !empty($_SESSION["bigtree_login_redirect"]) ? $_SESSION["bigtree_login_redirect"] : false,
					"domains" => [],
					"csrf_token" => CSRF::$Token,
					"csrf_token_field" => CSRF::$Field
				];
				
				foreach (Router::$Config["sites"] as $site_key => $site_configuration) {
					$cache_data["domains"][] = $site_configuration["www_root"];
				}
				
				$cache_session_key = Cache::putUnique("org.bigtreecms.login-session", $cache_data);
				
				return $cache_session_key;
			}
			
			// We still set the email for BigTree bar usage even if they're not being "remembered"
			Cookie::create(static::$Namespace."[email]", $user->Email, "+1 month", true);
				
			if ($stay_logged_in) {
				Cookie::create(static::$Namespace."[login]", [$session, $chain], "+1 month", true);
			}
				
			// Regenerate session ID on user state change
			$old_session_id = session_id();
			session_regenerate_id();
				
			if (!empty(Router::$Config["session_handler"]) && Router::$Config["session_handler"] == "db") {
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
				
			return null;
		}
		
		/*
		 	Function: loginSessionChain
				Begins or continues the login process for a login session chain logging in a user across all domains.
			
			Parameters:
				session_key - The session key created by the login method
		*/
		
		public static function loginChainSession(string $session_key, string $namespace = "bigtree_admin"): void
		{
			static::$Namespace = $namespace;
			SessionHandler::start();

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
			
			$user = new User($cache_data["user_id"]);
			
			foreach ($cache_data["domains"] as $site_key => $www_root) {
				if ($site_key == BIGTREE_SITE_KEY) {
					// Don't pass cookies when linking directly to the admin
					$same_site = (strpos(ADMIN_ROOT, DOMAIN) === 0);
					
					// We still set the email for BigTree bar usage even if they're not being "remembered"
					Cookie::create(static::$Namespace."[email]", $user->Email, "+1 month", $same_site);
				
					if (!empty($cache_data["stay_logged_in"])) {
						Cookie::create(static::$Namespace."[login]", [$cache_data["session"], $cache_data["chain"]],
									   "+1 month", $same_site);
					}
					
					// Regenerate session ID on user state change
					$old_session_id = session_id();
					session_regenerate_id();
					
					if (!empty(Router::$Config["session_handler"]) && Router::$Config["session_handler"] == "db") {
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
					$_SESSION[static::$Namespace]["csrf_token"] = $cache_data["csrf_token"];
					$_SESSION[static::$Namespace]["csrf_token_field"] = $cache_data["csrf_token_field"];
				}
			}

			die();
		}
		
		/*
			Function: logout
				Destroys the user's session and unsets the login cookies.
		*/
		
		public static function logout(): void
		{
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
			if (!empty(Router::$Config["session_handler"]) && Router::$Config["session_handler"] == "db") {
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
		
		public function logoutAllUsers(): void
		{
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
		
		public static function stop(?string $message = null, ?string $file = null): void
		{
			global $admin, $bigtree, $cms, $db;
			
			if ($file) {
				include $file;
			} else {
				echo Text::translate($message);
			}
			
			Router::renderPage(true);

			die();
		}
		
		/*
			Function: user
				Returns a BigTree\Auth\AuthenticatedUser object.

			Parameters:
				user - Either a user ID, BigTree\User object, or null to use the currently logged in user.

			Returns:
				A BigTree\Auth\AuthenticatedUser object.
		*/
		
		public static function user($user = null): AuthenticatedUser
		{
			/** @var User $user */
			
			if (is_null($user)) {
				if (static::$ID) {
					return new AuthenticatedUser(static::$ID, static::$Name, static::$Level, static::$Permissions,
												 static::$Timezone);
				} else {
					return new AuthenticatedUser(null, "", -1, [], null);
				}
			} else {
				if (is_object($user)) {
					return new AuthenticatedUser($user->ID, $user->Name, $user->Level, $user->Permissions,
												 $user->Timezone);
				}
				
				$user = SQL::fetch("SELECT id, level, permissions FROM bigtree_users WHERE id = ?", $user);
				
				// Return a -1 level of anonymous user
				if (empty($user)) {
					return new AuthenticatedUser(null, -1, [], null);
				}
				
				$permissions = (array) @json_decode($user["permissions"], true);
					
				return new AuthenticatedUser($user["id"], $user["name"], $user["level"], $permissions, $user["timezone"]);
			}
		}
		
		/*
			Function: remove2FASecret
				Removes two factor authentication from a user.
		
			Parameters:
				user - A user ID
		*/
		
		public static function remove2FASecret(int $user): void
		{
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
		
		public static function verifyLogin2FA(string $email, string $password): ?string
		{
			global $bigtree;
			
			$ip = ip2long(Router::getRemoteIP());
			
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
			
			$phpass = new PasswordHash(Router::$Config["password_depth"], true);
			
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
