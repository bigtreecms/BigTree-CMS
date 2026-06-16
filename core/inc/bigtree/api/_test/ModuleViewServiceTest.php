<?php
	/**
	 * ModuleViewService (plan 028) owns the module view cluster pulled out of
	 * ModuleService: CRUD over view sub-resources stored in a module's JSONDB
	 * record, plus the gbp-categories lookup that drives the user editor's
	 * group-based-permission radios.
	 *
	 * Coverage map (reported in the plan's handoff):
	 *   - createView     — UNIT (seed module, assert insert + 201 envelope +
	 *                      persistence)
	 *   - updateView     — UNIT (assert patch of supplied fields, others left
	 *                      untouched; 200 envelope; 404 path)
	 *   - deleteView     — UNIT (assert removal + cascade delete of actions
	 *                      referencing the view — see deleteView's loop; 404 path)
	 *   - gbpCategories  — UNIT: disabled-gbp short-circuit (empty list, no SQL);
	 *                      incomplete-config short-circuit (empty list); and the
	 *                      enabled happy-path against a throwaway table when the
	 *                      DB is reachable (asserts the {id,title} rows)
	 *
	 * Tests seed a throwaway module (and, for the gbp happy-path, a throwaway
	 * table) and clean everything up in a finally block. Skips on an unavailable
	 * store/DB.
	 */

	use BigTree\Services\ModuleViewService;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\NotFoundException;

	/** Build a Request with the given route params + JSON body. */
	function _view_request(array $route_params, array $body = []): Request {
		$req = new Request();
		$req->method = "POST";
		$req->route_params = $route_params;
		$req->body = $body;

		return $req;
	}

	/** True when the modules JSONDB store is reachable in this harness. */
	function _view_store_ready(): bool {
		try {
			BigTreeJSONDB::getAll("modules");

			return true;
		} catch (\Throwable $e) {
			echo "  (skipped — modules store unavailable: " . $e->getMessage() . ")\n";

			return false;
		}
	}

	/** True when a live SQL connection is available. */
	function _view_sql_ready(): bool {
		try {
			SQL::fetchSingle("SELECT 1");

			return true;
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable: " . $e->getMessage() . ")\n";

			return false;
		}
	}

	/** Re-read a module's views straight from the store (bypassing any cache). */
	function _view_reload_views(string $module_id): array {
		BigTreeJSONDB::$Cache = [];
		$module = BigTreeJSONDB::get("modules", $module_id);

		return is_array($module["views"] ?? null) ? $module["views"] : [];
	}

	function test_view_create_inserts_and_returns_envelope() {
		if (!_view_store_ready()) {
			return;
		}

		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_viewsvc_" . uniqid(),
				"route" => "zz-viewsvc-" . uniqid(),
			]);

			$service = new ModuleViewService();
			$response = $service->createView(_view_request(
				["id" => $module_id],
				["title" => "My View", "description" => "Desc", "type" => "draggable"]
			));

			T::ok($response instanceof Response, "createView returns a Response");
			T::equals($response->status, 201, "createView responds 201 Created");

			$created = $response->body["data"];
			T::equals($created["title"], "My View", "created view carries the submitted title");
			T::equals($created["description"], "Desc", "created view carries the submitted description");
			T::equals($created["type"], "draggable", "created view carries the submitted type");
			T::ok(!empty($created["id"]), "created view has an id assigned");

			$persisted = _view_reload_views($module_id);
			T::equals(count($persisted), 1, "exactly one view persisted to the module store");
			T::equals($persisted[0]["id"], $created["id"], "persisted view id matches the returned id");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}
	}

	function test_view_update_patches_supplied_fields() {
		if (!_view_store_ready()) {
			return;
		}

		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_viewsvc_" . uniqid(),
				"route" => "zz-viewsvc-" . uniqid(),
				"views" => [
					["id" => "view-up", "title" => "Before", "description" => "Keep", "type" => "draggable"],
				],
			]);

			$service = new ModuleViewService();
			$response = $service->updateView(_view_request(
				["id" => $module_id, "sid" => "view-up"],
				["title" => "After"]
			));

			T::equals($response->status, 200, "updateView responds 200 OK");

			$updated = $response->body["data"];
			T::equals($updated["title"], "After", "updateView patched the title");
			T::equals($updated["description"], "Keep", "updateView left the un-submitted description untouched");
			T::equals($updated["type"], "draggable", "updateView left the un-submitted type untouched");

			$persisted = _view_reload_views($module_id);
			T::equals($persisted[0]["title"], "After", "patched title persisted to the store");
			T::equals($persisted[0]["description"], "Keep", "untouched description persisted unchanged");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}
	}

	function test_view_update_missing_throws_not_found() {
		if (!_view_store_ready()) {
			return;
		}

		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_viewsvc_" . uniqid(),
				"route" => "zz-viewsvc-" . uniqid(),
			]);

			$service = new ModuleViewService();

			T::throws(function () use ($service, $module_id) {
				$service->updateView(_view_request(
					["id" => $module_id, "sid" => "no-such-view"],
					["title" => "x"]
				));
			}, NotFoundException::class, "updateView throws NotFound for an absent view");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}
	}

	function test_view_delete_removes_view_and_cascades_actions() {
		if (!_view_store_ready()) {
			return;
		}

		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_viewsvc_" . uniqid(),
				"route" => "zz-viewsvc-" . uniqid(),
				"views" => [
					["id" => "view-del", "title" => "Doomed"],
					["id" => "view-keep", "title" => "Survivor"],
				],
				"actions" => [
					["id" => "act-on-doomed", "view" => "view-del", "title" => "Edit Doomed"],
					["id" => "act-on-keep", "view" => "view-keep", "title" => "Edit Survivor"],
					["id" => "act-no-view", "title" => "Standalone"],
				],
			]);

			$service = new ModuleViewService();
			$response = $service->deleteView(_view_request(["id" => $module_id, "sid" => "view-del"]));

			T::equals($response->status, 204, "deleteView responds 204 No Content");

			BigTreeJSONDB::$Cache = [];
			$module = BigTreeJSONDB::get("modules", $module_id);
			$view_ids = array_map(function ($v) {
				return $v["id"];
			}, $module["views"] ?? []);
			$action_ids = array_map(function ($a) {
				return $a["id"];
			}, $module["actions"] ?? []);

			T::ok(!in_array("view-del", $view_ids, true), "deleted view is gone from the store");
			T::ok(in_array("view-keep", $view_ids, true), "unrelated view survives the delete");
			T::ok(!in_array("act-on-doomed", $action_ids, true), "action referencing the deleted view cascaded away");
			T::ok(in_array("act-on-keep", $action_ids, true), "action referencing a surviving view is untouched");
			T::ok(in_array("act-no-view", $action_ids, true), "action with no view reference is untouched");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}
	}

	function test_view_delete_missing_throws_not_found() {
		if (!_view_store_ready()) {
			return;
		}

		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_viewsvc_" . uniqid(),
				"route" => "zz-viewsvc-" . uniqid(),
			]);

			$service = new ModuleViewService();

			T::throws(function () use ($service, $module_id) {
				$service->deleteView(_view_request(["id" => $module_id, "sid" => "no-such-view"]));
			}, NotFoundException::class, "deleteView throws NotFound for an absent view");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}
	}

	function test_view_gbp_categories_empty_when_disabled() {
		if (!_view_store_ready()) {
			return;
		}

		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_viewsvc_" . uniqid(),
				"route" => "zz-viewsvc-" . uniqid(),
				"gbp" => ["enabled" => false, "other_table" => "btx_nope", "title_field" => "name"],
			]);

			$service = new ModuleViewService();
			$response = $service->gbpCategories(_view_request(["id" => $module_id]));

			T::equals($response->status, 200, "gbpCategories responds 200 OK when gbp disabled");
			T::equals($response->body["data"], [], "gbpCategories short-circuits to an empty list when gbp disabled");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}
	}

	function test_view_gbp_categories_empty_when_config_incomplete() {
		if (!_view_store_ready()) {
			return;
		}

		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_viewsvc_" . uniqid(),
				"route" => "zz-viewsvc-" . uniqid(),
				"gbp" => ["enabled" => true, "other_table" => "", "title_field" => ""],
			]);

			$service = new ModuleViewService();
			$response = $service->gbpCategories(_view_request(["id" => $module_id]));

			T::equals($response->body["data"], [], "gbpCategories returns empty list when other_table/title_field missing");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}
	}

	function test_view_gbp_categories_returns_rows_when_enabled() {
		if (!_view_store_ready() || !_view_sql_ready()) {
			return;
		}

		$table = "btx_gbp_" . substr(md5(uniqid()), 0, 8);
		$module_id = null;

		try {
			SQL::query("CREATE TABLE `$table` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(64))");
			SQL::query("INSERT INTO `$table` (name) VALUES ('Apples'), ('Bananas')");

			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_viewsvc_" . uniqid(),
				"route" => "zz-viewsvc-" . uniqid(),
				"gbp" => ["enabled" => true, "other_table" => $table, "title_field" => "name"],
			]);

			$service = new ModuleViewService();
			$response = $service->gbpCategories(_view_request(["id" => $module_id]));

			T::equals($response->status, 200, "gbpCategories responds 200 OK for an enabled module");

			$rows = $response->body["data"];
			T::equals(count($rows), 2, "gbpCategories returns one row per category");
			T::equals($rows[0]["title"], "Apples", "gbpCategories returns titles ordered ascending by title_field");
			T::equals($rows[1]["title"], "Bananas", "gbpCategories returns the second category");
			T::ok(!empty($rows[0]["id"]), "gbpCategories returns an id per category");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}

			try {
				SQL::query("DROP TABLE IF EXISTS `$table`");
			} catch (\Throwable $e) {
				// best-effort cleanup
			}
		}
	}
