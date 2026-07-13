<?php
	/**
	 * PasskeyService CRUD against bigtree_user_passkeys.
	 * Skips when the DB is unavailable (same pattern as other DB-backed tests).
	 */

	use BigTree\Services\PasskeyService;

	function _passkey_service_skip_if_no_db() {
		try {
			SQL::fetchSingle("SELECT 1");
		} catch (Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return true;
		}

		// Need the passkeys table.
		try {
			SQL::fetchSingle("SELECT 1 FROM bigtree_user_passkeys LIMIT 1");
		} catch (Throwable $e) {
			echo "  (skipped — bigtree_user_passkeys unavailable: " . $e->getMessage() . ")\n";

			return true;
		}

		return false;
	}

	function test_passkey_service_crud_roundtrip() {
		if (_passkey_service_skip_if_no_db()) {
			return;
		}

		$user = SQL::fetch("SELECT id FROM bigtree_users ORDER BY id ASC LIMIT 1");

		if (!$user) {
			echo "  (skipped — no users in bigtree_users)\n";

			return;
		}

		$user_id = (int) $user["id"];
		$credential_id = "test-cred-" . bin2hex(random_bytes(8));

		$id = PasskeyService::createPasskey(
			$user_id,
			$credential_id,
			"-----BEGIN PUBLIC KEY-----\ntest\n-----END PUBLIC KEY-----",
			0,
			"Test Passkey",
			"00000000-0000-0000-0000-000000000000",
			"internal"
		);

		T::ok($id, "createPasskey returns an id");

		$by_cred = PasskeyService::getPasskeyByCredentialId($credential_id);
		T::ok($by_cred && $by_cred["credential_id"] === $credential_id, "lookup by credential id");
		T::ok((int) $by_cred["user_id"] === $user_id, "joined user id matches");

		$all = PasskeyService::getUserPasskeys($user_id);
		$found = false;

		foreach ($all as $row) {
			if ((int) $row["id"] === (int) $id) {
				$found = true;
				break;
			}
		}

		T::ok($found, "getUserPasskeys includes the new passkey");

		PasskeyService::updatePasskeyUsed($id, 7);
		$after = PasskeyService::getPasskeyByCredentialId($credential_id);
		T::ok((int) $after["sign_count"] === 7, "updatePasskeyUsed bumps sign_count");

		PasskeyService::deletePasskey($id, $user_id);
		T::ok(!PasskeyService::getPasskeyByCredentialId($credential_id), "deletePasskey removes the row");
	}

	function test_passkey_service_enabled_is_bool() {
		// Pure config check — no DB required.
		$result = PasskeyService::passkeysEnabled();
		T::ok(is_bool($result), "passkeysEnabled returns a boolean");
	}
