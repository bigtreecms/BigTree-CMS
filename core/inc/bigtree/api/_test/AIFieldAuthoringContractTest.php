<?php
	/**
	 * Audit #12 guard E3 (C3): the field-authoring contract.
	 *
	 * `Resources::aiUnconfigurableFieldError` is the gate audits #5, #10 C2 and #11 A4
	 * built — it refuses a field whose type needs structured configuration the
	 * assistant can't author, and any field whose own `settings_schema` marks a
	 * setting required that nothing filled in. It had twelve call sites and every one
	 * of them was in TemplateService or CalloutService, because those were the only
	 * two surfaces that authored a field list when it was written.
	 *
	 * `scaffold_module` then authored a third, through ModuleService, and inherited
	 * none of it: its only field check was "does this type exist in the registry?", so
	 * it could build a `list` with no options, an `image` with no directory, a
	 * `matrix` with no columns, or a relation with no target table — and then be told
	 * by RelationDomain, three turns later, that a developer has to finish the field
	 * the assistant itself created. Nothing failed when that landed, because nothing
	 * bound "authors a field list" to "runs the gate".
	 *
	 * This does. Every seam that reads a `fields` array must call the gate at staging
	 * *and* at approval, or carry a written exemption — and every mutating tool must
	 * be in the enumeration that decides which seams those are, which is the leg that
	 * would have caught the scaffold on the way in.
	 */

	use BigTree\Services\AIChatService;

	/**
	 * Seams that read a `fields` array and deliberately don't run the completeness
	 * gate. Each carries its reason; an unexplained one fails.
	 *
	 * @return array<string,string>
	 */
	function ai_field_authoring_exempt(): array {

		return [];
	}

	/**
	 * Tool => [class, approval method], parsed out of the approval dispatcher so the
	 * pairing can't drift from the switch that actually runs.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	function ai_field_authoring_approval_seams(): array {
		$reflection = new ReflectionMethod(AIChatService::class, "executeProposal");
		$lines = file(SERVER_ROOT . "core/inc/bigtree/services/AIChatService.php");
		$body = implode("", array_slice(
			$lines,
			$reflection->getStartLine() - 1,
			$reflection->getEndLine() - $reflection->getStartLine() + 1
		));

		preg_match_all(
			'/case "([a-z_]+)":\s*\n\s*return \(new ([A-Za-z0-9_]+)\(\)\)->([A-Za-z0-9_]+)\(/',
			$body,
			$matches,
			PREG_SET_ORDER
		);

		$seams = [];

		foreach ($matches as $match) {
			$seams[$match[1]] = ["BigTree\\Services\\" . $match[2], $match[3]];
		}

		return $seams;
	}

	/**
	 * The enumeration E3 stands on: every mutating tool is mapped to the validate
	 * seam it dispatches to.
	 *
	 * `scaffold_module` was absent from that map for a whole audit cycle — the same
	 * structural blindness C1 found in the referential contract, one map over. A seam
	 * nothing enumerates is a seam no contract can ask anything of.
	 */
	function test_every_mutating_tool_has_a_mapped_validate_seam() {
		$registry = ai_wiring_registry();
		$developer = ai_wiring_user(2);
		$seams = ai_inverse_validate_seams();
		$unmapped = [];

		foreach ($registry->availableTools($developer) as $tool) {
			if ($tool->kind() === "read") {

				continue;
			}

			// Extension tools dispatch through their own executeApproved rather than a
			// core service seam, and ExtensionTools has its own guard.
			if (!isset(ai_field_authoring_approval_seams()[$tool->name()])) {

				continue;
			}

			if (!isset($seams[$tool->name()])) {
				$unmapped[] = $tool->name();
			}
		}

		T::equals(
			implode(", ", $unmapped),
			"",
			"every core mutating tool names the validate seam it dispatches to"
		);
	}

	/**
	 * E3: a seam that authors a field list runs the settings-completeness gate, at
	 * staging and at approval.
	 */
	function test_every_field_authoring_seam_runs_the_completeness_gate() {
		$approvals = ai_field_authoring_approval_seams();
		$exempt = ai_field_authoring_exempt();
		$authoring = [];
		$missing = [];

		foreach (ai_inverse_validate_seams() as $tool => [$class, $method]) {
			$staging = ai_surface_method_body($class, $method);
			T::ok($staging !== "", "{$method}'s source was read");

			[$approval_class, $approval_method] = $approvals[$tool] ?? [null, null];
			$approval = $approval_class !== null ? ai_surface_method_body($approval_class, $approval_method) : "";

			// "Authors a field list" is a property of the seam's source, not of a list
			// someone remembered to update: either pass reads a `fields` array.
			if (strpos($staging, '["fields"]') === false && strpos($approval, '["fields"]') === false) {

				continue;
			}

			$authoring[] = $tool;

			if (isset($exempt[$tool])) {

				continue;
			}

			if (strpos($staging, "aiUnconfigurableFieldError") === false) {
				$missing[] = "{$tool} (staging: {$method})";
			}

			if ($approval_method === null) {
				$missing[] = "{$tool} (no approval seam found in executeProposal)";

				continue;
			}

			if (strpos($approval, "aiUnconfigurableFieldError") === false) {
				$missing[] = "{$tool} (approval: {$approval_method})";
			}
		}

		// The positive controls, so a regex that silently stopped matching anything
		// can't pass this test by finding nothing to check.
		foreach (["create_template", "create_callout", "scaffold_module"] as $tool) {
			T::ok(in_array($tool, $authoring, true), "{$tool} is recognised as a field-authoring seam");
		}

		T::equals(
			implode(", ", $missing),
			"",
			"every field-authoring seam runs aiUnconfigurableFieldError at staging and approval"
		);
	}

	/** No exemption may outlive the seam it excuses. */
	function test_field_authoring_exemptions_are_not_stale() {
		$stale = array_values(array_diff(array_keys(ai_field_authoring_exempt()), array_keys(ai_inverse_validate_seams())));

		T::equals(implode(", ", $stale), "", "no field-authoring exemption names a tool that no longer exists");
	}

	/**
	 * The module surface is wired into the shared helper rather than approximating
	 * it: the use case a module's field settings resolve against is `modules`, which
	 * is what decides an upload's seeded directory and which `contexts`-scoped
	 * settings are asked for at all.
	 */
	function test_module_surface_resolves_the_modules_use_case() {
		$settings = \BigTree\Api\Resources::aiFieldSettings(
			["title" => "Photo", "required" => true],
			"image",
			"module"
		);

		T::equals(
			(string)($settings["directory"] ?? ""),
			"files/modules/",
			"a required upload directory is seeded from the module context default"
		);
		T::equals((string)($settings["validation"] ?? ""), "required", "and `required` is stored as the validation rule");

		// The module surface is the one that takes a settings object from the model,
		// because scaffold_module declares one and writes it onto the form field.
		$supplied = \BigTree\Api\Resources::aiFieldSettings(
			["title" => "Region", "settings" => ["table" => "regions", "title_column" => "name"]],
			"one-to-many",
			"module"
		);
		T::equals((string)($supplied["table"] ?? ""), "regions", "a supplied setting is carried through on modules");

		// …and templates and callouts still refuse to carry one, which is what keeps
		// the gate meaningful there.
		$template = \BigTree\Api\Resources::aiFieldSettings(
			["id" => "region", "settings" => ["table" => "regions"]],
			"one-to-many",
			"template"
		);
		T::ok(!isset($template["table"]), "a template field's settings object is still ignored");
	}

	/**
	 * A static list's choices are normalized wherever the model puts them.
	 *
	 * The completeness gate only asks whether the `list` setting is non-empty, so a
	 * raw ["Small", "Large"] satisfied it and then stored rows the Select can't read —
	 * on the module surface only, because it is the one surface that passes a whole
	 * settings object through. The `options` argument the refusal names is the shape
	 * every other authoring tool takes, and both now land as {value, description}.
	 */
	function test_a_list_fields_choices_are_normalized_from_either_place() {
		$rows = [["value" => "Small", "description" => "Small"], ["value" => "Large", "description" => "Large"]];

		$declared = \BigTree\Api\Resources::aiFieldSettings(
			["title" => "Size", "options" => ["Small", "Large"]],
			"list",
			"module"
		);
		T::equals($declared["list"] ?? null, $rows, "the `options` argument the refusal names is normalized");

		$supplied = \BigTree\Api\Resources::aiFieldSettings(
			["title" => "Size", "settings" => ["list" => ["Small", "Large"]]],
			"list",
			"module"
		);
		T::equals($supplied["list"] ?? null, $rows, "and so is a raw list handed through the settings object");
		T::equals((string)($supplied["list_type"] ?? ""), "static", "which is a static list, said so explicitly");

		// Already-normalized rows are left alone, and a db-populated list isn't a set
		// of rows at all — normalizing one would destroy the settings it does have.
		$already = \BigTree\Api\Resources::aiFieldSettings(
			["title" => "Size", "settings" => ["list" => $rows]],
			"list",
			"module"
		);
		T::equals($already["list"] ?? null, $rows, "normalizing twice changes nothing");

		$db = \BigTree\Api\Resources::aiFieldSettings(
			["title" => "Size", "settings" => ["list_type" => "db", "pop-table" => "regions", "list" => []]],
			"list",
			"module"
		);
		T::equals((string)($db["list_type"] ?? ""), "db", "a db-populated list keeps its type");
		T::equals((string)($db["pop-table"] ?? ""), "regions", "and its table");
	}

	/**
	 * The scaffold card states the two things about a column an approver can only
	 * learn from the card: whether an entry must fill it in, and whether the
	 * assistant will be able to fill it at all (audit #12 A4/A5).
	 */
	function test_the_scaffold_card_states_required_and_the_columns_it_cannot_fill() {
		if (!function_exists("parity_db_available") || !parity_db_available()) {

			return;
		}

		$validation = (new \BigTree\Services\ModuleService())->aiValidateModuleScaffold([
			"name" => "ZZ Card Probe",
			"table" => "zz_card_probe_" . substr(md5((string)mt_rand()), 0, 8),
			"fields" => [
				["title" => "Headline", "type" => "text", "required" => true],
				["title" => "Byline", "type" => "text"],
				["title" => "Photo", "type" => "image"],
				["title" => "Regions", "type" => "one-to-many", "settings" => [
					"table" => "bigtree_pages",
					"title_column" => "nav_title",
				]],
			],
		], ai_wiring_user(2));

		T::ok(!empty($validation["ok"]), "the scaffold validates (" . (string)($validation["error"] ?? "") . ")");

		$rows = [];

		foreach ($validation["preview"]["fields"] as $field) {
			$rows[(string)$field["title"]] = (string)$field["to"];
		}

		T::ok(strpos($rows["Headline"] ?? "", "required") !== false, "a required column says so on the card");
		T::ok(strpos($rows["Byline"] ?? "", "required") === false, "an optional one doesn't");
		T::ok(
			strpos($rows["Regions"] ?? "", "TEXT") !== false,
			"a relation's id list is shown with the storage it actually gets"
		);

		// The summary is what the model reads back and what a one-line notification
		// shows, so the columns it can't fill are named there too, not only in the
		// warning banner.
		T::ok(
			strpos((string)$validation["summary"], "Photo") !== false,
			"the summary names the column the assistant can't fill"
		);
		T::ok(
			strpos((string)$validation["summary"], "4 columns") !== false,
			"and counts the columns the table actually gets, not the rows on the card"
		);

		// Preview keys render as their own rows, so the ones that are only worth a row
		// when they're true are absent when they're false.
		T::ok(!isset($validation["preview"]["group"]), "an ungrouped scaffold has no Group row");
		T::ok(!isset($validation["preview"]["tagging"]), "and no Tagging row when tagging is off");
		T::ok(!isset($validation["preview"]["open_graph"]), "and no Open Graph row when it's off");

		$tagged = (new \BigTree\Services\ModuleService())->aiValidateModuleScaffold([
			"name" => "ZZ Card Probe Tagged",
			"table" => "zz_card_probe_t_" . substr(md5((string)mt_rand()), 0, 8),
			"fields" => [["title" => "Headline", "type" => "text"]],
			"tagging" => true,
		], ai_wiring_user(2));

		T::equals($tagged["preview"]["tagging"] ?? null, true, "and it is there when it's on");
	}

	/**
	 * The completeness gate has to read a field in both shapes it arrives in.
	 *
	 * A create seam stages the *merged* resource list — rows carrying a `settings`
	 * blob — and then re-runs the gate on that payload at approval, "never trusted on
	 * the way back out". `aiFieldSettings` read `settings` on the module surface only,
	 * so on a template or a callout the merged row's static list was invisible: a
	 * `create_template` carrying a `list` field staged clean and then failed at
	 * approval with "has no choices, supply them as options" — for a field whose
	 * choices were sitting in the payload being judged. Found while wiring the
	 * default-value domain into the same chain (audit #20), which would otherwise
	 * have been a no-op at every create approval for the same reason.
	 *
	 * The rule, asserted rather than described: aiFieldSettings is idempotent on its
	 * own output.
	 */
	function test_the_field_gate_reads_a_field_in_both_shapes_it_arrives_in() {
		foreach (["template", "callout", "module"] as $surface) {
			$proposed = [
				"id" => "size",
				"title" => "Size",
				"type" => "list",
				"options" => ["Small", "Large"],
				"required" => true,
				"default" => "Small",
			];

			$merged = \BigTree\Api\Resources::mergeAiFields([$proposed], [], $surface);
			T::equals(count($merged), 1, "{$surface}: the proposal merges to one row");

			// The staged row, re-judged exactly as the approval seam judges it.
			T::equals(
				\BigTree\Api\Resources::aiUnconfigurableFieldError($merged, [], $surface),
				null,
				"{$surface}: the merged row passes the completeness gate a second time"
			);
			T::equals(
				\BigTree\Api\Resources::aiFieldSettings($merged[0], "list", $surface),
				$merged[0]["settings"],
				"{$surface}: re-deriving a merged row's settings changes nothing"
			);
		}

		// …and a template field's arbitrary `settings` object is still ignored, which
		// is what keeps the gate meaningful there: only the two settings the
		// assistant's own field shape can express are read back.
		$template = \BigTree\Api\Resources::aiFieldSettings(
			["id" => "region", "settings" => ["table" => "regions", "list" => [["value" => "a", "description" => "A"]]]],
			"one-to-many",
			"template"
		);
		T::ok(!isset($template["table"]), "a template field's structured settings are still not carried through");
	}

	// — audit #20 guard E1: a `default` is validated by the rules of a *value* —

	/**
	 * E1, option-domain leg: the string `FieldOptionDomain` refuses as an entry value
	 * is refused as the default that becomes every entry's value.
	 *
	 * `default` is the first field setting the assistant can author whose stored value
	 * later becomes record content — `applyFormFieldDefaults` seeds it into the
	 * entry's column and `normalizePageResources` writes it into
	 * `bigtree_pages.resources`, both of them after every gate has run. So it entered
	 * through the door with no gates on it (an `is_scalar` and a cast) and left
	 * through the one that has them, having never been checked by either (audit #20
	 * A1).
	 *
	 * The label leg is what proves the shared implementation was *called* rather than
	 * reimplemented: matching a recognisable label back to its stored value is
	 * FieldOptionDomain's own behaviour, and a second copy of the rule written here
	 * would not have it.
	 */
	function test_a_default_is_held_to_the_fields_own_option_domain() {
		$field = [
			"id" => "size",
			"title" => "Size",
			"type" => "list",
			"options" => ["Small", "Medium", "Large"],
			"default" => "Enormous",
		];

		foreach (["template", "callout", "module"] as $surface) {
			$error = (string)\BigTree\Api\Resources::aiFieldDefaultError([$field], [], $surface);

			T::ok($error !== "", "a {$surface} field's out-of-domain default is refused");
			T::ok(
				strpos($error, "isn't one of the options for") !== false,
				"…in FieldOptionDomain's own wording, not a second copy of the rule"
			);
			T::ok(strpos($error, "Small, Medium, Large") !== false, "…and it names the choices to pick from");
		}

		// A default given as a recognisable *label* resolves to the stored value in
		// place, exactly as an entry value does — the leg the shared implementation is
		// the only way to pass.
		$labelled = [
			"id" => "size",
			"title" => "Size",
			"type" => "list",
			"options" => [["value" => "sm", "description" => "Small"], ["value" => "lg", "description" => "Large"]],
			"default" => "Small",
		];

		T::equals(
			\BigTree\Api\Resources::aiFieldDefaultError([$labelled], [], "template"),
			null,
			"a default written as an option's label is accepted"
		);
		T::equals(
			(string)(\BigTree\Api\Resources::aiFieldSettings($labelled, "list", "template")["default"] ?? ""),
			"sm",
			"…and stored as that option's value, not as the label"
		);

		// An in-domain default is simply accepted.
		$fine = array_merge($field, ["default" => "Medium"]);
		T::equals(
			\BigTree\Api\Resources::aiFieldDefaultError([$fine], [], "template"),
			null,
			"a default that is one of the options passes"
		);
	}

	/**
	 * E1, storage-domain leg: a default that wouldn't survive the trip into storage is
	 * refused rather than silently truncated or coerced.
	 *
	 * With `sql_mode = ''` (audit #10's premise, unchanged) MySQL's answer to "this
	 * doesn't fit" is to cut the string to the column width and turn "tomorrow" into
	 * `0000-00-00` — on *every* entry created from the form, not once.
	 */
	function test_a_default_is_held_to_the_storage_it_lands_in() {
		$long = str_repeat("a", 5000);
		$error = (string)\BigTree\Api\Resources::aiFieldDefaultError(
			[["id" => "Blurb", "title" => "Blurb", "type" => "text", "default" => $long]],
			[],
			"module"
		);

		T::ok($error !== "", "a default longer than the column scaffold_module will emit is refused");
		T::ok(
			strpos($error, "191") !== false,
			"…named as the width ModuleService::columnSqlType actually emits, not a number written here"
		);

		// The field's own declared cap, which every value path honours and this was
		// the one value on the field never held to.
		$capped = (string)\BigTree\Api\Resources::aiFieldDefaultError(
			[[
				"id" => "Meta",
				"title" => "Meta Title",
				"type" => "text",
				"settings" => ["max_length" => 10],
				"default" => "far longer than ten characters",
			]],
			[],
			"module"
		);
		T::ok(strpos($capped, "limited to 10") !== false, "a default over the field's own max_length is refused");

		// A relative date resolves to one frozen day and is then handed to every
		// record created afterwards, which is not what "tomorrow" meant.
		$relative = (string)\BigTree\Api\Resources::aiFieldDefaultError(
			[["id" => "starts", "title" => "Starts", "type" => "date", "default" => "tomorrow"]],
			[],
			"template"
		);
		T::ok($relative !== "", "a relative date is refused as a default");
		T::ok(strpos($relative, "relative to the current date") !== false, "…and says why a default is different");

		$unparseable = (string)\BigTree\Api\Resources::aiFieldDefaultError(
			[["id" => "starts", "title" => "Starts", "type" => "date", "default" => "sometime soonish"]],
			[],
			"template"
		);
		T::ok($unparseable !== "", "and so is a string that is no date at all");

		T::equals(
			\BigTree\Api\Resources::aiFieldDefaultError(
				[["id" => "starts", "title" => "Starts", "type" => "date", "default" => "2026-03-04"]],
				[],
				"template"
			),
			null,
			"an explicit date passes"
		);

		// A field type whose value isn't one scalar has no default to set — refused
		// rather than dropped, since a dropped argument is a successful card for a
		// request the assistant didn't fulfil.
		$composite = (string)\BigTree\Api\Resources::aiFieldDefaultError(
			[["id" => "Rows", "title" => "Rows", "type" => "matrix", "settings" => ["columns" => [["id" => "a"]]], "default" => "x"]],
			[],
			"module"
		);
		T::ok(strpos($composite, "has no default") !== false, "a default on an array-valued field is refused");
	}

	/**
	 * E1's end-to-end leg: the assertion is on the stored row, not on the settings
	 * blob — the authored default really does become the content of every entry
	 * created from the form, normalized as the option domain resolved it.
	 */
	function test_an_authored_default_reaches_the_row_as_the_option_domain_resolved_it() {
		if (!function_exists("parity_db_available") || !parity_db_available()) {

			return;
		}

		$modules = new \BigTree\Services\ModuleService();
		$entries = new \BigTree\Services\AutoModuleService();
		$developer = ai_wiring_user(2);
		$table = "zz_default_row_" . substr(md5((string)mt_rand()), 0, 8);
		$module_id = "";
		$entry_id = 0;

		try {
			$staged = $modules->aiValidateModuleScaffold([
				"name" => "ZZ Default Row " . substr(md5((string)mt_rand()), 0, 6),
				"table" => $table,
				"fields" => [
					["title" => "Headline", "type" => "text"],
					[
						"title" => "Size",
						"type" => "list",
						// The default is written as a *label*; what has to land in the
						// row is the stored value behind it.
						"options" => [["value" => "md", "description" => "Medium"]],
						"default" => "Medium",
					],
				],
			], $developer);

			T::ok(!empty($staged["ok"]), "the scaffold stages (" . (string)($staged["error"] ?? "") . ")");

			$built = $modules->aiScaffoldModule($staged["payload"], $developer);
			T::equals((string)($built["mode"] ?? ""), "created", "and builds");
			$module_id = (string)($built["id"] ?? "");

			BigTreeJSONDB::$Cache = [];
			$validated = $entries->aiValidateEntryCreate([
				"module_id" => $module_id,
				"data" => ["headline" => "Only the headline"],
			], $developer);

			T::ok(!empty($validated["ok"]), "an entry that says nothing about the list field stages ("
				. (string)($validated["error"] ?? "") . ")");

			$created = $entries->aiCreateEntry($validated["payload"], $developer);
			$entry_id = (int)($created["entry_id"] ?? 0);

			T::equals((string)($created["mode"] ?? ""), "published", "and lands live");
			T::equals(
				(string)SQL::fetchSingle("SELECT size FROM `{$table}` WHERE id = ?", $entry_id),
				"md",
				"the row holds the option's stored value — the default was normalized, not stored as typed"
			);
		} finally {
			if ($module_id !== "") {
				BigTreeJSONDB::delete("modules", $module_id);
			}

			if (BigTree::tableExists($table)) {
				SQL::query("DROP TABLE `{$table}`");
			}
		}
	}

	/**
	 * A2's shared-helper half: `many-to-many` had no entry in the render-required map
	 * and no `required` descriptor in its own schema, so an m2m field with entirely
	 * empty settings authored clean on every surface that isn't pages.
	 */
	function test_many_to_many_needs_its_connecting_table_configured() {
		$error = \BigTree\Api\Resources::aiUnconfigurableFieldError(
			[["id" => "Products", "type" => "many-to-many"]],
			[],
			"module"
		);

		T::ok($error !== null, "an unconfigured many-to-many is refused");
		T::ok(
			strpos((string)$error, "Developer → Modules → Module Designer") !== false,
			"and the refusal names where a developer finishes it on the module surface"
		);

		$configured = \BigTree\Api\Resources::aiUnconfigurableFieldError(
			[[
				"id" => "Products",
				"type" => "many-to-many",
				"settings" => [
					"mtm-connecting-table" => "products_regions",
					"mtm-my-id" => "region",
					"mtm-other-id" => "product",
					"mtm-other-table" => "products",
					"mtm-other-descriptor" => "title",
				],
			]],
			[],
			"module"
		);

		T::equals($configured, null, "a fully configured one is accepted");
	}
