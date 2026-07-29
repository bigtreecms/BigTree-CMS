<?php
	/**
	 * Audit #12 guards E1, E2 and E4: the DDL boundary the scaffold surface writes
	 * across.
	 *
	 * `scaffold_module` is the first tool in the catalogue whose write is DDL, and
	 * the only proposal whose effect the assistant cannot walk back — deleting a
	 * module is admin-only and dropping a table isn't a capability at all. Every
	 * other AI write is checked against the thing it lands in; this one emits
	 * `CREATE TABLE` / `ALTER TABLE` and, until audit #12, checked nothing about
	 * whether MySQL would accept them:
	 *
	 *  - E1 (A1): a plan whose columns exceed InnoDB's 65,535-byte row definition
	 *    limit is refused at *plan* time, so REST gets a 400 and the assistant gets a
	 *    recoverable error instead of a module whose form references columns that
	 *    were never created. Pure — it tests the plan, not the DDL.
	 *  - E2 (A1 sub-finding): a field titled "Approved" cannot collide with the
	 *    builtin `approved` status column, with or without the action enabled.
	 *  - E4 (A1/D4): `performScaffold` checks each statement's result and stops
	 *    rather than building a form and a view over a table that isn't there.
	 *    `SQL::query` never fails loudly — it appends to `SQL::$ErrorLog` and returns
	 *    an object regardless — so "it ran" is not the same as "it worked".
	 *
	 * The 15/16 boundary at `VARCHAR(1024)` and the 85/86 boundary at `VARCHAR(191)`
	 * were both measured against the local MySQL 9.7.1 instance emitting exactly the
	 * DDL `performScaffold` writes.
	 */

	use BigTree\Services\ModuleService;

	/** A field list of `$count` fields of `$type`, titled Field 1…N. */
	function _scaffold_guard_fields(int $count, string $type = "text"): array {
		$fields = [];

		for ($i = 1; $i <= $count; $i++) {
			$fields[] = ["title" => "Field {$i}", "type" => $type];
		}

		return $fields;
	}

	/** A scaffold request body in the shape POST /modules/scaffold takes. */
	function _scaffold_guard_body(array $fields, array $overrides = []): array {

		return array_merge([
			"name" => "ZZ Scaffold Guard",
			"table" => "zz_scaffold_guard_" . substr(md5((string)mt_rand()), 0, 8),
			"fields" => $fields,
		], $overrides);
	}

	/**
	 * E1: the row budget is enforced where it can still be refused — at plan time,
	 * before a single statement runs.
	 */
	function test_scaffold_plan_refuses_a_field_list_that_exceeds_the_row_budget() {
		$service = new ModuleService();

		// 85 × VARCHAR(191) is 65,110 bytes plus the 4-byte `id`: the largest field
		// list InnoDB will accept. Measured, not derived.
		$ok = $service->scaffoldPlan(_scaffold_guard_body(_scaffold_guard_fields(85)));
		T::ok(!isset($ok["error"]), "85 default-width columns plan cleanly");

		$too_wide = $service->scaffoldPlan(_scaffold_guard_body(_scaffold_guard_fields(86)));
		T::equals((string)($too_wide["code"] ?? ""), "row_too_large", "86 columns are refused at plan time");
		T::ok(
			strpos((string)($too_wide["error"] ?? ""), "86") !== false,
			"the refusal names how many fields were asked for"
		);
		T::ok(
			strpos((string)($too_wide["error"] ?? ""), "85") !== false,
			"and how many will actually fit"
		);
		T::ok(
			stripos((string)($too_wide["error"] ?? ""), "textarea") !== false,
			"and points at the field type that costs almost nothing"
		);

		// A TEXT column's contents live off-page: it costs ~12 bytes toward the row
		// definition, not 4,098. Charging it the VARCHAR rate would refuse the one
		// module shape that is always safe.
		$long = $service->scaffoldPlan(_scaffold_guard_body(_scaffold_guard_fields(200, "textarea")));
		T::ok(!isset($long["error"]), "200 TEXT columns cost their real width and plan cleanly");

		// The status columns the actions and the view type add are part of the same
		// budget — they are emitted as separate ALTERs, so they'd otherwise be free at
		// plan time and fail at build time. 85 VARCHAR(191) plus 131 DATE columns is
		// the largest list that fits with none of them (measured); the same list is
		// refused once all four are added.
		$edge = array_merge(_scaffold_guard_fields(85), _scaffold_guard_fields(131, "date"));
		$plain = $service->scaffoldPlan(_scaffold_guard_body($edge));
		T::ok(!isset($plain["error"]), "the largest list that fits plans cleanly on its own");

		$with_status = $service->scaffoldPlan(_scaffold_guard_body($edge, [
			"actions" => ["approve" => true, "feature" => true, "archive" => true],
			"view_type" => "draggable",
		]));
		T::equals(
			(string)($with_status["code"] ?? ""),
			"row_too_large",
			"the builtin status columns count against the budget too"
		);
	}

	/**
	 * The two relation types, whose storage is not what the legacy mapping gave them.
	 *
	 * A many-to-many lives in a connecting table and the entry write path deletes the
	 * bound column out of every row it writes, so a column for it is one nothing can
	 * ever put a value in — and at utf8mb4 it spent 766 bytes of the row budget doing
	 * it. A one-to-many does use its column, but what it holds is a JSON list of ids:
	 * at the narrowed default width that is about 45 related entries before the write
	 * is refused, where the same field held five times as many before audit #12 D1.
	 */
	function test_scaffold_plan_stores_the_relation_types_the_way_they_are_read() {
		$service = new ModuleService();
		$plan = $service->scaffoldPlan(_scaffold_guard_body([
			["title" => "Headline", "type" => "text"],
			["title" => "Products", "type" => "many-to-many"],
			["title" => "Regions", "type" => "one-to-many"],
		]));

		T::ok(!isset($plan["error"]), "the plan is valid (" . (string)($plan["error"] ?? "") . ")");

		$adds = implode(" | ", $plan["column_adds"]);
		T::ok(strpos($adds, "`products`") === false, "a many-to-many gets no column at all (got {$adds})");
		T::ok(strpos($adds, "`regions` TEXT") !== false, "a one-to-many's id list is stored as TEXT");
		T::ok(strpos($adds, "`headline` VARCHAR(191)") !== false, "and an ordinary field is unaffected");

		// The two lists are read side by side — by the proposal card, and by the single
		// ALTER performScaffold builds. A skipped column must not slide every later
		// field onto the wrong DDL.
		foreach ($plan["column_adds"] as $index => $add) {
			T::ok(
				strpos($add, "`" . (string)$plan["form_fields"][$index]["column"] . "`") !== false,
				"column_adds[{$index}] still belongs to form_fields[{$index}]"
			);
		}

		// Three fields, two columns: the field with no column doesn't cost the budget
		// either, and it isn't the one that gets dropped when the budget is tight.
		T::equals(count($plan["form_fields"]), 3, "every field is still on the form");
		T::equals(count($plan["column_adds"]), 2, "but only the two that need columns get DDL");
	}

	/**
	 * E2: `approved` / `featured` / `archived` are reserved unconditionally. A field
	 * titled "Approved" used to sanitize onto the status column's name, so the
	 * second ALTER failed silently and the module got an approve button over a
	 * VARCHAR with no index.
	 */
	function test_scaffold_plan_reserves_the_builtin_status_columns() {
		$service = new ModuleService();
		$titles = ["Approved", "Featured", "Archived", "Position"];

		foreach ([true, false] as $actions_enabled) {
			$plan = $service->scaffoldPlan(_scaffold_guard_body(
				array_map(function (string $title): array {

					return ["title" => $title, "type" => "text"];
				}, $titles),
				$actions_enabled
					? ["actions" => ["approve" => true, "feature" => true, "archive" => true], "view_type" => "draggable"]
					: []
			));

			T::ok(!isset($plan["error"]), "the four-field plan is valid" . ($actions_enabled ? " with actions" : ""));

			$columns = array_map(function (array $field): string {

				return (string)$field["column"];
			}, $plan["form_fields"] ?? []);

			foreach (["approved", "featured", "archived", "position"] as $reserved) {
				T::ok(
					!in_array($reserved, $columns, true),
					"`{$reserved}` is reserved" . ($actions_enabled ? " with its action on" : " even with its action off")
						. " (got " . implode(", ", $columns) . ")"
				);
			}
		}
	}

	/** True when a live SQL connection is available. */
	function _scaffold_guard_sql_ready(): bool {
		try {
			SQL::fetchSingle("SELECT 1");

			return true;
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable: " . $e->getMessage() . ")\n";

			return false;
		}
	}

	/**
	 * E4: a statement that fails stops the build.
	 *
	 * Driven through a plan whose ALTER cannot succeed — the same shape a row-size
	 * refusal would have had before E1 caught it at plan time. The assertion that
	 * matters is the negative one: no form, no view, no actions built over a table
	 * that has none of the columns they name.
	 */
	function test_scaffold_stops_when_a_ddl_statement_fails() {
		if (!_scaffold_guard_sql_ready()) {

			return;
		}

		$service = new ModuleService();
		$plan = $service->scaffoldPlan(_scaffold_guard_body(_scaffold_guard_fields(2)));
		T::ok(!isset($plan["error"]), "the two-field plan is valid before it's corrupted");

		// Not a type MySQL has. Reaching this state through the tool is what E1 and
		// the field-type registry check prevent; what E4 asserts is what happens if
		// anything ever does.
		$plan["column_adds"][1] = "ADD COLUMN `broken` NOT_A_REAL_TYPE";
		$table = (string)$plan["table"];
		$module_id = null;

		try {
			$run = new ReflectionMethod(ModuleService::class, "performScaffold");
			$run->setAccessible(true);
			$result = $run->invoke($service, $plan);

			T::ok(is_array($result), "performScaffold reports its outcome rather than returning a bare id");
			$module_id = (string)($result["id"] ?? "");

			T::ok(!empty($result["error"]), "a failed statement is reported as an error");
			T::ok(
				strpos((string)($result["error"] ?? ""), $table) !== false,
				"the error names the table a developer has to finish or drop"
			);
			T::ok($module_id !== "", "and names the module id that was already inserted");

			BigTreeJSONDB::$Cache = [];
			$module = BigTreeJSONDB::get("modules", $module_id);

			T::equals(
				count(is_array($module["forms"] ?? null) ? $module["forms"] : []),
				0,
				"no form was built over the table that failed"
			);
			T::equals(
				count(is_array($module["views"] ?? null) ? $module["views"] : []),
				0,
				"and no landing view"
			);
			T::equals(
				count(is_array($module["actions"] ?? null) ? $module["actions"] : []),
				0,
				"and no add/edit/list actions"
			);
		} finally {
			if ($module_id !== null && $module_id !== "") {
				BigTreeJSONDB::delete("modules", $module_id);
			}

			if (BigTree::tableExists($table)) {
				SQL::query("DROP TABLE `{$table}`");
			}
		}
	}
