<?php
	namespace BigTree\Api;

	use BigTree\Api\Exceptions\AuthenticationException;
	use SQL;

	/**
	 * Refresh-token storage with rotation and theft detection.
	 *
	 * Cookie: bigtree_refresh = base64url(32 random bytes). Stored hashed (sha256).
	 * Each row carries a family_id; if a token presented for refresh has rotated_to != NULL,
	 * the entire family is revoked and the user is forced to re-login.
	 */
	class TokenStore {
		const COOKIE = "bigtree_refresh";
		const TTL_SECONDS = 2592000;          // 30 days
		const ACCESS_TTL_SECONDS = 900;       // 15 min

		public static function issueFamily($user_id, $ip, $user_agent) {
			$family_id = bin2hex(random_bytes(16));

			return self::issue($user_id, $family_id, $ip, $user_agent);
		}

		public static function issue($user_id, $family_id, $ip, $user_agent) {
			$raw = self::randomToken();
			$hash = hash("sha256", $raw);
			$expires_at = date("Y-m-d H:i:s", time() + self::TTL_SECONDS);

			$id = SQL::insert("bigtree_refresh_tokens", [
				"user_id" => (int)$user_id,
				"token_hash" => $hash,
				"family_id" => $family_id,
				"expires_at" => $expires_at,
				"user_agent" => substr((string)$user_agent, 0, 255),
				"ip" => substr((string)$ip, 0, 45),
			]);

			return ["id" => (int)$id, "raw" => $raw, "family_id" => $family_id, "expires_at" => $expires_at];
		}

		/**
		 * Validate a presented refresh token and rotate it.
		 * Returns ["user_id" => N, "new_token" => [...]] on success.
		 * Throws AuthenticationException on failure (and revokes family on reuse).
		 */
		public static function rotate($raw, $ip, $user_agent) {
			if (!is_string($raw) || $raw === "") {
				throw new AuthenticationException("Missing refresh token", "no_refresh_token", 401);
			}

			$hash = hash("sha256", $raw);
			$row = SQL::fetch("SELECT * FROM bigtree_refresh_tokens WHERE token_hash = ?", $hash);

			if (!$row) {
				throw new AuthenticationException("Refresh token invalid", "invalid_refresh_token", 401);
			}

			if ((int)$row["revoked"] === 1) {
				throw new AuthenticationException("Refresh token revoked", "revoked_refresh_token", 401);
			}

			if (!empty($row["rotated_to"])) {
				// Reuse detected — revoke entire family and force re-login.
				self::revokeFamily($row["family_id"]);
				throw new AuthenticationException("Refresh token reuse detected; family revoked", "token_reuse", 401);
			}

			if (strtotime($row["expires_at"]) < time()) {
				throw new AuthenticationException("Refresh token expired", "refresh_token_expired", 401);
			}

			$new = self::issue($row["user_id"], $row["family_id"], $ip, $user_agent);
			SQL::update("bigtree_refresh_tokens", $row["id"], ["rotated_to" => $new["id"]]);

			return ["user_id" => (int)$row["user_id"], "new_token" => $new];
		}

		public static function revokeByRaw($raw) {
			if (!is_string($raw) || $raw === "") {
				return false;
			}
			$hash = hash("sha256", $raw);
			SQL::query("UPDATE bigtree_refresh_tokens SET revoked = 1 WHERE token_hash = ?", $hash);

			return true;
		}

		public static function revokeFamily($family_id) {
			SQL::query("UPDATE bigtree_refresh_tokens SET revoked = 1 WHERE family_id = ?", $family_id);
		}

		public static function revokeAllForUser($user_id) {
			SQL::query("UPDATE bigtree_refresh_tokens SET revoked = 1 WHERE user_id = ?", (int)$user_id);
		}

		public static function gcExpired() {
			SQL::query("DELETE FROM bigtree_refresh_tokens WHERE expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
		}

		public static function cookieOptions() {

			return [
				"expires" => time() + self::TTL_SECONDS,
				"path" => rtrim(ADMIN_ROOT, "/") . "/api/v1/auth",
				"secure" => true,
				"httponly" => true,
				"samesite" => "Strict",
			];
		}

		public static function clearCookieOptions() {

			return [
				"expires" => time() - 3600,
				"path" => rtrim(ADMIN_ROOT, "/") . "/api/v1/auth",
				"secure" => true,
				"httponly" => true,
				"samesite" => "Strict",
			];
		}

		private static function randomToken() {

			return Jwt::base64url(random_bytes(32));
		}
	}
