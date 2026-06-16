<?php
	/**
	 * AuthService — two-factor (TOTP) flow coverage.
	 *
	 * Characterization tests for the 2FA seams the auth smoke suite never
	 * reaches: the MFA-partial / setup token format (expiry + remember/setup
	 * flags), the TOTP verify window, and twoFactorDisable's "valid current
	 * code required" gate. These pin current behavior; they do NOT change
	 * AuthService. If any assertion surfaces a real flaw it is marked with a
	 * SUSPECTED-BUG comment for a dedicated fix plan.
	 *
	 * Pure pieces (token encode/decode, expiry parsing, flag parsing, the TOTP
	 * verify math) run via reflection with no DB. twoFactorDisable needs a real
	 * bigtree_users row, so it seeds a throwaway user and cleans up in finally,
	 * skipping when the DB is unavailable (CI runs it).
	 */

	use BigTree\Services\AuthService;
	use BigTree\Api\Exceptions\BadRequestException;

	/** Reflect a private/protected AuthService method as a callable. */
	function _authmfa_method(string $name): ReflectionMethod {
		$ref = new ReflectionMethod(AuthService::class, $name);
		$ref->setAccessible(true);

		return $ref;
	}

	/** Insert a throwaway bigtree_users row (all NOT-NULL columns); return id. */
	function _authmfa_insert_user(string $email, array $overrides = []): int {
		$row = array_merge([
			"email" => $email,
			"password" => password_hash("AuthMfa-CI-2026!", PASSWORD_DEFAULT),
			"new_hash" => "on",
			"2fa_secret" => "",
			"2fa_login_token" => "",
			"name" => "Auth MFA Test",
			"company" => "",
			"level" => 0,
			"permissions" => "{}",
			"alerts" => "",
			"daily_digest" => "",
			"timezone" => "UTC",
			"change_password_hash" => "",
			"token_version" => 1,
		], $overrides);

		return (int)SQL::insert("bigtree_users", $row);
	}

	/** Skip helper — returns true (and prints) when the DB is unreachable. */
	function _authmfa_db_unavailable(): bool {
		try {
			SQL::fetchSingle("SELECT id FROM bigtree_users LIMIT 1");

			return false;
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return true;
		}
	}

	// — Pure: MFA-partial token format (issueMfaPartial needs DB to persist, so
	//   the format itself is exercised via tokenExpiry/tokenRemember/tokenIsSetup
	//   against hand-built tokens that match issueMfaPartial's construction). —

	function test_authmfa_token_expiry_parsing() {
		$svc = new AuthService();
		$expiry = _authmfa_method("tokenExpiry");

		$future = time() + AuthService::MFA_PARTIAL_TTL;

		// "<hex>.<unix_ts>" round-trips to the embedded timestamp.
		T::equals($expiry->invoke($svc, "abc123." . $future), $future, "expiry parsed from secret.ts token");

		// Flags segment does not disturb the expiry parse.
		T::equals($expiry->invoke($svc, "abc123." . $future . ".rs"), $future, "expiry parsed with flags segment present");

		// Legacy / malformed tokens (no expiry segment) parse to null → treated as invalid.
		T::ok($expiry->invoke($svc, "no-expiry-segment") === null, "token with no expiry segment → null");
		T::ok($expiry->invoke($svc, "abc.") === null, "token with empty expiry segment → null");
		T::ok($expiry->invoke($svc, "abc.notanumber") === null, "token with non-numeric expiry → null");
		T::ok($expiry->invoke($svc, "") === null, "empty token → null expiry");
	}

	function test_authmfa_token_flag_parsing() {
		$svc = new AuthService();
		$remember = _authmfa_method("tokenRemember");
		$setup = _authmfa_method("tokenIsSetup");

		$ts = time() + 300;

		// remember-only token ("r").
		$r_token = "deadbeef." . $ts . ".r";
		T::equals($remember->invoke($svc, $r_token), true, "remember flag detected on .r token");
		T::equals($setup->invoke($svc, $r_token), false, "setup flag absent on .r token");

		// setup-only token ("s") — used by the forced-enrollment flow.
		$s_token = "deadbeef." . $ts . ".s";
		T::equals($setup->invoke($svc, $s_token), true, "setup flag detected on .s token");
		T::equals($remember->invoke($svc, $s_token), false, "remember flag absent on .s token");

		// both flags ("rs").
		$rs_token = "deadbeef." . $ts . ".rs";
		T::equals($remember->invoke($svc, $rs_token), true, "remember flag detected on .rs token");
		T::equals($setup->invoke($svc, $rs_token), true, "setup flag detected on .rs token");

		// no flags segment → both false.
		$plain = "deadbeef." . $ts;
		T::equals($remember->invoke($svc, $plain), false, "no remember flag on flagless token");
		T::equals($setup->invoke($svc, $plain), false, "no setup flag on flagless token");
	}

	function test_authmfa_issue_partial_roundtrip() {
		if (_authmfa_db_unavailable()) {
			return;
		}

		$svc = new AuthService();
		$issue = _authmfa_method("issueMfaPartial");
		$expiry = _authmfa_method("tokenExpiry");
		$remember = _authmfa_method("tokenRemember");
		$setup = _authmfa_method("tokenIsSetup");

		$uid = _authmfa_insert_user("ZZ_authmfa_partial_" . uniqid() . "@ci.local");

		try {
			$before = time();
			// remember=true, setup=false → "r" flag, expiry within MFA_PARTIAL_TTL.
			$token = $issue->invoke($svc, $uid, true, false);

			// Persisted onto the user row (twoFactor() looks it up by this column).
			$stored = SQL::fetchSingle("SELECT 2fa_login_token FROM bigtree_users WHERE id = ?", $uid);
			T::equals($stored, $token, "issued partial token is persisted to 2fa_login_token");

			$exp = $expiry->invoke($svc, $token);
			T::ok($exp !== null, "issued token carries a parseable expiry");
			T::ok($exp >= $before + AuthService::MFA_PARTIAL_TTL && $exp <= time() + AuthService::MFA_PARTIAL_TTL, "expiry is now + MFA_PARTIAL_TTL");
			T::equals($remember->invoke($svc, $token), true, "issued token carries remember flag");
			T::equals($setup->invoke($svc, $token), false, "non-setup token has no setup flag");

			// setup=true → "s" flag set, used only by the forced-enrollment endpoints.
			$setup_token = $issue->invoke($svc, $uid, false, true);
			T::equals($setup->invoke($svc, $setup_token), true, "setup token carries setup flag");
			T::equals($remember->invoke($svc, $setup_token), false, "non-remember setup token has no remember flag");

			// The hex prefix is 16 random bytes → 32 hex chars before the first ".".
			$prefix = explode(".", $token)[0];
			T::equals(strlen($prefix), 32, "token secret prefix is 32 hex chars (16 random bytes)");
			T::ok(ctype_xdigit($prefix), "token secret prefix is hex");
		} finally {
			SQL::query("DELETE FROM bigtree_users WHERE id = ?", $uid);
		}
	}

	// — Pure: TOTP verify window (GoogleAuthenticator::verifyCode). —

	function test_authmfa_totp_verify_window() {
		include_once BigTree::path("inc/lib/GoogleAuthenticator.php");

		// A known, valid base32 secret.
		$secret = GoogleAuthenticator::generateSecret();
		T::ok(strlen($secret) === 64, "generated secret is 64 base32 chars");

		// The current-window code verifies.
		$code = GoogleAuthenticator::getCode($secret);
		T::equals(GoogleAuthenticator::verifyCode($secret, $code), true, "current TOTP code passes verifyCode");

		// A wrong code of the right length fails.
		$wrong = $code === "000000" ? "111111" : "000000";
		T::equals(GoogleAuthenticator::verifyCode($secret, $wrong), false, "incorrect 6-digit code fails verifyCode");

		// Codes of the wrong length are rejected outright (length guard).
		T::equals(GoogleAuthenticator::verifyCode($secret, "12345"), false, "5-digit code rejected by length guard");
		T::equals(GoogleAuthenticator::verifyCode($secret, "1234567"), false, "7-digit code rejected by length guard");

		// A code computed for a different secret does not cross-verify (overwhelmingly).
		$other_secret = GoogleAuthenticator::generateSecret();
		$other_code = GoogleAuthenticator::getCode($other_secret);

		if ($other_code !== $code) {
			T::equals(GoogleAuthenticator::verifyCode($secret, $other_code), false, "code for a different secret fails verifyCode");
		} else {
			// Astronomically unlikely collision; still record a pass so counts are stable.
			T::ok(true, "different-secret code collided with current code (skipped cross-check)");
		}
	}

	// — DB-seeded: twoFactorDisable requires a valid current code. —

	function test_authmfa_disable_requires_valid_code() {
		if (_authmfa_db_unavailable()) {
			return;
		}

		include_once BigTree::path("inc/lib/GoogleAuthenticator.php");

		$svc = new AuthService();
		$secret = GoogleAuthenticator::generateSecret();
		$uid = _authmfa_insert_user("ZZ_authmfa_disable_" . uniqid() . "@ci.local", ["2fa_secret" => $secret]);

		try {
			// A wrong code → BadRequestException, and the secret is left intact.
			$wrong = GoogleAuthenticator::getCode($secret) === "000000" ? "111111" : "000000";
			$req_wrong = _authmfa_make_request($uid, ["code" => $wrong]);

			T::throws(function () use ($svc, $req_wrong) {
				$svc->twoFactorDisable($req_wrong);
			}, BadRequestException::class, "twoFactorDisable with wrong code throws BadRequestException");

			$still = SQL::fetchSingle("SELECT 2fa_secret FROM bigtree_users WHERE id = ?", $uid);
			T::equals($still, $secret, "secret unchanged after a rejected disable");

			// A missing code → also rejected (empty code never matches).
			$req_missing = _authmfa_make_request($uid, []);

			T::throws(function () use ($svc, $req_missing) {
				$svc->twoFactorDisable($req_missing);
			}, BadRequestException::class, "twoFactorDisable with no code throws BadRequestException");

			// The correct current code → disables and clears the secret.
			$good = GoogleAuthenticator::getCode($secret);
			$req_good = _authmfa_make_request($uid, ["code" => $good]);
			$resp = $svc->twoFactorDisable($req_good);

			$cleared = SQL::fetchSingle("SELECT 2fa_secret FROM bigtree_users WHERE id = ?", $uid);
			T::equals($cleared, "", "secret cleared after a valid disable");
			T::equals($resp->body["data"]["two_factor_enabled"], false, "disable response reports two_factor_enabled false");
		} finally {
			SQL::query("DELETE FROM bigtree_users WHERE id = ?", $uid);
		}
	}

	function test_authmfa_disable_noop_when_already_off() {
		if (_authmfa_db_unavailable()) {
			return;
		}

		$svc = new AuthService();
		$uid = _authmfa_insert_user("ZZ_authmfa_off_" . uniqid() . "@ci.local");

		try {
			// No secret enrolled: disable is an idempotent no-op (no code required).
			$req = _authmfa_make_request($uid, []);
			$resp = $svc->twoFactorDisable($req);
			T::equals($resp->body["data"]["two_factor_enabled"], false, "disable on an account without 2FA reports disabled");
			T::equals($resp->body["data"]["id"], $uid, "disable no-op echoes the user id");
		} finally {
			SQL::query("DELETE FROM bigtree_users WHERE id = ?", $uid);
		}
	}

	/**
	 * Build a minimal Request whose ->user resolves to $uid and whose ->body
	 * carries the given fields. twoFactorDisable reads only $request->user->id
	 * and $request->body, so a lightweight stand-in is sufficient.
	 */
	function _authmfa_make_request(int $uid, array $body): \BigTree\Api\Request {
		$req = new \BigTree\Api\Request();
		$req->user = (object)["id" => $uid];
		$req->body = $body;

		return $req;
	}
