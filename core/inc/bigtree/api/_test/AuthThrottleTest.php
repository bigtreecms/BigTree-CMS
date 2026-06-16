<?php
	/**
	 * AuthService — login brute-force throttle coverage.
	 *
	 * Characterization tests for recordFailedAttempt: the per-user ban threshold
	 * ("ban at >= count failures in the window"), ban refresh-not-duplicate on
	 * repeated failures, and the int-cast guard that neutralizes hostile policy
	 * values interpolated into INTERVAL clauses. These pin current behavior; they
	 * do NOT change AuthService. A real flaw surfaced here is marked SUSPECTED-BUG
	 * for a dedicated fix plan.
	 *
	 * recordFailedAttempt is private and DB-backed (it writes bigtree_login_attempts
	 * and bigtree_login_bans), so these seed a throwaway user, drive the method via
	 * reflection, and clean up every seeded attempt/ban row in finally. They skip
	 * when the DB is unavailable (CI runs them). The threshold policy lives in the
	 * global $bigtree["security-policy"]; each test sets and restores it.
	 *
	 * NOTE on the threshold: recordFailedAttempt inserts the current attempt FIRST,
	 * then counts attempts in the window and bans when count >= policy count. So a
	 * policy count of N bans on the Nth consecutive failure.
	 */

	use BigTree\Services\AuthService;

	/** Insert a throwaway bigtree_users row (all NOT-NULL columns); return id. */
	function _auththrottle_insert_user(string $email): int {
		return (int)SQL::insert("bigtree_users", [
			"email" => $email,
			"password" => password_hash("Throttle-CI-2026!", PASSWORD_DEFAULT),
			"new_hash" => "on",
			"2fa_secret" => "",
			"2fa_login_token" => "",
			"name" => "Auth Throttle Test",
			"company" => "",
			"level" => 0,
			"permissions" => "{}",
			"alerts" => "",
			"daily_digest" => "",
			"timezone" => "UTC",
			"change_password_hash" => "",
			"token_version" => 1,
		]);
	}

	/** Skip helper — returns true (and prints) when the DB is unreachable. */
	function _auththrottle_db_unavailable(): bool {
		try {
			SQL::fetchSingle("SELECT id FROM bigtree_users LIMIT 1");

			return false;
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return true;
		}
	}

	/** Invoke the private recordFailedAttempt($ip, $user_id). */
	function _auththrottle_record($svc, $ip, $user_id): void {
		$ref = new ReflectionMethod(AuthService::class, "recordFailedAttempt");
		$ref->setAccessible(true);
		$ref->invoke($svc, $ip, $user_id);
	}

	/** Delete all attempt/ban rows for the given user id + ip (cleanup). */
	function _auththrottle_cleanup(int $uid, int $ip): void {
		SQL::query("DELETE FROM bigtree_login_attempts WHERE user = ?", $uid);
		SQL::query("DELETE FROM bigtree_login_bans WHERE user = ?", $uid);
		SQL::query("DELETE FROM bigtree_login_attempts WHERE ip = ?", $ip);
		SQL::query("DELETE FROM bigtree_login_bans WHERE ip = ?", $ip);
	}

	// — Below threshold: no ban. At threshold: exactly one ban. —

	function test_auththrottle_user_ban_at_threshold() {
		if (_auththrottle_db_unavailable()) {
			return;
		}

		global $bigtree;
		$saved_policy = $bigtree["security-policy"] ?? null;

		$svc = new AuthService();
		$uid = _auththrottle_insert_user("ZZ_auththrottle_user_" . uniqid() . "@ci.local");
		// A test-only IP unlikely to collide with real traffic.
		$ip = ip2long("203.0.113.7");

		try {
			// Per-user policy: ban after 3 failures within a 30-minute window, 60-min ban.
			// IP policy left empty so only the user branch can produce a ban.
			$bigtree["security-policy"] = [
				"user_fails" => ["count" => 3, "time" => 30, "ban" => 60],
				"ip_fails" => ["count" => "", "time" => "", "ban" => ""],
			];

			// First two failures: below threshold → no ban yet.
			_auththrottle_record($svc, $ip, $uid);
			_auththrottle_record($svc, $ip, $uid);
			$ban = SQL::fetch("SELECT * FROM bigtree_login_bans WHERE user = ?", $uid);
			T::ok($ban === false || $ban === null, "no ban after 2 failures (below threshold of 3)");

			$attempts = (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_login_attempts WHERE user = ?", $uid);
			T::equals($attempts, 2, "two attempts recorded before threshold");

			// Third failure: count(3) >= threshold(3) → ban created.
			_auththrottle_record($svc, $ip, $uid);
			$bans = SQL::fetchAll("SELECT * FROM bigtree_login_bans WHERE user = ?", $uid);
			T::equals(count($bans), 1, "exactly one ban created at the threshold");
			T::ok(strtotime($bans[0]["expires"]) > time(), "the ban's expiry is in the future");
		} finally {
			_auththrottle_cleanup($uid, (int)$ip);
			SQL::query("DELETE FROM bigtree_users WHERE id = ?", $uid);
			$bigtree["security-policy"] = $saved_policy;
		}
	}

	// — Repeated failures extend the existing ban rather than inserting duplicates. —

	function test_auththrottle_repeated_failures_extend_not_duplicate() {
		if (_auththrottle_db_unavailable()) {
			return;
		}

		global $bigtree;
		$saved_policy = $bigtree["security-policy"] ?? null;

		$svc = new AuthService();
		$uid = _auththrottle_insert_user("ZZ_auththrottle_ext_" . uniqid() . "@ci.local");
		$ip = ip2long("203.0.113.8");

		try {
			// Low threshold so the first failure already bans.
			$bigtree["security-policy"] = [
				"user_fails" => ["count" => 1, "time" => 30, "ban" => 60],
				"ip_fails" => ["count" => "", "time" => "", "ban" => ""],
			];

			_auththrottle_record($svc, $ip, $uid);
			$bans = SQL::fetchAll("SELECT * FROM bigtree_login_bans WHERE user = ?", $uid);
			T::equals(count($bans), 1, "ban created on the first failure at threshold 1");
			$first_ban_id = $bans[0]["id"];

			// Several more failures: still a single ban row (the existing one is extended).
			_auththrottle_record($svc, $ip, $uid);
			_auththrottle_record($svc, $ip, $uid);
			_auththrottle_record($svc, $ip, $uid);

			$bans_after = SQL::fetchAll("SELECT * FROM bigtree_login_bans WHERE user = ?", $uid);
			T::equals(count($bans_after), 1, "repeated failures do not duplicate the ban row");
			T::equals($bans_after[0]["id"], $first_ban_id, "the same ban row is reused (extended in place)");
		} finally {
			_auththrottle_cleanup($uid, (int)$ip);
			SQL::query("DELETE FROM bigtree_users WHERE id = ?", $uid);
			$bigtree["security-policy"] = $saved_policy;
		}
	}

	// — Hostile policy values: the int-cast neutralizes injection-shaped strings. —

	function test_auththrottle_hostile_policy_values_cast_safely() {
		if (_auththrottle_db_unavailable()) {
			return;
		}

		global $bigtree;
		$saved_policy = $bigtree["security-policy"] ?? null;

		$svc = new AuthService();
		$uid = _auththrottle_insert_user("ZZ_auththrottle_inj_" . uniqid() . "@ci.local");
		$ip = ip2long("203.0.113.9");

		try {
			// Injection-shaped, non-numeric policy values. The int-cast in
			// recordFailedAttempt turns each into a safe integer before it reaches
			// the interpolated INTERVAL clause, so the query must not error.
			//   (int)"1) MINUTE); DROP TABLE..." === 1
			//   (int)"5 OR 1=1"                  === 5
			//   (int)"not-a-number"              === 0
			$bigtree["security-policy"] = [
				"user_fails" => [
					"count" => "1) MINUTE); DROP TABLE bigtree_users; -- ",
					"time" => "30 UNION SELECT 1 -- ",
					"ban" => "60'; DROP TABLE bigtree_login_bans; -- ",
				],
				"ip_fails" => ["count" => "", "time" => "", "ban" => ""],
			];

			$threw = false;

			try {
				_auththrottle_record($svc, $ip, $uid);
			} catch (\Throwable $e) {
				$threw = true;
			}

			T::equals($threw, false, "hostile policy values do not raise an SQL error (int-cast guard holds)");

			// The seeded tables still exist and respond — no DROP took effect.
			T::ok(SQL::fetchSingle("SELECT id FROM bigtree_users WHERE id = ?", $uid) !== false, "bigtree_users table intact after hostile policy");
			$ban = SQL::fetch("SELECT * FROM bigtree_login_bans WHERE user = ?", $uid);

			// (int)"1) MINUTE)..." === 1, so the single recorded failure hits the
			// threshold and a sane ban is created with a future expiry.
			T::ok($ban !== false && $ban !== null, "the cast count (1) still produces a ban");
			T::ok(strtotime($ban["expires"]) > time(), "the ban expiry parses to a real future timestamp (sane interval)");
		} finally {
			_auththrottle_cleanup($uid, (int)$ip);
			SQL::query("DELETE FROM bigtree_users WHERE id = ?", $uid);
			$bigtree["security-policy"] = $saved_policy;
		}
	}

	// — Incomplete policy (not all three keys set): the branch is skipped entirely. —

	function test_auththrottle_incomplete_policy_no_ban() {
		if (_auththrottle_db_unavailable()) {
			return;
		}

		global $bigtree;
		$saved_policy = $bigtree["security-policy"] ?? null;

		$svc = new AuthService();
		$uid = _auththrottle_insert_user("ZZ_auththrottle_incomplete_" . uniqid() . "@ci.local");
		$ip = ip2long("203.0.113.10");

		try {
			// Only count + time set; ban missing → array_filter count !== 3 → branch skipped.
			$bigtree["security-policy"] = [
				"user_fails" => ["count" => 1, "time" => 30, "ban" => ""],
				"ip_fails" => ["count" => "", "time" => "", "ban" => ""],
			];

			_auththrottle_record($svc, $ip, $uid);
			_auththrottle_record($svc, $ip, $uid);

			$ban = SQL::fetch("SELECT * FROM bigtree_login_bans WHERE user = ?", $uid);
			T::ok($ban === false || $ban === null, "no ban when the user_fails policy is incomplete");

			// Attempts are still logged even when the ban branch is inactive.
			$attempts = (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_login_attempts WHERE user = ?", $uid);
			T::equals($attempts, 2, "attempts recorded even with an incomplete ban policy");
		} finally {
			_auththrottle_cleanup($uid, (int)$ip);
			SQL::query("DELETE FROM bigtree_users WHERE id = ?", $uid);
			$bigtree["security-policy"] = $saved_policy;
		}
	}
