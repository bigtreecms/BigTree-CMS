<?php
	namespace BigTree\Services;

	use BigTreeCMS;
	use SQL;

	/**
	 * Security policy reads: banned/allowed IP lists, login bans, password rules.
	 * Moved from legacy admin base class (cluster A). Keeps the $bigtree["security-policy"]
	 * memoization side effect that IpPolicyTest and AuthService depend on.
	 */
	class SecurityPolicyService {

		/*
			Function: getSecurityPolicy
				Loads the security policy into $bigtree["security-policy"] (if not already loaded) and returns it.

			Returns:
				The security policy array (empty array if none is configured).
		*/
		public static function getSecurityPolicy() {
			global $bigtree;

			if (!isset($bigtree["security-policy"]) || !is_array($bigtree["security-policy"])) {
				$policy = BigTreeCMS::getSetting("bigtree-internal-security-policy");
				$bigtree["security-policy"] = is_array($policy) ? $policy : [];
			}

			return $bigtree["security-policy"];
		}

		/*
			Function: isIPBannedByPolicy
				Checks an IP against the security policy's banned IPs list.

			Parameters:
				ip - An IP address as a long integer (via ip2long)

			Returns:
				true if the IP is on the banned list
		*/
		public static function isIPBannedByPolicy($ip) {
			$policy = static::getSecurityPolicy();

			if (!empty($policy["banned_ips"])) {
				$banned = explode("\n", $policy["banned_ips"]);

				foreach ($banned as $address) {
					if (ip2long(trim($address)) == $ip) {
						return true;
					}
				}
			}

			return false;
		}

		/*
			Function: isIPAllowedByPolicy
				Checks an IP against the security policy's allowed IP ranges.

			Parameters:
				ip - An IP address as a long integer (via ip2long)

			Returns:
				true if no ranges are configured or the IP falls inside one of them
		*/
		public static function isIPAllowedByPolicy($ip) {
			$policy = static::getSecurityPolicy();

			if (empty($policy["allowed_ips"])) {
				return true;
			}

			$list = explode("\n", $policy["allowed_ips"]);

			foreach ($list as $item) {
				[$begin, $end] = explode(",", $item);
				$begin = ip2long(trim($begin));
				$end = ip2long(trim($end));

				if ($begin <= $ip && $end >= $ip) {
					return true;
				}
			}

			return false;
		}

		/*
			Function: isIPBanned
				Checks to see if the requesting IP address is banned and should not be allowed to attempt login.

			Returns:
				true if the IP is banned
		*/
		public static function isIPBanned($ip) {
			global $bigtree;

			// Check to see if this IP is already banned from logging in.
			$ban = sqlfetch(sqlquery("SELECT * FROM bigtree_login_bans WHERE expires > NOW() AND ip = '$ip'"));

			if ($ban) {
				$bigtree["ban_expiration"] = date("F j, Y @ g:ia", strtotime($ban["expires"]));
				$bigtree["ban_is_user"] = false;

				return true;
			}

			return false;
		}

		/*
			Function: isUserBanned
				Checks to see if the logging in user is banned and should not be allowed to attempt login.

			Parameters:
				user - A user ID

			Returns:
				true if the user is banned
		*/
		public static function isUserBanned($user) {
			global $bigtree;

			// See if this user is banned due to failed login attempts
			$ban = sqlfetch(sqlquery("SELECT * FROM bigtree_login_bans WHERE expires > NOW() AND `user` = '".intval($user)."'"));

			if ($ban) {
				$bigtree["ban_expiration"] = date("F j, Y @ g:ia", strtotime($ban["expires"]));
				$bigtree["ban_is_user"] = true;

				return true;
			}

			return false;
		}

		/*
			Function: validatePassword
				Validates a password against the security policy.

			Parameters:
				password - Password to validate.

			Returns:
				true if it passes all password criteria.
		*/
		public static function validatePassword($password) {
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
			if ($policy["numbers"] && !preg_match("/[0-9]/", $password)) {
				$failed = true;
			}
			// Check non-alphanumeric policy
			if ($policy["nonalphanumeric"] && ctype_alnum($password)) {
				$failed = true;
			}

			return !$failed;
		}
	}
