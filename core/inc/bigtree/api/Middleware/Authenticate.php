<?php
	namespace BigTree\Api\Middleware;

	use BigTree\Api\Hooks;
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
	 * Auth is required for every route EXCEPT those that declare permission => 'public'.
	 * (We previously also looked at an `auth` route flag, but that flag is unused in our
	 * route manifests, and the original guard had a PHP precedence bug that caused this
	 * middleware to skip auth on every route. Permission middleware then failed because
	 * no user was loaded.)
	 */
	class Authenticate {
		const ISS = "bigtree";
		const AUD = "bigtree-admin";

		public function handle(Request $request, callable $next) {
			$route = $request->route;

			// Public routes (login, refresh, forgot/reset password, signed download tokens)
			// run without a Bearer token. Everything else must carry one.
			if (($route["permission"] ?? null) === "public") {
				return $next($request);
			}

			$token = $request->bearer();

			if (!$token) {
				throw new AuthenticationException("Missing Bearer token", "missing_token");
			}

			$claims = Jwt::decode($token, Jwt::secrets(), self::ISS, self::AUD);
			$request->token_claims = $claims;

			$user_id = (int)$claims["sub"];
			$row = SQL::fetch(
				"SELECT id, email, name, level, permissions, timezone, token_version FROM bigtree_users WHERE id = ?",
				$user_id
			);

			if (!$row) {
				throw new AuthenticationException("User no longer exists", "invalid_token");
			}

			// Stateless revocation check.
			if ((int)($claims["tv"] ?? -1) !== (int)$row["token_version"]) {
				throw new AuthenticationException("Token version mismatch", "token_revoked");
			}

			// Defense-in-depth: permissions hash must match the live row.
			$permissions = json_decode($row["permissions"] ?: "[]", true) ?: [];
			$live_phash = Jwt::permissionsHash($permissions);

			if (($claims["phash"] ?? "") !== $live_phash) {
				throw new AuthenticationException("Permission state changed; please re-login", "phash_mismatch");
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

			// Ambient actor attribution: every api.* hook fired downstream gets
			// user_id in its context without each call site passing it.
			Hooks::setDefaultContext(["user_id" => $user->id]);

			return $next($request);
		}
	}
