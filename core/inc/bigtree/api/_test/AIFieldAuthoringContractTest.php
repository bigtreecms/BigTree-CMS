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
