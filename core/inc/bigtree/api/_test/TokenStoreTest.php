<?php
	/**
	 * Refresh-token session classes. "Remember me" families get the 30-day
	 * sliding window; plain logins get the short window. Rotation must keep a
	 * family in its original class (derived from the presented row's lifetime,
	 * since there is no schema column for it).
	 *
	 * Needs one real user row for the FK; skips on an empty users table.
	 */

	use BigTree\Api\TokenStore;

	function _token_store_user_id() {
		try {
			$id = SQL::fetchSingle("SELECT id FROM bigtree_users ORDER BY id LIMIT 1");
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return null;
		}

		if (!$id) {
			echo "  (skipped — no users in bigtree_users)\n";

			return null;
		}

		return (int)$id;
	}

	function _token_store_lifetime($token_id) {
		$row = SQL::fetch("SELECT issued_at, expires_at FROM bigtree_refresh_tokens WHERE id = ?", $token_id);

		return strtotime($row["expires_at"]) - strtotime($row["issued_at"]);
	}

	function test_token_store_session_classes() {
		$user_id = _token_store_user_id();

		if (!$user_id) {
			return;
		}

		$families = [];

		try {
			$short = TokenStore::issueFamily($user_id, "127.0.0.1", "test");
			$families[] = $short["family_id"];
			T::equals(_token_store_lifetime($short["id"]), TokenStore::SHORT_TTL_SECONDS, "default family gets the short TTL");

			$long = TokenStore::issueFamily($user_id, "127.0.0.1", "test", true);
			$families[] = $long["family_id"];
			T::equals(_token_store_lifetime($long["id"]), TokenStore::TTL_SECONDS, "remembered family gets the 30-day TTL");

			$rotated_short = TokenStore::rotate($short["raw"], "127.0.0.1", "test");
			T::equals(
				_token_store_lifetime($rotated_short["new_token"]["id"]),
				TokenStore::SHORT_TTL_SECONDS,
				"rotation keeps a short family short"
			);

			$rotated_long = TokenStore::rotate($long["raw"], "127.0.0.1", "test");
			T::equals(
				_token_store_lifetime($rotated_long["new_token"]["id"]),
				TokenStore::TTL_SECONDS,
				"rotation keeps a remembered family remembered"
			);
		} finally {
			foreach ($families as $family_id) {
				SQL::query("DELETE FROM bigtree_refresh_tokens WHERE family_id = ?", $family_id);
			}
		}
	}
