<?php
	namespace BigTree\Api\Middleware;

	use BigTree\Api\Jwt;
	use BigTree\Api\Request;
	use BigTree\Api\Exceptions\AuthenticationException;
	use SQL;
	use stdClass;

	/**
	 * Verifies the JWT access token and loads the user row.
	 *
	 * Performs ONE query: SELECT id, email, name, level, permissions, timezone, token_version FROM bigtree_users WHERE id = ?
	 * Attaches the resulting user object to $request->user.
	 *
	 * Skipped when route declares permission => 'public' OR auth => false.
	 */
	class Authenticate {
		const ISS = "bigtree";
		const AUD = "bigtree-admin";

		public function handle(Request $request, callable $next) {
			$route = $request->route;
			$requires_auth = !($route["auth"] ?? null) === false && ($route["permission"] ?? null) !== "public";

			if (!$requires_auth) {
				return $next($request);
			}

			$token = $request->bearer();
			if (!$token) {
				throw new AuthenticationException("Missing Bearer token", "missing_token", 401);
			}

			$claims = Jwt::decode($token, Jwt::secrets(), self::ISS, self::AUD);
			$request->token_claims = $claims;

			$user_id = (int)$claims["sub"];
			$row = SQL::fetch(
				"SELECT id, email, name, level, permissions, timezone, token_version FROM bigtree_users WHERE id = ?",
				$user_id
			);

			if (!$row) {
				throw new AuthenticationException("User no longer exists", "invalid_token", 401);
			}

			// Stateless revocation check.
			if ((int)($claims["tv"] ?? -1) !== (int)$row["token_version"]) {
				throw new AuthenticationException("Token version mismatch", "token_revoked", 401);
			}

			// Defense-in-depth: permissions hash must match the live row.
			$permissions = json_decode($row["permissions"] ?: "[]", true) ?: [];
			$live_phash = Jwt::permissionsHash($permissions);
			if (($claims["phash"] ?? "") !== $live_phash) {
				throw new AuthenticationException("Permission state changed; please re-login", "phash_mismatch", 401);
			}

			$user = new stdClass();
			$user->id = (int)$row["id"];
			$user->email = $row["email"];
			$user->name = $row["name"];
			$user->level = (int)$row["level"];
			$user->permissions = $permissions;
			$user->timezone = $row["timezone"];
			$user->token_version = (int)$row["token_version"];

			$request->user = $user;

			return $next($request);
		}
	}
