<?php
	namespace BigTree\Api;

	use BigTree\Api\Exceptions\AuthenticationException;

	/**
	 * Hand-rolled HS256 JWT encode/verify. RFC 7519.
	 *
	 * Strictness:
	 *  - alg is never read from the header — always enforced as HS256 here.
	 *  - signature compared with hash_equals (constant time).
	 *  - exp/iat checked with a small leeway (default 30s).
	 *  - iss/aud must match.
	 */
	class Jwt {
		const ALG = "HS256";
		const LEEWAY_SECONDS = 30;

		public static function encode(array $claims, $secret) {
			$header = ["alg" => self::ALG, "typ" => "JWT"];
			$h = self::base64url(json_encode($header));
			$p = self::base64url(json_encode($claims));
			$signing_input = $h . "." . $p;
			$sig = self::base64url(hash_hmac("sha256", $signing_input, $secret, true));

			return $signing_input . "." . $sig;
		}

		/**
		 * @param string $token
		 * @param string|array $secret Either current secret, or [current, previous] for cutover.
		 * @param string $expected_iss
		 * @param string $expected_aud
		 * @return array Verified claims.
		 * @throws AuthenticationException
		 */
		public static function decode($token, $secret, $expected_iss, $expected_aud) {
			if (!is_string($token) || substr_count($token, ".") !== 2) {
				throw new AuthenticationException("Malformed token", "invalid_token");
			}

			[$h64, $p64, $s64] = explode(".", $token, 3);
			$signing_input = $h64 . "." . $p64;
			$secrets = is_array($secret) ? $secret : [$secret];
			$secrets = array_filter($secrets, "strlen");

			if (empty($secrets)) {
				throw new AuthenticationException("Server has no JWT secret configured", "server_misconfigured", 500);
			}

			$verified = false;
			$computed = "";

			foreach ($secrets as $candidate_secret) {
				$computed = self::base64url(hash_hmac("sha256", $signing_input, $candidate_secret, true));

				if (hash_equals($computed, $s64)) {
					$verified = true;
					break;
				}
			}

			if (!$verified) {
				throw new AuthenticationException("Signature mismatch", "invalid_token");
			}

			// Decode header and payload AFTER signature verification — never trust pre-verified input.
			$header = json_decode(self::base64urlDecode($h64), true);
			$claims = json_decode(self::base64urlDecode($p64), true);

			if (!is_array($header) || !is_array($claims)) {
				throw new AuthenticationException("Malformed token payload", "invalid_token");
			}

			// The header alg is ignored for verification (we hardcode HS256 above),
			// but we still sanity-check it so misconfigured clients fail clearly.
			if (($header["alg"] ?? "") !== self::ALG) {
				throw new AuthenticationException("Unsupported algorithm", "invalid_token");
			}

			$now = time();

			if (!isset($claims["exp"]) || ($claims["exp"] + self::LEEWAY_SECONDS) < $now) {
				throw new AuthenticationException("Token expired", "token_expired");
			}

			if (isset($claims["nbf"]) && ($claims["nbf"] - self::LEEWAY_SECONDS) > $now) {
				throw new AuthenticationException("Token not yet valid", "invalid_token");
			}

			if (($claims["iss"] ?? null) !== $expected_iss) {
				throw new AuthenticationException("Wrong issuer", "invalid_token");
			}

			if (($claims["aud"] ?? null) !== $expected_aud) {
				throw new AuthenticationException("Wrong audience", "invalid_token");
			}

			if (!isset($claims["sub"])) {
				throw new AuthenticationException("Missing subject", "invalid_token");
			}

			return $claims;
		}

		public static function base64url($data) {

			return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
		}

		public static function base64urlDecode($data) {
			$pad = strlen($data) % 4;

			if ($pad) {
				$data .= str_repeat("=", 4 - $pad);
			}

			return base64_decode(strtr($data, "-_", "+/"));
		}

		/** Returns [current, previous] for the verifier, or [current] if no previous configured. */
		public static function secrets() {
			global $bigtree;
			$current = $bigtree["config"]["api"]["jwt_secret"] ?? "";

			if ($current === "") {
				throw new AuthenticationException("Server is missing config api.jwt_secret", "server_misconfigured", 500);
			}

			$previous = $bigtree["config"]["api"]["jwt_secret_previous"] ?? "";

			return $previous !== "" ? [$current, $previous] : [$current];
		}

		public static function currentSecret() {
			global $bigtree;
			$s = $bigtree["config"]["api"]["jwt_secret"] ?? "";

			if ($s === "") {
				throw new AuthenticationException("Server is missing config api.jwt_secret", "server_misconfigured", 500);
			}

			return $s;
		}

		public static function permissionsHash($permissions) {
			$normalized = is_array($permissions) ? json_encode($permissions) : (string)$permissions;

			return substr(sha1($normalized), 0, 16);
		}
	}
