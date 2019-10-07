<?php
	/*
		Class: BigTree\User
			Provides an interface for users.
			Easily extended for custom user systems by overriding the static $Table property.
	*/
	
	namespace BigTree;
	
	use Hautelook\Phpass\PasswordHash;
	
	class User extends SQLObject
	{
		
		/**
		 * @property-read string $Gravatar
		 * @property-read int $ID
		 * @property-read bool $IsBanned
		 * @property-read bool $NewHash
		 * @property-read string $OriginalPassword
		 */
		
		protected $ID;
		protected $NewHash;
		protected $OriginalLevel;
		protected $OriginalPassword;
		protected $OriginalPermissions;
		
		public $Alerts;
		public $ChangePasswordHash;
		public $Company;
		public $DailyDigest;
		public $Email;
		public $Level;
		public $Name;
		public $Password;
		public $Permissions;
		public $Timezone;
		public $TwoFactorSecret;
		public $TwoFactorLoginToken;
		
		public static $Table = "bigtree_users";
		
		/*
			Constructor:
				Builds a User object referencing an existing database entry.

			Parameters:
				user - Either an ID (to pull a record) or an array (to use the array as the record)
				on_fail - An optional callable to call on non-exist or bad data (rather than triggering an error).
		*/
		
		public function __construct($user = null, ?callable $on_fail = null)
		{
			if ($user !== null) {
				// Passing in just an ID
				if (!is_array($user)) {
					$user = SQL::fetch("SELECT * FROM ".static::$Table." WHERE id = ?", $user);
				}
				
				// Bad data set
				if (!is_array($user)) {
					if ($on_fail) {
						return $on_fail();
					} else {
						trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
					}
				} else {
					$this->ID = $user["id"];
					
					$this->Alerts = $user["alerts"] ? json_decode($user["alerts"], true) : null;
					$this->Company = $user["company"] ?: null;
					$this->ChangePasswordHash = $user["change_password_hash"] ?: null;
					$this->DailyDigest = $user["daily_digest"] ? true : false;
					$this->Email = $user["email"];
					$this->Level = $this->OriginalLevel = $user["level"] ?: 0;
					$this->Name = $user["name"] ?: null;
					$this->NewHash = !empty($user["new_hash"]);
					$this->Password = $this->OriginalPassword = $user["password"];
					$this->Permissions = $user["permissions"] ? json_decode($user["permissions"], true) : null;
					$this->Timezone = $user["timezone"];
					$this->TwoFactorSecret = $user["2fa_secret"];
					$this->TwoFactorLoginToken = $user["2fa_login_token"];
					
					// Verify a correct permissions array
					if (!is_array($this->Permissions)) {
						$this->Permissions = [];
					}
					
					if (!is_array($this->Permissions["page"])) {
						$this->Permissions["page"] = [];
					}
					
					if (!is_array($this->Permissions["module"])) {
						$this->Permissions["module"] = [];
					}
					
					if (!is_array($this->Permissions["resources"])) {
						$this->Permissions["resources"] = [];
					}
					
					if (!is_array($this->Permissions["module_gbp"])) {
						$this->Permissions["module_gbp"] = [];
					}
					
					$this->OriginalPermissions = $this->Permissions;
					
					// Verify alerts is an array as well
					if (!is_array($this->Alerts)) {
						$this->Alerts = [];
					}
				}
			}
		}
		
		/*
			Function: create
				Creates a user.

			Parameters:
				email - Email Address
				password - Password
				name - Name
				company - Company
				level - User Level (0 for regular, 1 for admin, 2 for developer)
				permission - Array of permissions data
				alerts - Array of alerts data
				daily_digest - Whether the user wishes to receive the daily digest email

			Returns:
				A User object of the newly created user or null if a user already exists with the provided email
		*/
		
		public static function create(string $email, ?string $password = null, ?string $name = null,
									  ?string $company = null, int $level = 0, array $permissions = [],
									  array $alerts = [], bool $daily_digest = false, ?string $timezone = ""): ?User
		{
			global $bigtree;
			
			// See if user exists already
			if (SQL::exists(static::$Table, ["email" => $email])) {
				return null;
			}
			
			$insert = [
				"email" => Text::htmlEncode($email),
				"level" => $level,
				"name" => Text::htmlEncode($name),
				"company" => Text::htmlEncode($company),
				"daily_digest" => $daily_digest ? "on" : "",
				"alerts" => $alerts,
				"permissions" => $permissions,
				"timezone" => $timezone
			];
			
			if (empty($bigtree["security-policy"]["password"]["invitations"])) {
				$insert["password"] = password_hash(trim($password), PASSWORD_DEFAULT);
				$insert["new_hash"] = "on";
			} else {
				$insert["change_password_hash"] = $hash = Text::getRandomString(64);
				
				while (SQL::exists("bigtree_users", ["change_password_hash" => $insert["change_password_hash"]])) {
					$insert["change_password_hash"] = Text::getRandomString(64);
				}
				
				$site_title = SQL::fetchSingle("SELECT `nav_title` FROM `bigtree_pages` WHERE id = 0");
				$login_root = (Router::$Config["force_secure_login"] ? str_replace("http://", "https://", ADMIN_ROOT) : ADMIN_ROOT)."login/";
				
				$html = file_get_contents(Router::getIncludePath("admin/email/welcome.html"));
				$html = str_ireplace("{www_root}", WWW_ROOT, $html);
				$html = str_ireplace("{admin_root}", ADMIN_ROOT, $html);
				$html = str_ireplace("{site_title}", $site_title, $html);
				$html = str_ireplace("{person}", Auth::$Name, $html);
				$html = str_ireplace("{reset_link}", $login_root."reset-password/$hash/?welcome", $html);
				
				$email_obj = new Email;
				$email_obj->To = $email;
				$email_obj->Title = "$site_title - Set Your Password";
				$email_obj->HTML = $html;
				$email_obj->ReplyTo = "no-reply@".(isset($_SERVER["HTTP_HOST"]) ? str_replace("www.", "", $_SERVER["HTTP_HOST"]) : str_replace(["http://www.", "https://www.", "http://", "https://"], "", DOMAIN));
				$email_obj->send();
			}
			
			$id = SQL::insert(static::$Table, $insert);
			AuditTrail::track(static::$Table, $id, "add", "created");
			
			return new User($id);
		}
		
		/*
			Function: delete
				Deletes the user
		*/
		
		public function delete(): ?bool
		{
			SQL::delete(static::$Table, $this->ID);
			AuditTrail::track(static::$Table, $this->ID, "delete", "deleted");
			
			// Add the user to the deleted users cache
			$deleted_users = Setting::value("bigtree-internal-security-policy");
			$deleted_users[$this->ID] = [
				"name" => $this->Name,
				"email" => $this->Email,
				"company" => $this->Company
			];
			
			Setting::updateValue("bigtree-internal-security-policy", $deleted_users);
			
			return true;
		}
		
		/*
			Function: getByEmail
				Gets a user entry for a given email address

			Parameters:
				email - Email address

			Returns:
				A User object or null if the user was not found
		*/
		
		public static function getByEmail(string $email): ?User
		{
			$user = SQL::fetch("SELECT * FROM ".static::$Table." WHERE LOWER(email) = ?", trim(strtolower($email)));
			
			if ($user) {
				return new User($user);
			}
			
			return null;
		}
		
		/*
			Function: getByChangePasswordHash
				Gets a user entry for a change password hash

			Parameters:
				hash - Change Password Hash

			Returns:
				A User object or null if the user was not found
		*/
		
		public static function getByChangePasswordHash(string $hash): ?User
		{
			$user = SQL::fetch("SELECT * FROM ".static::$Table." WHERE change_password_hash = ?", $hash);
			
			if ($user) {
				return new User($user);
			}
			
			return null;
		}
		
		/*
			Function: getGravatar
				Returns a user gravatar.
		*/
		
		public static function gravatar(string $email, int $size = 56, ?string $default = null,
										string $rating = "g"): string
		{
			if (!$default) {
				global $bigtree;
				
				if (!empty(Router::$Config["default_gravatar"])) {
					$default = Router::$Config["default_gravatar"];
				} else {
					$default = "https://www.bigtreecms.org/images/bigtree-gravatar.png";
				}
			}
			
			return "https://secure.gravatar.com/avatar/".md5(strtolower($email))."?s=$size&d=".urlencode($default)."&rating=$rating";
		}
		
		/*
			Function: getIsBanned
				Checks to see if the user is banned and should not be allowed to attempt login.
		
			Returns:
				true if the user is banned
		*/
		
		public function getIsBanned(): bool
		{
			// See if this user is banned due to failed login attempts
			$ban = SQL::fetch("SELECT * FROM bigtree_login_bans WHERE expires > NOW() AND `user` = ?", $this->ID);
			
			if ($ban) {
				Auth::$BanExpiration = date("F j, Y @ g:ia", strtotime($ban["expires"]));
				
				return true;
			}
			
			return false;
		}
		
		/*
			Function: initPasswordReset
				Creates a new password change hash and sends an email to the user.
		*/
		
		public function initPasswordReset(): void
		{
			global $bigtree;
			
			// Update the user's password reset hash code
			$hash = $this->setPasswordHash();
			
			// Get site title for email
			$site_title = SQL::fetchSingle("SELECT `nav_title` FROM `bigtree_pages` WHERE id = '0'");
			
			$login_root = (Router::$Config["force_secure_login"] ? str_replace("http://", "https://", ADMIN_ROOT) : ADMIN_ROOT)."login/";
			
			$html = file_get_contents(Router::getIncludePath("admin/email/reset-password.html"));
			$html = str_ireplace("{www_root}", WWW_ROOT, $html);
			$html = str_ireplace("{admin_root}", ADMIN_ROOT, $html);
			$html = str_ireplace("{site_title}", $site_title, $html);
			$html = str_ireplace("{reset_link}", $login_root."reset-password/$hash/", $html);
			
			$reply_to = "no-reply@".(isset($_SERVER["HTTP_HOST"]) ? str_replace("www.", "", $_SERVER["HTTP_HOST"]) : str_replace(["http://www.", "https://www.", "http://", "https://"], "", DOMAIN));
			
			$email = new Email;
			
			$email->To = $this->Email;
			$email->Subject = "Reset Your Password";
			$email->HTML = $html;
			$email->ReplyTo = $reply_to;
			
			$email->send();
		}
		
		/*
			Function: removeBans
				Removes all login bans for the user
		*/
		
		public function removeBans(): void
		{
			SQL::delete("bigtree_login_bans", ["user" => $this->ID]);
		}
		
		/*
			Function: save
				Saves the current object properties back to the database.
		*/
		
		public function save(): ?bool
		{
			if (empty($this->ID)) {
				$new = static::create(
					$this->Email,
					$this->Password,
					$this->Name,
					$this->Company,
					$this->Level,
					$this->Permissions,
					$this->Alerts,
					$this->DailyDigest
				);
				
				if ($new === false) {
					trigger_error(Text::translate("Failed to create user due to invalid email address."), E_USER_WARNING);
					
					return null;
				} else {
					$this->inherit($new);
					
					return true;
				}
			} else {
				$update_values = [
					"email" => $this->Email,
					"name" => Text::htmlEncode($this->Name),
					"company" => Text::htmlEncode($this->Company),
					"level" => intval($this->Level),
					"permissions" => (array) $this->Permissions,
					"alerts" => (array) $this->Alerts,
					"daily_digest" => $this->DailyDigest ? "on" : ""
				];
				
				if ($this->Password != $this->OriginalPassword) {
					$update_values["password"] = password_hash(trim($this->Password), PASSWORD_DEFAULT);
					$update_values["new_hash"] = "on";
					$update_values["change_password_hash"] = "";
					
					// Clean existing sessions
					SQL::delete("bigtree_sessions", ["logged_in_user" => $this->ID]);
					SQL::delete("bigtree_user_sessions", ["email" => $this->Email]);
				}
				
				if ($this->Permissions != $this->OriginalPermissions || $this->Level != $this->OriginalLevel) {
					AuditTrail::track("bigtree_users", $this->ID, "update", "permissions changed");
				}
				
				SQL::update(static::$Table, $this->ID, $update_values);
				AuditTrail::track("bigtree_users", $this->ID, "update", "updated");
				
				return true;
			}
		}
		
		/*
			Function: setPasswordHash
				Creates a change password hash for a user

			Returns:
				A change password hash.
		*/
		
		public function setPasswordHash(): string
		{
			$this->ChangePasswordHash = md5(microtime().$this->Password);
			SQL::update("bigtree_users", $this->ID, ["change_password_hash" => $this->ChangePasswordHash]);
			
			return $this->ChangePasswordHash;
		}
		
		/*
			Function: setTwoFactorToken
				Sets a temporary token for this user to continue throught a two factor authentication flow.
			
			Returns:
				Two factor token
	 	*/
		
		public function setTwoFactorToken(): string
		{
			$token = password_hash(Text::getRandomString(64).$this->Password.Text::getRandomString(64),
								   PASSWORD_DEFAULT);
			
			SQL::update("bigtree_users", $this->ID, ["2fa_login_token" => $token]);
			
			return $token;
		}
		
		/*
			Function: update
				Updates the user properties and saves the changes to the database.

			Parameters:
				email - Email Address
				password - Password
				name - Name
				company - Company
				level - User Level (0 for regular, 1 for admin, 2 for developer)
				permission - Array of permissions data
				alerts - Array of alerts data
				daily_digest - Whether the user wishes to receive the daily digest email

			Returns:
				true if successful. false if there was an email collision.
		*/
		
		public function update(string $email, ?string $password = null, ?string $name = null, ?string $company = null,
							   int $level = 0, array $permissions = [], array $alerts = [],
							   bool $daily_digest = false): bool
		{
			// See if there's an email collission
			if (SQL::fetchSingle("SELECT COUNT(*) FROM ".static::$Table." WHERE `email` = ? AND `id` != ?", $email, $this->ID)) {
				return false;
			}
			
			$this->Email = $email;
			$this->Name = $name;
			$this->Company = $company;
			$this->Level = $level;
			$this->Permissions = $permissions;
			$this->Alerts = $alerts;
			$this->DailyDigest = $daily_digest;
			
			if ($password != "") {
				$this->Password = $password;
			}
			
			$this->save();
			
			return true;
		}
		
		/*
			Function: updateProfile
				Updates the logged-in user's name, company, digest setting, and (optionally) password.

			Parameters:
				name - Name
				company - Company
				daily_digest - Whether to receive the daily digest (truthy value) or not (falsey value)
				password - Password (leave empty or false to not update)
		*/
		
		public static function updateProfile(string $name, ?string $company = null, bool $daily_digest = false,
											 string $timezone = "", ?string $password = null): bool
		{
			global $bigtree;
			
			$user = Auth::user()->ID;
			
			// Make sure a user is logged in
			if (is_null($user)) {
				trigger_error("Method updateProfile not available outside logged-in user context.");
				
				return false;
			}
			
			$update_values = [
				"name" => Text::htmlEncode($name),
				"company" => Text::htmlEncode($company),
				"daily_digest" => $daily_digest ? "on" : "",
				"timezone" => $timezone
			];
			
			if (!is_null($password) && $password !== "" && $password !== false) {
				$phpass = new PasswordHash(Router::$Config["password_depth"], true);
				$update_values["password"] = $phpass->HashPassword($password);
			}
			
			SQL::update("bigtree_users", $user, $update_values);
			
			return true;
		}
		
		/*
			Function: validatePassword
				Validates a password against the security policy.

			Parameters:
				password - Password to validate.

			Returns:
				true if it passes all password criteria.
		*/
		
		public static function validatePassword(string $password): bool
		{
			global $bigtree;
			
			$security_policy = Setting::value("bigtree-internal-security-policy");
			$policy = $security_policy["password"];
			$failed = false;
			
			if (!is_array($policy)) {
				return true;
			}
			
			// Check length policy
			if ($policy["length"] && strlen($password) < $policy["length"]) {
				$failed = true;
			}
			
			// Check case policy
			if ($policy["multicase"] && strtolower($password) === $password) {
				$failed = true;
			}
			
			// Check numeric policy
			if ($policy["numbers"] && !preg_match("/[0-9]/", $password)) {
				$failed = true;
			}
			
			// Check non-alphanumeric policy
			if (function_exists("ctype_alnum") && $policy["nonalphanumeric"] && ctype_alnum($password)) {
				$failed = true;
			}
			
			return !$failed;
		}
		
	}
