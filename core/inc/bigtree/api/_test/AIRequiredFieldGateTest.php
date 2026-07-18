<?php
	/**
	 * Phase 1 (A2): the required-field completeness gate.
	 *
	 * aiEntrySchema / aiTemplateResourceSchema drop complex field types before the
	 * required check runs, so a form or template with a *required complex* field used
	 * to pass AI validation and produce a record the admin UI's own required check
	 * would refuse to save. These cover the scan that closes that hole.
	 *
	 * DB-free: the scan helpers are pure functions of the form/template definition, so
	 * they're driven directly via reflection with synthetic definitions.
	 */

	use BigTree\Services\AutoModuleService;
	use BigTree\Services\PageService;

	/** Call a private/protected method for a focused unit test. */
	function ai_gate_invoke(object $object, string $method, array $args) {
		$ref = new ReflectionMethod($object, $method);
		$ref->setAccessible(true);

		return $ref->invokeArgs($object, $args);
	}

	function test_ai_gate_entry_flags_required_complex_fields() {
		$svc = new AutoModuleService();

		$form = ["fields" => [
			["column" => "title", "type" => "text", "title" => "Title", "settings" => ["required" => "on"]],
			["column" => "blurb", "type" => "textarea", "title" => "Blurb", "settings" => []],
			["column" => "hero", "type" => "upload", "title" => "Hero Image", "settings" => ["required" => "on"]],
			["column" => "related", "type" => "many-to-many", "title" => "Related", "settings" => ["required" => "on"]],
		]];

		$blocked = ai_gate_invoke($svc, "aiRequiredUnsettableFields", [$form]);

		T::equals(count($blocked), 2, "both required complex fields flagged");
		T::ok(strpos($blocked[0], "Hero Image") !== false, "flags the upload by title");
		T::ok(strpos($blocked[0], "hero") !== false, "names the column");
		T::ok(strpos($blocked[0], "upload") !== false, "names the type");
		T::ok(strpos($blocked[1], "Related") !== false, "flags the relationship field");
	}

	function test_ai_gate_entry_ignores_settable_and_optional_fields() {
		$svc = new AutoModuleService();

		$form = ["fields" => [
			["column" => "title", "type" => "text", "settings" => ["required" => "on"]],
			["column" => "hero", "type" => "upload", "settings" => []],
			["column" => "body", "type" => "html", "settings" => ["required" => "on"]],
		]];

		$blocked = ai_gate_invoke($svc, "aiRequiredUnsettableFields", [$form]);

		T::equals($blocked, [], "required simple fields and optional complex fields are not gaps");
	}

	function test_ai_gate_entry_ignores_server_derived_fields() {
		$svc = new AutoModuleService();

		// route and geocoding are populated by applyEntryProcessors at write time, so
		// they must never be reported as something the assistant failed to supply —
		// otherwise every routed module becomes uncreatable via the assistant.
		$form = ["fields" => [
			["column" => "route", "type" => "route", "settings" => ["required" => "on", "source" => "title"]],
			["column" => "geo", "type" => "geocoding", "settings" => ["required" => "on"]],
		]];

		$blocked = ai_gate_invoke($svc, "aiRequiredUnsettableFields", [$form]);

		T::equals($blocked, [], "server-derived required fields are not gaps");
	}

	function test_ai_gate_entry_skips_fields_without_a_column() {
		$svc = new AutoModuleService();

		$form = ["fields" => [
			["column" => "", "type" => "upload", "settings" => ["required" => "on"]],
		]];

		T::equals(ai_gate_invoke($svc, "aiRequiredUnsettableFields", [$form]), [], "a column-less field is not a gap");
	}

	function test_ai_gate_page_flags_required_complex_resources() {
		$svc = new PageService();
		$id = "zz_gate_" . bin2hex(random_bytes(4));

		BigTreeJSONDB::insert("templates", [
			"id" => $id,
			"name" => "Gate Fixture",
			"routed" => "",
			"level" => 0,
			"module" => "",
			"resources" => [
				["id" => "headline", "type" => "text", "title" => "Headline", "settings" => ["validation" => "required"]],
				["id" => "hero", "type" => "image", "title" => "Hero", "settings" => ["validation" => "required"]],
				["id" => "gallery", "type" => "matrix", "title" => "Gallery", "settings" => ["validation" => ""]],
			],
		]);

		try {
			$blocked = ai_gate_invoke($svc, "aiRequiredUnsettableResources", [$id, []]);

			T::equals(count($blocked), 1, "only the required complex resource is flagged");
			T::ok(strpos($blocked[0], "Hero") !== false, "flags the image resource by title");
			T::ok(strpos($blocked[0], "image") !== false, "names the resource type");

			// A resource that already carries a value is not a gap — this is what keeps
			// a template switch from reporting content the outgoing template supplied.
			$satisfied = ai_gate_invoke($svc, "aiRequiredUnsettableResources", [$id, ["hero" => "files/hero.jpg"]]);
			T::equals($satisfied, [], "an already-populated required resource is not a gap");
		} finally {
			BigTreeJSONDB::delete("templates", $id);
		}
	}

	function test_ai_gate_page_template_switch_reports_unmet_content() {
		$svc = new PageService();
		$id = "zz_switch_" . bin2hex(random_bytes(4));

		BigTreeJSONDB::insert("templates", [
			"id" => $id,
			"name" => "Switch Fixture",
			"routed" => "",
			"level" => 0,
			"module" => "",
			"resources" => [
				["id" => "headline", "type" => "text", "title" => "Headline", "settings" => ["validation" => "required"]],
				["id" => "hero", "type" => "image", "title" => "Hero", "settings" => ["validation" => "required"]],
			],
		]);

		try {
			// A page whose stored resources satisfy neither required field.
			$unmet = ai_gate_invoke($svc, "aiTemplateSwitchGaps", [$id, ["resources" => json_encode(["other" => "x"])]]);

			T::equals(count($unmet), 2, "both unmet required fields reported");
			T::ok(strpos(implode(", ", $unmet), "Headline") !== false, "reports the missing simple field");
			T::ok(strpos(implode(", ", $unmet), "Hero") !== false, "reports the missing complex field");

			// Same template, but the page already has content for both.
			$page = ["resources" => json_encode(["headline" => "Existing headline", "hero" => "files/hero.jpg"])];
			T::equals(ai_gate_invoke($svc, "aiTemplateSwitchGaps", [$id, $page]), [], "a fully-satisfied switch has no gaps");

			// Whitespace-only content does not satisfy a required field.
			$blank = ["resources" => json_encode(["headline" => "   ", "hero" => "files/hero.jpg"])];
			$gaps = ai_gate_invoke($svc, "aiTemplateSwitchGaps", [$id, $blank]);
			T::equals(count($gaps), 1, "whitespace-only content is still a gap");
		} finally {
			BigTreeJSONDB::delete("templates", $id);
		}
	}
