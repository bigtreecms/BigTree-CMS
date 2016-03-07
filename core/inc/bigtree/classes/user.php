<?php
	/*
		Class: BigTree\User
			Provides an interface for users.
			Easily extended for custom user systems by overriding the static $Table property.
	*/

	namespace BigTree;
	
	use BigTreeCMS;

	class User {

		static $Table = "bigtree_users";

		/*
			Function: all
				Returns a list of all users

			Parameters:
				sort - Sort order (defaults to "name ASC")

			Returns:
				An array of entries from bigtree_users.
				The keys of the array are the ids of the user.
		*/

		static function all($sort = "name ASC") {
			$users = BigTreeCMS::$DB->fetchAll("SELECT * FROM ".static::$Table." ORDER BY $sort");
			$user_array = array();

			// Get the keys all nice
			foreach ($users as $user) {
				$user_array[$user["id"]] = $user;
			}

			return $user_array;
		}

		/*
			Function: create
				Creates a user.
				Supports pre-4.3 syntax by passing an array as the first parameter.

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
				id of the newly created user or false if a user already exists with the provided email
		*/

		function create($email,$password = "",$name = "",$company = "",$level = 0,$permissions = array(),$alerts = array(),$daily_digest = "") {
			global $bigtree;

			// See if user exists already
			if (BigTree::$DB->exists(static::$Table,array("email" => $email))) {
				return false;
			}

			// Hash the password.
			$phpass = new PasswordHash($bigtree["config"]["password_depth"], TRUE);
			$password = $phpass->HashPassword(trim($password));

			// Create the user
			$id = BigTreeCMS::$DB->insert(static::$Table,array(
				"email" => $email,
				"password" => $password,
				"name" => BigTree::safeEncode($name),
				"company" => BigTree::safeEncode($company),
				"level" => intval($level),
				"permissions" => $permissions,
				"alerts" => $alerts,
				"daily_digest" => ($daily_digest ? "on" : "")
			));

			BigTree\AuditTrail::track(static::$Table,$id,"created");

			return $id;
		}

		/*
			Function: delete
				Deletes a user

			Parameters:
				id - User ID

			Returns:
				true if successful. false if the logged in user does not have permission to delete the user.
		*/

		function delete($id) {
			BigTreeCMS::$DB->delete(static::$Table,$id);
			BigTree\AuditTrail::track(static::$Table,$id,"deleted");

			return true;
		}

		/*
			Function: get
				Gets a user by ID with permissions and alerts decoded.

			Parameters:
				id - User ID

			Returns:
				A user array or false if the user was not found
		*/

		static function get($id) {
			$user = BigTreeCMS::$DB->fetch("SELECT * FROM ".static::$Table." WHERE id = ?", $id);
			if (!$user) {
				return false;
			}

			$user["permissions"] = isset($user["permissions"]) ? @json_decode($user["permissions"],true) : null;
			$user["alerts"] = isset($user["alerts"]) ? @json_decode($user["alerts"],true) : null;
			
			return $user;
		}

		/*
			Function: getByEmail
				Gets a user entry for a given email address

			Parameters:
				email - Email address

			Returns:
				A user array or false if the user was not found
		*/

		static function getByEmail($email) {
			return BigTreeCMS::$DB->fetch("SELECT * FROM ".static::$Table." WHERE LOWER(email) = ?", trim(strtolower($email)));
		}

		/*
			Function: getByHash
				Gets a user entry for a change password hash

			Parameters:
				hash - Change Password Hash

			Returns:
				A user array or false if the user was not found
		*/

		static function getUserByHash($hash) {
			return BigTreeCMS::$DB->fetch("SELECT * FROM ".static::$Table." WHERE change_password_hash = ?", $hash);
		}

		/*
			Function: setPasswordHash
				Creates a change password hash for a user

			Parameters:
				user - A user entry.

			Returns:
				A change password hash.
		*/

		static function setPasswordHash($user) {
			BigTreeCMS::$DB->update("bigtree_users",$user["id"],array("change_password_hash" => md5(microtime().$user["password"])));
		}

		/*
			Function: update
				Updates a user.

			Parameters:
				id - The user's ID
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

		function update($id,$email,$password = "",$name = "",$company = "",$level = 0,$permissions = array(),$alerts = array(),$daily_digest = "") {
			global $bigtree;

			// See if there's an email collission
			if (BigTreeCMS::$DB->fetchSingle("SELECT COUNT(*) FROM ".static::$Table." WHERE `email` = ? AND `id` != ?", $email, $id)) {
				return false;
			}

			$update_values = array(
				"email" => $email,
				"name" => BigTree::safeEncode($name),
				"company" => BigTree::safeEncode($company),
				"level" => intval($level),
				"permissions" => $permissions,
				"alerts" => $alerts,
				"daily_digest" => $daily_digest ? "on" : ""
			);

			if ($password) {
				$phpass = new PasswordHash($bigtree["config"]["password_depth"], TRUE);
				$update_values["password"] = $phpass->HashPassword(trim($password));
			}

			BigTreeCMS::$DB->update(static::$Table,$id,$update_values);
			BigTree\AuditTrail::track("bigtree_users",$id,"updated");

			return true;
		}

		/*
			Function: updatePassword
				Updates a user's password.

			Parameters:
				id - User ID
				password - New password
		*/

		static function updatePassword($id,$password) {
			global $bigtree;

			$phpass = new PasswordHash($bigtree["config"]["password_depth"], TRUE);
			static::$DB->update(static::$Table,$id,array("password" => $phpass->HashPassword(trim($password))));
		}

		/*
			Function: validatePassword
				Validates a password against the security policy.

			Parameters:
				password - Password to validate.

			Returns:
				true if it passes all password criteria.
		*/

		static function validatePassword($password) {
			global $bigtree;

			$policy = $bigtree["security-policy"]["password"];
			$failed = false;

			// Check length policy
			if ($policy["length"] && strlen($password) < $policy["length"]) {
				$failed = true;
			}
			// Check case policy
			if ($policy["multicase"] && strtolower($password) === $password) {
				$failed = true;
			}
			// Check numeric policy
			if ($policy["numbers"] && !preg_match("/[0-9]/",$password)) {
				$failed = true;
			}
			// Check non-alphanumeric policy
			if ($policy["nonalphanumeric"] && ctype_alnum($password)) {
				$failed = true;
			}

			return !$failed;
		}

	}
