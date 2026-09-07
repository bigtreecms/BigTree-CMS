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

	/**
	 * Audit #25 guard E4: the publish card discloses what the create card disclosed.
	 *
	 * Both create gates relax the blocked-required refusal for a draft on purpose — a
	 * draft is the "a human completes this" case — and both say so on the card
	 * (`incomplete_required` plus a sentence in the mode note). Then the draft leaves
	 * that conversation and nothing ever re-asked: `aiValidatePublishChange` built a
	 * diff and staged "It will go live immediately once you approve" with no required
	 * check of any kind, and the queue read carried no marker either.
	 *
	 * The asymmetry is on the authoring side. The admin UI cannot produce this draft —
	 * the page editor validates required fields before submitting — so the assistant
	 * is the only writer that can queue a change with an empty required field, and
	 * also the tool that publishes it, to a publisher shown nothing about it.
	 *
	 * Fails on the tree before audit #25: the card names nothing.
	 */
	function test_ai_publish_card_names_the_required_fields_a_draft_left_empty() {
		if (!function_exists("parity_db_available") || !parity_db_available()) {

			return;
		}

		// Seeded rather than borrowed: the queue row has a foreign key onto
		// bigtree_users, and a fresh CI install has no users at all.
		$owner = parity_seed_user();
		$template = "zz_publish_gate_" . bin2hex(random_bytes(4));
		BigTreeJSONDB::insert("templates", [
			"id" => $template,
			"name" => "Publish Gate Fixture",
			"routed" => "",
			"level" => 0,
			"module" => "",
			"resources" => [
				["id" => "headline", "type" => "text", "title" => "Headline", "settings" => ["validation" => "required"]],
				["id" => "hero", "type" => "image", "title" => "Hero", "settings" => ["validation" => "required"]],
			],
		]);

		$change_id = (int)SQL::insert("bigtree_pending_changes", [
			"user" => $owner,
			"date" => "NOW()",
			"title" => "ZZ Publish Gate Draft",
			"table" => "bigtree_pages",
			"item_id" => null,
			"changes" => json_encode([
				"template" => $template,
				"nav_title" => "Publish Gate Draft",
				"resources" => ["headline" => "Filled in"],
			]),
			"mtm_changes" => "[]",
			"tags_changes" => "[]",
			"open_graph_changes" => "[]",
			"type" => "NEW",
			"module" => "",
			"pending_page_parent" => 0,
		]);

		try {
			$svc = new \BigTree\Services\PendingChangeService();
			$publisher = ai_wiring_user(2);
			$validation = $svc->aiValidatePublishChange(["change_id" => $change_id], $publisher);

			T::ok(!empty($validation["ok"]), "the publish stages (" . (string)($validation["error"] ?? "") . ")");
			T::equals(
				$validation["preview"]["incomplete_required"] ?? null,
				["Hero (hero, image)"],
				"the card carries the required field the draft left empty"
			);
			T::ok(
				strpos((string)$validation["summary"], "Hero") !== false,
				"and the summary names it, which is what the model reads back"
			);
			// The required field the draft *did* fill is not a gap — reporting it would
			// make the disclosure noise a publisher learns to skip past.
			T::ok(
				strpos((string)$validation["summary"], "Headline") === false,
				"a required field the draft filled in is not reported"
			);

			// The same answer on both reads the model makes while reviewing the queue,
			// so it can see this before it proposes the publish at all.
			$detail = $svc->aiGetPendingChange($change_id, $publisher);
			T::equals(
				$detail["pending_change"]["incomplete_required"] ?? null,
				["Hero (hero, image)"],
				"get_pending_change carries it too"
			);

			$listed = null;

			foreach ($svc->aiPendingChanges(50, $publisher)["pending_changes"] as $row) {
				if ((int)$row["id"] === $change_id) {
					$listed = $row;
				}
			}

			T::equals(
				$listed["incomplete_required"] ?? null,
				["Hero (hero, image)"],
				"and so does the queue listing"
			);

			// Fill it in and the disclosure goes away — this is a statement about the
			// draft, not about the template.
			SQL::update("bigtree_pending_changes", $change_id, [
				"changes" => json_encode([
					"template" => $template,
					"nav_title" => "Publish Gate Draft",
					"resources" => ["headline" => "Filled in", "hero" => "files/hero.jpg"],
				]),
			]);

			$complete = $svc->aiValidatePublishChange(["change_id" => $change_id], $publisher);
			T::ok(
				!isset($complete["preview"]["incomplete_required"]),
				"a complete draft's card says nothing about required fields"
			);
		} finally {
			parity_delete_pending($change_id);
			parity_delete_users($owner);
			BigTreeJSONDB::delete("templates", $template);
		}
	}

	/**
	 * Audit #29 E4: the discovered sweep that would have seen A1.
	 *
	 * The required-content rule is keyed, everywhere else in this file, to the seams
	 * that take content *from the model* — which is exactly why a restore walked past
	 * it for twenty-eight audits. restore_page_revision writes `template` +
	 * `resources` out of a revision row, supplies nothing, and was therefore invisible
	 * to every guard that starts from an argument.
	 *
	 * So this one starts from the write instead: every place PageService puts a
	 * `resources`-bearing array onto bigtree_pages has to ask the question in its own
	 * body, or be exempted here with the reason it doesn't. A fifth door fails until
	 * somebody decides which it is.
	 *
	 * @return array<string,string> method => why it doesn't ask
	 */
	function ai_page_content_write_exemptions(): array {

		return [
			"aiUpdatePageContent" => "delegates to aiMergePageContent, which runs aiMissingRequiredResources "
				. "over the merged content before this method writes anything",
			"aiMovePage" => "a move writes parent/route/path/in_nav — the `resources` column never enters "
				. "its update array, only the variable holding it makes that unprovable by inspection",
			"update" => "the REST update delegates to performUpdate; the body's own rules are declared in "
				. "routes/pages.php",
			"performUpdate" => "the shared live-write epilogue, reached from REST, from publishing a draft "
				. "and from the AI seams. Its callers gate: the admin's own save refuses an empty required "
				. "field client-side, and every AI door above runs a gate before reaching it. A gate here "
				. "would refuse a draft that a person deliberately queued incomplete (audit #14)",
			"publishPendingChange" => "publishing is not authoring: the draft's content was gated when it "
				. "was queued, and the card discloses what it left empty (incomplete_required)",
		];
	}

	function test_every_page_content_write_asks_the_required_content_question() {
		$file = dirname(__DIR__, 2) . "/services/PageService.php";
		T::ok(is_readable($file), "PageService.php is readable");

		$lines = file($file) ?: [];
		$method = "";
		$bodies = [];
		$sites = [];

		foreach ($lines as $line) {
			if (preg_match('/function\s+([A-Za-z0-9_]+)\s*\(/', $line, $named)) {
				$method = $named[1];
			}

			$bodies[$method] = ($bodies[$method] ?? "") . $line;

			if (preg_match('/SQL::update\("bigtree_pages",\s*[^,]+,\s*(.+)\);/', $line, $update)) {
				$written = trim($update[1]);

				// An inline literal that names no `resources` key structurally cannot
				// carry content — archiving, reordering and repathing are not this
				// rule's business.
				if (strpos($written, "[") === 0 && strpos($written, "resources") === false) {

					continue;
				}

				$sites[$method] = true;
			}

			if (strpos($line, "->performUpdate(") !== false) {
				$sites[$method] = true;
			}
		}

		T::ok(count($sites) > 4, "the sweep found the page-content write sites");

		$exemptions = ai_page_content_write_exemptions();
		$ungated = [];

		foreach (array_keys($sites) as $writer) {
			// revisionRestoreNotices is the restore doors' wrapper around
			// revisionRestoreGaps — the same question, asked of a row instead of an
			// argument.
			$asks = (bool)preg_match(
				'/aiMissingRequiredResources|aiTemplateSwitchGaps|revisionRestoreGaps|revisionRestoreNotices/',
				$bodies[$writer]
			);

			if ($asks || array_key_exists($writer, $exemptions)) {

				continue;
			}

			$ungated[] = $writer;
		}

		T::equals(
			implode(", ", $ungated),
			"",
			"every writer of a page's content asks the required-content question, or is exempted with a reason"
		);

		// Staleness the other way: an exemption for a method that no longer writes
		// content (or now asks the question itself) is a reason nobody re-derived.
		$stale = [];

		foreach ($exemptions as $writer => $reason) {
			if (!isset($sites[$writer])) {
				$stale[] = "{$writer} (no longer writes page content)";

				continue;
			}

			if (strlen(trim($reason)) < 40) {
				$stale[] = "{$writer} (reason too thin to be a decision)";
			}
		}

		T::equals(
			implode(", ", $stale),
			"",
			"and every exemption still names a live write site, with a reason"
		);
	}
