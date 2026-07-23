<?php
	/**
	 * Audit #5 Phases 3 and 4: what the assistant can read, and the validation and
	 * parity nits underneath it.
	 *
	 * Phase 3 is about reads that leaked (no per-row group check), reads that lied
	 * (the wrong form's row), and reads that simply weren't there (page content,
	 * entry tags, callout groups, a module's entry list). Phase 4 is the long tail:
	 * caps the REST routes declare and the AI path never checked, the redirect that
	 * could never fire, and the audit rows that landed under table names nothing else
	 * uses.
	 */

	use BigTree\Api\TemplateScaffold;
	use BigTree\Services\AIChatService;
	use BigTree\Services\AI\ContentLock;
	use BigTree\Services\AutoModuleService;
	use BigTree\Services\CalloutService;
	use BigTree\Services\LockService;
	use BigTree\Services\PageService;
	use BigTree\Services\PendingChangeService;
	use BigTree\Services\SearchService;
	use BigTree\Services\TagService;
	use BigTree\Services\UserService;

	/**
	 * C1: getModuleEntryDetail checked module-level view access only, so a GBP editor
	 * scoped to one group could read every column of any other group's row. Writing
	 * was correctly blocked all along; reading was not.
	 */
	function test_parity_ai_get_module_entry_enforces_per_row_access() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$module_id = parity_news_module_id();
		$original = BigTreeJSONDB::get("modules", $module_id);
		$svc = new AutoModuleService();
		$search = new SearchService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$entry_id = 0;

		try {
			$created = parity_ai_create_entry($svc, $dev, ["title" => "zz Audit5 GBP Row"]);
			$entry_id = (int)$created["id"];
			T::ok($entry_id > 0, "live entry created");

			// Turn the News module into a group-based one keyed on a column the entry
			// actually has, then scope an editor to a different group.
			$patched = $original;
			$patched["gbp"] = ["enabled" => "on", "group_field" => "id", "group_module" => ""];
			BigTreeJSONDB::update("modules", $module_id, $patched);

			$editor = (object)[
				"id" => 424242,
				"level" => 0,
				"permissions" => [
					"module" => [$module_id => ""],
					// A grant on some other row's id, never this one.
					"module_gbp" => [$module_id => [($entry_id + 100000) => "e"]],
				],
			];

			$detail = $search->getModuleEntryDetail($module_id, (string)$entry_id, $editor);
			T::ok(isset($detail["error"]), "an out-of-group row is refused rather than returned");

			// The row they *are* scoped to still reads.
			$in_group = (object)[
				"id" => 424242,
				"level" => 0,
				"permissions" => [
					"module" => [$module_id => ""],
					"module_gbp" => [$module_id => [$entry_id => "e"]],
				],
			];
			$allowed = $search->getModuleEntryDetail($module_id, (string)$entry_id, $in_group);
			T::ok(!isset($allowed["error"]), "the row they are scoped to still reads");
		} finally {
			BigTreeJSONDB::update("modules", $module_id, $original);
			parity_delete_news_entries($entry_id);
			parity_delete_users($dev_id);
		}
	}

	/**
	 * C3/C5: what get_module_entry returns beside the row — the tags and Open Graph
	 * both entry write tools can set, plus which form and table it read from.
	 */
	function test_parity_ai_get_module_entry_reads_tags_and_open_graph() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$svc = new AutoModuleService();
		$search = new SearchService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$entry_id = 0;
		$tag_id = 0;
		$tag = "zz audit five entry";
		$restore = parity_enable_news_relations();

		try {
			$tag_id = (int)SQL::insert("bigtree_tags", [
				"tag" => $tag,
				"route" => "zz-audit-five-entry-" . bin2hex(random_bytes(3)),
				"usage_count" => 0,
			]);

			$validated = $svc->aiValidateEntryCreate([
				"module_id" => parity_news_module_id(),
				"data" => ["title" => "zz Audit5 Relations"],
				"tags" => [$tag],
				"og_title" => "Social Title",
				"og_description" => "Social description.",
			], $dev);
			T::ok(!empty($validated["ok"]), "the create validates");

			$entry_id = (int)($svc->aiCreateEntry($validated["payload"], $dev)["entry_id"] ?? 0);
			T::ok($entry_id > 0, "the entry was published");

			$detail = $search->getModuleEntryDetail(parity_news_module_id(), (string)$entry_id, $dev);
			T::ok(!isset($detail["error"]), "the entry reads back");

			$payload = $detail["payload"];
			T::equals($payload["tags"], [$tag], "the entry's tags read back");
			T::equals($payload["open_graph"]["og_title"], "Social Title", "the OG title reads back");
			T::equals(
				$payload["open_graph"]["og_description"],
				"Social description.",
				"the OG description reads back"
			);
			T::equals($payload["table"], "timber_news", "the payload names the table it read from");
		} finally {
			$restore();
			parity_delete_news_entries($entry_id);
			parity_delete_tags($tag_id);
			parity_delete_users($dev_id);
		}
	}

	/**
	 * C5: there was no way to list a module's entries at all — search_module_entries
	 * requires a query, sweeps every module and slices to five rows with no note.
	 */
	function test_parity_ai_list_module_entries_pages_and_reports_truncation() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$svc = new AutoModuleService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$ids = [];

		try {
			foreach (range(1, 3) as $n) {
				$created = parity_ai_create_entry($svc, $dev, ["title" => "zz Audit5 List {$n}"]);
				$ids[] = (int)$created["id"];
			}

			T::ok(count(array_filter($ids)) === 3, "three entries created");

			$first = $svc->aiListEntries(parity_news_module_id(), "", 2, 0, $dev);
			T::ok(!isset($first["error"]) && !isset($first["denied"]), "the list comes back");
			T::equals(count($first["entries"]), 2, "the limit is honoured");
			T::equals($first["has_more"], true, "and the payload says there are more");
			T::equals($first["table"], "timber_news", "the module's table is named");

			// Newest first, so the most recent fixture is first.
			T::equals(
				(string)$first["entries"][0]["title"],
				"zz Audit5 List 3",
				"entries come back newest first"
			);

			$second = $svc->aiListEntries(parity_news_module_id(), "", 2, 2, $dev);
			T::ok(count($second["entries"]) > 0, "offset pages past the first window");
			T::ok(
				(string)($second["entries"][0]["title"] ?? "") !== "zz Audit5 List 3",
				"the second page isn't the first page again"
			);
		} finally {
			parity_delete_news_entries(...array_filter($ids));
			parity_delete_users($dev_id);
		}
	}

	/**
	 * C3 (D7): get_page returned only `content_text` — strip_tags'd, unkeyed and
	 * truncated — so every AI copy edit was proposed blind and flattened the HTML it
	 * replaced.
	 */
	function test_parity_ai_get_page_reads_keyed_markup_preserving_content() {
		if (!parity_db_available()) {
			return;
		}

		$required = parity_ai_content_required();

		if (!$required) {
			echo "  (skipped — stock content template unavailable)\n";

			return;
		}

		$svc = new PageService();
		$search = new SearchService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$page_id = 0;
		$markup = "<p>Hello <strong>world</strong>.</p>";

		try {
			$content = [];

			foreach ($required as $field) {
				$content[$field] = $markup;
			}

			$page_id = parity_ai_content_page($svc, $dev, "content", $content);
			T::ok($page_id > 0, "the fixture page was created");

			$detail = $search->getPageDetail($page_id, $dev);
			T::ok(!isset($detail["error"]), "the page reads back");

			$payload = $detail["payload"];
			T::ok(is_array($payload["content"] ?? null), "get_page returns a keyed content map");

			foreach ($required as $field) {
				T::ok(array_key_exists($field, $payload["content"]), "content is keyed by the write path's id {$field}");
				T::equals($payload["content"][$field], $markup, "and keeps its markup intact");
			}

			// The plain-text summary is still there for search-style questions.
			T::ok(strpos((string)$payload["content_text"], "<strong>") === false, "content_text is still plain text");
		} finally {
			parity_delete_page($page_id);
			parity_delete_users($dev_id);
		}
	}

	/**
	 * C4: aiChangeDiff decoded only `changes`. For a module entry, tags and Open
	 * Graph live exclusively in their own columns — so an entry draft whose only
	 * change was its tag set read as "0 fields affected".
	 */
	function test_parity_ai_change_diff_renders_tags_and_open_graph() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$svc = new AutoModuleService();
		$changes = new PendingChangeService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$editor_id = parity_seed_user(["level" => 0]);
		$editor = (object)[
			"id" => $editor_id,
			"level" => 0,
			"permissions" => ["module" => [parity_news_module_id() => "e"]],
		];
		$entry_id = 0;
		$tag_id = 0;
		$tag = "zz audit five diff";

		try {
			$created = parity_ai_create_entry($svc, $dev, ["title" => "zz Audit5 Diff"]);
			$entry_id = (int)$created["id"];
			T::ok($entry_id > 0, "live entry created");

			$tag_id = (int)SQL::insert("bigtree_tags", [
				"tag" => $tag,
				"route" => "zz-audit-five-diff-" . bin2hex(random_bytes(3)),
				"usage_count" => 0,
			]);

			// An editor's tag change now queues (audit #5 A2) — and its whole content
			// lives in tags_changes, which the diff used to ignore entirely.
			$tags = new TagService();
			$validated = $tags->aiValidateAddTags([
				"module_id" => parity_news_module_id(),
				"entry_id" => $entry_id,
				"tags" => [$tag],
			], $editor);
			T::ok(!empty($validated["ok"]), "the tag change validates");
			$result = $tags->aiAddTags($validated["payload"], $editor);
			T::equals((string)$result["mode"], "pending", "it queues");

			$change_id = (int)SQL::fetchSingle(
				"SELECT id FROM bigtree_pending_changes WHERE `table` = 'timber_news' AND item_id = ?",
				$entry_id
			);
			T::ok($change_id > 0, "a change row exists");

			$read = $changes->aiGetPendingChange($change_id, $dev);
			$fields = $read["pending_change"]["changes"] ?? [];
			$columns = array_column($fields, "column");

			T::ok(in_array("tags", $columns, true), "the diff names the tag change");

			$row = null;

			foreach ($fields as $field) {
				if ((string)$field["column"] === "tags") {
					$row = $field;
				}
			}

			T::ok(strpos((string)$row["to"], $tag) !== false, "and renders it as a tag name, not an id");
		} finally {
			parity_delete_news_entries($entry_id);
			parity_delete_tags($tag_id);
			parity_delete_users($dev_id, $editor_id);
		}
	}

	/**
	 * D (Part D, validation drift): page field caps the REST routes declare and the
	 * AI path never checked, at staging *and* at approval.
	 */
	function test_parity_ai_page_fields_respect_their_column_caps() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$page_id = 0;

		try {
			$page_id = parity_a5_page($svc, $dev);
			T::ok($page_id > 0, "fixture page created");

			$long = str_repeat("a", 1100);
			$staged = $svc->aiValidatePageUpdate(["id" => (string)$page_id, "nav_title" => $long], $dev);
			T::ok(isset($staged["error"]), "an over-length nav_title is refused at staging");

			// And at approval, because a payload is never trusted on the way out.
			$result = $svc->aiUpdatePage([
				"id" => (string)$page_id,
				"changes" => ["nav_title" => $long],
			], $dev);
			T::equals((string)($result["mode"] ?? ""), "error", "and refused again at approval");

			$stored = (string)SQL::fetchSingle("SELECT nav_title FROM bigtree_pages WHERE id = ?", $page_id);
			T::ok(mb_strlen($stored) < 1100, "nothing over-length was written");
		} finally {
			parity_delete_page($page_id);
			parity_delete_users($dev_id);
		}
	}

	/** D: create_page accepts an explicit route, and refuses an archived parent. */
	function test_parity_ai_create_page_takes_a_route_and_refuses_an_archived_parent() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$parent_id = 0;
		$child_id = 0;

		try {
			$parent_id = parity_a5_page($svc, $dev);
			T::ok($parent_id > 0, "parent created");

			$route = parity_unique_route("zz-a5-route");
			$validated = $svc->aiValidatePageCreate([
				"parent" => $parent_id,
				"nav_title" => "Some Long Navigation Title " . bin2hex(random_bytes(3)),
				"template" => "",
				"external" => "https://example.com/a5-route",
				"route" => $route,
			], $dev);

			T::ok(!empty($validated["ok"]), "the create validates");
			T::equals($validated["payload"]["route"], $route, "the requested route is used, not one derived from the title");

			$child_id = (int)($svc->aiCreatePage($validated["payload"], $dev)["page_id"] ?? 0);
			T::ok($child_id > 0, "the page was created");
			T::equals(
				(string)SQL::fetchSingle("SELECT route FROM bigtree_pages WHERE id = ?", $child_id),
				$route,
				"and stored with that route"
			);

			// Now archive the parent and try again.
			$archive = $svc->aiValidatePageArchive(["id" => $parent_id], $dev);
			$svc->aiArchivePage($archive["payload"], $dev);

			$refused = $svc->aiValidatePageCreate([
				"parent" => $parent_id,
				"nav_title" => "ZZ A5 Under Archived",
				"template" => "",
				"external" => "https://example.com/a5-archived",
			], $dev);

			T::ok(isset($refused["error"]), "a page can't be created under an archived parent");
		} finally {
			parity_delete_page($parent_id);
			parity_delete_users($dev_id);
		}
	}

	/** D: template and callout ids are checked *after* urlify, not before. */
	function test_parity_ai_unusable_ids_are_refused_at_staging() {
		$templates = new \BigTree\Services\TemplateService();
		$callouts = new CalloutService();
		$dev = (object)["id" => 1, "level" => 2, "permissions" => []];

		$template = $templates->aiValidateTemplateCreate(["id" => "!!!", "name" => "ZZ"], $dev);
		T::ok(isset($template["error"]), "a template id with no slug-able characters is refused");
		T::ok(
			strpos((string)$template["error"], "no longer available") === false,
			"and refused for the real reason, not with the approval-time message"
		);

		$callout = $callouts->aiValidateCalloutCreate(["id" => "???", "name" => "ZZ"], $dev);
		T::ok(isset($callout["error"]), "the same for a callout id");
	}

	/** D (D4): notification preferences are settable, not just readable. */
	function test_parity_ai_update_user_sets_notification_preferences() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new UserService();
		$admin = (object)["id" => 1, "level" => 1, "permissions" => []];
		$target_id = parity_seed_user(["level" => 0, "name" => "ZZ Audit5 Prefs"]);

		try {
			$validated = $svc->aiValidateUserUpdate([
				"user_id" => $target_id,
				"daily_digest" => true,
				"alerts" => ["0" => 30],
			], $admin);

			T::ok(!empty($validated["ok"]), "the preference edit validates");

			$result = $svc->aiUpdateUser($validated["payload"], $admin);
			T::equals((string)$result["mode"], "updated", "the edit applies");

			$row = SQL::fetch("SELECT daily_digest, alerts FROM bigtree_users WHERE id = ?", $target_id);
			T::equals((string)$row["daily_digest"], "on", "the digest flag was written");
			// Stored in the encoding the rest of the system reads: a page-id => "on"
			// subscription map, never a number of days (the threshold is the page's
			// own max_age). Anything else is reverted by the next human save.
			T::equals(json_decode((string)$row["alerts"], true), ["0" => "on"], "the subscription was written as \"on\"");

			// Adding one page must not wipe the subscriptions already there.
			$page_id = parity_seed_page(["nav_title" => "ZZ Alert Target"]);

			try {
				$add = $svc->aiValidateUserUpdate([
					"user_id" => $target_id,
					"alerts" => [(string)$page_id => true],
				], $admin);

				T::ok(!empty($add["ok"]), "adding a single page alert validates");
				$svc->aiUpdateUser($add["payload"], $admin);
				T::equals(
					json_decode((string)SQL::fetchSingle("SELECT alerts FROM bigtree_users WHERE id = ?", $target_id), true),
					["0" => "on", (string)$page_id => "on"],
					"the new subscription is merged into the existing map, not substituted for it"
				);

				// …and false removes just that one.
				$remove = $svc->aiValidateUserUpdate([
					"user_id" => $target_id,
					"alerts" => [(string)$page_id => false],
				], $admin);

				T::ok(!empty($remove["ok"]), "removing a single page alert validates");
				$svc->aiUpdateUser($remove["payload"], $admin);
				T::equals(
					json_decode((string)SQL::fetchSingle("SELECT alerts FROM bigtree_users WHERE id = ?", $target_id), true),
					["0" => "on"],
					"false removes only the page it names"
				);
			} finally {
				parity_delete_page($page_id);
			}

			// A bad shape is a correction, not a silent no-op.
			$bad = $svc->aiValidateUserUpdate(["user_id" => $target_id, "alerts" => "everything"], $admin);
			T::ok(isset($bad["error"]), "a malformed alerts value is refused");

			// A subscription to a page that doesn't exist would never match anything.
			$ghost = $svc->aiValidateUserUpdate([
				"user_id" => $target_id,
				"alerts" => ["99999999" => true],
			], $admin);
			T::ok(isset($ghost["error"]), "a subscription to a non-existent page is refused");
		} finally {
			parity_delete_users($target_id);
		}
	}

	/** D: a name longer than the column is refused at staging and at approval. */
	function test_parity_ai_user_fields_respect_their_column_caps() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new UserService();
		$admin = (object)["id" => 1, "level" => 1, "permissions" => []];
		$target_id = parity_seed_user(["level" => 0, "name" => "ZZ Audit5 Caps"]);
		$long = str_repeat("n", 300);

		try {
			$staged = $svc->aiValidateUserUpdate(["user_id" => $target_id, "name" => $long], $admin);
			T::ok(isset($staged["error"]), "an over-length name is refused at staging");

			$result = $svc->aiUpdateUser([
				"user_id" => $target_id,
				"changes" => ["name" => $long],
			], $admin);
			T::equals((string)($result["mode"] ?? ""), "error", "and refused again at approval");

			T::equals(
				(string)SQL::fetchSingle("SELECT name FROM bigtree_users WHERE id = ?", $target_id),
				"ZZ Audit5 Caps",
				"nothing was written"
			);
		} finally {
			parity_delete_users($target_id);
		}
	}

	/** D: the invite token must be CSPRNG output, not a function of the clock. */
	function test_parity_ai_invite_tokens_are_unpredictable() {
		if (!parity_db_available()) {
			return;
		}

		$first = parity_seed_user(["level" => 0, "password" => ""]);
		$second = parity_seed_user(["level" => 0, "password" => ""]);

		try {
			$reflection = new ReflectionMethod(\BigTree\Services\AuthService::class, "issuePasswordToken");
			T::ok($reflection->isStatic(), "issuePasswordToken is the shared token minter");

			// Two password-less accounts minted back to back used to produce tokens
			// derived from md5("") plus a microsecond stamp.
			$tokens = [];

			foreach ([$first, $second] as $id) {
				$row = SQL::fetch("SELECT id, email, password FROM bigtree_users WHERE id = ?", $id);

				try {
					$tokens[] = \BigTree\Services\AuthService::issuePasswordToken($row, 60);
				} catch (Throwable $e) {
					// Mail delivery may fail in this harness; the token is still stored.
					$tokens[] = (string)SQL::fetchSingle(
						"SELECT change_password_hash FROM bigtree_users WHERE id = ?",
						$id
					);
				}
			}

			T::ok($tokens[0] !== $tokens[1], "two invites produce different tokens");

			foreach ($tokens as $token) {
				$hash = strtok((string)$token, ".");
				T::equals(strlen((string)$hash), 64, "the token hash is 32 bytes of CSPRNG output, hex encoded");
				T::ok($hash !== md5(md5("")), "and is not derived from the empty password hash");
			}
		} finally {
			parity_delete_users($first, $second);
		}
	}

	/** D: a routed template's stub belongs in its own directory. */
	function test_parity_routed_template_scaffold_path() {
		T::equals(
			TemplateScaffold::templatePath("landing", false),
			"templates/basic/landing.php",
			"a basic template keeps its flat path"
		);
		T::equals(
			TemplateScaffold::templatePath("landing", true),
			"templates/routed/landing/default.php",
			"a routed template's stub lives in its own directory, where its sub-route siblings go"
		);
	}

	/** D: developer objects audit under the JSON-DB store name every other writer uses. */
	function test_parity_ai_developer_audit_rows_use_the_json_db_store_names() {
		$cases = [
			["create_template", ["id" => "x"], ["mode" => "created", "id" => "x"], "templates"],
			["update_template", ["id" => "x"], ["mode" => "updated", "id" => "x"], "templates"],
			["create_callout", ["id" => "c"], ["mode" => "created", "id" => "c"], "callouts"],
			["update_callout", ["id" => "c"], ["mode" => "updated", "id" => "c"], "callouts"],
			["create_callout_group", [], ["mode" => "created", "id" => "g"], "callout-groups"],
			["create_module", [], ["mode" => "created", "id" => "m"], "modules"],
			["update_module", ["id" => "m"], ["mode" => "updated", "id" => "m"], "modules"],
			["create_module_group", [], ["mode" => "created", "id" => "mg"], "module-groups"],
		];

		foreach ($cases as [$tool, $payload, $result, $table]) {
			$descriptor = AIChatService::auditDescriptor($tool, $payload, $result);
			T::ok($descriptor !== null, "{$tool} produces an audit descriptor");
			T::equals($descriptor["table"], $table, "{$tool} audits under “{$table}”");
		}
	}

	/** D: amending a queued draft must audit the change row, not page #0. */
	function test_parity_ai_draft_amendment_audits_the_change_row() {
		$descriptor = AIChatService::auditDescriptor(
			"update_page",
			["id" => "p42"],
			["mode" => "pending", "page_id" => 0, "pending_change_id" => 42, "title" => "draft"]
		);

		T::ok($descriptor !== null, "a draft amendment is audited");
		T::equals($descriptor["table"], "bigtree_pending_changes", "against the pending-change row");
		T::equals((string)$descriptor["entry"], "42", "identified by the change id, not page 0");

		// A live page edit is unaffected.
		$live = AIChatService::auditDescriptor(
			"update_page",
			["id" => "42"],
			["mode" => "published", "page_id" => 42, "title" => "page"]
		);
		T::equals($live["table"], "bigtree_pages", "a live edit still audits the page");
		T::equals((string)$live["entry"], "42", "with the page id");
	}

	/** D3: list options are exposed in the schema and validated on the way in. */
	function test_parity_ai_list_options_are_exposed_and_validated() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$module_id = parity_news_module_id();
		$original = BigTreeJSONDB::get("modules", $module_id);
		$svc = new AutoModuleService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];

		try {
			$patched = $original;
			$form_key = array_key_first($patched["forms"]);
			$column = "";

			// Retype an existing text column to a static list, so the fixture writes
			// no DDL and the column really exists on the table.
			foreach ($patched["forms"][$form_key]["fields"] as $key => $field) {
				if ((string)($field["type"] ?? "") === "text" && (string)($field["column"] ?? "") !== "title") {
					$column = (string)$field["column"];
					$patched["forms"][$form_key]["fields"][$key]["type"] = "list";
					$patched["forms"][$form_key]["fields"][$key]["settings"] = [
						"list_type" => "static",
						"list" => [
							["key" => "portland", "description" => "Portland"],
							["key" => "seattle", "description" => "Seattle"],
						],
					];

					break;
				}
			}

			if ($column === "") {
				echo "  (skipped — News module has no spare text column to retype)\n";

				return;
			}

			BigTreeJSONDB::update("modules", $module_id, $patched);

			$schema = $svc->aiModuleSchema($module_id, "", $dev);
			$reported = null;

			foreach ($schema["schema"]["fields"] ?? [] as $field) {
				if ((string)$field["column"] === $column) {
					$reported = $field;
				}
			}

			T::ok($reported !== null, "the list field is in the schema");
			T::equals(count($reported["options"] ?? []), 2, "and carries its resolved option set");

			// A value outside the domain is a correction, not a silent write.
			$bad = $svc->aiValidateEntryCreate([
				"module_id" => $module_id,
				"data" => ["title" => "zz Audit5 Options", $column => "Vancouver"],
			], $dev);
			T::ok(isset($bad["error"]), "an out-of-domain list value is refused");
			T::ok(strpos((string)$bad["error"], "Portland") !== false, "and the options are named in the error");

			// The label the model would naturally write is matched back to its value.
			$good = $svc->aiValidateEntryCreate([
				"module_id" => $module_id,
				"data" => ["title" => "zz Audit5 Options", $column => "Portland"],
			], $dev);
			T::ok(!empty($good["ok"]), "the option's label is accepted");
			T::equals($good["payload"]["data"][$column], "portland", "and stored as the option's value");
		} finally {
			BigTreeJSONDB::update("modules", $module_id, $original);
			parity_delete_users($dev_id);
		}
	}

	/** C3/D6: callout group membership is readable, and settable after creation. */
	function test_parity_ai_callout_groups_are_readable_and_settable() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new CalloutService();
		$dev = (object)["id" => 1, "level" => 2, "permissions" => []];
		$callout_id = "zz-a5-grouped-" . bin2hex(random_bytes(3));
		$group_id = "zz-a5-group-" . bin2hex(random_bytes(3));

		try {
			BigTreeJSONDB::insert("callout-groups", [
				"id" => $group_id,
				"name" => "ZZ Audit5 Group",
				"callouts" => [],
			]);
			BigTreeJSONDB::insert("callouts", [
				"id" => $callout_id,
				"name" => "ZZ Audit5 Grouped",
				"description" => "",
				"level" => 0,
				"display_field" => "title",
				"display_default" => "",
				"resources" => [
					["id" => "title", "type" => "text", "title" => "Title", "subtitle" => "", "settings" => []],
				],
				"position" => 0,
			]);

			$listed = $svc->aiListCallouts($dev);
			T::ok(!isset($listed["denied"]), "list_callouts is available to a developer");
			T::ok(
				in_array($group_id, array_column($listed["groups"], "id"), true),
				"the group appears in list_callouts"
			);

			$before = $svc->aiGetCallout($callout_id, $dev);
			T::equals($before["callout"]["groups"], [], "an ungrouped callout reads back with no groups");

			$validated = $svc->aiValidateCalloutUpdate(["id" => $callout_id, "group" => "ZZ Audit5 Group"], $dev);
			T::ok(!empty($validated["ok"]), "a group can be set on an existing callout");
			T::equals((string)$svc->aiUpdateCallout($validated["payload"], $dev)["mode"], "updated", "the edit applies");

			$after = $svc->aiGetCallout($callout_id, $dev);
			T::equals(
				array_column($after["callout"]["groups"], "id"),
				[$group_id],
				"and the membership reads back"
			);
		} finally {
			foreach (["callouts" => $callout_id, "callout-groups" => $group_id] as $store => $id) {
				if (BigTreeJSONDB::exists($store, $id)) {
					BigTreeJSONDB::delete($store, $id);
				}
			}
		}
	}

	/**
	 * Part D: the concurrent-edit lock the admin has always shown, on the proposal
	 * card.
	 *
	 * `bigtree_locks` is held by PageEdit / ModuleEntryEdit / SettingEdit for the
	 * life of the screen, and a second arrival gets a banner naming the holder. The
	 * assistant wrote to exactly those records and said nothing — so the one surface
	 * where a change is reviewed before it lands was the one that didn't mention the
	 * colleague already working on it.
	 *
	 * Advisory, not a refusal: the admin lets a second editor take a held lock over,
	 * and approving is that same decision, now made knowingly.
	 */
	function test_parity_ai_proposal_surfaces_another_users_lock() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$holder_id = parity_seed_user(["level" => 1, "name" => "Dana Holder"]);
		$page_id = 0;
		$lock_id = 0;

		try {
			$page_id = parity_a5_page($svc, $dev);
			T::ok($page_id > 0, "fixture page created");

			$descriptor = ["table" => "bigtree_pages", "id" => $page_id];

			// Nobody holding it: no note, and nothing appended to any summary.
			T::equals(ContentLock::note($descriptor, $dev), "", "an unlocked page produces no note");

			$lock_id = (int)SQL::insert("bigtree_locks", [
				"table" => "bigtree_pages",
				"item_id" => (string)$page_id,
				"user" => $holder_id,
				"title" => "Fixture",
			]);

			$note = ContentLock::note($descriptor, $dev);
			T::ok(strpos($note, "Dana Holder") !== false, "the note names the holder");
			T::ok(strpos($note, "page") !== false, "and what kind of record it is");

			// The holder's own lock is a refresh in the admin, not a conflict.
			$holder = (object)["id" => $holder_id, "level" => 1, "permissions" => []];
			T::equals(ContentLock::note($descriptor, $holder), "", "your own lock is not a conflict");

			// Stale under LockService's own five-minute rule.
			SQL::query(
				"UPDATE bigtree_locks SET last_accessed = ? WHERE id = ?",
				date("Y-m-d H:i:s", time() - (LockService::STALE_SECONDS + 60)),
				$lock_id
			);
			T::equals(ContentLock::note($descriptor, $dev), "", "a stale lock is not reported");

			SQL::update("bigtree_locks", $lock_id, ["last_accessed" => "NOW()"]);

			// And the seam hands the framework the descriptor that produces it.
			$validated = $svc->aiValidatePageUpdate([
				"id" => (string)$page_id,
				"meta_description" => "Audit five lock check",
			], $dev);
			T::ok(!empty($validated["ok"]), "the edit still validates — a lock never blocks");
			T::equals(
				$validated["lock"],
				$descriptor,
				"update_page names the page's lock"
			);
			T::ok(
				strpos(ContentLock::note($validated["lock"], $dev), "Dana Holder") !== false,
				"which resolves to the warning the card shows"
			);
		} finally {
			if ($lock_id > 0) {
				SQL::delete("bigtree_locks", $lock_id);
			}

			parity_delete_page($page_id);
			parity_delete_users($dev_id, $holder_id);
		}
	}

	/**
	 * A draft has no live row for the editor to lock, and PageEdit deliberately
	 * doesn't lock one — so a proposal against a draft must not claim a lock on
	 * page #0 (which, being a real `item_id` string, could match a stray row).
	 */
	function test_parity_ai_draft_proposals_name_no_lock() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$editor_id = 0;
		$page_id = 0;
		$change_id = 0;

		try {
			$page_id = parity_a5_page($svc, $dev);
			T::ok($page_id > 0, "fixture page created");

			[$editor_id, $editor] = parity_a5_page_editor($page_id);

			// An editor creating a child queues it as a NEW change — a draft with no
			// live row behind it, which is exactly the case PageEdit doesn't lock.
			$created = $svc->aiValidatePageCreate([
				"parent" => $page_id,
				"nav_title" => "AI Audit5 Draft " . bin2hex(random_bytes(3)),
				"template" => "",
				"external" => "https://example.com/audit5-draft",
			], $editor);
			T::ok(!empty($created["ok"]), "the editor's create validates");
			$change_id = (int)($svc->aiCreatePage($created["payload"], $editor)["pending_change_id"] ?? 0);
			T::ok($change_id > 0, "it queued a draft rather than publishing");

			$amend = $svc->aiValidatePageUpdate([
				"id" => "p{$change_id}",
				"meta_keywords" => "audit,five",
			], $editor);
			T::ok(!empty($amend["ok"]), "the draft amendment validates");
			T::equals($amend["lock"], [], "amending a draft names no lock");
			T::equals(ContentLock::note($amend["lock"], $editor), "", "and so produces no note");
		} finally {
			parity_delete_pending($change_id);
			parity_delete_page($page_id);
			parity_delete_users($dev_id, $editor_id);
		}
	}
