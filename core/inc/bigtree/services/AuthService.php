<?php
	namespace BigTree\Services;

	use BigTree\Api\Entity;
	use BigTree\Api\Json;
	use BigTree\Api\Jwt;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\TokenStore;
	use BigTree\Api\Exceptions\AuthenticationException;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\ConflictException;
	use BigTree\Api\Exceptions\AuthorizationException;
	use BigTree\WebAuthn;
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
		const RESET_TOKEN_TTL = 3600;       // 1 hour for password-reset links

		// — Public endpoint methods —

		/**
		 * GET /auth/login-policy
		 * The slice of the security policy the login screen needs before
		 * authentication. Deliberately tiny — nothing here may leak information
		 * beyond what the login UI itself reveals.
		 */
		public function loginPolicy(Request $request) {
			$policy = SecurityPolicyService::getSecurityPolicy();

			return Response::ok([
				"remember_disabled" => !empty($policy["remember_disabled"]),
			]);
		}

		/**
		 * The client's "remember me" flag, clamped by the security policy — when
		 * remember_disabled is set, every login path ignores the flag (the legacy
		 * admin does the same in login/process.php).
		 */
		private function rememberRequested(Request $request) {
			if (!empty(SecurityPolicyService::getSecurityPolicy()["remember_disabled"])) {
				return false;
			}

			return $request->bodyBool("remember");
		}

		public function login(Request $request) {
			$email = strtolower($request->bodyString("email"));
			$password = $request->bodyString("password", "", false);
			$remember = $this->rememberRequested($request);

			if ($email === "" || $password === "") {
				throw new BadRequestException("Email and password required", "missing_credentials");
			}

			$ip = ip2long($request->ip) ?: null;

			if (SecurityPolicyService::isIPBanned($ip)) {
				throw new AuthorizationException("IP is temporarily banned", "ip_banned");
			}

			$user = SQL::fetch("SELECT * FROM bigtree_users WHERE LOWER(email) = ?", $email);

			if ($user && SecurityPolicyService::isUserBanned($user["id"])) {
				throw new AuthorizationException("User is temporarily banned", "user_banned");
			}

			$ok = false;

			if ($user) {
				$ok = $this->verifyPassword($user, $password);
			}

			if (!$ok) {
				$this->recordFailedAttempt($ip, $user ? (int)$user["id"] : null);
				throw new AuthenticationException("Invalid credentials", "invalid_credentials");
			}

			// 2FA gate: any user who has enrolled a TOTP secret (self-service or
			// otherwise) must complete the second factor.
			if (!empty($user["2fa_secret"])) {
				$mfa_token = $this->issueMfaPartial((int)$user["id"], $remember);

				return Response::ok(["mfa_required" => true, "mfa_token" => $mfa_token]);
			}

			// Policy-mandated 2FA: when the security policy requires TOTP, a user
			// with no enrolled secret must complete enrollment before any tokens
			// are issued (legacy redirects to login/2fa/setup at this point). The
			// setup token authorizes only the enrollment endpoints below. Passkey
			// logins bypass this gate — WebAuthn is already a strong factor.
			if ((SecurityPolicyService::getSecurityPolicy()["two_factor"] ?? "") === "google") {
				$setup_token = $this->issueMfaPartial((int)$user["id"], $remember, true);

				return Response::ok(["two_factor_setup_required" => true, "setup_token" => $setup_token]);
			}

			return $this->issueTokens($user, $request, $remember);
		}

		public function twoFactor(Request $request) {
			$mfa_token = $request->bodyString("mfa_token", "", false);
			$code = $request->bodyString("code", "", false);

			if ($mfa_token === "" || $code === "") {
				throw new BadRequestException("mfa_token and code required", "missing_fields");
			}

			$user = SQL::fetch("SELECT * FROM bigtree_users WHERE 2fa_login_token = ?", $mfa_token);

			// Setup tokens (forced-enrollment flow) authorize only the enrollment
			// endpoints — they can't complete a code-verification login.
			if (!$user || $this->tokenIsSetup($mfa_token)) {
				throw new AuthenticationException("Invalid or expired MFA token", "invalid_mfa_token");
			}

			// The partial token carries its own expiry (see issueMfaPartial). Enforce
			// the MFA_PARTIAL_TTL window so a captured hand-off token can't be replayed
			// indefinitely; clear it on expiry so it can't be retried.
			$expiry = $this->tokenExpiry($user["2fa_login_token"]);

			if ($expiry === null || $expiry < time()) {
				SQL::update("bigtree_users", $user["id"], ["2fa_login_token" => ""]);
				throw new AuthenticationException("Invalid or expired MFA token", "invalid_mfa_token");
			}

			include_once BigTree::path("inc/lib/GoogleAuthenticator.php");

			if (!GoogleAuthenticator::verifyCode($user["2fa_secret"], $code)) {
				throw new AuthenticationException("Invalid 2FA code", "invalid_2fa_code");
			}

			// The partial token carries the original "remember me" choice; read it
			// before the one-shot clear below. Re-clamped against the policy in case
			// it changed between the password step and the code entry.
			$remember = $this->tokenRemember($user["2fa_login_token"])
				&& empty(SecurityPolicyService::getSecurityPolicy()["remember_disabled"]);

			// One-shot: clear the token so the partial can't be reused.
			SQL::update("bigtree_users", $user["id"], ["2fa_login_token" => ""]);

			return $this->issueTokens($user, $request, $remember);
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

			return Response::ok($this->totpCeremony($request->user->email));
		}

		/** Mint a fresh TOTP secret + QR/otpauth payload for an enrollment ceremony. */
		private function totpCeremony($email) {
			include_once BigTree::path("inc/lib/GoogleAuthenticator.php");

			$site_title = SQL::fetchSingle("SELECT nav_title FROM bigtree_pages WHERE id = 0") ?: "BigTree";
			$label = $site_title . " (" . $email . ")";
			$secret = GoogleAuthenticator::generateSecret();
			$qr = GoogleAuthenticator::getQRCode($label, $secret);
			$otpauth = "otpauth://totp/" . rawurlencode($label) . "?secret=" . $secret . "&issuer=BigTree";

			return [
				"secret" => $secret,
				"qr_image" => $qr,
				"otpauth_uri" => $otpauth,
			];
		}

		/**
		 * POST /auth/2fa/enable { secret, code }
		 * Completes enrollment: verifies the user-entered code against the
		 * pending secret from /auth/2fa/setup, then stores it on the account.
		 */
		public function twoFactorEnable(Request $request) {
			$secret = $request->bodyString("secret");
			$code = $request->bodyString("code");
			$this->assertValidTotpPair($secret, $code);

			$user_id = (int)$request->user->id;
			SQL::update("bigtree_users", $user_id, ["2fa_secret" => $secret]);

			return Response::ok(["id" => $user_id, "two_factor_enabled" => true]);
		}

		/**
		 * POST /auth/2fa/setup-required { setup_token }
		 * Forced-enrollment ceremony start for a user the security policy blocked
		 * at login (policy mandates TOTP, user has no secret). The setup token from
		 * the login response is the sole credential. Mirrors twoFactorSetup but
		 * pre-auth: the secret is held client-side until enable-required verifies it.
		 */
		public function twoFactorSetupRequired(Request $request) {
			$user = $this->userBySetupToken($request->bodyString("setup_token", "", false));

			return Response::ok($this->totpCeremony($user["email"]));
		}

		/**
		 * POST /auth/2fa/enable-required { setup_token, secret, code }
		 * Completes forced enrollment: verifies the code against the pending
		 * secret, persists it, and finishes the login with a full token bundle.
		 */
		public function twoFactorEnableRequired(Request $request) {
			$setup_token = $request->bodyString("setup_token", "", false);
			$user = $this->userBySetupToken($setup_token);
			$secret = $request->bodyString("secret");
			$code = $request->bodyString("code");
			$this->assertValidTotpPair($secret, $code);

			// The setup token carries the original "remember me" choice; read it
			// before the one-shot clear, re-clamped against the current policy.
			$remember = $this->tokenRemember($setup_token)
				&& empty(SecurityPolicyService::getSecurityPolicy()["remember_disabled"]);

			SQL::update("bigtree_users", $user["id"], [
				"2fa_secret" => $secret,
				"2fa_login_token" => "",
			]);
			$user["2fa_secret"] = $secret;

			return $this->issueTokens($user, $request, $remember);
		}

		/**
		 * Resolve and validate a forced-enrollment setup token: must match a user,
		 * carry the setup flag, be unexpired, and the user must still be without a
		 * secret (someone who enrolled in another tab goes back through login).
		 */
		private function userBySetupToken($setup_token) {
			if ($setup_token === "") {
				throw new BadRequestException("setup_token required", "missing_fields");
			}

			$user = SQL::fetch("SELECT * FROM bigtree_users WHERE 2fa_login_token = ?", $setup_token);

			if (!$user || !$this->tokenIsSetup($setup_token) || !empty($user["2fa_secret"])) {
				throw new AuthenticationException("Invalid or expired setup token", "invalid_setup_token");
			}

			$expiry = $this->tokenExpiry($setup_token);

			if ($expiry === null || $expiry < time()) {
				SQL::update("bigtree_users", $user["id"], ["2fa_login_token" => ""]);
				throw new AuthenticationException("Invalid or expired setup token", "invalid_setup_token");
			}

			return $user;
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

			$code = $request->bodyString("code");

			if ($code === "" || !GoogleAuthenticator::verifyCode($user["2fa_secret"], $code)) {
				throw new BadRequestException("That code is incorrect or expired", "invalid_2fa_code");
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
			$raw = $request->bodyString("refresh_token", "", false);

			if ($raw === "") {
				throw new AuthenticationException("Missing refresh_token", "no_refresh_token");
			}

			$rotation = TokenStore::rotate($raw, $request->ip, $request->user_agent);
			$user = SQL::fetch("SELECT * FROM bigtree_users WHERE id = ?", $rotation["user_id"]);

			if (!$user) {
				throw new AuthenticationException("User no longer exists", "invalid_refresh_token");
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
			$raw = $request->bodyString("refresh_token", "", false);

			if ($raw !== "") {
				TokenStore::revokeByRaw($raw);
			}

			// Best-effort teardown of the legacy PHP session the bridge endpoint
			// may have established (mirrors the legacy logout cookie/session
			// clearing, minus the redirect).
			$this->destroyPhpSession();

			return Response::noContent();
		}

		/**
		 * POST /auth/php-session
		 * Bridges the SPA's token auth into the legacy PHP session so the
		 * front-end BigTree bar / on-page editing work after an SPA-only login.
		 * Mirrors the session-establishment half of the legacy admin login
		 * (single-site path): a bigtree_user_sessions chain, the
		 * bigtree_admin[*] cookies, and the $_SESSION["bigtree_admin"] payload.
		 *
		 * Cookies are host-scoped, so this only has an effect when the SPA is
		 * served from the site's own origin (prod). In dev (Vite origin) the
		 * call succeeds but the cookies land on the wrong host — harmless.
		 *
		 * Multi-site installs: the primary domain is covered here; alternate
		 * domains still require the legacy CORS login chain, so the response
		 * includes `multi_site_login_key` for a future SPA hand-off.
		 */
		public function phpSession(Request $request) {
			global $bigtree;

			$user = SQL::fetch("SELECT * FROM bigtree_users WHERE id = ?", (int)$request->user->id);

			if (!$user) {
				throw new AuthenticationException("User no longer exists", "unknown_user");
			}

			$remember = $this->rememberRequested($request);

			// CSRF token + session chain, generated exactly like the legacy admin login.
			$csrf_token = base64_encode(random_bytes(32));
			$csrf_token_field = "__csrf_token_" . BigTree::randomString(32) . "__";

			$chain = uniqid("chain-", true);

			while (SQL::fetchSingle("SELECT id FROM bigtree_user_sessions WHERE chain = ?", $chain)) {
				$chain = uniqid("chain-", true);
			}

			$session = uniqid("session-", true);

			while (SQL::fetchSingle("SELECT id FROM bigtree_user_sessions WHERE id = ?", $session)) {
				$session = uniqid("session-", true);
			}

			SQL::insert("bigtree_user_sessions", [
				"id" => $session,
				"chain" => $chain,
				"email" => $user["email"],
				"csrf_token" => $csrf_token,
				"csrf_token_field" => $csrf_token_field,
			]);

			$cookie_domain = str_replace(DOMAIN, "", WWW_ROOT);

			// The email cookie powers the BigTree bar even without "remember me".
			setcookie('bigtree_admin[email]', $user["email"], strtotime("+1 month"), $cookie_domain, "", false, true);

			if ($remember) {
				setcookie('bigtree_admin[login]', json_encode([$session, $chain]), strtotime("+1 month"), $cookie_domain, "", false, true);
			}

			// PHP session payload — what the legacy admin/front-end bar read.
			\BigTreeSessionHandler::start();

			if (session_status() === PHP_SESSION_ACTIVE) {
				// Fixation hygiene on the state change. Unlike the legacy login we
				// don't rename the old DB row to the new id — the API caller
				// usually has no prior session row, and the rename collides with
				// the row the handler writes for the regenerated id. Passing true
				// destroys the old session instead so exactly one row is written.
				session_regenerate_id(true);

				$_SESSION["bigtree_admin"]["id"] = $user["id"];
				$_SESSION["bigtree_admin"]["email"] = $user["email"];
				$_SESSION["bigtree_admin"]["level"] = $user["level"];
				$_SESSION["bigtree_admin"]["name"] = $user["name"];
				$_SESSION["bigtree_admin"]["permissions"] = json_decode($user["permissions"], true);
				$_SESSION["bigtree_admin"]["csrf_token"] = $csrf_token;
				$_SESSION["bigtree_admin"]["csrf_token_field"] = $csrf_token_field;
				$session_id = session_id();
				session_write_close();

				// Flag the (now-written) row as a login session so logout_all and
				// session bookkeeping treat it like a legacy login.
				if (($bigtree["config"]["session_handler"] ?? "") === "db") {
					SQL::update("bigtree_sessions", $session_id, [
						"is_login" => "on",
						"logged_in_user" => $user["id"],
					]);
				}
			}

			$payload = ["established" => true];

			// Multi-site: hand back the key for the legacy CORS chain so alternate
			// domains can be logged in by whoever wants to drive that flow.
			if (!empty($bigtree["config"]["sites"]) && is_array($bigtree["config"]["sites"])) {
				$cache_data = [
					"user_id" => $user["id"],
					"session" => $session,
					"chain" => $chain,
					"stay_logged_in" => $remember,
					"login_redirect" => false,
					"remaining_sites" => [],
					"csrf_token" => $csrf_token,
					"csrf_token_field" => $csrf_token_field,
				];

				foreach ($bigtree["config"]["sites"] as $site_key => $site_configuration) {
					$cache_data["remaining_sites"][$site_key] = $site_configuration["www_root"];
				}

				$payload["multi_site_login_key"] = BigTreeCMS::cacheUnique("org.bigtreecms.login-session", $cache_data);
			}

			return Response::ok($payload);
		}

		/**
		 * Clear the legacy PHP session + bigtree_admin cookies.
		 * Used by JWT logout and by the front-end bar logout endpoint
		 * (core/admin/ajax/bar-logout.php) — the bar has no refresh token.
		 */
		public static function clearLegacyPhpSession(): void {
			try {
				$cookie_domain = str_replace(DOMAIN, "", WWW_ROOT);

				if (!empty($_COOKIE["bigtree_admin"]["login"])) {
					$decoded = json_decode($_COOKIE["bigtree_admin"]["login"], true);

					if (is_array($decoded) && count($decoded) === 2) {
						[$session, $chain] = $decoded;
						$valid = SQL::fetchSingle(
							"SELECT id FROM bigtree_user_sessions WHERE id = ? AND chain = ?",
							(string)$session, (string)$chain
						);

						if ($valid) {
							SQL::query("DELETE FROM bigtree_user_sessions WHERE chain = ?", (string)$chain);
						}
					}
				}

				setcookie("bigtree_admin[email]", "", time() - 3600, $cookie_domain);
				setcookie("bigtree_admin[login]", "", time() - 3600, $cookie_domain);

				\BigTreeSessionHandler::start();

				if (session_status() === PHP_SESSION_ACTIVE) {
					unset($_SESSION["bigtree_admin"]);
					session_write_close();
				}
			} catch (\Throwable $e) {
				// Session teardown is best-effort — token revocation already happened.
			}
		}

		/** @see clearLegacyPhpSession */
		private function destroyPhpSession() {
			self::clearLegacyPhpSession();
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
				"timezone" => $request->user->timezone ?? "",
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
			$target_id = $request->bodyInt("user_id");
			$actor = $request->user;

			if (!$target_id) {
				throw new BadRequestException("user_id required", "missing_user_id");
			}

			if ($target_id === (int)$actor->id) {
				throw new BadRequestException("You cannot emulate yourself", "self_emulation");
			}

			$target = Entity::fetchOrFail(
				"SELECT * FROM bigtree_users WHERE id = ?",
				[$target_id],
				"User not found",
				"user_not_found"
			);

			$envelope = $this->tokenEnvelope($target, $request);
			$envelope["emulated_by"] = [
				"id" => (int)$actor->id,
				"name" => $actor->name,
				"email" => $actor->email,
			];

			return Response::ok($envelope);
		}

		// — Passkey endpoints —

		public function passkeyOptions(Request $request) {
			$parsed = parse_url(ADMIN_ROOT);
			$rp_id = $parsed["host"] ?? "";

			$options = WebAuthn::getAuthenticationOptions($rp_id, []);

			return $this->mintPasskeyChallenge($options, "auth");
		}

		public function passkeyVerify(Request $request) {
			$ip = ip2long($request->ip) ?: null;

			if (SecurityPolicyService::isIPBanned($ip)) {
				throw new AuthorizationException("IP is temporarily banned", "ip_banned");
			}

			$challenge_id = $request->bodyString("challenge_id", "", false);
			$credential_id = $request->bodyString("credential_id", "", false);
			$client_data_json = $request->bodyString("client_data_json", "", false);
			$auth_data = $request->bodyString("authenticator_data", "", false);
			$signature = $request->bodyString("signature", "", false);

			if ($challenge_id === "" || $credential_id === "" || $client_data_json === "" || $auth_data === "" || $signature === "") {
				throw new BadRequestException("Missing passkey verification fields", "missing_fields");
			}

			// INTERVAL needs an integer literal; ? would inject a quoted string.
			$ttl = (int)self::PASSKEY_CHALLENGE_TTL;
			$challenge_row = SQL::fetch(
				"SELECT * FROM bigtree_passkey_challenges WHERE id = ? AND consumed = 0 AND purpose = 'auth' AND created_at >= DATE_SUB(NOW(), INTERVAL $ttl SECOND)",
				$challenge_id
			);

			if (!$challenge_row) {
				throw new AuthenticationException("Passkey challenge invalid or expired", "invalid_challenge");
			}

			$passkey = PasskeyService::getPasskeyByCredentialId($credential_id);

			if (!$passkey) {
				$this->recordFailedAttempt($ip, null);
				throw new AuthenticationException("Unknown credential", "unknown_credential");
			}

			$user = SQL::fetch("SELECT * FROM bigtree_users WHERE id = ?", $passkey["user"]);

			if (!$user) {
				$this->recordFailedAttempt($ip, (int)$passkey["user"]);
				throw new AuthenticationException("Credential's user no longer exists", "unknown_user");
			}

			if (SecurityPolicyService::isUserBanned($user["id"])) {
				throw new AuthorizationException("User is temporarily banned", "user_banned");
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
				throw new AuthenticationException("Passkey verification failed", "passkey_failed");
			} finally {
				unset($_SESSION["bigtree_passkey_challenge"]);
			}

			SQL::update("bigtree_passkey_challenges", $challenge_row["id"], ["consumed" => 1]);
			PasskeyService::updatePasskeyUsed($passkey["id"], $new_sign_count);

			return $this->issueTokens($user, $request, $this->rememberRequested($request));
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

			$email = strtolower($request->bodyString("email"));

			if ($email === "") {
				throw new BadRequestException("email required", "missing_email");
			}

			$user = SQL::fetch("SELECT id, email, password FROM bigtree_users WHERE LOWER(email) = ?", $email);

			if ($user) {
				// Reset hash is unguessable without knowing the existing password hash + a microsecond timestamp.
				$hash = md5(md5($user["password"]) . md5(uniqid("bigtree-hash" . microtime(true))));
				// Append an absolute expiry so the link can't be redeemed forever. The
				// hash is hex, so the "." separator is unambiguous; the whole string is
				// both stored and emailed so the lookup still matches exactly.
				$token = $hash . "." . (time() + self::RESET_TOKEN_TTL);
				SQL::update("bigtree_users", $user["id"], ["change_password_hash" => $token]);
				$this->sendResetEmail($user["email"], $token);
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
			$token = $request->bodyString("token", "", false);
			$password = $request->bodyString("password");

			if ($token === "" || $password === "") {
				throw new BadRequestException("token and password required", "missing_fields");
			}

			if (!SecurityPolicyService::validatePassword($password)) {
				throw new BadRequestException("Password does not meet policy requirements", "weak_password");
			}

			$user = SQL::fetch("SELECT * FROM bigtree_users WHERE change_password_hash = ?", $token);

			if (!$user) {
				throw new AuthenticationException("Invalid or expired reset token", "invalid_token");
			}

			// Enforce the embedded expiry (see forgotPassword). Tokens without a valid
			// expiry suffix are rejected too, so a leaked link can't be redeemed past
			// its window; clear the consumed/expired hash either way.
			$expiry = $this->tokenExpiry($user["change_password_hash"]);

			if ($expiry === null || $expiry < time()) {
				SQL::update("bigtree_users", $user["id"], ["change_password_hash" => ""]);
				throw new AuthenticationException("Invalid or expired reset token", "invalid_token");
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

			$existing_ids = array_map(function ($p) { return $p["credential_id"]; }, PasskeyService::getUserPasskeys($user_id));

			$options = WebAuthn::getRegistrationOptions(
				$rp_id, $rp_name, $user_id, $user["email"], $user["name"] ?: $user["email"], $existing_ids
			);

			return $this->mintPasskeyChallenge($options, "register", $user_id);
		}

		/**
		 * POST /auth/passkey/register/verify { challenge_id, client_data_json, attestation_object, name? }
		 * Verifies the attestation, stores the new credential.
		 */
		public function passkeyRegisterVerify(Request $request) {
			$user_id = (int)$request->user->id;
			$challenge_id = $request->bodyString("challenge_id", "", false);
			$client_data_json = $request->bodyString("client_data_json", "", false);
			$attestation_object = $request->bodyString("attestation_object", "", false);
			$name = $request->bodyString("name") ?: "Passkey";

			if ($challenge_id === "" || $client_data_json === "" || $attestation_object === "") {
				throw new BadRequestException("Missing passkey registration fields", "missing_fields");
			}

			$ttl = (int)self::PASSKEY_CHALLENGE_TTL;
			$challenge_row = SQL::fetch(
				"SELECT * FROM bigtree_passkey_challenges WHERE id = ? AND consumed = 0 AND purpose = 'register' AND user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL $ttl SECOND)",
				$challenge_id, $user_id
			);

			if (!$challenge_row) {
				throw new AuthenticationException("Registration challenge invalid or expired", "invalid_challenge");
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
				throw new BadRequestException("Passkey verification failed", "passkey_invalid");
			} finally {
				unset($_SESSION["bigtree_passkey_challenge"]);
			}

			SQL::update("bigtree_passkey_challenges", $challenge_row["id"], ["consumed" => 1]);

			$passkey_id = PasskeyService::createPasskey(
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
			$rows = PasskeyService::getUserPasskeys((int)$request->user->id);

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
			$passkey_id = $request->id();
			PasskeyService::deletePasskey($passkey_id, (int)$request->user->id);

			return Response::noContent();
		}

		// — Internal helpers —

		private function sendResetEmail($to, $hash) {
			global $bigtree;

			$site_title = SQL::fetchSingle("SELECT nav_title FROM bigtree_pages WHERE id = 0") ?: "BigTree";
			$admin_root = ($bigtree["config"]["force_secure_login"] ?? false)
				? str_replace("http://", "https://", ADMIN_ROOT)
				: ADMIN_ROOT;

			// Config override first (used in dev where the SPA runs on its own
			// origin); default to the SPA's reset route. The legacy admin's
			// login/reset-password page accepts the same token if needed.
			$reset_url = ($bigtree["config"]["api"]["spa_reset_url"] ?? "")
				?: ($admin_root . "spa/login/reset/{token}");
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

		private function issueMfaPartial($user_id, $remember, $setup = false) {
			// Embed an absolute expiry in the token so twoFactor() can enforce the
			// MFA_PARTIAL_TTL window without a schema change. The random prefix is
			// hex, so the "." separator is unambiguous. A flags segment rides along:
			// "r" carries the "remember me" choice across the hand-off, "s" marks a
			// forced-enrollment setup token (valid only for the setup endpoints).
			$flags = ($remember ? "r" : "") . ($setup ? "s" : "");
			$token = bin2hex(random_bytes(16)) . "." . (time() + self::MFA_PARTIAL_TTL) . ($flags !== "" ? ".$flags" : "");
			SQL::update("bigtree_users", $user_id, ["2fa_login_token" => $token]);

			return $token;
		}

		/**
		 * Parse the absolute-expiry segment from an expiring opaque token
		 * ("<secret>.<unix_ts>[.flags]"). Returns the unix timestamp, or null when
		 * no valid segment is present (e.g. a legacy token minted before expiries
		 * were embedded — treated as invalid).
		 */
		private function tokenExpiry($value): ?int {
			$parts = explode(".", (string)$value);

			if (count($parts) < 2 || $parts[1] === "" || !ctype_digit($parts[1])) {
				return null;
			}

			return (int)$parts[1];
		}

		/** Whether an expiring opaque token carries the "remember me" flag. */
		private function tokenRemember($value): bool {
			$parts = explode(".", (string)$value);

			return strpos($parts[2] ?? "", "r") !== false;
		}

		/** Whether an expiring opaque token is a forced-enrollment setup token. */
		private function tokenIsSetup($value): bool {
			$parts = explode(".", (string)$value);

			return strpos($parts[2] ?? "", "s") !== false;
		}

		/**
		 * Mint access + refresh tokens and return the canonical 5-key envelope.
		 * Shared by issueTokens (login) and the emulate endpoint so a future
		 * envelope change (new claim surface, TTL field, etc.) lands in one place.
		 *
		 * Both tokens go in the body. The SPA persists them to localStorage.
		 * No cookie is set — there's no value in HttpOnly for the refresh
		 * token on an admin tool where XSS would already own the in-memory
		 * access token. Storing tokens client-side keeps deployment topology
		 * simple (no cookie path/domain dance) and is the de facto SPA pattern.
		 */
		private function tokenEnvelope(array $user, Request $request, bool $remember = false): array {
			$access = $this->issueAccessToken($user);
			$refresh = TokenStore::issueFamily((int)$user["id"], $request->ip, $request->user_agent, $remember);

			return [
				"access_token" => $access,
				"refresh_token" => $refresh["raw"],
				"token_type" => "Bearer",
				"expires_in" => self::ACCESS_TTL,
				"user" => $this->publicUser($user),
			];
		}

		private function issueTokens(array $user, Request $request, $remember = false) {

			return Response::ok($this->tokenEnvelope($user, $request, (bool)$remember));
		}

		private function issueAccessToken(array $user) {
			$permissions = Json::decode($user["permissions"] ?? "[]");
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

		/**
		 * Validate a TOTP enrollment pair: both fields present and the code
		 * verifies against the pending secret. Shared by the enable and forced
		 * enable-required ceremonies.
		 */
		private function assertValidTotpPair(string $secret, string $code): void {
			include_once BigTree::path("inc/lib/GoogleAuthenticator.php");

			if ($secret === "" || $code === "") {
				throw new BadRequestException("secret and code required", "missing_fields");
			}

			if (!GoogleAuthenticator::verifyCode($secret, $code)) {
				throw new BadRequestException("That code is incorrect or expired", "invalid_2fa_code");
			}
		}

		/**
		 * Replace WebAuthn's $_SESSION-stored challenge with a DB-stored one
		 * keyed by an id we hand back to the client, then strip the session side
		 * effect so no legacy copy lingers. Shared by the auth and registration
		 * ceremonies; a user id ties a registration challenge to its owner.
		 */
		private function mintPasskeyChallenge(array $options, string $purpose, ?int $user_id = null): Response {
			$challenge_id = bin2hex(random_bytes(16));
			$row = [
				"id" => $challenge_id,
				"challenge" => $options["challenge"],
				"purpose" => $purpose,
			];

			if ($user_id !== null) {
				$row["user_id"] = $user_id;
			}

			SQL::insert("bigtree_passkey_challenges", $row);

			// Strip the session side effect WebAuthn::generateChallenge() created
			// and substitute our id, so no legacy session-stored copy lingers.
			unset($_SESSION["bigtree_passkey_challenge"]);

			return Response::ok([
				"challenge_id" => $challenge_id,
				"options" => $options,
			]);
		}

		private function publicUser(array $user) {
			$ai = new \BigTreeAI();
			$level = (int)$user["level"];
			// Developers must apply schema revisions before using the rest of admin
			// (mirrors the legacy login → upgrade redirect). Only trust a real queue
			// check — do not force the gate on transient errors (that caused a
			// flash of /developer/migrations then an empty-queue bounce).
			$migrations_pending = false;

			if ($level >= 2) {
				try {
					$migrations_pending = (new SystemService())->hasPendingMigrations();
				} catch (\Throwable $e) {
					$migrations_pending = false;
				}
			}

			return [
				"id" => (int)$user["id"],
				"email" => $user["email"],
				"name" => $user["name"],
				"level" => $level,
				"timezone" => $user["timezone"] ?? "",
				"features" => [
					"ai_search" => $ai->isFeatureEnabled("search"),
					"ai_chat" => $ai->isFeatureEnabled("chat"),
				],
				"migrations_pending" => $migrations_pending,
			];
		}

		// enforceRefreshOrigin was removed: with localStorage-based refresh tokens
		// there's no CSRF surface to defend. CSRF only applies to ambient
		// credentials (cookies, basic-auth) the browser attaches automatically.
		// A refresh token in a JSON body cannot be sent without explicit JS, and
		// our CORS middleware already gates which origins can do that.
	}
