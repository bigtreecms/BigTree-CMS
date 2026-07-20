<?php
	/**
	 * A8: get_pending_changes must not lose an editor's own older draft.
	 *
	 * aiPendingChanges scanned the 500 newest rows and *then* filtered to
	 * mine-or-publishable, so on a busy site a user's older draft fell outside the
	 * scan window and the assistant reported it didn't exist. The owner side of the
	 * filter now runs in SQL, so the cap bounds results rather than visibility.
	 */

	use BigTree\Services\PendingChangeService;

	/** Insert a pending-change row directly, returning its id. */
	function parity_pending_window_row(int $user_id, string $title, string $date): int {

		return (int)SQL::insert("bigtree_pending_changes", [
			"user" => $user_id,
			"date" => $date,
			"title" => $title,
			"table" => "bigtree_pages",
			"changes" => "{}",
			"mtm_changes" => "{}",
			"tags_changes" => "{}",
			"open_graph_changes" => "{}",
			"item_id" => null,
			"type" => "NEW",
			"module" => "",
			"pending_page_parent" => 0,
		]);
	}

	function test_parity_ai_pending_changes_finds_an_owners_older_draft_beyond_the_scan_window() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PendingChangeService();
		$owner_id = parity_seed_user(["level" => 0]);
		$noise_id = parity_seed_user(["level" => 0]);
		$owner = (object)["id" => $owner_id, "level" => 0, "permissions" => []];
		$ids = [];

		try {
			// The owner's draft is the oldest row in the table…
			$mine = parity_pending_window_row($owner_id, "zz Owner's older draft", "2000-01-01 00:00:00");
			$ids[] = $mine;

			// …buried under more than a full scan window of newer rows they neither
			// own nor can publish.
			for ($i = 0; $i < 520; $i++) {
				$ids[] = parity_pending_window_row($noise_id, "zz Noise {$i}", "2030-01-01 00:00:00");
			}

			$result = $svc->aiPendingChanges(20, $owner);
			$found = false;

			foreach ($result["pending_changes"] as $row) {
				if ((int)$row["id"] === $mine) {
					$found = true;
					T::ok($row["mine"], "the row is tagged as the user's own");
				}
			}

			T::ok($found, "the owner's draft is found despite 520 newer rows they can't see");
			T::ok(count($result["pending_changes"]) <= 20, "the limit still bounds the result");
		} finally {
			if ($ids) {
				SQL::query("DELETE FROM bigtree_pending_changes WHERE id IN (" . implode(",", array_map("intval", $ids)) . ")");
			}

			parity_delete_users($owner_id, $noise_id);
		}
	}
