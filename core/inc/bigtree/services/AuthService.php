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

			// 2FA gate: any user who has enrolled a TOTP secret (self-service or
			// otherwise) must complete the second factor. The secret itself is the
			// per-user opt-in — there is no separate global toggle.
			$two_factor_required = !empty($user["2fa_secret"]);

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

		/**
		 * GET /auth/2fa/setup
		 * Begins TOTP enrollment for the current user: mints a fresh secret and
		 * returns it alongside a QR image (data URI) and the raw otpauth:// URI
		 * for manual key entry. The secret is NOT persisted here — the client
		 * holds it through the ceremony and posts it back to /auth/2fa/enable
		 * once the user has proven they can generate a valid code. Mirrors the
		 * legacy login/2fa/setup hidden-field handoff.
		 */
		public function twoFactorSetup(Request $request) {
			include_once BigTree::path("inc/lib/GoogleAuthenticator.php");

			$site_title = SQL::fetchSingle("SELECT nav_title FROM bigtree_pages WHERE id = 0") ?: "BigTree";
			$label = $site_title . " (" . $request->user->email . ")";
			$secret = GoogleAuthenticator::generateSecret();
			$qr = GoogleAuthenticator::getQRCode($label, $secret);
			$otpauth = "otpauth://totp/" . rawurlencode($label) . "?secret=" . $secret . "&issuer=BigTree";

			return Response::ok([
				"secret" => $secret,
				"qr_image" => $qr,
				"otpauth_uri" => $otpauth,
			]);
		}

		/**
		 * POST /auth/2fa/enable { secret, code }
		 * Completes enrollment: verifies the user-entered code against the
		 * pending secret from /auth/2fa/setup, then stores it on the account.
		 */
		public function twoFactorEnable(Request $request) {
			include_once BigTree::path("inc/lib/GoogleAuthenticator.php");

			$secret = trim((string)($request->body["secret"] ?? ""));
			$code = trim((string)($request->body["code"] ?? ""));

			if ($secret === "" || $code === "") {
				throw new BadRequestException("secret and code required", "missing_fields", 400);
			}

			if (!GoogleAuthenticator::verifyCode($secret, $code)) {
				throw new BadRequestException("That code is incorrect or expired", "invalid_2fa_code", 400);
			}

			$user_id = (int)$request->user->id;
			SQL::update("bigtree_users", $user_id, ["2fa_secret" => $secret]);

			return Response::ok(["id" => $user_id, "two_factor_enabled" => true]);
		}

		/**
		 * POST /auth/2fa/disable { code }
		 * Turns off TOTP for the current user. Requires a valid current code so
		 * a hijacked session can't silently strip the second factor; users who
		 * have lost their authenticator go through an admin remove-2FA instead.
		 */
		public function twoFactorDisable(Request $request) {
			include_once BigTree::path("inc/lib/GoogleAuthenticator.php");

			$user_id = (int)$request->user->id;
			$user = SQL::fetch("SELECT 2fa_secret FROM bigtree_users WHERE id = ?", $user_id);

			if (empty($user["2fa_secret"])) {
				return Response::ok(["id" => $user_id, "two_factor_enabled" => false]);
			}

			$code = trim((string)($request->body["code"] ?? ""));

			if ($code === "" || !GoogleAuthenticator::verifyCode($user["2fa_secret"], $code)) {
				throw new BadRequestException("That code is incorrect or expired", "invalid_2fa_code", 400);
			}

			SQL::update("bigtree_users", $user_id, ["2fa_secret" => ""]);

			return Response::ok(["id" => $user_id, "two_factor_enabled" => false]);
		}

		public function refresh(Request $request) {
			// Refresh token now travels in the request body (was previously a
			// cookie). The HttpOnly-cookie pattern had marginal XSS-resistance
			// benefit on an admin tool (the access token in memory is just as
			// dangerous if exfiltrated) and added real deployment friction —
			// path matching across install prefixes, dev proxy gymnastics, etc.
			// Bearer-in-Authorization + body-refresh-token is the standard SPA
			// pattern; we lose CSRF concerns entirely along with the cookie.
			$raw = (string)($request->body["refresh_token"] ?? "");

			if ($raw === "") {
				throw new AuthenticationException("Missing refresh_token", "no_refresh_token", 401);
			}

			$rotation = TokenStore::rotate($raw, $request->ip, $request->user_agent);
			$user = SQL::fetch("SELECT * FROM bigtree_users WHERE id = ?", $rotation["user_id"]);

			if (!$user) {
				throw new AuthenticationException("User no longer exists", "invalid_refresh_token", 401);
			}

			$access = $this->issueAccessToken($user);

			return Response::ok([
				"access_token" => $access,
				"refresh_token" => $rotation["new_token"]["raw"],
				"token_type" => "Bearer",
				"expires_in" => self::ACCESS_TTL,
				"user" => $this->publicUser($user),
			]);
		}

		public function logout(Request $request) {
			$raw = (string)($request->body["refresh_token"] ?? "");

			if ($raw !== "") {
				TokenStore::revokeByRaw($raw);
			}

			return Response::noContent();
		}

		public function logoutAll(Request $request) {
			$user_id = $request->user->id;
			SQL::query("UPDATE bigtree_users SET token_version = token_version + 1 WHERE id = ?", $user_id);
			TokenStore::revokeAllForUser($user_id);

			return Response::noContent();
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

		/**
		 * POST /auth/emulate { user_id }
		 * Developer-only. Mints a fresh token set for the target user and returns
		 * it alongside the acting developer's identity. The SPA stashes the
		 * developer's own tokens client-side so it can restore them on "stop
		 * emulating" — we deliberately do NOT touch the developer's refresh family
		 * here. The emulated access token carries only the target's level, so this
		 * is not a privilege-escalation path: a developer emulating a normal user
		 * temporarily drops to that user's permissions.
		 */
		public function emulate(Request $request) {
			$target_id = (int)($request->body["user_id"] ?? 0);
			$actor = $request->user;

			if (!$target_id) {
				throw new BadRequestException("user_id required", "missing_user_id", 400);
			}

			if ($target_id === (int)$actor->id) {
				throw new BadRequestException("You cannot emulate yourself", "self_emulation", 400);
			}

			$target = SQL::fetch("SELECT * FROM bigtree_users WHERE id = ?", $target_id);

			if (!$target) {
				throw new NotFoundException("User not found", "user_not_found", 404);
			}

			$access = $this->issueAccessToken($target);
			$refresh = TokenStore::issueFamily((int)$target["id"], $request->ip, $request->user_agent);

			return Response::ok([
				"access_token" => $access,
				"refresh_token" => $refresh["raw"],
				"token_type" => "Bearer",
				"expires_in" => self::ACCESS_TTL,
				"user" => $this->publicUser($target),
				"emulated_by" => [
					"id" => (int)$actor->id,
					"name" => $actor->name,
					"email" => $actor->email,
				],
			]);
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

			// INTERVAL needs an integer literal; ? would inject a quoted string.
			$ttl = (int)self::PASSKEY_CHALLENGE_TTL;
			$challenge_row = SQL::fetch(
				"SELECT * FROM bigtree_passkey_challenges WHERE id = ? AND consumed = 0 AND purpose = 'auth' AND created_at >= DATE_SUB(NOW(), INTERVAL $ttl SECOND)",
				$challenge_id
			);

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

			if ($email === "") {
				throw new BadRequestException("email required", "missing_email", 400);
			}

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

			$ttl = (int)self::PASSKEY_CHALLENGE_TTL;
			$challenge_row = SQL::fetch(
				"SELECT * FROM bigtree_passkey_challenges WHERE id = ? AND consumed = 0 AND purpose = 'register' AND user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL $ttl SECOND)",
				$challenge_id, $user_id
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

			// INTERVAL N MINUTE/HOUR can't take ? placeholders — they'd get quoted.
			// Int-casting the policy values upstream makes string interpolation safe here.

			// User-fail policy
			if ($user_id && !empty($policy["user_fails"]) && count(array_filter((array)$policy["user_fails"])) === 3) {
				$p = $policy["user_fails"];
				$window = (int)$p["time"];
				$ban_minutes = (int)$p["ban"];
				$count = (int)SQL::fetchSingle(
					"SELECT COUNT(*) FROM bigtree_login_attempts WHERE user = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL $window MINUTE)",
					(int)$user_id
				);

				if ($count >= (int)$p["count"]) {
					$existing = SQL::fetch("SELECT * FROM bigtree_login_bans WHERE user = ? AND expires >= NOW()", (int)$user_id);

					if ($existing) {
						SQL::query("UPDATE bigtree_login_bans SET expires = DATE_ADD(NOW(), INTERVAL $ban_minutes MINUTE) WHERE id = ?", $existing["id"]);
					} else {
						SQL::query("INSERT INTO bigtree_login_bans (ip, user, expires) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL $ban_minutes MINUTE))", $ip, (int)$user_id);
					}
				}
			}

			// IP-fail policy
			if (!empty($policy["ip_fails"]) && count(array_filter((array)$policy["ip_fails"])) === 3) {
				$p = $policy["ip_fails"];
				$window = (int)$p["time"];
				$ban_hours = (int)$p["ban"];
				$count = (int)SQL::fetchSingle(
					"SELECT COUNT(*) FROM bigtree_login_attempts WHERE ip = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL $window MINUTE)",
					$ip
				);

				if ($count >= (int)$p["count"]) {
					$existing = SQL::fetch("SELECT * FROM bigtree_login_bans WHERE ip = ? AND expires >= NOW()", $ip);

					if ($existing) {
						SQL::query("UPDATE bigtree_login_bans SET expires = DATE_ADD(NOW(), INTERVAL $ban_hours HOUR) WHERE id = ?", $existing["id"]);
					} else {
						SQL::query("INSERT INTO bigtree_login_bans (ip, expires) VALUES (?, DATE_ADD(NOW(), INTERVAL $ban_hours HOUR))", $ip);
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

			// Both tokens go in the body. The SPA persists them to localStorage.
			// No cookie is set — there's no value in HttpOnly for the refresh
			// token on an admin tool where XSS would already own the in-memory
			// access token. Storing tokens client-side keeps deployment topology
			// simple (no cookie path/domain dance) and is the de facto SPA pattern.
			return Response::ok([
				"access_token" => $access,
				"refresh_token" => $refresh["raw"],
				"token_type" => "Bearer",
				"expires_in" => self::ACCESS_TTL,
				"user" => $this->publicUser($user),
			]);
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

		// enforceRefreshOrigin was removed: with localStorage-based refresh tokens
		// there's no CSRF surface to defend. CSRF only applies to ambient
		// credentials (cookies, basic-auth) the browser attaches automatically.
		// A refresh token in a JSON body cannot be sent without explicit JS, and
		// our CORS middleware already gates which origins can do that.
	}
