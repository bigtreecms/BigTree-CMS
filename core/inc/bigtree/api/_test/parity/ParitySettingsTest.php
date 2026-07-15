<?php
	/**
	 * L1 parity: SettingService value edit + gates (p0/settings.md).
	 */

	use BigTree\Services\SettingService;
	use BigTree\Api\Exceptions\AuthorizationException;

	function test_parity_settings_update_text_value() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new SettingService();
		$admin_id = parity_seed_user(["level" => 1]);
		$id = "zz_parity_notice_" . bin2hex(random_bytes(3));

		try {
			// Create definition as developer
			$dev_id = parity_seed_user(["level" => 2]);
			$create = $svc->create(parity_request($dev_id, 2, [], [
				"id" => $id,
				"name" => "QA Site Notice",
				"type" => "text",
				"locked" => false,
				"system" => false,
				"encrypted" => false,
			]));
			T::equals($create->status, 201, "setting definition created");

			$res = $svc->update(parity_request($admin_id, 1, ["id" => $id], [
				"value" => "Hello from parity",
			]));
			T::equals($res->status, 200, "value update 200");

			$raw = SQL::fetchSingle("SELECT value FROM bigtree_settings WHERE id = ?", $id);
			$decoded = json_decode((string)$raw, true);
			// value may be stored as JSON string "Hello..." or raw
			if ($decoded === null && $raw !== null && $raw !== "") {
				// plain string storage without json - accept either
				T::ok(
					strpos((string)$raw, "Hello from parity") !== false,
					"value stored (raw contains text)"
				);
			} else {
				T::equals($decoded, "Hello from parity", "value stored as JSON string");
			}
		} finally {
			parity_delete_setting($id);
			parity_delete_users($admin_id, $dev_id ?? 0);
		}
	}

	function test_parity_settings_locked_requires_developer() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new SettingService();
		$admin_id = parity_seed_user(["level" => 1]);
		$dev_id = parity_seed_user(["level" => 2]);
		$id = "zz_parity_locked_" . bin2hex(random_bytes(3));

		try {
			$svc->create(parity_request($dev_id, 2, [], [
				"id" => $id,
				"name" => "Locked Notice",
				"type" => "text",
				"locked" => true,
				"system" => false,
			]));

			T::throws(
				function () use ($svc, $admin_id, $id) {
					$svc->update(parity_request($admin_id, 1, ["id" => $id], [
						"value" => "nope",
					]));
				},
				AuthorizationException::class,
				"level 1 cannot update locked setting"
			);

			// Developer can
			$res = $svc->update(parity_request($dev_id, 2, ["id" => $id], [
				"value" => "dev ok",
			]));
			T::equals($res->status, 200, "developer can update locked setting value");
		} finally {
			parity_delete_setting($id);
			parity_delete_users($admin_id, $dev_id);
		}
	}

	function test_parity_settings_system_denied() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new SettingService();
		$admin_id = parity_seed_user(["level" => 1]);
		$dev_id = parity_seed_user(["level" => 2]);
		$id = "zz_parity_system_" . bin2hex(random_bytes(3));

		try {
			$svc->create(parity_request($dev_id, 2, [], [
				"id" => $id,
				"name" => "Systemish",
				"type" => "text",
				"system" => true,
			]));

			T::throws(
				function () use ($svc, $admin_id, $id) {
					$svc->update(parity_request($admin_id, 1, ["id" => $id], [
						"value" => "nope",
					]));
				},
				AuthorizationException::class,
				"system setting value edit denied"
			);
		} finally {
			parity_delete_setting($id);
			parity_delete_users($admin_id, $dev_id);
		}
	}

	function test_parity_settings_editor_forbidden() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new SettingService();
		$editor_id = parity_seed_user(["level" => 0]);
		$dev_id = parity_seed_user(["level" => 2]);
		$id = "zz_parity_editor_" . bin2hex(random_bytes(3));

		try {
			$svc->create(parity_request($dev_id, 2, [], [
				"id" => $id,
				"name" => "Editor Blocked",
				"type" => "text",
			]));

			T::throws(
				function () use ($svc, $editor_id, $id) {
					$svc->update(parity_request($editor_id, 0, ["id" => $id], [
						"value" => "nope",
					]));
				},
				AuthorizationException::class,
				"level 0 cannot update setting values"
			);
		} finally {
			parity_delete_setting($id);
			parity_delete_users($editor_id, $dev_id);
		}
	}
