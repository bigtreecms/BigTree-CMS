<?php
	/**
	 * JsonStore — the JSONDB CRUD-skeleton helper from API consolidation round 2
	 * (finding #2). Binds a store name + label and collapses the boilerplate the
	 * resource services (Callout, Template, Feed, FieldType) repeated verbatim:
	 *   - assertAbsent($id)  → ConflictException("<Label> <id> already exists") on a dup,
	 *   - assertExists($id)  → NotFoundException("<Label> <id> not found") when missing,
	 *   - deleteOrFail($id)  → assertExists + delete,
	 *   - reorder($ids)      → the descending position-- loop.
	 *
	 * The tests seed throwaway records in the modules store and clean them up; the
	 * miss paths only need the store reachable.
	 */

	use BigTree\Api\JsonStore;
	use BigTree\Api\Exceptions\ConflictException;
	use BigTree\Api\Exceptions\NotFoundException;

	/** True when the modules JSONDB store is reachable in this harness. */
	function _jsonstore_ready(): bool {
		try {
			BigTreeJSONDB::getAll("modules");

			return true;
		} catch (\Throwable $e) {
			echo "  (skipped — modules store unavailable: " . $e->getMessage() . ")\n";

			return false;
		}
	}

	function test_jsonstore_assert_absent_passes_and_conflicts() {
		if (!_jsonstore_ready()) {
			return;
		}

		$store = new JsonStore("modules", "Module");

		// A brand-new id is absent: no-op.
		$store->assertAbsent("jsonstore-zzz-" . uniqid());
		T::ok(true, "assertAbsent is a no-op when nothing uses the id");

		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_jsonstore_" . uniqid(),
				"route" => "zz-jsonstore-" . uniqid(),
			]);

			$threw = null;

			try {
				$store->assertAbsent($module_id);
			} catch (ConflictException $e) {
				$threw = $e;
			}

			T::ok($threw instanceof ConflictException, "assertAbsent throws Conflict for an id already in use");
			T::equals($threw->getMessage(), "Module $module_id already exists", "assertAbsent builds the '<label> <id> already exists' message");
			T::equals($threw->code_string, "duplicate_id", "assertAbsent uses the duplicate_id code");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}
	}

	function test_jsonstore_assert_exists_passes_and_throws() {
		if (!_jsonstore_ready()) {
			return;
		}

		$store = new JsonStore("modules", "Module");
		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_jsonstore_" . uniqid(),
				"route" => "zz-jsonstore-" . uniqid(),
			]);

			$store->assertExists($module_id);
			T::ok(true, "assertExists is a no-op when the record exists");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}

		T::throws(function () use ($store) {
			$store->assertExists("jsonstore-zzz-" . uniqid());
		}, NotFoundException::class, "assertExists throws NotFound for an id that does not exist");
	}

	function test_jsonstore_delete_or_fail_removes_and_throws() {
		if (!_jsonstore_ready()) {
			return;
		}

		$store = new JsonStore("modules", "Module");

		$module_id = BigTreeJSONDB::insert("modules", [
			"name" => "ZZ_jsonstore_" . uniqid(),
			"route" => "zz-jsonstore-" . uniqid(),
		]);

		$store->deleteOrFail($module_id);
		T::ok(!BigTreeJSONDB::exists("modules", $module_id), "deleteOrFail removes the record");

		// Deleting again now 404s rather than silently succeeding.
		T::throws(function () use ($store, $module_id) {
			$store->deleteOrFail($module_id);
		}, NotFoundException::class, "deleteOrFail throws NotFound when the record is already gone");
	}

	function test_jsonstore_reorder_assigns_descending_positions() {
		if (!_jsonstore_ready()) {
			return;
		}

		$store = new JsonStore("modules", "Module");
		$ids = [];

		try {
			foreach (["a", "b", "c"] as $tag) {
				$ids[] = BigTreeJSONDB::insert("modules", [
					"name" => "ZZ_jsonstore_{$tag}_" . uniqid(),
					"route" => "zz-jsonstore-$tag-" . uniqid(),
				]);
			}

			// A non-existent id mixed in is skipped without erroring.
			$store->reorder([$ids[0], "jsonstore-missing-" . uniqid(), $ids[1], $ids[2]]);

			// First id gets the highest position so a position-DESC sort reflects order.
			T::equals((int)BigTreeJSONDB::get("modules", $ids[0])["position"], 4, "reorder gives the first id the highest position");
			T::equals((int)BigTreeJSONDB::get("modules", $ids[1])["position"], 3, "reorder decrements past the skipped id");
			T::equals((int)BigTreeJSONDB::get("modules", $ids[2])["position"], 2, "reorder keeps the supplied order descending");
		} finally {
			foreach ($ids as $id) {
				BigTreeJSONDB::delete("modules", $id);
			}
		}
	}
