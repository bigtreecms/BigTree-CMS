<?php
	/**
	 * The shared ModuleSubResourceSupport trait (plan 027 phase A) holds the three
	 * private helpers every module collaborator uses to resolve nested resources:
	 *   - loadModule($id)        → module row, or NotFound,
	 *   - findSub($rows, $id)    → matching row (== on "id"), or null,
	 *   - getSubResource(...)    → a named sub-row, or NotFound.
	 *
	 * These were previously private to ModuleService; the trait moved them verbatim
	 * and both ModuleService and ModuleReportService `use` it. The helpers are
	 * private, so the tests reach them via reflection on a ModuleReportService
	 * instance (which uses the trait). loadModule/getSubResource touch JSONDB, so
	 * those tests seed a throwaway module and clean it up; findSub is a pure array
	 * search and needs no DB.
	 */

	use BigTree\Services\ModuleReportService;
	use BigTree\Api\Exceptions\NotFoundException;

	/** Invoke a private trait method on a fresh ModuleReportService instance. */
	function _subres_invoke(string $method, array $args) {
		$instance = new ModuleReportService();
		$ref = new ReflectionMethod(ModuleReportService::class, $method);
		$ref->setAccessible(true);

		return $ref->invokeArgs($instance, $args);
	}

	/** True when the modules JSONDB store is reachable in this harness. */
	function _subres_db_ready(): bool {
		try {
			BigTreeJSONDB::getAll("modules");

			return true;
		} catch (\Throwable $e) {
			echo "  (skipped — modules store unavailable: " . $e->getMessage() . ")\n";

			return false;
		}
	}

	function test_subres_find_sub_returns_match_and_null() {
		$rows = [
			["id" => "a-1", "title" => "First"],
			["id" => "a-2", "title" => "Second"],
		];

		$found = _subres_invoke("findSub", [$rows, "a-2"]);
		T::equals(is_array($found) ? $found["title"] : null, "Second", "findSub returns the row whose id matches");

		// Loose-equality match (== on id) mirrors the production helper.
		$loose = _subres_invoke("findSub", [[["id" => 7]], "7"]);
		T::equals(is_array($loose) ? $loose["id"] : null, 7, "findSub matches with loose equality on id");

		$missing = _subres_invoke("findSub", [$rows, "nope"]);
		T::equals($missing, null, "findSub returns null when no row matches");

		$empty = _subres_invoke("findSub", [[], "anything"]);
		T::equals($empty, null, "findSub returns null on an empty row set");
	}

	function test_subres_load_module_throws_on_missing_id() {
		if (!_subres_db_ready()) {
			return;
		}

		$missing_id = "modules-zzz-" . uniqid();

		T::throws(function () use ($missing_id) {
			_subres_invoke("loadModule", [$missing_id]);
		}, NotFoundException::class, "loadModule throws NotFound for a module id that does not exist");
	}

	function test_subres_load_and_get_sub_for_seeded_module() {
		if (!_subres_db_ready()) {
			return;
		}

		$module_id = null;

		try {
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ_subres_" . uniqid(),
				"route" => "zz-subres-" . uniqid(),
				"reports" => [
					["id" => "rep-known", "title" => "Known"],
				],
			]);

			$module = _subres_invoke("loadModule", [$module_id]);
			T::equals(is_array($module) ? $module["id"] : null, $module_id, "loadModule returns the seeded module row");

			$sub = _subres_invoke("getSubResource", [$module_id, "reports", "rep-known"]);
			T::equals(is_array($sub) ? $sub["title"] : null, "Known", "getSubResource returns the named sub-row");

			T::throws(function () use ($module_id) {
				_subres_invoke("getSubResource", [$module_id, "reports", "rep-missing"]);
			}, NotFoundException::class, "getSubResource throws NotFound for an absent sub-id");
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}
		}
	}
