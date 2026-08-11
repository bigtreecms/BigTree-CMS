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
			// Audit #22 A3. The gate here isn't that the page becomes invisible — it is
			// that every URL under it becomes a different URL. `repathChildren` rewrites
			// `path` on every descendant and writes a route-history redirect for each, so
			// renaming one route changes the address of an entire branch of the site.
			// `move_page` disclosed the blast radius and the mitigation; `update_page`,
			// the same event through a different door, showed one diff row reading
			// `route: old → new` and said neither. The token is the shared helper both
			// validators now compose the sentences with, so deleting it from either fails
			// here.
			"page: route change repaths the subtree" => [
				"why" => "performMove and performUpdate's route block both call repathChildren, which rewrites "
					. "every descendant's path and leaves a route-history redirect behind for each old URL",
				"disclosed_by" => [
					[PageService::class, "aiValidatePageMove", "aiPathChangeDisclosure"],
					[PageService::class, "aiValidatePageUpdate", "aiPathChangeDisclosure"],
					[PageService::class, "aiPathChangeDisclosure", "A redirect will be left behind for every old URL."],
				],
			],
			"page: editor's create queues as pending" => [
				"why" => "the same queue, one content type over",
				"disclosed_by" => [
					[PageService::class, "aiValidatePageCreate", "pending"],
				],
			],
			// Audit #21 A1. Every gate above this line is about a *record* — an entry or
			// a page — which is why A1 survived twenty audits: the one thing the
			// assistant authors that isn't a record is a module, and a module has a
			// visibility gate of exactly the same kind. `create_module` and `get_module`
			// both named it; `scaffold_module`, the tool that exists precisely so the
			// assistant produces a module that *works*, returned a literal
			// `"is_complete" => true` and said nothing about permissions at all.
			"module: no permission grant" => [
				"why" => "PermissionService::userModuleLevel defaults an ungranted module to \"n\", so a module "
					. "nobody holds a grant on is reachable only by administrators and developers — a "
					. "fully-built module is invisible to every editor it was built for",
				"disclosed_by" => [
					[ModuleService::class, "aiGetModule", "moduleMissingSetup"],
					[ModuleService::class, "aiValidateModuleCreate", "aiModuleSetupSteps"],
					[ModuleService::class, "aiCreateModule", "aiModuleSetupSteps"],
					[ModuleService::class, "aiValidateModuleScaffold", "aiScaffoldRemainingSetup"],
					[ModuleService::class, "aiScaffoldModule", "aiScaffoldRemainingSetup"],
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
	 * Audit #21 B6, the container equivalent of E1b's enumeration leg: every seam
	 * that reports `is_complete` must *derive* it.
	 *
	 * A literal is what A1 was. `scaffold_module` returned `"is_complete" => true`
	 * because it had just built a table, a form and a view — true of the four steps
	 * it knew about and false of the module, since `get_module` counts a permission
	 * grant as setup too. Nothing could catch that while the value was an assertion
	 * rather than a question, and this is the cheap check that catches the whole
	 * class: a completeness claim has to be computed from something.
	 */
	function test_no_module_seam_asserts_its_own_completeness() {
		$source = file_get_contents(__DIR__ . "/../../services/ModuleService.php");
		T::ok(is_string($source) && $source !== "", "ModuleService's source was read");

		// Comments stripped first: this file explains the finding in prose, and a
		// guard that reads its own explanation as code is a guard that fails for the
		// wrong reason the moment somebody quotes the bug.
		$code = "";

		foreach (token_get_all((string)$source) as $token) {
			if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {

				continue;
			}

			$code .= is_array($token) ? $token[1] : $token;
		}

		preg_match_all('/"is_complete"\s*=>\s*([^,\n]+)/', $code, $matches);
		T::ok(count($matches[1]) >= 3, "the is_complete sites were found (" . count($matches[1]) . ")");

		$literal = [];

		foreach ($matches[1] as $expression) {
			$expression = trim($expression);

			if ($expression === "true" || $expression === "false") {
				$literal[] = $expression;
			}
		}

		T::equals(
			implode(", ", $literal),
			"",
			"no seam reports a hard-coded is_complete — every one is derived from the module's real state"
		);
	}

	/**
	 * Audit #21 B6: the contradiction stated directly.
	 *
	 * Scaffold a module, then read it back, and assert the two seams agree about
	 * whether it is finished. This is the thing a developer would have noticed if
	 * anything had ever asked — the same module, in the same turn, was
	 * `is_complete: true` from the tool that built it and `is_complete: false` from
	 * the tool that reads it.
	 *
	 * An editor account is seeded first so this runs the same way on every install.
	 * A1 is about the state where editors exist and none can reach the module, and
	 * the local development install has no level-0 users at all — which is A4's
	 * other state, and would have left the finding's own case untested.
	 */
	function test_a_scaffolded_module_agrees_with_get_module_about_whether_it_is_finished() {
		if (!function_exists("parity_db_available") || !parity_db_available()) {

			return;
		}

		$developer = ai_wiring_user(2);
		$modules = new ModuleService();
		$suffix = substr(md5((string)mt_rand()), 0, 8);
		$table = "zz_complete_probe_" . $suffix;
		$editor_id = parity_seed_user(["level" => 0, "permissions" => ["module" => []]]);

		$validation = $modules->aiValidateModuleScaffold([
			"name" => "ZZ Complete Probe " . $suffix,
			"table" => $table,
			"fields" => [["title" => "Headline", "type" => "text"]],
		], $developer);

		T::ok(!empty($validation["ok"]), "the scaffold validates (" . (string)($validation["error"] ?? "") . ")");

		// The card is what the approver sees first, so the disclosure has to be there
		// and not only in the note the model reads back afterwards.
		$remaining = is_array($validation["preview"]["remaining_setup"] ?? null)
			? $validation["preview"]["remaining_setup"]
			: [];
		T::ok($remaining !== [], "the proposal card lists what the scaffold won't finish");
		T::ok(
			strpos(implode(" ", $remaining), "Module Permissions") !== false,
			"including the permission grant"
		);
		T::ok(
			stripos((string)($validation["summary"] ?? ""), "granted access") !== false,
			"and the summary says so too, since that is what a one-line notification shows"
		);

		// And it names only what a scaffold really leaves behind: the four structural
		// steps it just performed must not be on the list.
		T::equals(
			implode("; ", array_values(array_filter($remaining, function (string $step): bool {

				return stripos($step, "Create the database table") === 0
					|| stripos($step, "Add an entry form") === 0
					|| stripos($step, "Add a view") === 0
					|| stripos($step, "Add the module's actions") === 0;
			}))),
			"",
			"and none of the steps the scaffold itself performs"
		);

		$built = $modules->aiScaffoldModule($validation["payload"], $developer);
		$module_id = (string)($built["id"] ?? "");

		try {
			T::equals((string)($built["mode"] ?? ""), "created", "and builds (" . (string)($built["message"] ?? "") . ")");

			BigTreeJSONDB::$Cache = [];

			$read = $modules->aiGetModule($module_id, $developer);
			$module = is_array($read["module"] ?? null) ? $read["module"] : [];

			T::ok(array_key_exists("is_complete", $built), "the scaffold reports whether the module is finished");
			T::equals(
				!empty($built["is_complete"]),
				!empty($module["is_complete"]),
				"and agrees with get_module about the same module in the same turn"
					. " (missing: " . implode(", ", (array)($module["missing_setup"] ?? [])) . ")"
			);

			// The seeded editor holds no grant, so this is A1's exact state: a module
			// with a table, a form, a view and its actions, that no editor can open.
			T::ok(empty($built["is_complete"]), "a module no editor can reach is not reported as finished");
			T::ok(
				in_array("a permission grant for at least one user", (array)($module["missing_setup"] ?? []), true),
				"and the grant is what it is missing"
			);
			T::ok(
				strpos((string)($built["note"] ?? ""), "Module Permissions") !== false,
				"the post-write note says where to grant it"
			);
			T::ok(
				stripos((string)($built["note"] ?? ""), "administrators and developers only") !== false,
				"and scopes \"ready to use\" to who it is actually true for"
			);
			T::equals(
				(string)($module["permissions_note"] ?? ""),
				"",
				"with no no-editors note, because this site has one"
			);

			// Grant it and both seams flip together — the disclosure is conditional on
			// the gate, not a sentence every scaffold carries.
			SQL::update("bigtree_users", $editor_id, ["permissions" => json_encode(["module" => [$module_id => "e"]])]);
			BigTreeJSONDB::$Cache = [];

			$granted = $modules->aiGetModule($module_id, $developer);
			$granted_module = is_array($granted["module"] ?? null) ? $granted["module"] : [];

			T::equals((array)($granted_module["missing_setup"] ?? [null]), [], "a granted module is missing nothing");
			T::ok(!empty($granted_module["is_complete"]), "and reads as complete");
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
	 * Audit #21 A4: the other grant state, which wants a different sentence.
	 *
	 * `moduleHasAnyGrant` iterated level-0 users and returned false when there were
	 * none, so an install run entirely by administrators — every module fully built
	 * — reported every one of them as forever missing "a permission grant for at
	 * least one user". Not false, but unactionable: it tells a developer to grant
	 * access on a site with nobody to grant it to, and holds `is_complete`
	 * permanently false for a site that isn't incomplete.
	 *
	 * Runs only on an install that really has no editor accounts, which is the
	 * condition the finding is about — seeding one would make it a different test.
	 */
	function test_a_site_with_no_editors_is_not_told_to_grant_access_to_nobody() {
		if (!function_exists("parity_db_available") || !parity_db_available()) {

			return;
		}

		if ((int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_users WHERE level = 0") > 0) {
			echo "  (skipped — this install has editor accounts, so the state under test doesn't exist here)\n";

			return;
		}

		$developer = ai_wiring_user(2);
		$modules = new ModuleService();
		$suffix = substr(md5((string)mt_rand()), 0, 8);
		$table = "zz_noeditor_probe_" . $suffix;

		$validation = $modules->aiValidateModuleScaffold([
			"name" => "ZZ No Editor Probe " . $suffix,
			"table" => $table,
			"fields" => [["title" => "Headline", "type" => "text"]],
		], $developer);

		T::ok(!empty($validation["ok"]), "the scaffold validates (" . (string)($validation["error"] ?? "") . ")");
		T::ok(
			strpos(implode(" ", (array)($validation["preview"]["remaining_setup"] ?? [])), "Module Permissions") === false,
			"the card doesn't list a grant among what's left"
		);

		$built = $modules->aiScaffoldModule($validation["payload"], $developer);
		$module_id = (string)($built["id"] ?? "");

		try {
			T::equals((string)($built["mode"] ?? ""), "created", "and builds (" . (string)($built["message"] ?? "") . ")");
			T::ok(!empty($built["is_complete"]), "the module is reported finished, because it is");

			BigTreeJSONDB::$Cache = [];

			$read = $modules->aiGetModule($module_id, $developer);
			$module = is_array($read["module"] ?? null) ? $read["module"] : [];

			T::ok(!empty($module["is_complete"]), "and get_module agrees");
			T::ok(
				!in_array("a permission grant for at least one user", (array)($module["missing_setup"] ?? []), true),
				"with no unactionable grant step in missing_setup"
			);
			T::ok(
				stripos((string)($module["permissions_note"] ?? ""), "no editor accounts") !== false,
				"the state is said as a note instead, which is what it is"
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
