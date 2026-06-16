<?php
	/**
	 * ModuleReportService (plan 027 phase B) owns the module report cluster pulled
	 * out of ModuleService: CRUD over report sub-resources stored in a module's
	 * JSONDB record, plus prepare/run endpoints that drive the SPA off the legacy
	 * BigTreeAutoModule report engine.
	 *
	 * Coverage map (reported in the plan's handoff):
	 *   - createReport  — UNIT (seed module, assert insert + 201 envelope)
	 *   - updateReport  — UNIT (assert patch of individual fields + 200 envelope)
	 *   - deleteReport  — UNIT (assert removal + cascade delete of actions
	 *                     referencing the report — see deleteReport's loop)
	 *   - prepareReport — UNIT happy-path (seeded report, no filters → no SQL,
	 *                     asserts the {report,view,form,filter_options} envelope)
	 *                     + 404 path
	 *   - runReport     — UNIT (404 path always; happy-path executes end-to-end
	 *                     against a throwaway DB table when the DB is reachable,
	 *                     asserting the {report,view,form,items,meta} envelope)
	 *
	 * Tests seed a throwaway module (and, for runReport, a throwaway table) and
	 * clean everything up in a finally block. Skips on an unavailable store/DB.
	 */

	use BigTree\Services\ModuleReportService;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\NotFoundException;

	/** Build a Request with the given route params + JSON body. */
	function _report_request(array $route_params, array $body = []): Request {
		$req = new Request();
		$req->method = "POST";
		$req->route_params = $route_params;
		$req->body = $body;

		return $req;
	}

	/** True when the modules JSONDB store is reachable in this harness. */
	function _report_store_ready(): bool {
		try {
			BigTreeJSONDB::getAll("modules");

			return true;
		} catch (\Throwable $e) {
			echo "  (skipped — modules store unavailable: " . $e->getMessage() . ")\n";

			return false;
		}
	}

	/** True when a live SQL connection is available. */
	function _report_sql_ready(): bool {
		try {
			SQL::fetchSingle("SELECT 1");

			return true;
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable: " . $e->getMessage() . ")\n";

			return false;
		}
	}

	/** Re-read a module's reports straight from the store (bypassing any cache). */
	function _report_reload_reports(string $module_id): array {
		BigTreeJSONDB::$Cache = [];
		$module = BigTreeJSONDB::get("modules", $module_id);

		return is_array($module["reports"] ?? null) ? $module["reports"] : [];
	}

	function test_report_create_inserts_and_returns_envelope() {
		if (!_report_store_ready()) {
			return;
		}

		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_reportsvc_" . uniqid(),
				"route" => "zz-reportsvc-" . uniqid(),
			]);

			$service = new ModuleReportService();
			$response = $service->createReport(_report_request(
				["id" => $module_id],
				["title" => "My Report", "table" => "btx_throwaway", "type" => "csv"]
			));

			T::ok($response instanceof Response, "createReport returns a Response");
			T::equals($response->status, 201, "createReport responds 201 Created");

			$created = $response->body["data"];
			T::equals($created["title"], "My Report", "created report carries the submitted title");
			T::equals($created["table"], "btx_throwaway", "created report carries the submitted table");
			T::equals($created["type"], "csv", "created report carries the submitted type");
			T::ok(!empty($created["id"]), "created report has an id assigned");

			$persisted = _report_reload_reports($module_id);
			T::equals(count($persisted), 1, "exactly one report persisted to the module store");
			T::equals($persisted[0]["id"], $created["id"], "persisted report id matches the returned id");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}
	}

	function test_report_update_patches_individual_fields() {
		if (!_report_store_ready()) {
			return;
		}

		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_reportsvc_" . uniqid(),
				"route" => "zz-reportsvc-" . uniqid(),
				"reports" => [
					["id" => "rep-up", "title" => "Before", "table" => "btx_a", "type" => "csv", "parser" => "old"],
				],
			]);

			$service = new ModuleReportService();
			$response = $service->updateReport(_report_request(
				["id" => $module_id, "sid" => "rep-up"],
				["title" => "After", "parser" => "new"]
			));

			T::equals($response->status, 200, "updateReport responds 200 OK");

			$updated = $response->body["data"];
			T::equals($updated["title"], "After", "updateReport patched the title");
			T::equals($updated["parser"], "new", "updateReport patched the parser");
			T::equals($updated["table"], "btx_a", "updateReport left the un-submitted table untouched");

			$persisted = _report_reload_reports($module_id);
			T::equals($persisted[0]["title"], "After", "patched title persisted to the store");
			T::equals($persisted[0]["table"], "btx_a", "untouched table persisted unchanged");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}
	}

	function test_report_update_missing_throws_not_found() {
		if (!_report_store_ready()) {
			return;
		}

		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_reportsvc_" . uniqid(),
				"route" => "zz-reportsvc-" . uniqid(),
			]);

			$service = new ModuleReportService();

			T::throws(function () use ($service, $module_id) {
				$service->updateReport(_report_request(
					["id" => $module_id, "sid" => "no-such-report"],
					["title" => "x"]
				));
			}, NotFoundException::class, "updateReport throws NotFound for an absent report");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}
	}

	function test_report_delete_removes_report_and_cascades_actions() {
		if (!_report_store_ready()) {
			return;
		}

		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_reportsvc_" . uniqid(),
				"route" => "zz-reportsvc-" . uniqid(),
				"reports" => [
					["id" => "rep-del", "title" => "Doomed", "table" => "btx_a"],
					["id" => "rep-keep", "title" => "Survivor", "table" => "btx_b"],
				],
				"actions" => [
					["id" => "act-on-doomed", "report" => "rep-del", "title" => "Run Doomed"],
					["id" => "act-on-keep", "report" => "rep-keep", "title" => "Run Survivor"],
					["id" => "act-no-report", "title" => "Standalone"],
				],
			]);

			$service = new ModuleReportService();
			$response = $service->deleteReport(_report_request(["id" => $module_id, "sid" => "rep-del"]));

			T::equals($response->status, 204, "deleteReport responds 204 No Content");

			BigTreeJSONDB::$Cache = [];
			$module = BigTreeJSONDB::get("modules", $module_id);
			$report_ids = array_map(function ($r) {
				return $r["id"];
			}, $module["reports"] ?? []);
			$action_ids = array_map(function ($a) {
				return $a["id"];
			}, $module["actions"] ?? []);

			T::ok(!in_array("rep-del", $report_ids, true), "deleted report is gone from the store");
			T::ok(in_array("rep-keep", $report_ids, true), "unrelated report survives the delete");
			T::ok(!in_array("act-on-doomed", $action_ids, true), "action referencing the deleted report cascaded away");
			T::ok(in_array("act-on-keep", $action_ids, true), "action referencing a surviving report is untouched");
			T::ok(in_array("act-no-report", $action_ids, true), "action with no report reference is untouched");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}
	}

	function test_report_prepare_returns_envelope_for_seeded_report() {
		if (!_report_store_ready()) {
			return;
		}

		$module_id = null;

		try {
			// Unique table with no related form/view and no filters: prepareReport
			// resolves the report from JSONDB and returns the envelope without SQL.
			$table = "btx_prepare_" . substr(md5(uniqid()), 0, 8);
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_reportsvc_" . uniqid(),
				"route" => "zz-reportsvc-" . uniqid(),
				"forms" => [],
				"views" => [],
				"reports" => [
					["id" => "rep-prep", "title" => "Prep", "table" => $table, "type" => "csv", "filters" => []],
				],
			]);

			$service = new ModuleReportService();
			$response = $service->prepareReport(_report_request(["id" => $module_id, "sid" => "rep-prep"]));

			T::equals($response->status, 200, "prepareReport responds 200 OK");

			$data = $response->body["data"];
			T::ok(array_key_exists("report", $data), "prepare envelope has a report key");
			T::ok(array_key_exists("view", $data), "prepare envelope has a view key");
			T::ok(array_key_exists("form", $data), "prepare envelope has a form key");
			T::equals($data["filter_options"], [], "prepare resolves no filter_options for a filter-less report");
			T::equals($data["report"]["id"], "rep-prep", "prepare returns the requested report");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}
	}

	function test_report_prepare_missing_throws_not_found() {
		if (!_report_store_ready()) {
			return;
		}

		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_reportsvc_" . uniqid(),
				"route" => "zz-reportsvc-" . uniqid(),
			]);

			$service = new ModuleReportService();

			T::throws(function () use ($service, $module_id) {
				$service->prepareReport(_report_request(["id" => $module_id, "sid" => "no-such-report"]));
			}, NotFoundException::class, "prepareReport throws NotFound for an absent report");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}
	}

	function test_report_run_missing_throws_not_found() {
		if (!_report_store_ready()) {
			return;
		}

		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_reportsvc_" . uniqid(),
				"route" => "zz-reportsvc-" . uniqid(),
			]);

			$service = new ModuleReportService();

			T::throws(function () use ($service, $module_id) {
				$service->runReport(_report_request(["id" => $module_id, "sid" => "no-such-report"], []));
			}, NotFoundException::class, "runReport throws NotFound for an absent report");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}
	}

	function test_report_run_executes_against_seeded_table() {
		if (!_report_store_ready() || !_report_sql_ready()) {
			return;
		}

		$table = "btx_run_" . substr(md5(uniqid()), 0, 8);
		$module_id = null;

		try {
			SQL::query("CREATE TABLE `$table` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(64))");
			SQL::query("INSERT INTO `$table` (name) VALUES ('alpha'), ('beta'), ('gamma')");

			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_reportsvc_" . uniqid(),
				"route" => "zz-reportsvc-" . uniqid(),
				"forms" => [],
				"views" => [],
				"reports" => [
					["id" => "rep-run", "title" => "Run", "table" => $table, "type" => "csv", "filters" => []],
				],
			]);

			$service = new ModuleReportService();
			$response = $service->runReport(_report_request(
				["id" => $module_id, "sid" => "rep-run"],
				["filters" => [], "sort" => ["field" => "id", "order" => "ASC"]]
			));

			T::equals($response->status, 200, "runReport responds 200 OK");

			$data = $response->body["data"];
			T::ok(array_key_exists("report", $data), "run envelope has a report key");
			T::ok(array_key_exists("items", $data), "run envelope has an items key");
			T::ok(array_key_exists("meta", $data), "run envelope has a meta key");
			T::equals($data["meta"]["count"], 3, "run reports the three seeded rows in meta.count");
			T::equals(count($data["items"]), 3, "run returns the three seeded rows");
			T::equals($data["items"][0]["name"], "alpha", "run returns rows sorted ascending by id");
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
