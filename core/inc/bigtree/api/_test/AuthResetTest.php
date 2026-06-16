<?php
	/**
	 * AuthService — password-reset flow coverage.
	 *
	 * Characterization tests for resetPassword (and the reset-token format that
	 * forgotPassword mints): single-use consumption, embedded-expiry enforcement,
	 * and per-user binding. These pin current behavior; they do NOT change
	 * AuthService. A real flaw surfaced here is marked SUSPECTED-BUG for a
	 * dedicated fix plan rather than fixed in place.
	 *
	 * resetPassword needs a real bigtree_users row (it consumes change_password_hash
	 * and bumps token_version), so these seed a throwaway user and clean up in
	 * finally, skipping when the DB is unavailable (CI runs them). forgotPassword
	 * itself triggers an email send, so its token-minting contract is exercised by
	 * constructing the same token shape it builds (see _authreset_mint_token) and
	 * feeding it through resetPassword — the security-critical half.
	 *
	 * password policy: BigTreeAdmin::validatePassword reads
	 * $bigtree["security-policy"]["password"]; an empty policy imposes no
	 * constraints, so tests neutralize it to keep the new password acceptable.
	 */

	use BigTree\Services\AuthService;
	use BigTree\Api\Exceptions\AuthenticationException;
	use BigTree\Api\Exceptions\BadRequestException;

	/** Insert a throwaway bigtree_users row (all NOT-NULL columns); return id. */
	function _authreset_insert_user(string $email, string $password_plain, array $overrides = []): int {
		$row = array_merge([
			"email" => $email,
			"password" => password_hash($password_plain, PASSWORD_DEFAULT),
			"new_hash" => "on",
			"2fa_secret" => "",
			"2fa_login_token" => "",
			"name" => "Auth Reset Test",
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

	/**
	 * Build a reset token exactly the way forgotPassword does
	 * (md5(md5(password).md5(uniqid...)) . "." . absolute_expiry) and persist it
	 * onto the user's change_password_hash column.
	 */
	function _authreset_mint_token(int $uid, string $password_hash, int $expiry): string {
		$hash = md5(md5($password_hash) . md5(uniqid("bigtree-hash" . microtime(true))));
		$token = $hash . "." . $expiry;
		SQL::update("bigtree_users", $uid, ["change_password_hash" => $token]);

		return $token;
	}

	/** Skip helper — returns true (and prints) when the DB is unreachable. */
	function _authreset_db_unavailable(): bool {
		try {
			SQL::fetchSingle("SELECT id FROM bigtree_users LIMIT 1");

			return false;
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return true;
		}
	}

	/** Disable the password policy so any non-empty password passes validation. */
	function _authreset_relax_password_policy(): void {
		global $bigtree;
		$bigtree["security-policy"]["password"] = [
			"length" => 0,
			"multicase" => "",
			"numbers" => "",
			"nonalphanumeric" => "",
		];
	}

	/** Build a Request carrying a reset {token, password} body. */
	function _authreset_make_request(string $token, string $password): \BigTree\Api\Request {
		$req = new \BigTree\Api\Request();
		$req->body = ["token" => $token, "password" => $password];
		$req->ip = "127.0.0.1";
		$req->user_agent = "auth-reset-test";

		return $req;
	}

	// — Pure: token format (md5 hash + "." + expiry) parses to its expiry. —

	function test_authreset_token_expiry_format() {
		$svc = new AuthService();
		$ref = new ReflectionMethod(AuthService::class, "tokenExpiry");
		$ref->setAccessible(true);

		$expiry = time() + AuthService::RESET_TOKEN_TTL;
		$hash = md5("anything");
		T::equals($ref->invoke($svc, $hash . "." . $expiry), $expiry, "reset token expiry parsed from hash.ts");

		// A bare hash with no expiry suffix → null → resetPassword treats it as invalid.
		T::ok($ref->invoke($svc, $hash) === null, "reset token without expiry suffix → null");
	}

	// — DB-seeded: happy path + single-use. —

	function test_authreset_valid_token_succeeds_and_is_single_use() {
		if (_authreset_db_unavailable()) {
			return;
		}

		_authreset_relax_password_policy();

		$svc = new AuthService();
		$old_plain = "OldPass-CI-2026!";
		$uid = _authreset_insert_user("ZZ_authreset_ok_" . uniqid() . "@ci.local", $old_plain);

		try {
			$pw_hash = SQL::fetchSingle("SELECT password FROM bigtree_users WHERE id = ?", $uid);
			$token = _authreset_mint_token($uid, $pw_hash, time() + AuthService::RESET_TOKEN_TTL);
			$old_version = (int)SQL::fetchSingle("SELECT token_version FROM bigtree_users WHERE id = ?", $uid);

			$new_plain = "BrandNewPass-CI-2026!";
			$resp = $svc->resetPassword(_authreset_make_request($token, $new_plain));
			T::equals($resp->status, 204, "successful reset returns 204 No Content");

			// New password is now active; old one no longer verifies.
			$new_hash = SQL::fetchSingle("SELECT password FROM bigtree_users WHERE id = ?", $uid);
			T::equals(password_verify($new_plain, $new_hash), true, "new password verifies after reset");
			T::equals(password_verify($old_plain, $new_hash), false, "old password no longer verifies after reset");

			// The hash is consumed (single-use) and token_version bumped (JWTs invalidated).
			$consumed = SQL::fetchSingle("SELECT change_password_hash FROM bigtree_users WHERE id = ?", $uid);
			T::equals($consumed, "", "change_password_hash cleared after a successful reset");
			$new_version = (int)SQL::fetchSingle("SELECT token_version FROM bigtree_users WHERE id = ?", $uid);
			T::equals($new_version, $old_version + 1, "token_version bumped on reset (existing JWTs invalidated)");

			// Replaying the same token now fails — single-use is enforced.
			T::throws(function () use ($svc, $token) {
				$svc->resetPassword(_authreset_make_request($token, "AnotherPass-CI-2026!"));
			}, AuthenticationException::class, "replaying a consumed reset token throws AuthenticationException");
		} finally {
			SQL::query("DELETE FROM bigtree_users WHERE id = ?", $uid);
		}
	}

	// — DB-seeded: expired token rejected and cleared. —

	function test_authreset_expired_token_rejected() {
		if (_authreset_db_unavailable()) {
			return;
		}

		_authreset_relax_password_policy();

		$svc = new AuthService();
		$old_plain = "OldPass-CI-2026!";
		$uid = _authreset_insert_user("ZZ_authreset_exp_" . uniqid() . "@ci.local", $old_plain);

		try {
			$pw_hash = SQL::fetchSingle("SELECT password FROM bigtree_users WHERE id = ?", $uid);
			// Expiry one second in the past.
			$token = _authreset_mint_token($uid, $pw_hash, time() - 1);

			T::throws(function () use ($svc, $token) {
				$svc->resetPassword(_authreset_make_request($token, "ShouldNotApply-CI-2026!"));
			}, AuthenticationException::class, "expired reset token throws AuthenticationException");

			// Old password is untouched and the expired hash is cleared.
			$pw_after = SQL::fetchSingle("SELECT password FROM bigtree_users WHERE id = ?", $uid);
			T::equals(password_verify($old_plain, $pw_after), true, "password unchanged after an expired reset attempt");
			$consumed = SQL::fetchSingle("SELECT change_password_hash FROM bigtree_users WHERE id = ?", $uid);
			T::equals($consumed, "", "expired reset hash is cleared so the link can't be retried");
		} finally {
			SQL::query("DELETE FROM bigtree_users WHERE id = ?", $uid);
		}
	}

	// — DB-seeded: token bound to one user only. —

	function test_authreset_token_for_other_user_rejected() {
		if (_authreset_db_unavailable()) {
			return;
		}

		_authreset_relax_password_policy();

		$svc = new AuthService();
		$victim_plain = "VictimPass-CI-2026!";
		$victim = _authreset_insert_user("ZZ_authreset_victim_" . uniqid() . "@ci.local", $victim_plain);
		$attacker = _authreset_insert_user("ZZ_authreset_attacker_" . uniqid() . "@ci.local", "AttackerPass-CI-2026!");

		try {
			// A token is minted ONLY for the attacker's row. The lookup is by exact
			// change_password_hash, so this token must not touch the victim.
			$attacker_hash = SQL::fetchSingle("SELECT password FROM bigtree_users WHERE id = ?", $attacker);
			$token = _authreset_mint_token($attacker, $attacker_hash, time() + AuthService::RESET_TOKEN_TTL);

			// A fabricated token that matches no row at all is rejected.
			$bogus = md5("not-a-real-hash") . "." . (time() + 3600);
			T::throws(function () use ($svc, $bogus) {
				$svc->resetPassword(_authreset_make_request($bogus, "Whatever-CI-2026!"));
			}, AuthenticationException::class, "a token matching no user row is rejected");

			// Consuming the attacker's own valid token must not alter the victim's password.
			$svc->resetPassword(_authreset_make_request($token, "AttackerNew-CI-2026!"));
			$victim_pw = SQL::fetchSingle("SELECT password FROM bigtree_users WHERE id = ?", $victim);
			T::equals(password_verify($victim_plain, $victim_pw), true, "victim's password is untouched by another user's reset");
			$victim_token = SQL::fetchSingle("SELECT change_password_hash FROM bigtree_users WHERE id = ?", $victim);
			T::equals($victim_token, "", "victim's change_password_hash is untouched (was empty)");
		} finally {
			SQL::query("DELETE FROM bigtree_users WHERE id = ?", $victim);
			SQL::query("DELETE FROM bigtree_users WHERE id = ?", $attacker);
		}
	}

	// — DB-seeded: missing fields short-circuit before any lookup. —

	function test_authreset_missing_fields_rejected() {
		if (_authreset_db_unavailable()) {
			return;
		}

		$svc = new AuthService();

		T::throws(function () use ($svc) {
			$svc->resetPassword(_authreset_make_request("", "SomePass-CI-2026!"));
		}, BadRequestException::class, "missing token throws BadRequestException");

		T::throws(function () use ($svc) {
			$svc->resetPassword(_authreset_make_request("sometoken." . (time() + 3600), ""));
		}, BadRequestException::class, "missing password throws BadRequestException");
	}
