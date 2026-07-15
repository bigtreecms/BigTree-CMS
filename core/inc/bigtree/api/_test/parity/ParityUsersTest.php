<?php
	/**
	 * L1 parity: UserService create/update/delete happy paths (p0/users.md).
	 *
	 * Privilege-boundary matrix is already covered by UserServiceTest; this suite
	 * pins DB outcomes for the admin rewrite golden path.
	 */

	use BigTree\Services\UserService;
	use BigTree\Api\Exceptions\ConflictException;
	use BigTree\Api\Exceptions\BadRequestException;

	function test_parity_users_create_hashes_password_and_clamps_level() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new UserService();
		$admin_id = parity_seed_user(["level" => 1, "name" => "Parity Admin Actor"]);
		$created_id = 0;

		try {
			$res = $svc->create(parity_request($admin_id, 1, [], [
				"email" => "zz_parity_create_" . uniqid() . "@test.local",
				"name" => "QA Editor",
				"company" => "Acme",
				"level" => 2, // request developer; actor is admin → clamp to 1
				"password" => "ValidPass1!",
				"timezone" => "America/New_York",
				"daily_digest" => true,
			]));
			T::equals($res->status, 201, "create returns 201");
			$data = parity_data($res);
			$created_id = (int)$data["id"];

			$row = SQL::fetch("SELECT * FROM bigtree_users WHERE id = ?", $created_id);
			T::equals((int)$row["level"], 1, "level clamped to actor max");
			T::ok(password_verify("ValidPass1!", $row["password"]), "password hashed verifiably");
			T::equals($row["new_hash"], "on", "new_hash flag set");
			T::equals($row["daily_digest"], "on", "daily_digest checkbox on");
			T::equals($row["timezone"], "America/New_York", "timezone stored");
			T::ok(strpos($row["name"], "QA Editor") !== false || $row["name"] === "QA Editor", "name stored");
		} finally {
			parity_delete_users($created_id, $admin_id);
		}
	}

	function test_parity_users_create_duplicate_email_conflict() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new UserService();
		$email = "zz_parity_dup_" . uniqid() . "@test.local";
		$existing = parity_seed_user(["email" => $email, "level" => 0]);
		$admin_id = parity_seed_user(["level" => 1]);

		try {
			T::throws(
				function () use ($svc, $admin_id, $email) {
					$svc->create(parity_request($admin_id, 1, [], [
						"email" => $email,
						"name" => "Dup",
						"password" => "ValidPass1!",
						"level" => 0,
					]));
				},
				ConflictException::class,
				"duplicate email → conflict"
			);
		} finally {
			parity_delete_users($existing, $admin_id);
		}
	}

	function test_parity_users_update_permissions_roundtrip() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new UserService();
		$admin_id = parity_seed_user(["level" => 1]);
		$target_id = parity_seed_user(["level" => 0, "name" => "Perm Target"]);

		try {
			$perms = [
				"page" => ["1" => "e", "0" => "n"],
				"module" => [parity_news_module_id() => "p"],
				"resources" => [],
				"module_gbp" => [],
			];
			$res = $svc->update(parity_request($admin_id, 1, ["id" => $target_id], [
				"name" => "Perm Target Updated",
				"permissions" => $perms,
			]));
			T::equals($res->status, 200, "update returns 200");

			$raw = SQL::fetchSingle("SELECT permissions FROM bigtree_users WHERE id = ?", $target_id);
			$decoded = is_string($raw) ? json_decode($raw, true) : $raw;
			// permissions may be double-encoded depending on SQL layer — normalize
			if (is_string($decoded)) {
				$decoded = json_decode($decoded, true);
			}
			T::ok(is_array($decoded), "permissions stored as array/json");
			T::equals($decoded["page"]["1"] ?? $decoded["page"][1] ?? null, "e", "page grant e stored");
			T::equals(
				$decoded["module"][parity_news_module_id()] ?? null,
				"p",
				"module publisher grant stored"
			);

			$name = SQL::fetchSingle("SELECT name FROM bigtree_users WHERE id = ?", $target_id);
			T::ok(strpos((string)$name, "Updated") !== false, "name updated");
		} finally {
			parity_delete_users($target_id, $admin_id);
		}
	}

	function test_parity_users_delete() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new UserService();
		$admin_id = parity_seed_user(["level" => 1]);
		$target_id = parity_seed_user(["level" => 0]);

		try {
			$res = $svc->delete(parity_request($admin_id, 1, ["id" => $target_id]));
			T::equals($res->status, 204, "delete returns 204");
			T::ok(!SQL::exists("bigtree_users", $target_id), "user row removed");
			$target_id = 0;
		} finally {
			parity_delete_users($target_id, $admin_id);
		}
	}

	function test_parity_users_cannot_delete_self() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new UserService();
		$admin_id = parity_seed_user(["level" => 1]);

		try {
			T::throws(
				function () use ($svc, $admin_id) {
					$svc->delete(parity_request($admin_id, 1, ["id" => $admin_id]));
				},
				BadRequestException::class,
				"cannot delete self"
			);
		} finally {
			parity_delete_users($admin_id);
		}
	}
