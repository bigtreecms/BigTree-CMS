<?php
	namespace BigTree\Services;

	use BigTree\Api\Jwt;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\TokenStore;
	use BigTree\Api\Exceptions\AuthenticationException;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\ConflictException;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTree\Api\Exceptions\AuthorizationException;
	use BigTree\WebAuthn;
	use BigTreeAdmin;
	use BigTreeCMS;
	use BigTree;
	use Exception;
	use GoogleAuthenticator;
	use PasswordHash;
	use SQL;

	/**
	 * Authentication service: password login, 2FA, passkey, token issuance, refresh, logout.
	 * Owns bigtree_refresh_tokens + bigtree_passkey_challenges + token_version bumps.
	 */
	class AuthService {
		const ACCESS_TTL = 900;             // 15 min
		const MFA_PARTIAL_TTL = 300;        // 5 min for the 2fa hand-off token
		const PASSKEY_CHALLENGE_TTL = 300;  // 5 min

		// — Public endpoint methods —

		public function login(Request $request) {
			global $bigtree;

			$email = strtolower(trim((string)($request->body["email"] ?? "")));
			$password = (string)($request->body["password"] ?? "");
			$remember = !empty($request->body["remember"]);

			if ($email === "" || $password === "") {
				throw new BadRequestException("Email and password required", "missing_credentials", 400);
			}

			$ip = ip2long($request->ip) ?: null;
			if (BigTreeAdmin::isIPBanned($ip)) {
				throw new AuthorizationException("IP is temporarily banned", "ip_banned", 403);
			}

			$user = SQL::fetch("SELECT * FROM bigtree_users WHERE LOWER(email) = ?", $email);
			if ($user && BigTreeAdmin::isUserBanned($user["id"])) {
				throw new AuthorizationException("User is temporarily banned", "user_banned", 403);
			}

			$ok = false;
			if ($user) {
				$ok = $this->verifyPassword($user, $password);
			}

			if (!$ok) {
				$this->recordFailedAttempt($ip, $user ? (int)$user["id"] : null);
				throw new AuthenticationException("Invalid credentials", "invalid_credentials", 401);
			}

			// 2FA gate: if enabled in security policy and the user has a secret set.
			$two_factor_required = ($bigtree["security-policy"]["two_factor"] ?? "") === "google"
				&& !empty($user["2fa_secret"]);

			if ($two_factor_required) {
				$mfa_token = $this->issueMfaPartial((int)$user["id"], $remember);
				return Response::ok(["mfa_required" => true, "mfa_token" => $mfa_token]);
			}

			return $this->issueTokens($user, $request);
		}

		public function twoFactor(Request $request) {
			$mfa_token = (string)($request->body["mfa_token"] ?? "");
			$code = (string)($request->body["code"] ?? "");

			if ($mfa_token === "" || $code === "") {
				throw new BadRequestException("mfa_token and code required", "missing_fields", 400);
			}

			$user = SQL::fetch("SELECT * FROM bigtree_users WHERE 2fa_login_token = ?", $mfa_token);
			if (!$user) {
				throw new AuthenticationException("Invalid or expired MFA token", "invalid_mfa_token", 401);
			}

			include_once BigTree::path("inc/lib/GoogleAuthenticator.php");
			if (!GoogleAuthenticator::verifyCode($user["2fa_secret"], $code)) {
				throw new AuthenticationException("Invalid 2FA code", "invalid_2fa_code", 401);
			}

			// One-shot: clear the token so the partial can't be reused.
			SQL::update("bigtree_users", $user["id"], ["2fa_login_token" => ""]);

			return $this->issueTokens($user, $request);
		}

		public function refresh(Request $request) {
			$this->enforceRefreshOrigin($request);

			$cookie_value = $_COOKIE[TokenStore::COOKIE] ?? "";
			$rotation = TokenStore::rotate($cookie_value, $request->ip, $request->user_agent);

			$user = SQL::fetch("SELECT * FROM bigtree_users WHERE id = ?", $rotation["user_id"]);
			if (!$user) {
				throw new AuthenticationException("User no longer exists", "invalid_refresh_token", 401);
			}

			$access = $this->issueAccessToken($user);

			$response = Response::ok([
				"access_token" => $access,
				"token_type" => "Bearer",
				"expires_in" => self::ACCESS_TTL,
				"user" => $this->publicUser($user),
			]);
			$response->cookie(TokenStore::COOKIE, $rotation["new_token"]["raw"], TokenStore::cookieOptions());
			return $response;
		}

		public function logout(Request $request) {
			$cookie_value = $_COOKIE[TokenStore::COOKIE] ?? "";
			if ($cookie_value !== "") {
				TokenStore::revokeByRaw($cookie_value);
			}
			$response = Response::noContent();
			$response->cookie(TokenStore::COOKIE, "", TokenStore::clearCookieOptions());
			return $response;
		}

		public function logoutAll(Request $request) {
			$user_id = $request->user->id;
			SQL::query("UPDATE bigtree_users SET token_version = token_version + 1 WHERE id = ?", $user_id);
			TokenStore::revokeAllForUser($user_id);

			$response = Response::noContent();
			$response->cookie(TokenStore::COOKIE, "", TokenStore::clearCookieOptions());
			return $response;
		}

		public function me(Request $request) {
			return Response::ok($this->publicUser([
				"id" => $request->user->id,
				"email" => $request->user->email,
				"name" => $request->user->name,
				"level" => $request->user->level,
				"timezone" => $request->user->timezone,
				"permissions" => $request->user->permissions,
			]));
		}

		// — Passkey endpoints —

		public function passkeyOptions(Request $request) {
			$parsed = parse_url(ADMIN_ROOT);
			$rp_id = $parsed["host"] ?? "";

			$options = WebAuthn::getAuthenticationOptions($rp_id, []);

			// Replace the $_SESSION-stored challenge with a DB-stored one keyed by an id we hand back.
			$challenge_id = bin2hex(random_bytes(16));
			SQL::insert("bigtree_passkey_challenges", [
				"id" => $challenge_id,
				"challenge" => $options["challenge"],
				"purpose" => "auth",
			]);

			// Strip the session side effect WebAuthn::generateChallenge() created and substitute our id.
			unset($_SESSION["bigtree_passkey_challenge"]);

			return Response::ok([
				"challenge_id" => $challenge_id,
				"options" => $options,
			]);
		}

		public function passkeyVerify(Request $request) {
			$ip = ip2long($request->ip) ?: null;

			if (BigTreeAdmin::isIPBanned($ip)) {
				throw new AuthorizationException("IP is temporarily banned", "ip_banned", 403);
			}

			$challenge_id = (string)($request->body["challenge_id"] ?? "");
			$credential_id = (string)($request->body["credential_id"] ?? "");
			$client_data_json = (string)($request->body["client_data_json"] ?? "");
			$auth_data = (string)($request->body["authenticator_data"] ?? "");
			$signature = (string)($request->body["signature"] ?? "");

			if ($challenge_id === "" || $credential_id === "" || $client_data_json === "" || $auth_data === "" || $signature === "") {
				throw new BadRequestException("Missing passkey verification fields", "missing_fields", 400);
			}

			$challenge_row = SQL::fetch("SELECT * FROM bigtree_passkey_challenges WHERE id = ? AND consumed = 0 AND purpose = 'auth' AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)",
				$challenge_id, self::PASSKEY_CHALLENGE_TTL);
			if (!$challenge_row) {
				throw new AuthenticationException("Passkey challenge invalid or expired", "invalid_challenge", 401);
			}

			$passkey = BigTreeAdmin::getPasskeyByCredentialId($credential_id);
			if (!$passkey) {
				$this->recordFailedAttempt($ip, null);
				throw new AuthenticationException("Unknown credential", "unknown_credential", 401);
			}

			$user = SQL::fetch("SELECT * FROM bigtree_users WHERE id = ?", $passkey["user"]);
			if (!$user) {
				$this->recordFailedAttempt($ip, (int)$passkey["user"]);
				throw new AuthenticationException("Credential's user no longer exists", "unknown_user", 401);
			}

			if (BigTreeAdmin::isUserBanned($user["id"])) {
				throw new AuthorizationException("User is temporarily banned", "user_banned", 403);
			}

			// Install the stored challenge into the WebAuthn library's expected location so its check passes.
			$_SESSION["bigtree_passkey_challenge"] = $challenge_row["challenge"];

			$parsed = parse_url(ADMIN_ROOT);
			$origin = ($parsed["scheme"] ?? "https") . "://" . ($parsed["host"] ?? "");
			$rp_id = $parsed["host"] ?? "";

			try {
				$new_sign_count = WebAuthn::verifyAuthentication([
					"clientDataJSON" => $client_data_json,
					"authenticatorData" => $auth_data,
					"signature" => $signature,
				], $passkey, $origin, $rp_id);
			} catch (Exception $e) {
				// CRITICAL: record the failed attempt — the legacy loginPasskey forgot to.
				$this->recordFailedAttempt($ip, (int)$user["id"]);
				BigTree::log("Passkey authentication failed for credential $credential_id: " . $e->getMessage());
				throw new AuthenticationException("Passkey verification failed", "passkey_failed", 401);
			} finally {
				unset($_SESSION["bigtree_passkey_challenge"]);
			}

			SQL::update("bigtree_passkey_challenges", $challenge_row["id"], ["consumed" => 1]);
			BigTreeAdmin::updatePasskeyUsed($passkey["id"], $new_sign_count);

			return $this->issueTokens($user, $request);
		}

		// — Password reset —

		/**
		 * POST /auth/forgot-password { email }
		 * Always returns 204 regardless of whether the email exists (don't leak which
		 * accounts are valid). On match, generates a reset hash and emails the user
		 * a link to the SPA's reset page.
		 */
		public function forgotPassword(Request $request) {
			global $bigtree;

			$email = strtolower(trim((string)($request->body["email"] ?? "")));
			if ($email === "") throw new BadRequestException("email required", "missing_email", 400);

			$user = SQL::fetch("SELECT id, email, password FROM bigtree_users WHERE LOWER(email) = ?", $email);
			if ($user) {
				// Reset hash is unguessable without knowing the existing password hash + a microsecond timestamp.
				$hash = md5(md5($user["password"]) . md5(uniqid("bigtree-hash" . microtime(true))));
				SQL::update("bigtree_users", $user["id"], ["change_password_hash" => $hash]);
				$this->sendResetEmail($user["email"], $hash);
			}

			// Constant-ish time: don't reveal whether the email was on file.
			return Response::noContent();
		}

		/**
		 * POST /auth/reset-password { token, password }
		 * Consumes the token, applies the new password, and revokes all existing
		 * sessions + refresh tokens (token_version bump).
		 */
		public function resetPassword(Request $request) {
			$token = (string)($request->body["token"] ?? "");
			$password = trim((string)($request->body["password"] ?? ""));

			if ($token === "" || $password === "") {
				throw new BadRequestException("token and password required", "missing_fields", 400);
			}
			if (!BigTreeAdmin::validatePassword($password)) {
				throw new BadRequestException("Password does not meet policy requirements", "weak_password", 400);
			}

			$user = SQL::fetch("SELECT * FROM bigtree_users WHERE change_password_hash = ?", $token);
			if (!$user) {
				throw new AuthenticationException("Invalid or expired reset token", "invalid_token", 401);
			}

			SQL::update("bigtree_users", $user["id"], [
				"password" => password_hash($password, PASSWORD_DEFAULT),
				"new_hash" => "on",
				"change_password_hash" => "",
			]);

			// Bump token_version → invalidates all JWTs.
			SQL::query("UPDATE bigtree_users SET token_version = token_version + 1 WHERE id = ?", $user["id"]);
			TokenStore::revokeAllForUser((int)$user["id"]);

			// Clear legacy sessions too so the user can re-login both surfaces cleanly.
			SQL::delete("bigtree_sessions", ["logged_in_user" => $user["id"]]);
			SQL::delete("bigtree_user_sessions", ["email" => $user["email"]]);

			// Lift any active bans so the user can immediately log in.
			SQL::query("UPDATE bigtree_login_bans SET expires = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE user = ?", $user["id"]);

			return Response::noContent();
		}

		// — Passkey registration (authenticated) —

		/**
		 * GET /auth/passkey/register/options
		 * Returns WebAuthn registration ceremony options + a server-stored challenge id.
		 */
		public function passkeyRegisterOptions(Request $request) {
			global $bigtree;
			$user_id = (int)$request->user->id;
			$user = SQL::fetch("SELECT id, email, name FROM bigtree_users WHERE id = ?", $user_id);

			$parsed = parse_url(ADMIN_ROOT);
			$rp_id = $parsed["host"] ?? "";
			$rp_name = $bigtree["config"]["domain_name"] ?? ($parsed["host"] ?? "BigTree");

			$existing_ids = array_map(function ($p) { return $p["credential_id"]; }, BigTreeAdmin::getUserPasskeys($user_id));

			$options = WebAuthn::getRegistrationOptions(
				$rp_id, $rp_name, $user_id, $user["email"], $user["name"] ?: $user["email"], $existing_ids
			);

			$challenge_id = bin2hex(random_bytes(16));
			SQL::insert("bigtree_passkey_challenges", [
				"id" => $challenge_id,
				"challenge" => $options["challenge"],
				"user_id" => $user_id,
				"purpose" => "register",
			]);

			// Don't leave the legacy session-stored copy lying around.
			unset($_SESSION["bigtree_passkey_challenge"]);

			return Response::ok([
				"challenge_id" => $challenge_id,
				"options" => $options,
			]);
		}

		/**
		 * POST /auth/passkey/register/verify { challenge_id, client_data_json, attestation_object, name? }
		 * Verifies the attestation, stores the new credential.
		 */
		public function passkeyRegisterVerify(Request $request) {
			$user_id = (int)$request->user->id;
			$challenge_id = (string)($request->body["challenge_id"] ?? "");
			$client_data_json = (string)($request->body["client_data_json"] ?? "");
			$attestation_object = (string)($request->body["attestation_object"] ?? "");
			$name = trim((string)($request->body["name"] ?? "")) ?: "Passkey";

			if ($challenge_id === "" || $client_data_json === "" || $attestation_object === "") {
				throw new BadRequestException("Missing passkey registration fields", "missing_fields", 400);
			}

			$challenge_row = SQL::fetch(
				"SELECT * FROM bigtree_passkey_challenges WHERE id = ? AND consumed = 0 AND purpose = 'register' AND user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)",
				$challenge_id, $user_id, self::PASSKEY_CHALLENGE_TTL
			);
			if (!$challenge_row) {
				throw new AuthenticationException("Registration challenge invalid or expired", "invalid_challenge", 401);
			}

			$parsed = parse_url(ADMIN_ROOT);
			$origin = ($parsed["scheme"] ?? "https") . "://" . ($parsed["host"] ?? "");
			$rp_id = $parsed["host"] ?? "";

			// Install the stored challenge where the WebAuthn library expects it.
			$_SESSION["bigtree_passkey_challenge"] = $challenge_row["challenge"];

			try {
				$credential = WebAuthn::verifyRegistration([
					"clientDataJSON" => $client_data_json,
					"attestationObject" => $attestation_object,
				], $origin, $rp_id);
			} catch (Exception $e) {
				BigTree::log("Passkey registration failed for user $user_id: " . $e->getMessage());
				throw new BadRequestException("Passkey verification failed", "passkey_invalid", 400);
			} finally {
				unset($_SESSION["bigtree_passkey_challenge"]);
			}

			SQL::update("bigtree_passkey_challenges", $challenge_row["id"], ["consumed" => 1]);

			$passkey_id = BigTreeAdmin::createPasskey(
				$user_id,
				$credential["credential_id"],
				$credential["public_key"],
				(int)($credential["sign_count"] ?? 0),
				$name,
				$credential["aaguid"] ?? "",
				is_array($credential["transports"] ?? null) ? implode(",", $credential["transports"]) : (string)($credential["transports"] ?? "")
			);

			return Response::created([
				"id" => (int)$passkey_id,
				"name" => $name,
				"credential_id" => $credential["credential_id"],
				"aaguid" => $credential["aaguid"] ?? "",
			], null);
		}

		/**
		 * GET /auth/passkeys — list current user's passkeys.
		 */
		public function listPasskeys(Request $request) {
			$rows = BigTreeAdmin::getUserPasskeys((int)$request->user->id);
			return Response::ok(array_map(function ($p) {
				return [
					"id" => (int)$p["id"],
					"name" => $p["name"],
					"aaguid" => $p["aaguid"],
					"transports" => $p["transports"],
					"created_at" => $p["created_at"],
					"last_used" => $p["last_used"],
				];
			}, $rows));
		}

		/**
		 * DELETE /auth/passkeys/{id} — remove a passkey owned by the current user.
		 */
		public function deletePasskey(Request $request) {
			$passkey_id = (int)$request->route_params["id"];
			BigTreeAdmin::deletePasskey($passkey_id, (int)$request->user->id);
			return Response::noContent();
		}

		// — Internal helpers —

		private function sendResetEmail($to, $hash) {
			global $bigtree;

			$site_title = SQL::fetchSingle("SELECT nav_title FROM bigtree_pages WHERE id = 0") ?: "BigTree";
			$login_root = ($bigtree["config"]["force_secure_login"] ?? false)
				? str_replace("http://", "https://", ADMIN_ROOT) . "login/"
				: ADMIN_ROOT . "login/";

			// SPA reset URL takes precedence; falls back to legacy admin reset page.
			$reset_url = ($bigtree["config"]["api"]["spa_reset_url"] ?? "")
				?: ($login_root . "reset-password/$hash/");
			$reset_url = str_replace("{token}", $hash, $reset_url);

			$tmpl = BigTree::path("admin/email/reset-password.html");
			$html = file_exists($tmpl) ? file_get_contents($tmpl) : "<p>Reset your password: <a href='{reset_link}'>{reset_link}</a></p>";
			$html = str_ireplace([
				"{www_root}", "{admin_root}", "{site_title}", "{reset_link}",
			], [
				WWW_ROOT, ADMIN_ROOT, $site_title, $reset_url,
			], $html);

			try {
				$es = new \BigTreeEmailService();
				if (!empty($es->Settings["bigtree_from"])) {
					$host = $_SERVER["HTTP_HOST"] ?? str_replace(["http://www.", "https://www.", "http://", "https://"], "", DOMAIN);
					$reply_to = "no-reply@" . str_replace("www.", "", $host);
					$es->sendEmail("Reset Your Password", $html, $to, $es->Settings["bigtree_from"], "BigTree CMS", $reply_to);
					return;
				}
			} catch (\Throwable $e) {
				// fall through to BigTree::sendEmail
			}
			BigTree::sendEmail($to, "Reset Your Password", $html);
		}

		private function verifyPassword(array $user, $password) {
			if (!empty($user["new_hash"])) {
				$ok = password_verify($password, $user["password"]);
				if ($ok && password_needs_rehash($user["password"], PASSWORD_DEFAULT)) {
					SQL::update("bigtree_users", $user["id"], ["password" => password_hash($password, PASSWORD_DEFAULT)]);
				}
				return $ok;
			}
			global $bigtree;
			include_once BigTree::path("inc/lib/PasswordHash.php");
			$phpass = new PasswordHash($bigtree["config"]["password_depth"] ?? 8, true);
			$ok = $phpass->CheckPassword($password, $user["password"]);
			if ($ok) {
				SQL::update("bigtree_users", $user["id"], [
					"password" => password_hash($password, PASSWORD_DEFAULT),
					"new_hash" => "on",
				]);
			}
			return $ok;
		}

		private function recordFailedAttempt($ip, $user_id) {
			global $bigtree;
			$ip = (int)$ip;
			$user_id_sql = $user_id ? (int)$user_id : "NULL";

			SQL::query("INSERT INTO bigtree_login_attempts (ip, user) VALUES (?, " . $user_id_sql . ")", $ip);

			$policy = $bigtree["security-policy"] ?? [];

			// User-fail policy
			if ($user_id && !empty($policy["user_fails"]) && count(array_filter((array)$policy["user_fails"])) === 3) {
				$p = $policy["user_fails"];
				$count = (int)SQL::fetchSingle(
					"SELECT COUNT(*) FROM bigtree_login_attempts WHERE user = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL ? MINUTE)",
					(int)$user_id, (int)$p["time"]
				);
				if ($count >= (int)$p["count"]) {
					$existing = SQL::fetch("SELECT * FROM bigtree_login_bans WHERE user = ? AND expires >= NOW()", (int)$user_id);
					if ($existing) {
						SQL::query("UPDATE bigtree_login_bans SET expires = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?", (int)$p["ban"], $existing["id"]);
					} else {
						SQL::query("INSERT INTO bigtree_login_bans (ip, user, expires) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))", $ip, (int)$user_id, (int)$p["ban"]);
					}
				}
			}

			// IP-fail policy
			if (!empty($policy["ip_fails"]) && count(array_filter((array)$policy["ip_fails"])) === 3) {
				$p = $policy["ip_fails"];
				$count = (int)SQL::fetchSingle(
					"SELECT COUNT(*) FROM bigtree_login_attempts WHERE ip = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL ? MINUTE)",
					$ip, (int)$p["time"]
				);
				if ($count >= (int)$p["count"]) {
					$existing = SQL::fetch("SELECT * FROM bigtree_login_bans WHERE ip = ? AND expires >= NOW()", $ip);
					if ($existing) {
						SQL::query("UPDATE bigtree_login_bans SET expires = DATE_ADD(NOW(), INTERVAL ? HOUR) WHERE id = ?", (int)$p["ban"], $existing["id"]);
					} else {
						SQL::query("INSERT INTO bigtree_login_bans (ip, expires) VALUES (?, DATE_ADD(NOW(), INTERVAL ? HOUR))", $ip, (int)$p["ban"]);
					}
				}
			}
		}

		private function issueMfaPartial($user_id, $remember) {
			$token = bin2hex(random_bytes(16));
			SQL::update("bigtree_users", $user_id, ["2fa_login_token" => $token]);
			return $token;
		}

		private function issueTokens(array $user, Request $request) {
			$access = $this->issueAccessToken($user);
			$refresh = TokenStore::issueFamily((int)$user["id"], $request->ip, $request->user_agent);

			$response = Response::ok([
				"access_token" => $access,
				"token_type" => "Bearer",
				"expires_in" => self::ACCESS_TTL,
				"user" => $this->publicUser($user),
			]);
			$response->cookie(TokenStore::COOKIE, $refresh["raw"], TokenStore::cookieOptions());
			return $response;
		}

		private function issueAccessToken(array $user) {
			$permissions = json_decode($user["permissions"] ?? "[]", true) ?: [];
			$now = time();
			$claims = [
				"iss" => "bigtree",
				"aud" => "bigtree-admin",
				"sub" => (int)$user["id"],
				"iat" => $now,
				"exp" => $now + self::ACCESS_TTL,
				"jti" => bin2hex(random_bytes(16)),
				"lvl" => (int)$user["level"],
				"tv" => (int)($user["token_version"] ?? 1),
				"phash" => Jwt::permissionsHash($permissions),
			];
			return Jwt::encode($claims, Jwt::currentSecret());
		}

		private function publicUser($user) {
			if (is_array($user)) {
				return [
					"id" => (int)$user["id"],
					"email" => $user["email"],
					"name" => $user["name"],
					"level" => (int)$user["level"],
					"timezone" => $user["timezone"] ?? "",
				];
			}
			return [
				"id" => (int)$user["id"],
				"email" => $user["email"],
				"name" => $user["name"],
				"level" => (int)$user["level"],
				"timezone" => $user["timezone"] ?? "",
			];
		}

		private function enforceRefreshOrigin(Request $request) {
			global $bigtree;
			$origin = $request->header("origin");
			if (!$origin) return; // server-to-server is permitted (curl); browser refreshes always send Origin.

			$allowed = [];
			$allowed[] = rtrim($bigtree["config"]["www_root"] ?? "", "/");
			foreach (($bigtree["config"]["sites"] ?? []) as $site) {
				if (!empty($site["www_root"])) $allowed[] = rtrim($site["www_root"], "/");
			}
			foreach (($bigtree["config"]["api"]["cors_origins"] ?? []) as $o) $allowed[] = rtrim($o, "/");
			$allowed = array_filter($allowed);

			if (!in_array($origin, $allowed, true)) {
				throw new AuthorizationException("Refresh origin not allowed", "origin_not_allowed", 403);
			}
		}
	}
