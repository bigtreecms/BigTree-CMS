<?php
	/**
	 * Audit #13 guard E1: the post-write visibility contract.
	 *
	 * Every guard written before this one asserts something about the *input* to a
	 * seam — the value against its field, the shape of an argument, the container it
	 * names, the column it lands in. All of them stop at the moment the row lands,
	 * which is why A1 survived twelve audits: a module whose scaffold requested the
	 * `approve` action gets a `CHAR(2) NOT NULL` column with no default, so every new
	 * entry is born `""` — unapproved — and `BigTreeModel::getApproved()` /
	 * `BigTreeModule::getApproved()` exclude it from the live site. The write
	 * succeeded, the card went green, the audit row was written, and the press
	 * release wasn't on the site.
	 *
	 * This is the map of every state that can make a successful, approved write
	 * inert, and the seam that has to say so. A gate is either *incidental* — the
	 * write is about something else and the gate applies silently, so it owes a
	 * disclosure — or *requested*, where the gate is the whole subject of the write
	 * and the card already names it.
	 */

	use BigTree\Services\AutoModuleService;
	use BigTree\Services\ModuleService;
	use BigTree\Services\PageService;
	use BigTree\Services\AI\ColumnDomain;

	/**
	 * The visibility gates, keyed by name, each either disclosed or exempt.
	 *
	 * `disclosed_by` entries are [class, method, token]: the token must appear in
	 * that method's source, so a disclosure can't be deleted without this failing.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	function ai_visibility_gates(): array {

		return [
			// A1. The finding this guard exists for.
			"module entry: approved column" => [
				"why" => "a CHAR(2) NOT NULL column with no default, so a new row is born \"\" and every "
					. "getApproved() read on the front end excludes it",
				"disclosed_by" => [
					[AutoModuleService::class, "aiModuleSchema", "approval_note"],
					[AutoModuleService::class, "aiValidateEntryCreate", "aiApprovalNote"],
					[AutoModuleService::class, "aiCreateEntry", "aiApprovalNote"],
					[AutoModuleService::class, "aiValidateEntryUpdate", "aiApprovalNote"],
					[AutoModuleService::class, "aiUpdateEntry", "aiApprovalNote"],
				],
			],
			// Audit #10 C3, the positive control this finding was measured against.
			"module entry: view filter callback" => [
				"why" => "a per-row PHP callback on the view decides whether an entry appears in the module's "
					. "landing list at all",
				"disclosed_by" => [
					[AutoModuleService::class, "aiModuleSchema", "view_filter_note"],
				],
			],
			"module entry: group-based permission column" => [
				"why" => "an entry with no value in the module's group column is hidden from every group-scoped "
					. "editor",
				"disclosed_by" => [
					[AutoModuleService::class, "aiModuleSchema", "group_column_note"],
					[AutoModuleService::class, "aiValidateEntryCreate", "aiGroupFieldViolation"],
				],
			],
			"module entry: editor's create queues as pending" => [
				"why" => "an editor's create is a row in the pending queue, not in the module's table, so "
					. "nothing on the site can read it until a publisher approves it",
				"disclosed_by" => [
					[AutoModuleService::class, "aiValidateEntryCreate", "pending entry for a publisher to review"],
				],
			],
			"page: publish_at in the future" => [
				"why" => "cms.php gates page lookup on publish_at, so a scheduled page 404s until its date",
				"disclosed_by" => [
					[PageService::class, "aiValidatePageCreate", "publish_at"],
					[PageService::class, "aiPageDetailFields", "publish_at"],
					// Audit #13 found this one missing while writing the map: the tree is
					// the surface the model browses before it decides a page is fine, and
					// it reported `archived` without reporting the other way a page is off
					// the site.
					[PageService::class, "aiPageTree", "scheduled"],
				],
			],
			"page: editor's create queues as pending" => [
				"why" => "the same queue, one content type over",
				"disclosed_by" => [
					[PageService::class, "aiValidatePageCreate", "pending"],
				],
			],
			// The requested gates. Both are reachable only through a tool whose entire
			// subject is the flag, and whose card names the flag and its new value, so a
			// separate visibility disclosure would be restating the proposal.
			"module entry: archived flag" => [
				"exempt" => "only reachable through set_module_entry_flag, whose card names the flag, its old "
					. "value and its new one — the invisibility is what was asked for",
			],
			"page: archived" => [
				"exempt" => "only reachable through archive_page, whose whole subject is taking the page off "
					. "the site",
			],
		];
	}

	/**
	 * Every column `performScaffold` adds to a module's table beyond its form fields,
	 * classified. A new one appearing here with no classification is exactly the
	 * shape of A1 — a column the DDL creates, the front end reads, and nothing on the
	 * AI surface mentions.
	 *
	 * A `gate:` value must name a key of ai_visibility_gates().
	 *
	 * @return array<string,string>
	 */
	function ai_visibility_status_columns(): array {

		return [
			"approved" => "gate:module entry: approved column",
			"archived" => "gate:module entry: archived flag",
			"featured" => "harmless: sorts an entry to the top of a view; no read anywhere decides visibility "
				. "on it",
			"position" => "harmless: orders a drag-orderable view; every entry is in that view regardless",
		];
	}

	/**
	 * E1a: every disclosure the map names is a seam that exists and still says it.
	 */
	function test_every_visibility_gate_names_a_disclosure_that_exists() {
		$missing = [];

		foreach (ai_visibility_gates() as $gate => $entry) {
			if (isset($entry["exempt"])) {
				T::ok(trim((string)$entry["exempt"]) !== "", "\"{$gate}\" states why it owes no disclosure");

				continue;
			}

			$sites = is_array($entry["disclosed_by"] ?? null) ? $entry["disclosed_by"] : [];
			T::ok($sites !== [], "\"{$gate}\" names at least one disclosure site");

			foreach ($sites as [$class, $method, $token]) {
				if (!method_exists($class, $method)) {
					$missing[] = "{$gate}: {$class}::{$method} does not exist";

					continue;
				}

				$body = ai_surface_method_body($class, $method);

				if (strpos($body, $token) === false) {
					$missing[] = "{$gate}: {$class}::{$method} no longer mentions \"{$token}\"";
				}
			}
		}

		T::equals(
			implode("; ", $missing),
			"",
			"every visibility gate is disclosed by a seam that exists and still carries the disclosure"
		);
	}

	/**
	 * E1b: the enumeration leg. Every status column the scaffold's own DDL creates is
	 * classified — as a gate with a disclosure, or as harmless with a reason.
	 */
	function test_every_scaffolded_status_column_is_classified() {
		$body = ai_surface_method_body(ModuleService::class, "performScaffold");
		T::ok($body !== "", "performScaffold's source was read");

		preg_match_all('/ADD COLUMN `([a-z_]+)`/', $body, $matches);
		$columns = array_values(array_unique($matches[1]));

		// The form's own columns are added through $column_adds (an interpolated
		// variable), so everything the literal DDL names is a builtin status column.
		T::ok(count($columns) >= 4, "the scaffold's builtin status columns were found (" . implode(", ", $columns) . ")");

		$classified = ai_visibility_status_columns();
		$gates = ai_visibility_gates();
		$unaccounted = [];

		foreach ($columns as $column) {
			if (!isset($classified[$column])) {
				$unaccounted[] = "{$column} (created by performScaffold, classified nowhere)";

				continue;
			}

			$verdict = $classified[$column];

			if (strpos($verdict, "gate:") === 0) {
				$named = substr($verdict, 5);

				if (!isset($gates[$named])) {
					$unaccounted[] = "{$column} names the gate \"{$named}\", which the map doesn't have";
				}

				continue;
			}

			T::ok(strpos($verdict, "harmless: ") === 0, "{$column} is classified harmless with a stated reason");
		}

		T::equals(
			implode("; ", $unaccounted),
			"",
			"every column the scaffold's DDL creates is a classified visibility verdict"
		);

		// And no classification outlives the column it describes.
		$stale = array_values(array_diff(array_keys($classified), $columns));
		T::equals(implode(", ", $stale), "", "no status-column classification names a column the scaffold no longer adds");
	}

	/**
	 * E1c: the behaviour, end to end, on a module that really is approval-gated.
	 *
	 * Scaffolds a module with the `approve` action — which is what actually creates
	 * the `approved` column — then asks the read seam and the two write seams what
	 * they say about it.
	 */
	function test_an_approval_gated_module_discloses_the_gate_before_and_after_the_write() {
		if (!function_exists("parity_db_available") || !parity_db_available()) {

			return;
		}

		$developer = ai_wiring_user(2);
		$modules = new ModuleService();
		$suffix = substr(md5((string)mt_rand()), 0, 8);
		$table = "zz_approval_probe_" . $suffix;

		$validation = $modules->aiValidateModuleScaffold([
			"name" => "ZZ Approval Probe " . $suffix,
			"table" => $table,
			"fields" => [["title" => "Headline", "type" => "text"]],
			"actions" => ["approve" => true],
		], $developer);

		T::ok(!empty($validation["ok"]), "the approval-gated scaffold validates (" . (string)($validation["error"] ?? "") . ")");

		$built = $modules->aiScaffoldModule($validation["payload"], $developer);
		$module_id = (string)($built["id"] ?? "");

		try {
			T::equals((string)($built["mode"] ?? ""), "created", "and builds (" . (string)($built["message"] ?? "") . ")");

			BigTreeJSONDB::$Cache = [];
			ColumnDomain::forget($table);

			$entries = new AutoModuleService();
			$read = $entries->aiModuleSchema($module_id, "", $developer);
			$schema = is_array($read["schema"] ?? null) ? $read["schema"] : [];

			T::equals(
				$schema["status_actions"] ?? null,
				["approved" => "approve"],
				"get_module_schema names the status action and the column it gates"
			);
			T::ok(
				strpos((string)($schema["approval_note"] ?? ""), "set_module_entry_flag") !== false,
				"and its approval_note names the tool that approves an entry"
			);

			$column = (string)($schema["fields"][0]["column"] ?? "");
			T::ok($column !== "", "the scaffolded form has a settable column to write into");

			$create = $entries->aiValidateEntryCreate([
				"module_id" => $module_id,
				"data" => [$column => "ZZ Approval Probe Entry"],
			], $developer);

			T::ok(!empty($create["ok"]), "an entry stages (" . (string)($create["error"] ?? "") . ")");
			T::ok(
				stripos((string)($create["preview"]["warning"] ?? ""), "approv") !== false,
				"and the proposal card warns that the entry won't be live until it is approved"
			);

			$result = $entries->aiCreateEntry($create["payload"], $developer);
			T::equals((string)($result["mode"] ?? ""), "published", "approving it writes the row (" . (string)($result["message"] ?? "") . ")");
			T::ok(
				strpos((string)($result["note"] ?? ""), "set_module_entry_flag") !== false,
				"and the result the model reads back says the row is not live yet"
			);

			$entry_id = (int)($result["entry_id"] ?? 0);
			T::ok($entry_id > 0, "the entry has an id");
			T::equals(
				(string)SQL::fetchSingle("SELECT approved FROM `{$table}` WHERE id = ?", $entry_id),
				"",
				"and it really is stored unapproved — which is what all of the above is about"
			);

			// An update to the same still-unapproved row repeats it; the row is no more
			// visible after the edit than before it.
			$update = $entries->aiValidateEntryUpdate([
				"module_id" => $module_id,
				"entry_id" => (string)$entry_id,
				"data" => [$column => "ZZ Approval Probe Entry, revised"],
			], $developer);

			T::ok(!empty($update["ok"]), "an edit stages (" . (string)($update["error"] ?? "") . ")");
			T::ok(
				stripos((string)($update["preview"]["warning"] ?? ""), "approv") !== false,
				"and warns that the entry it is editing still isn't on the site"
			);

			// Once approved, there is nothing left to disclose.
			SQL::query("UPDATE `{$table}` SET approved = 'on' WHERE id = ?", $entry_id);
			$approved_update = $entries->aiValidateEntryUpdate([
				"module_id" => $module_id,
				"entry_id" => (string)$entry_id,
				"data" => [$column => "ZZ Approval Probe Entry, approved"],
			], $developer);

			T::ok(!empty($approved_update["ok"]), "an edit to an approved entry stages");
			T::equals(
				(string)($approved_update["preview"]["warning"] ?? ""),
				"",
				"and says nothing about approval, because there is nothing to say"
			);
		} finally {
			if ($module_id !== "") {
				BigTreeJSONDB::delete("modules", $module_id);
				BigTreeJSONDB::$Cache = [];
			}

			if (BigTree::tableExists($table)) {
				SQL::query("DROP TABLE `{$table}`");
			}

			ColumnDomain::forget($table);
		}
	}

	/**
	 * A module with no approve action gains no column and is owed no note — the
	 * disclosure has to be conditional on the gate, or it becomes noise on every
	 * card in the catalogue.
	 */
	function test_a_module_without_the_gate_says_nothing_about_approval() {
		if (!function_exists("parity_db_available") || !parity_db_available()) {

			return;
		}

		$developer = ai_wiring_user(2);
		$modules = new ModuleService();
		$suffix = substr(md5((string)mt_rand()), 0, 8);
		$table = "zz_open_probe_" . $suffix;

		$validation = $modules->aiValidateModuleScaffold([
			"name" => "ZZ Open Probe " . $suffix,
			"table" => $table,
			"fields" => [["title" => "Headline", "type" => "text"]],
		], $developer);

		T::ok(!empty($validation["ok"]), "the ungated scaffold validates (" . (string)($validation["error"] ?? "") . ")");

		$built = $modules->aiScaffoldModule($validation["payload"], $developer);
		$module_id = (string)($built["id"] ?? "");

		try {
			T::equals((string)($built["mode"] ?? ""), "created", "and builds");

			BigTreeJSONDB::$Cache = [];
			ColumnDomain::forget($table);

			$entries = new AutoModuleService();
			$read = $entries->aiModuleSchema($module_id, "", $developer);
			$schema = is_array($read["schema"] ?? null) ? $read["schema"] : [];

			T::equals($schema["status_actions"] ?? null, [], "no status actions are claimed");
			T::equals((string)($schema["approval_note"] ?? ""), "", "and no approval note is written");

			$create = $entries->aiValidateEntryCreate([
				"module_id" => $module_id,
				"data" => [(string)($schema["fields"][0]["column"] ?? "") => "ZZ Open Probe Entry"],
			], $developer);

			T::ok(!empty($create["ok"]), "an entry stages (" . (string)($create["error"] ?? "") . ")");
			T::equals((string)($create["preview"]["warning"] ?? ""), "", "and the card carries no approval warning");
		} finally {
			if ($module_id !== "") {
				BigTreeJSONDB::delete("modules", $module_id);
				BigTreeJSONDB::$Cache = [];
			}

			if (BigTree::tableExists($table)) {
				SQL::query("DROP TABLE `{$table}`");
			}

			ColumnDomain::forget($table);
		}
	}

	/**
	 * C2/C3: the module read has to describe the actions and view types A1's
	 * disclosure is derived from. `action_count` was a number that told the model
	 * nothing, and a view with no `type` can't be told from a drag-orderable one.
	 */
	function test_get_module_describes_its_actions_and_view_types() {
		if (!function_exists("parity_db_available") || !parity_db_available()) {

			return;
		}

		$developer = ai_wiring_user(2);
		$modules = new ModuleService();
		$suffix = substr(md5((string)mt_rand()), 0, 8);
		$table = "zz_readback_probe_" . $suffix;

		$validation = $modules->aiValidateModuleScaffold([
			"name" => "ZZ Readback Probe " . $suffix,
			"table" => $table,
			"fields" => [["title" => "Headline", "type" => "text"]],
			"actions" => ["approve" => true],
			"view_type" => "draggable",
		], $developer);

		T::ok(!empty($validation["ok"]), "the scaffold validates (" . (string)($validation["error"] ?? "") . ")");

		$built = $modules->aiScaffoldModule($validation["payload"], $developer);
		$module_id = (string)($built["id"] ?? "");

		try {
			T::equals((string)($built["mode"] ?? ""), "created", "and builds");
			BigTreeJSONDB::$Cache = [];

			$read = $modules->aiGetModule($module_id, $developer);
			$module = is_array($read["module"] ?? null) ? $read["module"] : [];

			T::ok(!isset($module["action_count"]), "get_module no longer reports a bare action count");
			T::ok(is_array($module["actions"] ?? null) && $module["actions"] !== [], "it reports the actions themselves");

			$routes = array_map(function ($a) {

				return (string)($a["route"] ?? "");
			}, $module["actions"]);

			T::ok(in_array("add", $routes, true), "including the add route");
			T::equals(
				(string)($module["views"][0]["type"] ?? ""),
				"draggable",
				"and a view says what type it is, so position-bearing views can be told apart"
			);
			T::equals(
				$module["status_actions"] ?? null,
				["approved" => "approve"],
				"and the status actions are named on the read side too"
			);
		} finally {
			if ($module_id !== "") {
				BigTreeJSONDB::delete("modules", $module_id);
				BigTreeJSONDB::$Cache = [];
			}

			if (BigTree::tableExists($table)) {
				SQL::query("DROP TABLE `{$table}`");
			}

			ColumnDomain::forget($table);
		}
	}
