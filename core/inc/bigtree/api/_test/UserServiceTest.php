<?php
	/**
	 * Characterization + boundary tests for the UserService privilege guards.
	 *
	 * UserService is the authorization boundary for user management. These tests
	 * PIN the CURRENT behavior of its cross-user invariants so a regression in any
	 * of them (each a silent privilege-escalation risk) fails loudly:
	 *  - create level clamp           max(0, min(actor.level, requested))
	 *  - update refuses higher-level target / re-clamps level / self can't self-elevate
	 *  - token_version bump on structural change (level/permissions), not on cosmetic
	 *  - password self-vs-higher-level guard (+ current-password check on self)
	 *  - delete: can't delete self, can't delete higher-level
	 *  - removeTwoFactor: can't act on a higher-level user
	 *
	 * This file does NOT change UserService. Where a guard is pure arithmetic the
	 * boundary matrix is also pinned with a no-DB test (test_userservice_clamp_*).
	 * Every method that touches a bigtree_users row is exercised against a real
	 * seeded row and cleaned up in a finally block; the whole suite skips cleanly
	 * when no database is configured in this harness.
	 *
	 * SUSPECTED BUG (escape hatch — NOT fixed here, see report):
	 *  test_userservice_create_copies_permissions_verbatim and
	 *  test_userservice_update_copies_permissions_verbatim document that a lower-
	 *  level actor can assign an ARBITRARY permissions blob (no subset check
	 *  against the actor's own permissions). The audit flagged this as a Low-
	 *  severity gap. The tests lock the current (permissive) behavior.
	 */

	use BigTree\Services\UserService;
	use BigTree\Api\Request;
	use BigTree\Api\Exceptions\AuthorizationException;
	use BigTree\Api\Exceptions\BadRequestException;

	/** True when a bigtree_users row can be read (DB is wired up in this harness). */
	function _userservice_db_available(): bool {
		try {
			SQL::fetchSingle("SELECT id FROM bigtree_users LIMIT 1");

			return true;
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return false;
		}
	}

	/**
	 * Insert a throwaway bigtree_users row supplying every NOT NULL / no-default
	 * column (mirrors the CI "Seed smoke-test admin user" INSERT). Returns the id.
	 * Caller is responsible for deleting it (finally block).
	 */
	function _userservice_seed(array $overrides = []): int {
		$row = array_merge([
			"email" => "zz_userservice_" . uniqid() . "@test.local",
			"password" => password_hash("InitialPass-1!", PASSWORD_DEFAULT),
			"new_hash" => "on",
			"2fa_secret" => "",
			"2fa_login_token" => "",
			"name" => "ZZ UserService Fixture",
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

	/** Build a minimal Request with an authenticated actor + optional route/body. */
	function _userservice_request(int $actor_id, int $actor_level, array $route_params = [], array $body = []): Request {
		$req = new Request();
		$req->user = (object)[
			"id" => $actor_id,
			"level" => $actor_level,
			"permissions" => [],
		];
		$req->route_params = $route_params;
		$req->body = $body;

		return $req;
	}

	/** Read a single column straight from the row (bypasses presentFull gating). */
	function _userservice_col(int $id, string $col) {
		return SQL::fetchSingle("SELECT `$col` FROM bigtree_users WHERE id = ?", $id);
	}

	// ───────────────────────── pure clamp boundary matrix ─────────────────────────
	// The level clamp is inline in create()/update() as max(0, min(actor, requested)).
	// These no-DB tests pin the boundary matrix of that exact formula cheaply.

	function test_userservice_clamp_boundary_matrix() {
		$clamp = function (int $actor, int $requested): int {

			return max(0, min($actor, $requested));
		};

		T::equals($clamp(2, 1), 1, "actor 2 granting 1 → 1 (below actor, allowed)");
		T::equals($clamp(2, 2), 2, "actor 2 granting 2 → 2 (equal to actor, allowed)");
		T::equals($clamp(1, 2), 1, "actor 1 granting 2 → clamped to 1 (cannot exceed actor)");
		T::equals($clamp(0, 5), 0, "actor 0 granting 5 → clamped to 0 (level-0 cannot elevate)");
		T::equals($clamp(2, -3), 0, "negative requested → floored at 0");
	}

	// ───────────────────────────────── create ─────────────────────────────────

	function test_userservice_create_level_clamped_to_actor() {
		if (!_userservice_db_available()) {

			return;
		}

		$svc = new UserService();
		$created_id = null;

		try {
			// Actor level 1 attempts to create a level-2 user → must clamp to 1.
			$req = _userservice_request(999999, 1, [], [
				"email" => "zz_userservice_create_" . uniqid() . "@test.local",
				"name" => "Clamp Target",
				"level" => 2,
			]);
			$res = $svc->create($req);

			T::equals($res->status, 201, "create returns 201");

			$created_id = (int)$res->body["data"]["id"];
			T::equals((int)_userservice_col($created_id, "level"), 1, "requested level 2 clamped to actor level 1");
		} finally {
			if ($created_id) {
				SQL::query("DELETE FROM bigtree_users WHERE id = ?", $created_id);
			}
		}
	}

	function test_userservice_create_level_zero_actor_cannot_create_above_zero() {
		if (!_userservice_db_available()) {

			return;
		}

		$svc = new UserService();
		$created_id = null;

		try {
			$req = _userservice_request(999999, 0, [], [
				"email" => "zz_userservice_create0_" . uniqid() . "@test.local",
				"name" => "Zero Actor Target",
				"level" => 5,
			]);
			$res = $svc->create($req);
			$created_id = (int)$res->body["data"]["id"];

			T::equals((int)_userservice_col($created_id, "level"), 0, "level-0 actor: requested level 5 clamped to 0");
		} finally {
			if ($created_id) {
				SQL::query("DELETE FROM bigtree_users WHERE id = ?", $created_id);
			}
		}
	}

	function test_userservice_create_level_below_actor_preserved() {
		if (!_userservice_db_available()) {

			return;
		}

		$svc = new UserService();
		$created_id = null;

		try {
			// Actor level 2 creating a level-1 user → preserved (not over-clamped).
			$req = _userservice_request(999999, 2, [], [
				"email" => "zz_userservice_below_" . uniqid() . "@test.local",
				"name" => "Below Actor Target",
				"level" => 1,
			]);
			$res = $svc->create($req);
			$created_id = (int)$res->body["data"]["id"];

			T::equals((int)_userservice_col($created_id, "level"), 1, "level below actor is preserved");
		} finally {
			if ($created_id) {
				SQL::query("DELETE FROM bigtree_users WHERE id = ?", $created_id);
			}
		}
	}

	/**
	 * SUSPECTED BUG (documented, not fixed): create copies the permissions array
	 * verbatim with NO subset check against the actor's own permissions, so a
	 * lower-privilege actor can mint a user with an arbitrary permissions blob.
	 * This test pins the CURRENT (permissive) behavior.
	 */
	function test_userservice_create_copies_permissions_verbatim() {
		if (!_userservice_db_available()) {

			return;
		}

		$svc = new UserService();
		$created_id = null;

		try {
			$perms = ["module" => [5 => "p"]]; // actor has NO module perms of its own
			$req = _userservice_request(999999, 1, [], [
				"email" => "zz_userservice_perm_" . uniqid() . "@test.local",
				"name" => "Perm Copy Target",
				"level" => 1,
				"permissions" => $perms,
			]);
			// Actor level 1 ⇒ presentFull is_self_or_admin true ⇒ permissions returned.
			$res = $svc->create($req);
			$created_id = (int)$res->body["data"]["id"];

			T::equals($res->body["data"]["permissions"], $perms, "permissions copied verbatim — NO subset check (suspected bug, pinned)");
		} finally {
			if ($created_id) {
				SQL::query("DELETE FROM bigtree_users WHERE id = ?", $created_id);
			}
		}
	}

	// ───────────────────────────────── update ─────────────────────────────────

	function test_userservice_update_refuses_higher_level_target() {
		if (!_userservice_db_available()) {

			return;
		}

		$svc = new UserService();
		$target_id = _userservice_seed(["level" => 2]);

		try {
			// Actor level 1 attempting to modify a level-2 user → 403.
			$req = _userservice_request(999999, 1, ["id" => $target_id], ["name" => "Renamed"]);

			T::throws(function () use ($svc, $req) {
				$svc->update($req);
			}, AuthorizationException::class, "update refuses a higher-level target");

			T::equals(_userservice_col($target_id, "name"), "ZZ UserService Fixture", "higher-level target left unchanged");
		} finally {
			SQL::query("DELETE FROM bigtree_users WHERE id = ?", $target_id);
		}
	}

	function test_userservice_update_clamps_level_elevation() {
		if (!_userservice_db_available()) {

			return;
		}

		$svc = new UserService();
		$target_id = _userservice_seed(["level" => 0]);

		try {
			// Actor level 1 raising a level-0 target to level 5 → clamped to 1.
			$req = _userservice_request(999999, 1, ["id" => $target_id], ["level" => 5]);
			$svc->update($req);

			T::equals((int)_userservice_col($target_id, "level"), 1, "elevation request clamped to actor level 1");
		} finally {
			SQL::query("DELETE FROM bigtree_users WHERE id = ?", $target_id);
		}
	}

	function test_userservice_update_self_cannot_change_own_level() {
		if (!_userservice_db_available()) {

			return;
		}

		$svc = new UserService();
		$self_id = _userservice_seed(["level" => 1]);

		try {
			// is_self ⇒ the level/permissions block is skipped entirely.
			$req = _userservice_request($self_id, 1, ["id" => $self_id], ["level" => 2, "name" => "Self Rename"]);
			$svc->update($req);

			T::equals((int)_userservice_col($self_id, "level"), 1, "self-update cannot raise own level");
			T::equals(_userservice_col($self_id, "name"), "Self Rename", "non-level self fields still update");
		} finally {
			SQL::query("DELETE FROM bigtree_users WHERE id = ?", $self_id);
		}
	}

	function test_userservice_update_token_version_bumps_on_level_change() {
		if (!_userservice_db_available()) {

			return;
		}

		$svc = new UserService();
		$target_id = _userservice_seed(["level" => 0, "token_version" => 7]);

		try {
			$req = _userservice_request(999999, 2, ["id" => $target_id], ["level" => 1]);
			$svc->update($req);

			T::equals((int)_userservice_col($target_id, "token_version"), 8, "level change bumps token_version 7 → 8");
		} finally {
			SQL::query("DELETE FROM bigtree_users WHERE id = ?", $target_id);
		}
	}

	function test_userservice_update_token_version_bumps_on_permissions_change() {
		if (!_userservice_db_available()) {

			return;
		}

		$svc = new UserService();
		$target_id = _userservice_seed(["level" => 0, "token_version" => 3]);

		try {
			$req = _userservice_request(999999, 2, ["id" => $target_id], ["permissions" => ["module" => [1 => "e"]]]);
			$svc->update($req);

			T::equals((int)_userservice_col($target_id, "token_version"), 4, "permissions change bumps token_version 3 → 4");
		} finally {
			SQL::query("DELETE FROM bigtree_users WHERE id = ?", $target_id);
		}
	}

	function test_userservice_update_token_version_no_bump_on_cosmetic_change() {
		if (!_userservice_db_available()) {

			return;
		}

		$svc = new UserService();
		$target_id = _userservice_seed(["level" => 0, "token_version" => 5]);

		try {
			// Name-only change is non-structural → no token_version bump.
			$req = _userservice_request(999999, 2, ["id" => $target_id], ["name" => "Cosmetic Rename"]);
			$svc->update($req);

			T::equals(_userservice_col($target_id, "name"), "Cosmetic Rename", "cosmetic field updated");
			T::equals((int)_userservice_col($target_id, "token_version"), 5, "cosmetic change does NOT bump token_version");
		} finally {
			SQL::query("DELETE FROM bigtree_users WHERE id = ?", $target_id);
		}
	}

	function test_userservice_update_token_version_no_bump_when_level_unchanged() {
		if (!_userservice_db_available()) {

			return;
		}

		$svc = new UserService();
		$target_id = _userservice_seed(["level" => 1, "token_version" => 2]);

		try {
			// Submitting the SAME level (1) is not a structural change → no bump.
			$req = _userservice_request(999999, 2, ["id" => $target_id], ["level" => 1]);
			$svc->update($req);

			T::equals((int)_userservice_col($target_id, "token_version"), 2, "re-submitting the same level does not bump token_version");
		} finally {
			SQL::query("DELETE FROM bigtree_users WHERE id = ?", $target_id);
		}
	}

	/**
	 * SUSPECTED BUG (documented, not fixed): update copies the permissions array
	 * verbatim with no subset check against the actor's own permissions. Pins the
	 * current permissive behavior for a non-self target the actor outranks.
	 */
	function test_userservice_update_copies_permissions_verbatim() {
		if (!_userservice_db_available()) {

			return;
		}

		$svc = new UserService();
		$target_id = _userservice_seed(["level" => 0]);

		try {
			$perms = ["module" => [9 => "p"]];
			$req = _userservice_request(999999, 1, ["id" => $target_id], ["permissions" => $perms]);
			$res = $svc->update($req); // actor level 1 ⇒ permissions returned by presentFull

			T::equals($res->body["data"]["permissions"], $perms, "permissions copied verbatim on update — NO subset check (suspected bug, pinned)");
		} finally {
			SQL::query("DELETE FROM bigtree_users WHERE id = ?", $target_id);
		}
	}

	// ───────────────────────────────── delete ─────────────────────────────────

	function test_userservice_delete_self_rejected() {
		// Self-delete check fires BEFORE loadOrFail, so this needs no DB row.
		$svc = new UserService();
		$req = _userservice_request(12345, 2, ["id" => 12345]);

		T::throws(function () use ($svc, $req) {
			$svc->delete($req);
		}, BadRequestException::class, "cannot delete your own account");
	}

	function test_userservice_delete_higher_level_rejected() {
		if (!_userservice_db_available()) {

			return;
		}

		$svc = new UserService();
		$target_id = _userservice_seed(["level" => 2]);

		try {
			$req = _userservice_request(999999, 1, ["id" => $target_id]);

			T::throws(function () use ($svc, $req) {
				$svc->delete($req);
			}, AuthorizationException::class, "cannot delete a higher-level user");

			T::ok(SQL::exists("bigtree_users", ["id" => $target_id]), "higher-level target not deleted");
		} finally {
			SQL::query("DELETE FROM bigtree_users WHERE id = ?", $target_id);
		}
	}

	function test_userservice_delete_lower_level_succeeds() {
		if (!_userservice_db_available()) {

			return;
		}

		$svc = new UserService();
		$target_id = _userservice_seed(["level" => 0]);
		$cleaned = false;

		try {
			$req = _userservice_request(999999, 2, ["id" => $target_id]);
			$res = $svc->delete($req);

			T::equals($res->status, 204, "delete of lower-level user returns 204");
			T::ok(!SQL::exists("bigtree_users", ["id" => $target_id]), "lower-level target row removed");
			$cleaned = true;
		} finally {
			if (!$cleaned) {
				SQL::query("DELETE FROM bigtree_users WHERE id = ?", $target_id);
			}
		}
	}

	// ──────────────────────────────── password ────────────────────────────────

	function test_userservice_password_higher_level_rejected() {
		if (!_userservice_db_available()) {

			return;
		}

		$svc = new UserService();
		$target_id = _userservice_seed(["level" => 2]);

		try {
			$req = _userservice_request(999999, 1, ["id" => $target_id], ["new_password" => "BrandNew-Pass-1!"]);

			T::throws(function () use ($svc, $req) {
				$svc->password($req);
			}, AuthorizationException::class, "cannot change a higher-level user's password");
		} finally {
			SQL::query("DELETE FROM bigtree_users WHERE id = ?", $target_id);
		}
	}

	function test_userservice_password_lower_level_succeeds_without_current() {
		if (!_userservice_db_available()) {

			return;
		}

		$svc = new UserService();
		$target_id = _userservice_seed(["level" => 0, "token_version" => 1]);

		try {
			// Acting on someone you outrank: no current_password required.
			$req = _userservice_request(999999, 2, ["id" => $target_id], ["new_password" => "BrandNew-Pass-1!"]);
			$res = $svc->password($req);

			T::equals($res->status, 204, "admin password reset of lower-level user returns 204");
			T::ok(password_verify("BrandNew-Pass-1!", _userservice_col($target_id, "password")), "new password persisted");
			T::equals((int)_userservice_col($target_id, "token_version"), 2, "password change bumps token_version");
		} finally {
			SQL::query("DELETE FROM bigtree_users WHERE id = ?", $target_id);
		}
	}

	function test_userservice_password_self_requires_correct_current() {
		if (!_userservice_db_available()) {

			return;
		}

		$svc = new UserService();
		$self_id = _userservice_seed(["level" => 1, "password" => password_hash("CurrentPass-1!", PASSWORD_DEFAULT)]);

		try {
			// Self change with WRONG current password → 403.
			$bad = _userservice_request($self_id, 1, ["id" => $self_id], [
				"new_password" => "BrandNew-Pass-1!",
				"current_password" => "WrongPass-1!",
			]);

			T::throws(function () use ($svc, $bad) {
				$svc->password($bad);
			}, AuthorizationException::class, "self password change with wrong current password is rejected");

			T::ok(password_verify("CurrentPass-1!", _userservice_col($self_id, "password")), "password unchanged after failed self-change");

			// Self change with CORRECT current password → 204.
			$good = _userservice_request($self_id, 1, ["id" => $self_id], [
				"new_password" => "BrandNew-Pass-1!",
				"current_password" => "CurrentPass-1!",
			]);
			$res = $svc->password($good);

			T::equals($res->status, 204, "self password change with correct current password succeeds");
			T::ok(password_verify("BrandNew-Pass-1!", _userservice_col($self_id, "password")), "self password updated");
		} finally {
			SQL::query("DELETE FROM bigtree_users WHERE id = ?", $self_id);
		}
	}

	function test_userservice_password_missing_new_password_rejected() {
		if (!_userservice_db_available()) {

			return;
		}

		$svc = new UserService();
		$target_id = _userservice_seed(["level" => 0]);

		try {
			// Authorized actor but no new_password supplied → 400 (not an auth error).
			$req = _userservice_request(999999, 2, ["id" => $target_id], []);

			T::throws(function () use ($svc, $req) {
				$svc->password($req);
			}, BadRequestException::class, "missing new_password is a 400");
		} finally {
			SQL::query("DELETE FROM bigtree_users WHERE id = ?", $target_id);
		}
	}

	// ───────────────────────────── removeTwoFactor ─────────────────────────────

	function test_userservice_remove_two_factor_higher_level_rejected() {
		if (!_userservice_db_available()) {

			return;
		}

		$svc = new UserService();
		$target_id = _userservice_seed(["level" => 2, "2fa_secret" => "SECRETSEED"]);

		try {
			$req = _userservice_request(999999, 1, ["id" => $target_id]);

			T::throws(function () use ($svc, $req) {
				$svc->removeTwoFactor($req);
			}, AuthorizationException::class, "cannot remove 2FA on a higher-level user");

			T::equals(_userservice_col($target_id, "2fa_secret"), "SECRETSEED", "higher-level target's 2FA secret untouched");
		} finally {
			SQL::query("DELETE FROM bigtree_users WHERE id = ?", $target_id);
		}
	}

	function test_userservice_remove_two_factor_lower_level_succeeds() {
		if (!_userservice_db_available()) {

			return;
		}

		$svc = new UserService();
		$target_id = _userservice_seed(["level" => 0, "2fa_secret" => "SECRETSEED"]);

		try {
			$req = _userservice_request(999999, 2, ["id" => $target_id]);
			$res = $svc->removeTwoFactor($req);

			T::equals($res->status, 200, "removeTwoFactor returns 200");
			T::equals($res->body["data"]["two_factor_enabled"], false, "response reports 2FA disabled");
			T::equals(_userservice_col($target_id, "2fa_secret"), "", "lower-level target's 2FA secret cleared");
		} finally {
			SQL::query("DELETE FROM bigtree_users WHERE id = ?", $target_id);
		}
	}

	function test_userservice_ai_update_user_rechecks_email_at_approval() {
		if (!_userservice_db_available()) {

			return;
		}

		$svc = new UserService();
		$taken = "zz_taken_" . uniqid() . "@test.local";
		$other_id = _userservice_seed(["email" => $taken, "level" => 0]);
		$target_id = _userservice_seed(["level" => 0]);
		$original_email = _userservice_col($target_id, "email");
		$actor = (object)["id" => 999999, "level" => 2, "permissions" => []];

		try {
			// A payload staged before $taken existed must not write a duplicate email
			// once another account holds it — the collision is re-checked at approval.
			$collision = $svc->aiUpdateUser([
				"user_id" => $target_id,
				"changes" => ["email" => $taken],
			], $actor);

			T::equals($collision["mode"], "error", "email collision at approval returns mode:error");
			T::ok(strpos($collision["message"], "no longer available") !== false, "message mirrors the create path");
			T::equals(_userservice_col($target_id, "email"), $original_email, "target email is left unchanged on collision");

			// A malformed email is also rejected rather than written blind.
			$invalid = $svc->aiUpdateUser([
				"user_id" => $target_id,
				"changes" => ["email" => "not-an-email"],
			], $actor);
			T::equals($invalid["mode"], "error", "invalid email at approval returns mode:error");

			// A free address still applies, so the re-check doesn't block valid updates.
			$fresh = "zz_fresh_" . uniqid() . "@test.local";
			$ok = $svc->aiUpdateUser([
				"user_id" => $target_id,
				"changes" => ["email" => $fresh],
			], $actor);
			T::equals($ok["mode"], "updated", "a still-available email applies");
			T::equals(_userservice_col($target_id, "email"), $fresh, "the new email was written");
		} finally {
			SQL::query("DELETE FROM bigtree_users WHERE id IN (?, ?)", $target_id, $other_id);
		}
	}
