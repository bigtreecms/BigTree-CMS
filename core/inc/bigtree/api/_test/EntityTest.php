<?php
	/**
	 * Entity loader helpers (API consolidation round 1, finding #3). Covers the
	 * two fetch-or-404 conveniences that replaced the repeated
	 *   $row = SQL::fetch(...); if (!$row) { throw new NotFoundException(...); }
	 * idiom across the service layer:
	 *   - Entity::findOrFail($table, $id, $label[, $columns]) → SQL row or 404,
	 *   - Entity::findOrFailJson($store, $id, $label)         → JSONDB record or 404.
	 *
	 * The hit paths touch the database / JSONDB, so those tests seed a throwaway
	 * record and clean it up; the miss paths only need the store reachable.
	 */

	use BigTree\Api\Entity;
	use BigTree\Api\Exceptions\NotFoundException;

	/** True when the bigtree_tags table is reachable in this harness. */
	function _entity_sql_ready(): bool {
		try {
			SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_tags");

			return true;
		} catch (\Throwable $e) {
			echo "  (skipped — bigtree_tags unavailable: " . $e->getMessage() . ")\n";

			return false;
		}
	}

	/** True when the modules JSONDB store is reachable in this harness. */
	function _entity_json_ready(): bool {
		try {
			BigTreeJSONDB::getAll("modules");

			return true;
		} catch (\Throwable $e) {
			echo "  (skipped — modules store unavailable: " . $e->getMessage() . ")\n";

			return false;
		}
	}

	function test_entity_find_or_fail_returns_row_and_throws() {
		if (!_entity_sql_ready()) {
			return;
		}

		$tag = "zz_entity_" . uniqid();
		$id = null;

		try {
			$id = (int)SQL::insert("bigtree_tags", [
				"tag" => $tag,
				"metaphone" => metaphone($tag),
				"route" => $tag,
				"usage_count" => 0,
			]);

			$row = Entity::findOrFail("bigtree_tags", $id, "Tag");
			T::equals((int)$row["id"], $id, "findOrFail returns the matching row");

			$narrow = Entity::findOrFail("bigtree_tags", $id, "Tag", "tag");
			T::equals($narrow["tag"], $tag, "findOrFail honors a custom column list");
			T::ok(!isset($narrow["usage_count"]), "findOrFail with a column list omits unselected columns");
		} finally {
			if ($id !== null) {
				SQL::delete("bigtree_tags", $id);
			}
		}

		T::throws(function () {
			Entity::findOrFail("bigtree_tags", 0, "Tag");
		}, NotFoundException::class, "findOrFail throws NotFound for an id that does not exist");
	}

	function test_entity_fetch_or_fail_handles_arbitrary_queries() {
		if (!_entity_sql_ready()) {
			return;
		}

		$tag = "zz_entity_" . uniqid();
		$id = null;

		try {
			$id = (int)SQL::insert("bigtree_tags", [
				"tag" => $tag,
				"metaphone" => metaphone($tag),
				"route" => $tag,
				"usage_count" => 0,
			]);

			// Multi-parameter predicate (the compound-key case findOrFail can't express).
			$row = Entity::fetchOrFail(
				"SELECT * FROM bigtree_tags WHERE id = ? AND tag = ?",
				[$id, $tag],
				"Tag not found"
			);
			T::equals((int)$row["id"], $id, "fetchOrFail binds every parameter and returns the row");
		} finally {
			if ($id !== null) {
				SQL::delete("bigtree_tags", $id);
			}
		}

		// A custom code string flows through to the thrown exception.
		$threw = null;

		try {
			Entity::fetchOrFail("SELECT * FROM bigtree_tags WHERE id = ?", [0], "Tag missing", "custom_missing");
		} catch (NotFoundException $e) {
			$threw = $e;
		}

		T::ok($threw instanceof NotFoundException, "fetchOrFail throws NotFound when the query matches nothing");
		T::equals($threw->code_string, "custom_missing", "fetchOrFail forwards a custom code string");
		T::equals($threw->getMessage(), "Tag missing", "fetchOrFail forwards the custom message");
	}

	function test_entity_find_or_fail_json_returns_record_and_throws() {
		if (!_entity_json_ready()) {
			return;
		}

		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_entity_" . uniqid(),
				"route" => "zz-entity-" . uniqid(),
			]);

			$record = Entity::findOrFailJson("modules", $module_id, "Module");
			T::equals(is_array($record) ? $record["id"] : null, $module_id, "findOrFailJson returns the matching record");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}

		T::throws(function () {
			Entity::findOrFailJson("modules", "entity-zzz-" . uniqid(), "Module");
		}, NotFoundException::class, "findOrFailJson throws NotFound for an id that does not exist");
	}
