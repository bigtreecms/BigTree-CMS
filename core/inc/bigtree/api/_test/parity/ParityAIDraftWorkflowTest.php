<?php
	/**
	 * Audit #4 Phase 2 (A3, A5, B1): the halves of the draft workflow that were
	 * missing.
	 *
	 * The write tools could address a "p"-prefixed draft but the read tool cast it to
	 * an int, so the model could edit a draft it could never look at. An editor could
	 * create an entry but never tag it, because their create lands as a draft and
	 * add_tags only accepts a live row. And a publisher had no way to say "queue this
	 * for review" — the mode was derived purely from rank, so approval always went
	 * live.
	 *
	 * Also covers the relation-preservation bug those first two exposed: the entry
	 * write calls treat their tags/Open Graph arguments as the complete new set, so
	 * the AI update path passing [] deleted both on every edit.
	 */

	use BigTree\Services\AutoModuleService;
	use BigTree\Services\PageService;
	use BigTree\Services\SearchService;

	/** A publisher on the News module (level 0, explicit "p" grant). */
	function parity_draft_publisher(): array {
		$id = parity_seed_user(["level" => 0]);
		$user = (object)[
			"id" => $id,
			"level" => 0,
			"permissions" => ["module" => [parity_news_module_id() => "p"]],
		];

		return [$id, $user];
	}

	function test_parity_ai_get_module_entry_reads_a_draft() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$svc = new AutoModuleService();
		$search = new SearchService();
		[$editor_id, $editor] = parity_pending_entry_editor();
		$title = "zz AI Draft Read " . bin2hex(random_bytes(3));
		$draft_id = "";
		$live_id = 0;

		try {
			// A live row that shares the module, so reading the draft can be shown to
			// return the draft's values rather than falling back to any live row.
			$live_id = (int)SQL::insert("timber_news", ["title" => "zz AI Live Row", "route" => "zz-ai-live-row"]);

			$draft_id = parity_pending_entry_draft($svc, $editor, $title);
			T::ok($draft_id !== "" && $draft_id !== "p0", "the editor's create landed as a draft");

			$detail = $search->getModuleEntryDetail(parity_news_module_id(), $draft_id, $editor);
			T::ok(!isset($detail["error"]), "a \"p\"-prefixed id resolves instead of erroring");
			T::equals($detail["payload"]["entry"]["title"] ?? null, $title, "the draft's own values come back");
			T::ok(!empty($detail["payload"]["is_draft"]), "the payload flags it as a draft");
			T::equals($detail["payload"]["id"] ?? null, $draft_id, "the payload echoes the addressable draft id");
			T::ok(
				strpos((string)($detail["payload"]["note"] ?? ""), "unpublished draft") !== false,
				"the note says it isn't live yet"
			);

			// Artifacts are navigable rows; a draft has none to navigate to.
			T::ok(!isset($detail["artifact"]), "no artifact is emitted for a draft");

			$live = $search->getModuleEntryDetail(parity_news_module_id(), (string)$live_id, $editor);
			T::equals($live["payload"]["entry"]["title"] ?? null, "zz AI Live Row", "a numeric id still reads live");
			T::ok(empty($live["payload"]["is_draft"]), "a live read isn't flagged as a draft");
			T::ok(isset($live["artifact"]), "a live read still emits an artifact");

			$junk = $search->getModuleEntryDetail(parity_news_module_id(), "not-an-id", $editor);
			T::ok(isset($junk["error"]), "a junk id is still refused");
		} finally {
			if ($draft_id !== "") {
				parity_delete_pending((int)substr($draft_id, 1));
			}

			parity_delete_news_entries($live_id);
			parity_delete_users($editor_id);
		}
	}

	function test_parity_ai_editor_can_tag_an_entry_at_create() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$svc = new AutoModuleService();
		[$editor_id, $editor] = parity_pending_entry_editor();
		$tag = "zz ai entry tag " . bin2hex(random_bytes(3));
		$tag_id = (int)SQL::insert("bigtree_tags", ["tag" => $tag, "route" => str_replace(" ", "-", $tag), "usage_count" => 0]);
		$change_id = 0;
		// Tagging and Open Graph are per-form opt-ins; the AI path refuses a relation
		// the form doesn't offer, exactly as the editor screen hides it.
		$restore = parity_enable_news_relations();

		try {
			// Coining a brand-new tag stays administrator-only, as it is on create_page.
			$denied = $svc->aiValidateEntryCreate([
				"module_id" => parity_news_module_id(),
				"data" => ["title" => "zz AI Tagged"],
				"tags" => ["zz brand new " . bin2hex(random_bytes(3))],
			], $editor);

			T::ok(isset($denied["denied"]), "an editor cannot coin a new tag at entry create");

			$validated = $svc->aiValidateEntryCreate([
				"module_id" => parity_news_module_id(),
				"data" => ["title" => "zz AI Tagged " . bin2hex(random_bytes(3))],
				"tags" => [$tag],
				"og_title" => "Social Title",
				"og_description" => "Social description.",
			], $editor);

			T::ok(!empty($validated["ok"]), "an existing tag validates for an editor");
			T::equals($validated["preview"]["tags"][0] ?? null, $tag, "the preview names the tag");
			T::equals($validated["preview"]["og_title"] ?? null, "Social Title", "the preview shows the OG title");
			T::equals(
				$validated["payload"]["open_graph"]["description"] ?? null,
				"Social description.",
				"OG is staged in the shape the write path stores"
			);

			$result = $svc->aiCreateEntry($validated["payload"], $editor);
			T::equals($result["mode"], "pending", "the editor's create is still a draft");

			// The tags ride the pending change itself, which is what the approve path
			// replays — without that the tagging intent is lost by publish time.
			$change_id = (int)$result["pending_id"];
			$row = SQL::fetch(
				"SELECT tags_changes, open_graph_changes FROM bigtree_pending_changes WHERE id = ?",
				$change_id
			);

			$staged_tags = json_decode((string)$row["tags_changes"], true);
			T::ok(in_array($tag_id, array_map("intval", (array)$staged_tags), true), "the tag id rides the draft");

			$staged_og = json_decode((string)$row["open_graph_changes"], true);
			T::equals($staged_og["title"] ?? null, "Social Title", "the OG record rides the draft too");
		} finally {
			$restore();
			parity_delete_pending($change_id);
			parity_delete_tags($tag_id);
			parity_delete_users($editor_id);
		}
	}

	/**
	 * updateItem/submitChange replace an entry's tags and Open Graph wholesale, so
	 * the AI update path passing [] wiped both on every edit — including the tags a
	 * create had just staged.
	 */
	function test_parity_ai_entry_update_preserves_tags_and_open_graph() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$svc = new AutoModuleService();
		[$pub_id, $publisher] = parity_draft_publisher();
		$tag = "zz ai keep tag " . bin2hex(random_bytes(3));
		$tag_id = (int)SQL::insert("bigtree_tags", ["tag" => $tag, "route" => str_replace(" ", "-", $tag), "usage_count" => 0]);
		$entry_id = 0;
		$restore = parity_enable_news_relations();

		try {
			$validated = $svc->aiValidateEntryCreate([
				"module_id" => parity_news_module_id(),
				"data" => ["title" => "zz AI Keep " . bin2hex(random_bytes(3))],
				"tags" => [$tag],
				"og_title" => "Keep Me",
			], $publisher);

			T::ok(!empty($validated["ok"]), "the publisher's tagged create validates");

			$created = $svc->aiCreateEntry($validated["payload"], $publisher);
			T::equals($created["mode"], "published", "a publisher's create goes live");

			$entry_id = (int)$created["entry_id"];
			T::equals(
				(int)SQL::fetchSingle(
					"SELECT COUNT(*) FROM bigtree_tags_rel WHERE `table` = 'timber_news' AND entry = ?",
					$entry_id
				),
				1,
				"the tag relation was written"
			);

			$edit = $svc->aiValidateEntryUpdate([
				"module_id" => parity_news_module_id(),
				"entry_id" => (string)$entry_id,
				"data" => ["title" => "zz AI Keep Edited"],
			], $publisher);

			T::ok(!empty($edit["ok"]), "the edit validates");
			T::equals($svc->aiUpdateEntry($edit["payload"], $publisher)["mode"], "published", "the edit goes live");

			// …and an Open Graph edit amends that record rather than replacing it.
			$og_edit = $svc->aiValidateEntryUpdate([
				"module_id" => parity_news_module_id(),
				"entry_id" => (string)$entry_id,
				"og_description" => "Added later.",
			], $publisher);

			T::ok(!empty($og_edit["ok"]), "an Open Graph-only edit validates with no data fields");
			$svc->aiUpdateEntry($og_edit["payload"], $publisher);

			$og = SQL::fetch(
				"SELECT title, description FROM bigtree_open_graph WHERE `table` = 'timber_news' AND entry = ?",
				$entry_id
			);
			T::equals($og["description"], "Added later.", "the new description was written");
			T::equals($og["title"], "Keep Me", "the untouched Open Graph title survived");

			// The edit said nothing about tags or Open Graph, so neither may be lost.
			T::equals(
				(int)SQL::fetchSingle(
					"SELECT tag FROM bigtree_tags_rel WHERE `table` = 'timber_news' AND entry = ?",
					$entry_id
				),
				$tag_id,
				"an unrelated edit keeps the entry's tags"
			);
			T::equals(
				(string)SQL::fetchSingle(
					"SELECT title FROM bigtree_open_graph WHERE `table` = 'timber_news' AND entry = ?",
					$entry_id
				),
				"Keep Me",
				"an unrelated edit keeps the entry's Open Graph record"
			);
		} finally {
			$restore();
			SQL::query("DELETE FROM bigtree_tags_rel WHERE `table` = 'timber_news' AND entry = ?", $entry_id);
			SQL::query("DELETE FROM bigtree_open_graph WHERE `table` = 'timber_news' AND entry = ?", $entry_id);
			parity_delete_news_entries($entry_id);
			parity_delete_tags($tag_id);
			parity_delete_users($pub_id);
		}
	}

	function test_parity_ai_publisher_can_save_an_entry_as_a_draft() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$svc = new AutoModuleService();
		[$pub_id, $publisher] = parity_draft_publisher();
		$title = "zz AI Deliberate Draft " . bin2hex(random_bytes(3));
		$change_id = 0;

		try {
			$validated = $svc->aiValidateEntryCreate([
				"module_id" => parity_news_module_id(),
				"data" => ["title" => $title],
				"save_as_draft" => true,
			], $publisher);

			T::ok(!empty($validated["ok"]), "the draft create validates for a publisher");
			T::equals($validated["preview"]["mode"], "pending", "the preview says pending, not published");
			T::ok(!empty($validated["preview"]["save_as_draft"]), "the preview flags the deliberate draft");
			T::ok(
				strpos((string)$validated["summary"], "published live") === false,
				"the summary doesn't promise a live publish"
			);

			$result = $svc->aiCreateEntry($validated["payload"], $publisher);
			T::equals($result["mode"], "pending", "approval queues it rather than publishing");

			$change_id = (int)$result["pending_id"];
			T::ok($change_id > 0, "a pending change exists");
			T::ok(
				!SQL::fetchSingle("SELECT id FROM timber_news WHERE title = ?", $title),
				"no live row was written"
			);

			// Without the flag the same publisher publishes as before.
			$normal = $svc->aiValidateEntryCreate([
				"module_id" => parity_news_module_id(),
				"data" => ["title" => $title],
			], $publisher);

			T::equals($normal["preview"]["mode"], "published", "the flag is what changes the mode, not the rank");
		} finally {
			parity_delete_pending($change_id);
			parity_delete_users($pub_id);
		}
	}

	function test_parity_ai_publisher_can_save_a_page_as_a_draft() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		$admin_id = parity_seed_user(["level" => 1]);
		$admin = (object)["id" => $admin_id, "level" => 1, "permissions" => []];
		$nav_title = "ZZ AI Draft Page " . bin2hex(random_bytes(3));
		$change_id = 0;
		$page_id = 0;

		try {
			$validated = $svc->aiValidatePageCreate([
				"parent" => 0,
				"nav_title" => $nav_title,
				"content" => parity_surface_default_content(),
				"save_as_draft" => true,
			], $admin);

			T::ok(!empty($validated["ok"]), "the draft page create validates");
			T::equals($validated["preview"]["mode"], "pending", "the preview says pending");
			T::ok(!empty($validated["preview"]["save_as_draft"]), "the preview flags the deliberate draft");

			$result = $svc->aiCreatePage($validated["payload"], $admin);
			T::equals($result["mode"], "pending", "approval queues the page rather than publishing it");

			$change_id = (int)$result["pending_change_id"];
			T::ok($change_id > 0, "a pending change exists");
			T::ok(!SQL::fetchSingle("SELECT id FROM bigtree_pages WHERE nav_title = ?", $nav_title), "no live page");

			// save_as_draft is a control flag, never a page column.
			$changes = json_decode(
				(string)SQL::fetchSingle("SELECT changes FROM bigtree_pending_changes WHERE id = ?", $change_id),
				true
			);
			T::ok(!array_key_exists("save_as_draft", (array)$changes), "the flag isn't stored as page data");

			// An update against a live page behaves the same way.
			$page_id = (int)$svc->aiCreatePage(
				$svc->aiValidatePageCreate([
					"parent" => 0,
					"nav_title" => $nav_title . " Live",
					"content" => parity_surface_default_content(),
				], $admin)["payload"],
				$admin
			)["page_id"];

			$edit = $svc->aiValidatePageUpdate([
				"id" => $page_id,
				"title" => "ZZ Draft Edited Title",
				"save_as_draft" => true,
			], $admin);

			T::equals($edit["preview"]["mode"], "pending", "a drafted edit previews as pending");

			$applied = $svc->aiUpdatePage($edit["payload"], $admin);
			T::equals($applied["mode"], "pending", "approving the drafted edit queues it");
			T::equals(
				(string)SQL::fetchSingle("SELECT title FROM bigtree_pages WHERE id = ?", $page_id),
				$nav_title . " Live",
				"the live page was not touched"
			);

			parity_delete_pending((int)$applied["pending_change_id"]);
		} finally {
			parity_delete_pending($change_id);
			parity_delete_page($page_id);
			parity_delete_users($admin_id);
		}
	}
