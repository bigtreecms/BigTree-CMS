<?php
	/**
	 * Row-level permission + membership guard on AutoModuleService::reorder.
	 *
	 * reorder() writes the `position` column for request-supplied ids. Before this
	 * guard it did so for ANY id, with no check that the id is a real row of the
	 * module's table or that the user has row-level access to it — so a user with
	 * reorder access could rewrite the ordering of rows that group-based
	 * permissions (gbp) hide from them, or write positions onto ids belonging to
	 * an unrelated table. The fix fetches the candidate rows in one query and only
	 * repositions ids the user has publisher ("p") access to.
	 *
	 * The decision logic lives in the private helper filterReorderableIds(), which
	 * is pure (it calls PermissionService::userRowLevel, itself pure when the
	 * user's level/permissions are in-memory). The first tests reach it via
	 * reflection — no DB required, like MtmValidationTest. The final test seeds a
	 * throwaway table to prove the real SELECT * ... IN membership read pairs with
	 * the same helper end-to-end; it skips cleanly when no DB is reachable.
	 */

	use BigTree\Services\AutoModuleService;

	/** Build a gbp-enabled module config grouped on $group_field. */
	function _amr_module(string $id = "things", string $group_field = "category"): array {
		return [
			"id" => $id,
			"gbp" => [
				"enabled" => "on",
				"group_field" => $group_field,
			],
		];
	}

	/**
	 * A non-admin (level 0) user with explicit gbp access to specific group
	 * values on the module. Any group not listed resolves to "n" (hidden).
	 *
	 * @param array<string,string> $group_perms group value => "n"|"e"|"p"
	 */
	function _amr_user(string $module_id, array $group_perms): object {
		return (object)[
			"id" => 7,
			"level" => 0,
			"permissions" => [
				"module" => [$module_id => ""],
				"module_gbp" => [$module_id => $group_perms],
			],
		];
	}

	/** Invoke the private filterReorderableIds helper. */
	function _amr_filter(object $user, array $module, array $rows, array $ids): array {
		$svc = new AutoModuleService();
		$ref = new ReflectionMethod($svc, "filterReorderableIds");
		$ref->setAccessible(true);

		return $ref->invoke($svc, $user, $module, $rows, $ids);
	}

	function test_reorder_filters_to_publisher_rows_only() {
		$module = _amr_module("things", "category");

		// User is publisher on "alpha", but has no access ("n") to "beta".
		$user = _amr_user("things", ["alpha" => "p", "beta" => "n"]);

		$rows = [
			1 => ["id" => 1, "category" => "alpha"],
			2 => ["id" => 2, "category" => "beta"],
			3 => ["id" => 3, "category" => "alpha"],
		];

		$reorderable = _amr_filter($user, $module, $rows, [1, 2, 3]);

		T::ok(isset($reorderable[1]), "publisher row 1 (alpha) is reorderable");
		T::ok(!isset($reorderable[2]), "hidden row 2 (beta) is NOT reorderable");
		T::ok(isset($reorderable[3]), "publisher row 3 (alpha) is reorderable");
		T::equals(count($reorderable), 2, "only the two accessible rows pass the filter");
	}

	function test_reorder_ignores_non_member_ids() {
		$module = _amr_module("things", "category");
		$user = _amr_user("things", ["alpha" => "p"]);

		// Row 99 was requested but was NOT returned by the membership query
		// (not a member of the table) — it must be ignored, no row created.
		$rows = [
			1 => ["id" => 1, "category" => "alpha"],
		];

		$reorderable = _amr_filter($user, $module, $rows, [1, 99]);

		T::ok(isset($reorderable[1]), "member row 1 is reorderable");
		T::ok(!isset($reorderable[99]), "non-member id 99 is ignored");
		T::equals(count($reorderable), 1, "only the member row passes the filter");
	}

	function test_reorder_editor_access_is_not_enough() {
		$module = _amr_module("things", "category");

		// "e" (editor) is below publisher — reorder is publish-like, so editor
		// rows must NOT be reorderable, matching toggleFlag's "p" requirement.
		$user = _amr_user("things", ["alpha" => "e"]);

		$rows = [1 => ["id" => 1, "category" => "alpha"]];
		$reorderable = _amr_filter($user, $module, $rows, [1]);

		T::ok(!isset($reorderable[1]), "editor-only row is NOT reorderable (publisher required)");
		T::equals(count($reorderable), 0, "no rows pass with editor-only access");
	}

	function test_reorder_admin_can_reorder_all_members() {
		// Admin (level > 0) shortcuts userRowLevel to "p" regardless of gbp.
		$module = _amr_module("things", "category");
		$admin = (object)["id" => 1, "level" => 1, "permissions" => []];

		$rows = [
			1 => ["id" => 1, "category" => "alpha"],
			2 => ["id" => 2, "category" => "beta"],
		];

		$reorderable = _amr_filter($admin, $module, $rows, [1, 2]);

		T::equals(count($reorderable), 2, "admin may reorder every member row");
		T::ok(isset($reorderable[1]) && isset($reorderable[2]), "both member rows are reorderable for admin");
	}

	/**
	 * End-to-end against a real DB: seed a throwaway table, run the same
	 * SELECT * ... WHERE id IN read reorder() uses, feed the fetched rows through
	 * the helper, and assert the resulting positions. Skips when no DB.
	 */
	function test_reorder_membership_query_with_real_db() {
		try {
			SQL::fetchSingle("SELECT 1");
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return;
		}

		$table = "zz_amr_" . substr(str_replace(".", "", uniqid("", true)), 0, 12);

		try {
			SQL::query(
				"CREATE TABLE `$table` (
					`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
					`category` VARCHAR(64) NOT NULL DEFAULT '',
					`position` INT NOT NULL DEFAULT 0
				)"
			);

			$alpha1 = (int)SQL::insert($table, ["category" => "alpha", "position" => 0]);
			$beta = (int)SQL::insert($table, ["category" => "beta", "position" => 0]);
			$alpha2 = (int)SQL::insert($table, ["category" => "alpha", "position" => 0]);

			$module = _amr_module("things", "category");

			// Publisher on alpha; beta is hidden ("n").
			$user = _amr_user("things", ["alpha" => "p", "beta" => "n"]);

			// Request reorders all three, plus a non-member id, in this order.
			$ids = [$alpha2, $beta, $alpha1, 999999999];

			// Replicate reorder()'s membership read.
			$placeholders = implode(",", array_fill(0, count($ids), "?"));
			$fetched = SQL::fetchAll("SELECT * FROM `$table` WHERE id IN ($placeholders)", ...$ids);
			$rows = [];

			foreach ($fetched as $row) {
				$rows[(int)$row["id"]] = $row;
			}

			T::equals(count($rows), 3, "membership read returns only the 3 real rows (non-member 999999999 absent)");

			$reorderable = _amr_filter($user, $module, $rows, $ids);
			$pos = count($ids);

			foreach ($ids as $id) {
				if (isset($reorderable[$id])) {
					SQL::update($table, $id, ["position" => $pos--]);
				} else {
					$pos--;
				}
			}

			$beta_pos = (int)SQL::fetchSingle("SELECT position FROM `$table` WHERE id = ?", $beta);
			$alpha2_pos = (int)SQL::fetchSingle("SELECT position FROM `$table` WHERE id = ?", $alpha2);
			$alpha1_pos = (int)SQL::fetchSingle("SELECT position FROM `$table` WHERE id = ?", $alpha1);

			// beta is hidden → its position is left at the seeded 0 (never written).
			T::equals($beta_pos, 0, "hidden beta row position unchanged");

			// Accessible rows take their slot positions: alpha2 is first (pos 4),
			// alpha1 is third (pos 2) — the beta and non-member slots are consumed
			// but not written, so the accessible rows keep their requested order.
			T::equals($alpha2_pos, 4, "alpha2 (first requested) gets the top slot");
			T::equals($alpha1_pos, 2, "alpha1 (third requested) gets its slot");
			T::ok($alpha2_pos > $alpha1_pos, "accessible rows end up in the requested relative order");
		} finally {
			SQL::query("DROP TABLE IF EXISTS `$table`");
		}
	}
