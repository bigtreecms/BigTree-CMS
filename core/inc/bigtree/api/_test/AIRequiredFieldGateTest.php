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
			["column" => "blocks", "type" => "matrix", "title" => "Blocks", "settings" => ["required" => "on"]],
			// Settable since audit #11: an image reference is a file id the assistant
			// can look up, and a relationship is a list of entry ids — neither is a
			// gap a human has to fill, so neither belongs in blocked_required.
			["column" => "photo", "type" => "image-reference", "title" => "Photo", "settings" => ["required" => "on"]],
			["column" => "related", "type" => "many-to-many", "title" => "Related", "settings" => ["required" => "on"]],
		]];

		$blocked = ai_gate_invoke($svc, "aiRequiredUnsettableFields", [$form]);

		T::equals(count($blocked), 2, "both required unauthorable fields flagged");
		T::ok(strpos($blocked[0], "Hero Image") !== false, "flags the upload by title");
		T::ok(strpos($blocked[0], "hero") !== false, "names the column");
		T::ok(strpos($blocked[0], "upload") !== false, "names the type");
		T::ok(strpos($blocked[1], "Blocks") !== false, "flags the matrix");
		T::equals(
			implode(" ", $blocked),
			$blocked[0] . " " . $blocked[1],
			"and nothing else — a reference or a relation is a lookup, not a gap"
		);
	}

	/**
	 * Audit #14 C2: the *optional* half of the same scan.
	 *
	 * A required unauthorable field blocks the record from going live and has been
	 * disclosed for several audits. An optional one said nothing at all, which is the
	 * quieter failure: the entry saves, the card looks complete, and the gallery or
	 * matrix renders empty on the site with nobody told a human still has to finish it.
	 * Disclosure, not refusal — refusing would make every composite content model
	 * unauthorable — and capped at three names plus a count, because a form with ten
	 * composite fields would otherwise turn a warning into a paragraph.
	 */
	function test_ai_gate_discloses_optional_unauthorable_fields() {
		$svc = new AutoModuleService();

		$form = ["fields" => [
			["column" => "title", "type" => "text", "title" => "Title", "settings" => ["required" => "on"]],
			["column" => "hero", "type" => "upload", "title" => "Hero Image", "settings" => ["required" => "on"]],
			["column" => "gallery", "type" => "photo-gallery", "title" => "Gallery", "settings" => []],
			["column" => "blocks", "type" => "matrix", "title" => "Blocks", "settings" => []],
			// Settable, so not a gap in either direction.
			["column" => "photo", "type" => "image-reference", "title" => "Photo", "settings" => []],
			// Populated by the write path whatever is supplied.
			["column" => "route", "type" => "route", "title" => "Route", "settings" => []],
		]];

		$optional = ai_gate_invoke($svc, "aiOptionalUnsettableFields", [$form]);

		T::equals(count($optional), 2, "the two optional unauthorable fields are named");
		T::ok(strpos(implode(" ", $optional), "Gallery") !== false, "the gallery is named");
		T::ok(strpos(implode(" ", $optional), "Blocks") !== false, "and the matrix");
		T::ok(strpos(implode(" ", $optional), "Hero Image") === false, "the required one is not — it is a blocker");
		T::ok(strpos(implode(" ", $optional), "Photo") === false, "a reference is settable, not a gap");
		T::ok(strpos(implode(" ", $optional), "Route") === false, "and a derived field fills itself in");

		// The note the card carries: says it saves anyway, and says who has to finish it.
		$note = \BigTree\Services\AI\FieldTypeDomain::optionalUnsettableNote($optional);
		T::ok(strpos($note, "Gallery") !== false, "the note names the fields");
		T::ok(strpos($note, "optional, so this saves fine") !== false, "and says the record is still valid");
		T::ok(strpos($note, "in the admin afterwards") !== false, "and says where the rest of the work happens");
		T::equals(
			\BigTree\Services\AI\FieldTypeDomain::optionalUnsettableNote([]),
			"",
			"a form with nothing to disclose produces no note"
		);

		// D6: three names, then a count.
		$many = \BigTree\Services\AI\FieldTypeDomain::optionalUnsettableNote(["A", "B", "C", "D", "E"]);
		T::ok(strpos($many, "A, B, C, and 2 more") !== false, "past three fields it counts the rest: {$many}");
		T::ok(strpos($many, "D") === false, "and stops naming them");
	}

	/**
	 * The template side of the same disclosure — a page's composite resources land empty
	 * on a card that otherwise reads as complete.
	 */
	function test_ai_gate_discloses_optional_unauthorable_template_resources() {
		$svc = new PageService();
		$id = "zz_optional_" . bin2hex(random_bytes(4));

		BigTreeJSONDB::insert("templates", [
			"id" => $id,
			"name" => "Optional Fixture",
			"routed" => "",
			"level" => 0,
			"module" => "",
			"resources" => [
				["id" => "headline", "type" => "text", "title" => "Headline", "settings" => ["validation" => "required"]],
				["id" => "hero", "type" => "image", "title" => "Hero", "settings" => ["validation" => "required"]],
				["id" => "gallery", "type" => "matrix", "title" => "Gallery", "settings" => ["validation" => ""]],
				["id" => "promo", "type" => "callouts", "title" => "Promos", "settings" => []],
			],
		]);

		try {
			$optional = ai_gate_invoke($svc, "aiOptionalUnsettableResources", [$id, []]);

			T::equals(count($optional), 2, "both optional composite resources are disclosed");
			T::ok(strpos(implode(" ", $optional), "Gallery") !== false, "the matrix is named");
			T::ok(strpos(implode(" ", $optional), "Promos") !== false, "and the callouts resource");
			T::ok(strpos(implode(" ", $optional), "Hero") === false, "the required one stays a blocker, not a note");
			T::ok(strpos(implode(" ", $optional), "Headline") === false, "and a settable resource is neither");

			// A resource already carrying a value is not a gap, so a template switch
			// doesn't warn about content the outgoing template supplied.
			T::equals(
				ai_gate_invoke($svc, "aiOptionalUnsettableResources", [$id, ["gallery" => ["a"], "promo" => ["b"]]]),
				[],
				"a populated composite resource is not disclosed as empty"
			);
		} finally {
			BigTreeJSONDB::delete("templates", $id);
		}

		T::equals(
			ai_gate_invoke($svc, "aiOptionalUnsettableResources", ["zz-audit14-missing-template", []]),
			[],
			"a template that doesn't exist discloses nothing"
		);
	}

	/**
	 * A3: the gate's verdict depends on whether the approver can publish, and rank can
	 * change during a proposal's 24h life. An editor may stage a draft with a blocked
	 * required field — a human completes it in the pending queue — but if that same
	 * user is a publisher by the time they approve, the write would land live and
	 * incomplete. Hence the gate is re-asked at approval, and must answer by rank.
	 */
	function test_ai_gate_entry_create_verdict_depends_on_publish_rank() {
		$svc = new AutoModuleService();
		$resolved = [
			"schema" => ["title" => ["title" => "Title", "type" => "text", "required" => true]],
			"blocked_required" => ["Hero Image (hero, upload)"],
		];
		$data = ["title" => "A complete title"];

		T::equals(
			ai_gate_invoke($svc, "aiEntryCreateGate", [$resolved, $data, false]),
			null,
			"an editor may stage a draft with a blocked required field"
		);

		$publisher = ai_gate_invoke($svc, "aiEntryCreateGate", [$resolved, $data, true]);
		T::ok(is_string($publisher), "a publisher's write is refused instead of landing live incomplete");
		T::ok(strpos((string)$publisher, "Hero Image") !== false, "the refusal names the field");
	}

	function test_ai_gate_entry_create_catches_missing_required_at_either_rank() {
		$svc = new AutoModuleService();
		$resolved = [
			"schema" => ["title" => ["title" => "Title", "type" => "text", "required" => true]],
			"blocked_required" => [],
		];

		foreach ([false, true] as $can_publish) {
			$gate = ai_gate_invoke($svc, "aiEntryCreateGate", [$resolved, [], $can_publish]);
			T::ok(is_string($gate), "an empty required field is refused regardless of rank");
			T::ok(strpos((string)$gate, "title") !== false, "the refusal names the missing column");
		}

		T::equals(
			ai_gate_invoke($svc, "aiEntryCreateGate", [$resolved, ["title" => "Set"], true]),
			null,
			"complete data passes"
		);
	}

	/**
	 * Create ran a required-field check; update ran none, so setting a required
	 * column to "" sifted cleanly, staged, and published live for a publisher.
	 */
	function test_ai_gate_entry_update_refuses_to_blank_a_required_column() {
		$svc = new AutoModuleService();
		$schema = [
			"title" => ["title" => "Title", "type" => "text", "required" => true],
			"blurb" => ["title" => "Blurb", "type" => "textarea", "required" => false],
		];
		$row = ["title" => "The stored title", "blurb" => "Stored blurb"];

		$blanked = ai_gate_invoke($svc, "aiEntryUpdateGate", [$schema, $row, ["title" => ""]]);
		T::ok(is_string($blanked), "blanking a required column is refused");
		T::ok(strpos((string)$blanked, "Title") !== false, "the refusal names the field");

		T::ok(
			is_string(ai_gate_invoke($svc, "aiEntryUpdateGate", [$schema, $row, ["title" => "   "]])),
			"whitespace is not a value either"
		);

		T::equals(
			ai_gate_invoke($svc, "aiEntryUpdateGate", [$schema, $row, ["blurb" => ""]]),
			null,
			"clearing an optional column is fine"
		);

		T::equals(
			ai_gate_invoke($svc, "aiEntryUpdateGate", [$schema, $row, ["title" => "A new title"]]),
			null,
			"replacing a required column with a real value passes"
		);
	}

	/**
	 * A row that was already missing a required value — imported, or made required
	 * after the fact — is not this edit's doing. Refusing every unrelated change to
	 * it would make those rows uneditable through the assistant rather than fixable.
	 */
	function test_ai_gate_entry_update_judges_only_the_columns_it_touches() {
		$svc = new AutoModuleService();
		$schema = [
			"title" => ["title" => "Title", "type" => "text", "required" => true],
			"blurb" => ["title" => "Blurb", "type" => "textarea", "required" => false],
		];
		$incomplete = ["title" => "", "blurb" => "Stored blurb"];

		T::equals(
			ai_gate_invoke($svc, "aiEntryUpdateGate", [$schema, $incomplete, ["blurb" => "New blurb"]]),
			null,
			"an unrelated edit to an already-incomplete row is allowed"
		);

		T::ok(
			is_string(ai_gate_invoke($svc, "aiEntryUpdateGate", [$schema, $incomplete, ["title" => ""]])),
			"but re-stating the empty required column is still refused"
		);
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
