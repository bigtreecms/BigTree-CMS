<?php
	/**
	 * Audit #6 Phase 1 (A1–A8): the assistant producing structurally broken output,
	 * and the publisher-branch data loss audit #5 fixed only on the editor branch.
	 *
	 * Every test here fails against the pre-audit-6 code. The theme this time is not
	 * "a partial body broke a seam that wanted a whole one" (audit #5) but "the write
	 * that landed is not the write the card described" — a field id silently
	 * rewritten, a render file that won't parse, an allocation set deleted for fields
	 * nobody touched, somebody else's draft annihilated by a publish.
	 */

	use BigTree\Api\Resources;
	use BigTree\Api\TemplateScaffold;
	use BigTree\Services\AutoModuleService;
	use BigTree\Services\CalloutService;
	use BigTree\Services\PageService;
	use BigTree\Services\TemplateService;

	/**
	 * A1: mergeAiFields over a shipped template's real field list, with `type`
	 * omitted, must hand every stored record back unchanged.
	 *
	 * The lookup used to urlify the proposed id, and urlify turns underscores into
	 * hyphens — so on every shipped template (page_header, page_content, …) the
	 * stored record missed the lookup entirely. The field was then rebuilt from the
	 * default type "text", its settings were dropped, the unconfigurable-type guard
	 * never fired, and the id was written back as "page-header", orphaning every
	 * value already stored under the old key. None of it showed on the diff.
	 */
	function test_parity_ai_merge_fields_round_trips_a_shipped_template() {
		$stored = [
			[
				"id" => "page_header",
				"type" => "text",
				"title" => "Page Header",
				"subtitle" => "",
				"settings" => ["validation" => "required"],
			],
			[
				"id" => "page_image",
				"type" => "image",
				"title" => "Page Image",
				"subtitle" => "Min 1440x810px",
				"settings" => ["directory" => "files/pages/", "min_width" => "1440", "preview_prefix" => "sml_"],
			],
			[
				"id" => "callouts_full",
				"type" => "callouts",
				"title" => "Full Width Callouts",
				"subtitle" => "",
				"settings" => ["groups" => ["1"]],
			],
		];

		// What "restate the fields you aren't changing" looks like from the model:
		// id only, type omitted to keep each field as it is.
		$proposed = [];

		foreach ($stored as $field) {
			$proposed[] = ["id" => $field["id"]];
		}

		$merged = Resources::mergeAiFields($proposed, $stored);

		T::equals(count($merged), count($stored), "every field survives the merge");

		foreach ($stored as $index => $field) {
			T::equals($merged[$index]["id"], $field["id"], "the {$field["id"]} id is used exactly as supplied");
			T::equals($merged[$index]["type"], $field["type"], "the {$field["id"]} type is retained, not defaulted to text");
			T::equals($merged[$index]["settings"], $field["settings"], "the {$field["id"]} settings survive intact");
		}

		// And the unconfigurable-type guard has to agree with that lookup, or it
		// silently stops protecting the fields it exists for.
		$matrix = [[
			"id" => "team_members",
			"type" => "matrix",
			"title" => "Team",
			"settings" => ["columns" => [["id" => "name", "type" => "text"]]],
		]];
		T::ok(
			Resources::aiUnconfigurableFieldError([["id" => "team_members", "type" => "matrix"]], $matrix) === null,
			"an underscore-id matrix field is found by the unconfigurable-type guard too"
		);
	}

	/**
	 * A1: a field id is validated, never transformed. Template fields are held to the
	 * stricter PHP-label rule because they become variables in the render file.
	 */
	function test_parity_ai_field_ids_are_validated_not_rewritten() {
		T::equals(
			Resources::aiFieldIdError([["id" => "page_header"], ["id" => "_intro"]], "template"),
			null,
			"underscore ids are accepted for templates"
		);
		T::ok(
			Resources::aiFieldIdError([["id" => "page-header"]], "template") !== null,
			"a hyphenated id is refused for a template (it can't be a PHP variable)"
		);
		T::ok(
			Resources::aiFieldIdError([["id" => "2024_stats"]], "template") !== null,
			"a digit-leading id is refused for a template"
		);
		T::ok(
			strpos((string)Resources::aiFieldIdError([["id" => "2024_stats"]], "template"), "2024_stats") !== false,
			"the refusal names the offending id so the model can correct itself"
		);

		// Callouts index $callout["id"], so hyphens are fine there — but nothing else is.
		T::equals(
			Resources::aiFieldIdError([["id" => "sub-title"]], "callout"),
			null,
			"a hyphenated id is fine on a callout"
		);
		T::ok(
			Resources::aiFieldIdError([["id" => "sub title"]], "callout") !== null,
			"a space is refused on a callout"
		);
		T::ok(
			Resources::aiFieldIdError([["id" => ""]], "callout") !== null,
			"an empty id is refused rather than silently dropped"
		);
	}

	/** A1: the validate seams actually consult it, on create and on update. */
	function test_parity_ai_template_create_refuses_an_unusable_field_id() {
		if (!parity_db_available()) {
			return;
		}

		$templates = new TemplateService();
		$callouts = new CalloutService();
		$dev = (object)["id" => 1, "level" => 2, "permissions" => []];

		$result = $templates->aiValidateTemplateCreate([
			"id" => "zz-audit6-" . bin2hex(random_bytes(3)),
			"name" => "ZZ Audit6",
			"fields" => [["id" => "2024 stats", "type" => "text", "title" => "Stats"]],
		], $dev);
		T::ok(isset($result["error"]), "create_template refuses an unusable field id");
		T::ok(
			strpos((string)$result["error"], "2024 stats") !== false,
			"and names it in the refusal"
		);

		$callout = $callouts->aiValidateCalloutCreate([
			"id" => "zz-audit6-co-" . bin2hex(random_bytes(3)),
			"name" => "ZZ Audit6",
			"fields" => [["id" => "head line!", "type" => "text", "title" => "Headline"]],
		], $dev);
		T::ok(isset($callout["error"]), "create_callout refuses one too");
	}

	/**
	 * A2: every stub TemplateScaffold writes has to be loadable PHP.
	 *
	 * router.php includes the stub directly, so a parse error in it 500s every page
	 * on the template rather than rendering one field wrong. The scaffold used to
	 * interpolate the field id straight into a variable name (`<?=$page-header?>`).
	 */
	function test_parity_ai_scaffold_writes_only_loadable_php() {
		$fields = [
			["id" => "page_header", "type" => "text", "title" => "Header"],
			["id" => "page_image", "type" => "image", "title" => "Image"],
			["id" => "page_content", "type" => "html", "title" => "Content"],
			["id" => "callouts_full", "type" => "callouts", "title" => "Callouts"],
			// Ids that could never be bare variables. They can no longer reach the
			// scaffold through the AI path (A1), but a template created before that
			// validation existed still renders through here.
			["id" => "page-header", "type" => "text", "title" => "Hyphenated"],
			["id" => "2024stats", "type" => "html", "title" => "Digit leading"],
			["id" => "quote\"d", "type" => "text", "title" => "Quoted"],
			// A field id that would shadow the render scope's own globals.
			["id" => "page", "type" => "text", "title" => "Shadows \$page"],
			["id" => "resources", "type" => "text", "title" => "Shadows \$resources"],
		];

		foreach ([true, false] as $routed) {
			$body = TemplateScaffold::templateBody($fields, $routed);
			$label = $routed ? "routed" : "basic";

			T::ok(parity_php_parses($body), "the {$label} template stub parses");
		}

		T::ok(parity_php_parses(TemplateScaffold::calloutBody($fields)), "the callout stub parses");

		// The shadowing ids must not be emitted as bare variables at all — that would
		// echo the whole $page array rather than the field.
		$body = TemplateScaffold::templateBody([["id" => "page", "type" => "text", "title" => "Shadow"]], false);
		T::ok(strpos($body, '<?=$page?>') === false, "a field named `page` is not scaffolded as \$page");
	}

	/** Whether a PHP source string parses, via `php -l` on a temp file. */
	function parity_php_parses(string $body): bool {
		$path = tempnam(sys_get_temp_dir(), "bt-scaffold-") . ".php";
		file_put_contents($path, $body);
		$output = [];
		$status = 0;
		exec("php -l " . escapeshellarg($path) . " 2>&1", $output, $status);
		@unlink($path);

		if ($status !== 0) {
			echo "  (stub did not parse: " . implode(" ", $output) . ")\n";
		}

		return $status === 0;
	}

	/**
	 * A4: a partial AI entry update must not delete the allocations for the fields it
	 * didn't touch.
	 *
	 * allocateResources DELETEs every allocation row for (table, entry) before
	 * re-inserting what the data it was handed references, and the AI path handed it
	 * the sifted change set. An entry whose hero image wasn't part of a title edit
	 * dropped to zero usage in Files, where anyone could then delete it while it was
	 * still live on the site.
	 */
	function test_parity_ai_partial_entry_update_keeps_untouched_allocations() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$svc = new AutoModuleService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$entry_id = 0;
		$resource_id = 0;

		try {
			$resource_id = (int)SQL::insert("bigtree_resources", [
				"file" => "zz-audit6-" . bin2hex(random_bytes(4)) . ".png",
				"name" => "ZZ Audit6 fixture",
				"date" => "NOW()",
				"metadata" => "",
				"crops" => "",
				"thumbs" => "",
			]);

			// An entry whose body references the resource.
			$created = parity_ai_create_entry($svc, $dev, [
				"title" => "zz Audit6 Allocations",
				"content" => "<p>See <img src=\"irl://{$resource_id}\" alt=\"\"></p>",
			]);
			$entry_id = (int)$created["id"];
			T::ok($entry_id > 0, "live entry created");

			$before = (int)SQL::fetchSingle(
				"SELECT COUNT(*) FROM bigtree_resource_allocation WHERE `table` = ? AND entry = ? AND resource = ?",
				"timber_news", (string)$entry_id, $resource_id
			);
			T::equals($before, 1, "the entry's image is allocated to it");

			// An edit that says nothing at all about the body.
			$validated = $svc->aiValidateEntryUpdate([
				"module_id" => parity_news_module_id(),
				"entry_id" => (string)$entry_id,
				"data" => ["title" => "zz Audit6 Allocations (renamed)"],
			], $dev);
			T::ok(!empty($validated["ok"]), "the title-only edit validates");

			$result = $svc->aiUpdateEntry($validated["payload"], $dev);
			T::equals((string)($result["mode"] ?? ""), "published", "and publishes");

			$after = (int)SQL::fetchSingle(
				"SELECT COUNT(*) FROM bigtree_resource_allocation WHERE `table` = ? AND entry = ? AND resource = ?",
				"timber_news", (string)$entry_id, $resource_id
			);
			T::equals($after, 1, "the untouched field's allocation survives the partial update");
		} finally {
			parity_delete_news_entries($entry_id);

			if ($resource_id) {
				SQL::query("DELETE FROM bigtree_resource_allocation WHERE resource = ?", $resource_id);
				SQL::delete("bigtree_resources", $resource_id);
			}

			parity_delete_users($dev_id);
		}
	}

	/**
	 * A3: a publisher's AI page edit publishes the queued draft alongside it rather
	 * than annihilating it.
	 *
	 * The pending overlay was gated on !$can_publish, so a publisher's diff came from
	 * the live row — and performUpdate deletes every queued change for the page. The
	 * admin never does this: PageEdit PATCHes the overlaid body back, so a
	 * publisher's save publishes the draft.
	 */
	function test_parity_ai_publisher_page_edit_carries_the_queued_draft() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$page_id = 0;
		$editor_id = 0;

		try {
			$page_id = parity_a5_page($svc, $dev);
			T::ok($page_id > 0, "fixture page created");

			[$editor_id, $editor] = parity_a5_page_editor($page_id);

			// An editor queues a draft touching two fields.
			$staged = $svc->aiValidatePageUpdate([
				"id" => (string)$page_id,
				"nav_title" => "ZZ Editor Draft Title",
				"meta_keywords" => "editor, draft",
			], $editor);
			T::ok(!empty($staged["ok"]), "the editor's edit validates");
			T::equals((string)$svc->aiUpdatePage($staged["payload"], $editor)["mode"], "pending", "and queues");

			$change_id = (int)SQL::fetchSingle(
				"SELECT id FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND item_id = ? AND type = 'EDIT'",
				$page_id
			);
			T::ok($change_id > 0, "the draft is queued against the live page");

			// A publisher now changes one unrelated field.
			$publish = $svc->aiValidatePageUpdate([
				"id" => (string)$page_id,
				"meta_description" => "Set by the publisher",
			], $dev);
			T::ok(!empty($publish["ok"]), "the publisher's edit validates");

			$disclosure = $publish["preview"]["publishes_draft"] ?? [];
			T::ok(!empty($disclosure), "the card discloses the draft the approval will publish");
			T::equals((int)($disclosure["pending_change_id"] ?? 0), $change_id, "it names the draft");
			T::ok(
				strpos((string)$publish["summary"], "unpublished draft") !== false,
				"the summary says so in words"
			);

			$result = $svc->aiUpdatePage($publish["payload"], $dev);
			T::equals((string)$result["mode"], "published", "the publisher's edit publishes live");

			$row = SQL::fetch("SELECT nav_title, meta_keywords, meta_description FROM bigtree_pages WHERE id = ?", $page_id);
			T::equals(
				\BigTree\Api\Sanitize::decodeEntities((string)$row["nav_title"]),
				"ZZ Editor Draft Title",
				"the editor's draft title went live rather than being destroyed"
			);
			T::equals((string)$row["meta_keywords"], "editor, draft", "and the rest of the editor's draft with it");
			T::equals((string)$row["meta_description"], "Set by the publisher", "alongside the publisher's own change");
		} finally {
			parity_delete_page($page_id);
			parity_delete_users($dev_id, $editor_id);
		}
	}

	/**
	 * A3 (entries): the same on the entry side — updateItem destroys the queued
	 * change either way, so publishing has to carry it rather than drop it.
	 */
	function test_parity_ai_publisher_entry_edit_carries_the_queued_draft() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$svc = new AutoModuleService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		[$editor_id, $editor] = parity_pending_entry_editor();
		$entry_id = 0;
		$change_id = 0;

		try {
			$created = parity_ai_create_entry($svc, $dev, [
				"title" => "zz Audit6 Entry Draft",
				"content" => "<p>Published body.</p>",
			]);
			$entry_id = (int)$created["id"];
			T::ok($entry_id > 0, "live entry created");

			// The editor queues a body rewrite.
			$staged = $svc->aiValidateEntryUpdate([
				"module_id" => parity_news_module_id(),
				"entry_id" => (string)$entry_id,
				"data" => ["content" => "<p>Editor's queued rewrite.</p>"],
			], $editor);
			T::ok(!empty($staged["ok"]), "the editor's edit validates");
			$svc->aiUpdateEntry($staged["payload"], $editor);

			$change_id = (int)SQL::fetchSingle(
				"SELECT id FROM bigtree_pending_changes WHERE `table` = ? AND item_id = ?",
				"timber_news", $entry_id
			);
			T::ok($change_id > 0, "the draft is queued");

			// The publisher changes only the title.
			$publish = $svc->aiValidateEntryUpdate([
				"module_id" => parity_news_module_id(),
				"entry_id" => (string)$entry_id,
				"data" => ["title" => "zz Audit6 Entry Draft (published)"],
			], $dev);
			T::ok(!empty($publish["ok"]), "the publisher's edit validates");
			T::ok(!empty($publish["preview"]["publishes_draft"]), "the card discloses the draft");

			$result = $svc->aiUpdateEntry($publish["payload"], $dev);
			T::equals((string)($result["mode"] ?? ""), "published", "it publishes live");

			$row = SQL::fetch("SELECT title, content FROM timber_news WHERE id = ?", $entry_id);
			T::equals(
				(string)$row["content"],
				"<p>Editor's queued rewrite.</p>",
				"the editor's queued body went live rather than being destroyed"
			);
			T::equals(
				\BigTree\Api\Sanitize::decodeEntities((string)$row["title"]),
				"zz Audit6 Entry Draft (published)",
				"alongside the publisher's own change"
			);
		} finally {
			parity_delete_pending($change_id);
			parity_delete_news_entries($entry_id);
			parity_delete_users($dev_id, $editor_id);
		}
	}

	/**
	 * A7: a numeric tag *name* resolves to that tag, not to the row whose id happens
	 * to match it. merge_tags has no undo.
	 */
	function test_parity_ai_tag_reference_prefers_the_name() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new \BigTree\Services\TagService();
		$admin = (object)["id" => 1, "level" => 1, "permissions" => []];
		$named_id = 0;
		$target_id = 0;

		try {
			// A tag literally named after some other tag's id.
			$decoy_id = (int)SQL::insert("bigtree_tags", [
				"tag" => "zz audit6 decoy",
				"route" => "zz-audit6-decoy-" . bin2hex(random_bytes(3)),
				"usage_count" => 0,
			]);
			$named_id = (int)SQL::insert("bigtree_tags", [
				"tag" => (string)$decoy_id,
				"route" => "zz-audit6-named-" . bin2hex(random_bytes(3)),
				"usage_count" => 0,
			]);
			$target_id = (int)SQL::insert("bigtree_tags", [
				"tag" => "zz audit6 archive",
				"route" => "zz-audit6-archive-" . bin2hex(random_bytes(3)),
				"usage_count" => 0,
			]);

			$validated = $svc->aiValidateTagMerge([
				"from" => [(string)$decoy_id],
				"into" => "zz audit6 archive",
			], $admin);
			T::ok(!empty($validated["ok"]), "the merge validates");
			T::equals(
				$validated["payload"]["from"],
				[$named_id],
				"the numeric reference resolved to the tag NAMED that, not the tag with that id"
			);

			// The decoy — the tag that would have been destroyed — is untouched.
			T::ok(
				(int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_tags WHERE id = ?", $decoy_id) === 1,
				"the unrelated tag whose id matched is not in the merge"
			);

			parity_delete_tags($decoy_id);
		} finally {
			parity_delete_tags($named_id, $target_id);
		}
	}
