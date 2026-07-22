<?php
	/**
	 * Audit #5 Phase 1 (A1–A8): the ways an assistant write destroyed data that
	 * already existed, or reached the live site without a publisher.
	 *
	 * Every test here fails against the pre-audit-5 code. They are grouped in one
	 * file because they share one theme: the AI path is the only writer that submits
	 * a *partial* body, and every seam that assumed a whole body broke on it.
	 */

	use BigTree\Api\Resources;
	use BigTree\Services\AutoModuleService;
	use BigTree\Services\CalloutService;
	use BigTree\Services\PageService;
	use BigTree\Services\PermissionService;
	use BigTree\Services\SettingService;
	use BigTree\Services\TagService;
	use BigTree\Services\TemplateService;

	/** An editor with edit-but-not-publish access to one page. */
	function parity_a5_page_editor(int $page_id): array {
		$id = parity_seed_user(["level" => 0]);
		$user = (object)[
			"id" => $id,
			"level" => 0,
			"permissions" => ["page" => [$page_id => "e"], "module" => [], "resources" => [], "module_gbp" => []],
		];

		return [$id, $user];
	}

	/** A live page owned by nobody in particular, for a queued-draft fixture. */
	function parity_a5_page(PageService $svc, $dev): int {
		$validated = $svc->aiValidatePageCreate([
			"parent" => 0,
			"nav_title" => "AI Audit5 Fixture " . bin2hex(random_bytes(3)),
			"template" => "",
			"external" => "https://example.com/audit5",
		], $dev);

		if (empty($validated["ok"])) {

			return 0;
		}

		return (int)($svc->aiCreatePage($validated["payload"], $dev)["page_id"] ?? 0);
	}

	/**
	 * A1 (pages): a second AI edit must merge into the queued draft, not replace it.
	 *
	 * writePendingPageChange's EDIT collapse assigned `changes` wholesale. The SPA
	 * survives that because it PATCHes a whole body; the assistant hands over only
	 * the fields it changed, so edit #2 erased edit #1.
	 */
	function test_parity_ai_page_edit_merges_into_queued_draft() {
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

			// Edit #1 — meta_description only.
			$first = $svc->aiValidatePageUpdate([
				"id" => (string)$page_id,
				"meta_description" => "Audit five first edit",
			], $editor);
			T::ok(!empty($first["ok"]), "editor's first edit validates");
			$result = $svc->aiUpdatePage($first["payload"], $editor);
			T::equals((string)$result["mode"], "pending", "an editor's edit queues");

			// Edit #2 — a different field entirely.
			$second = $svc->aiValidatePageUpdate([
				"id" => (string)$page_id,
				"meta_keywords" => "audit,five",
			], $editor);
			T::ok(!empty($second["ok"]), "editor's second edit validates");
			$svc->aiUpdatePage($second["payload"], $editor);

			$row = SQL::fetch(
				"SELECT id, changes FROM bigtree_pending_changes
				 WHERE `table` = 'bigtree_pages' AND item_id = ? AND type = 'EDIT'",
				$page_id
			);
			T::ok(!empty($row), "the two edits collapsed into one queued change");

			$changes = json_decode((string)$row["changes"], true);
			T::equals(
				(string)($changes["meta_keywords"] ?? ""),
				"audit,five",
				"the second edit is queued"
			);
			T::equals(
				(string)($changes["meta_description"] ?? ""),
				"Audit five first edit",
				"the first edit SURVIVED the second"
			);
		} finally {
			parity_delete_page($page_id);
			parity_delete_users($dev_id, $editor_id);
		}
	}

	/**
	 * A1 (pages, part 3): the proposal's `from` values must describe the draft the
	 * edit is joining, not the published page it will never touch.
	 */
	function test_parity_ai_page_edit_diffs_against_the_queued_draft() {
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

			$first = $svc->aiValidatePageUpdate([
				"id" => (string)$page_id,
				"meta_description" => "Queued description",
			], $editor);
			$svc->aiUpdatePage($first["payload"], $editor);

			$second = $svc->aiValidatePageUpdate([
				"id" => (string)$page_id,
				"meta_description" => "Revised description",
			], $editor);

			T::ok(!empty($second["ok"]), "the follow-up edit validates");
			T::equals(
				(string)($second["preview"]["changes"]["meta_description"]["from"] ?? ""),
				"Queued description",
				"the card diffs against the queued draft, not the live page"
			);
		} finally {
			parity_delete_page($page_id);
			parity_delete_users($dev_id, $editor_id);
		}
	}

	/**
	 * A1 (entries): the same clobber through BigTreeAutoModule::submitChange.
	 */
	function test_parity_ai_entry_edit_merges_into_queued_change() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$svc = new AutoModuleService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$editor_id = parity_seed_user(["level" => 0]);
		$editor = (object)[
			"id" => $editor_id,
			"level" => 0,
			"permissions" => ["module" => [parity_news_module_id() => "e"]],
		];
		$entry_id = 0;

		try {
			$created = parity_ai_create_entry($svc, $dev, ["title" => "zz Audit5 Entry", "blurb" => "Original"]);
			$entry_id = (int)$created["id"];
			T::ok($entry_id > 0, "live entry created by a publisher");

			$first = $svc->aiValidateEntryUpdate([
				"module_id" => parity_news_module_id(),
				"entry_id" => (string)$entry_id,
				"data" => ["blurb" => "Editor blurb"],
			], $editor);
			T::ok(!empty($first["ok"]), "editor's first edit validates");
			T::equals((string)$svc->aiUpdateEntry($first["payload"], $editor)["mode"], "pending", "it queues");

			$second = $svc->aiValidateEntryUpdate([
				"module_id" => parity_news_module_id(),
				"entry_id" => (string)$entry_id,
				"data" => ["title" => "zz Audit5 Entry Retitled"],
			], $editor);
			T::ok(!empty($second["ok"]), "editor's second edit validates");
			$svc->aiUpdateEntry($second["payload"], $editor);

			$row = SQL::fetch(
				"SELECT changes FROM bigtree_pending_changes WHERE `table` = 'timber_news' AND item_id = ?",
				$entry_id
			);
			T::ok(!empty($row), "the two edits collapsed into one queued change");

			$changes = json_decode((string)$row["changes"], true);
			T::equals((string)($changes["title"] ?? ""), "zz Audit5 Entry Retitled", "the second edit is queued");
			T::equals((string)($changes["blurb"] ?? ""), "Editor blurb", "the first edit SURVIVED the second");
		} finally {
			parity_delete_news_entries($entry_id);
			parity_delete_users($dev_id, $editor_id);
		}
	}

	/**
	 * A2: add_tags gated on edit access and wrote bigtree_tags_rel live, so an
	 * editor could put a tag on the public site that the admin UI would have queued.
	 */
	function test_parity_ai_add_tags_queues_for_a_non_publisher() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		$tags = new TagService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$page_id = 0;
		$editor_id = 0;
		$tag_id = 0;

		try {
			$page_id = parity_a5_page($svc, $dev);
			T::ok($page_id > 0, "fixture page created");

			[$editor_id, $editor] = parity_a5_page_editor($page_id);

			// An existing tag: coining one stays administrator-only either way.
			$tag_name = "zz audit five";
			$tag_id = (int)SQL::insert("bigtree_tags", [
				"tag" => $tag_name,
				"route" => "zz-audit-five-" . bin2hex(random_bytes(3)),
				"usage_count" => 0,
			]);

			$validated = $tags->aiValidateAddTags([
				"page_id" => $page_id,
				"tags" => [$tag_name],
			], $editor);

			T::ok(!empty($validated["ok"]), "an editor may still stage a tag");
			T::equals((string)($validated["preview"]["mode"] ?? ""), "pending", "the card says it will be queued");

			$result = $tags->aiAddTags($validated["payload"], $editor);
			T::equals((string)($result["mode"] ?? ""), "pending", "approving queues rather than writing live");

			$live = (int)SQL::fetchSingle(
				"SELECT COUNT(*) FROM bigtree_tags_rel WHERE `table` = 'bigtree_pages' AND entry = ? AND tag = ?",
				(string)$page_id,
				$tag_id
			);
			T::equals($live, 0, "nothing was written to the live tag relations");

			$row = SQL::fetch(
				"SELECT changes, tags_changes FROM bigtree_pending_changes
				 WHERE `table` = 'bigtree_pages' AND item_id = ? AND type = 'EDIT'",
				$page_id
			);
			T::ok(!empty($row), "a pending change was queued instead");

			$staged = json_decode((string)$row["tags_changes"], true) ?: [];
			T::ok(in_array($tag_id, array_map("intval", $staged), true), "the tag rides the change's tags_changes");
		} finally {
			parity_delete_page($page_id);
			parity_delete_tags($tag_id);
			parity_delete_users($dev_id, $editor_id);
		}
	}

	/**
	 * A3: publish rights came from userModuleLevel, which returns the *best* of a
	 * user's group grants — so an editor on group 3 published live in group 3
	 * because they happened to be a publisher on group 9.
	 */
	function test_parity_permission_entry_level_is_per_row() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		// A real module id, because userModuleLevel resolves it through the JSON-DB.
		$module_id = parity_news_module_id();
		$module = [
			"id" => $module_id,
			"gbp" => ["enabled" => "on", "group_field" => "category"],
		];
		$user = (object)[
			"id" => 91,
			"level" => 0,
			"permissions" => [
				"module" => [$module_id => ""],
				"module_gbp" => [$module_id => [3 => "e", 9 => "p"]],
			],
		];

		T::equals(
			PermissionService::userModuleLevel($user, $module_id),
			"p",
			"the module-wide rank is still best-of-any-group"
		);
		T::equals(
			PermissionService::userEntryLevel($user, $module, ["id" => 1, "category" => 3]),
			"e",
			"a group-3 row is judged by the group-3 grant"
		);
		T::equals(
			PermissionService::userEntryLevel($user, $module, ["id" => 2, "category" => 9]),
			"p",
			"a group-9 row is still publishable"
		);

		// A module without group-based permissions falls back to the module rank.
		T::equals(
			PermissionService::userEntryLevel($user, ["id" => $module_id], ["id" => 1]),
			"p",
			"a non-GBP module keeps the module-wide rank"
		);

		// So does a row that doesn't declare its group — a create whose data omits
		// the group field has nothing per-row to judge, and tightening there would
		// lock the user out of a write they have always been allowed to make.
		T::equals(
			PermissionService::userEntryLevel($user, $module, ["title" => "no group supplied"]),
			"p",
			"a row with no group value keeps the module-wide rank"
		);
	}

	/**
	 * A3 at the seam, not just in PermissionService: a user who publishes in *some*
	 * group must not publish live in a group where they only edit. The rank is
	 * computed in one place per seam, and a stray second computation would silently
	 * undo this — which is exactly what happened once while building the fix.
	 */
	function test_parity_ai_entry_edit_queues_in_a_group_the_user_only_edits() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$module_id = parity_news_module_id();
		$original = BigTreeJSONDB::get("modules", $module_id);
		$svc = new AutoModuleService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		// A real row: a queued change carries a foreign key to bigtree_users.
		$editor_id = parity_seed_user(["level" => 0]);
		$entry_id = 0;

		try {
			$created = parity_ai_create_entry($svc, $dev, ["title" => "zz Audit5 Group Rank"]);
			$entry_id = (int)$created["id"];
			T::ok($entry_id > 0, "live entry created");

			// Group the module on `id` so each row is its own group, with no DDL.
			$patched = $original;
			$patched["gbp"] = ["enabled" => "on", "group_field" => "id", "group_module" => ""];
			BigTreeJSONDB::update("modules", $module_id, $patched);

			// Publisher on some other group, editor on this row's.
			$editor = (object)[
				"id" => $editor_id,
				"level" => 0,
				"permissions" => [
					"module" => [$module_id => ""],
					"module_gbp" => [$module_id => [
						$entry_id => "e",
						($entry_id + 100000) => "p",
					]],
				],
			];

			T::equals(
				PermissionService::userModuleLevel($editor, $module_id),
				"p",
				"the module-wide rank is the publisher grant"
			);

			$validated = $svc->aiValidateEntryUpdate([
				"module_id" => $module_id,
				"entry_id" => (string)$entry_id,
				"data" => ["title" => "zz Audit5 Group Rank Edited"],
			], $editor);

			T::ok(!empty($validated["ok"]), "the edit validates");
			T::equals(
				(string)$validated["preview"]["mode"],
				"pending",
				"the card says it will be queued, not published"
			);

			$result = $svc->aiUpdateEntry($validated["payload"], $editor);
			T::equals((string)$result["mode"], "pending", "and approving it queues rather than publishing live");

			T::equals(
				(string)SQL::fetchSingle("SELECT title FROM timber_news WHERE id = ?", $entry_id),
				"zz Audit5 Group Rank",
				"the live row is untouched"
			);
		} finally {
			BigTreeJSONDB::update("modules", $module_id, $original);
			parity_delete_news_entries($entry_id);
			parity_delete_users($dev_id, $editor_id);
		}
	}

	/**
	 * A4: update_setting checked the internal prefix, `encrypted` and `locked` —
	 * never `system`, which REST refuses outright on the same branch.
	 */
	function test_parity_ai_update_setting_refuses_a_system_setting() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new SettingService();
		$admin = (object)["id" => 1, "level" => 1, "permissions" => []];
		$id = "zz-audit5-system-" . bin2hex(random_bytes(3));

		try {
			BigTreeJSONDB::insert("settings", [
				"id" => $id,
				"name" => "ZZ Audit5 System Setting",
				"description" => "",
				"type" => "text",
				"settings" => [],
				"extension" => "",
				"system" => "on",
				"encrypted" => "",
				"locked" => "",
			]);

			$validated = $svc->aiValidateSettingUpdate(["id" => $id, "value" => "hijacked"], $admin);
			T::ok(isset($validated["denied"]), "staging refuses a system setting");

			// And again at approval, because a definition can gain the flag mid-TTL.
			$result = $svc->aiUpdateSetting(["id" => $id, "value" => "hijacked"], $admin);
			T::equals((string)($result["mode"] ?? ""), "error", "approval refuses it too");

			$listed = $svc->aiGetSettings("zz-audit5-system", 50, $admin);
			$ids = array_map(function ($row) {

				return (string)($row["id"] ?? "");
			}, $listed["settings"] ?? []);
			T::ok(!in_array($id, $ids, true), "get_settings hides system settings, matching list()'s default");
		} finally {
			parity_delete_setting($id);
		}
	}

	/**
	 * A6: archiving set only `archived_inherited` on descendants, while the front
	 * end filters on `archived` — so a child of an archived page stayed routable.
	 */
	function test_parity_ai_archive_takes_descendants_off_the_site() {
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
			T::ok($parent_id > 0, "parent page created");

			$child_route = parity_unique_route("zz-audit5-child");
			$validated = $svc->aiValidatePageCreate([
				"parent" => $parent_id,
				"nav_title" => $child_route,
				"template" => "",
				"external" => "https://example.com/audit5-child",
			], $dev);
			T::ok(!empty($validated["ok"]), "child validates");
			$child_id = (int)($svc->aiCreatePage($validated["payload"], $dev)["page_id"] ?? 0);
			T::ok($child_id > 0, "child page created");

			$child_path = (string)SQL::fetchSingle("SELECT path FROM bigtree_pages WHERE id = ?", $child_id);

			$archive = $svc->aiValidatePageArchive(["id" => $parent_id], $dev);
			T::ok(!empty($archive["ok"]), "archive validates");
			$svc->aiArchivePage($archive["payload"], $dev);

			$child = SQL::fetch("SELECT archived, archived_inherited FROM bigtree_pages WHERE id = ?", $child_id);
			T::equals((string)$child["archived_inherited"], "on", "the child is marked inherited-archived");
			T::equals((string)$child["archived"], "on", "the child is ALSO archived, so the site stops routing it");

			// The column the router actually filters on.
			$routable = SQL::fetchSingle(
				"SELECT id FROM bigtree_pages WHERE path = ? AND archived = ''",
				$child_path
			);
			T::ok(!$routable, "getPageIDForPath's query no longer finds the child");

			// Unarchiving releases it again.
			$unarchive = $svc->aiValidatePageUnarchive(["id" => $parent_id], $dev);
			T::ok(!empty($unarchive["ok"]), "unarchive validates");
			$svc->aiUnarchivePage($unarchive["payload"], $dev);

			$child = SQL::fetch("SELECT archived, archived_inherited FROM bigtree_pages WHERE id = ?", $child_id);
			T::equals((string)$child["archived"], "", "unarchiving the parent releases the child");
			T::equals((string)$child["archived_inherited"], "", "and clears the inherited flag");
		} finally {
			parity_delete_page($parent_id);
			parity_delete_users($dev_id);
		}
	}

	/**
	 * A6 (second half): a child archived in its own right must survive the parent
	 * being unarchived.
	 */
	function test_parity_ai_unarchive_leaves_independently_archived_children_alone() {
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
			$validated = $svc->aiValidatePageCreate([
				"parent" => $parent_id,
				"nav_title" => parity_unique_route("zz-audit5-own"),
				"template" => "",
				"external" => "https://example.com/audit5-own",
			], $dev);
			$child_id = (int)($svc->aiCreatePage($validated["payload"], $dev)["page_id"] ?? 0);
			T::ok($parent_id > 0 && $child_id > 0, "parent and child created");

			// The child is archived on its own, with no inherited flag.
			$archive_child = $svc->aiValidatePageArchive(["id" => $child_id], $dev);
			$svc->aiArchivePage($archive_child["payload"], $dev);

			$archive_parent = $svc->aiValidatePageArchive(["id" => $parent_id], $dev);
			$svc->aiArchivePage($archive_parent["payload"], $dev);

			$unarchive = $svc->aiValidatePageUnarchive(["id" => $parent_id], $dev);
			$svc->aiUnarchivePage($unarchive["payload"], $dev);

			$child = SQL::fetch("SELECT archived FROM bigtree_pages WHERE id = ?", $child_id);
			T::equals(
				(string)$child["archived"],
				"on",
				"a child archived in its own right is not resurrected by unarchiving its parent"
			);
		} finally {
			parity_delete_page($parent_id);
			parity_delete_users($dev_id);
		}
	}

	/**
	 * A7: `required` declared in the `validation` rule string — how every pre-SPA
	 * module spells it — was invisible to aiRequiredUnsettableFields, so
	 * blocked_required came back empty and the create gate let a publisher publish
	 * an entry the admin's own validator refuses.
	 */
	function test_parity_ai_required_rule_string_reaches_the_blocked_gate() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$module_id = parity_news_module_id();
		$original = BigTreeJSONDB::get("modules", $module_id);
		$svc = new AutoModuleService();
		$dev_id = parity_seed_user(["level" => 2]);
		$dev = (object)["id" => $dev_id, "level" => 2, "permissions" => []];

		try {
			// An image field made required through the rule string only.
			$patched = $original;
			$patched["forms"][array_key_first($patched["forms"])]["fields"][] = [
				"column" => "zz_audit5_image",
				"title" => "ZZ Audit5 Image",
				"type" => "image",
				"settings" => ["validation" => "required"],
			];
			BigTreeJSONDB::update("modules", $module_id, $patched);

			$schema = $svc->aiModuleSchema($module_id, "", $dev);
			$blocked = $schema["schema"]["blocked_required"] ?? [];
			T::ok(
				(bool)array_filter($blocked, function ($entry) {

					return strpos((string)$entry, "zz_audit5_image") !== false;
				}),
				"a rule-string-required complex field appears in blocked_required"
			);

			$reported = null;

			foreach ($schema["schema"]["fields"] ?? [] as $field) {
				if ((string)($field["column"] ?? "") === "zz_audit5_image") {
					$reported = $field;
				}
			}

			T::ok($reported !== null, "the field is listed in the schema");
			T::equals(!empty($reported["required"]), true, "get_module_schema reports it as required");

			// And the create gate actually refuses a publisher.
			$validated = $svc->aiValidateEntryCreate([
				"module_id" => $module_id,
				"data" => ["title" => "zz Audit5 Blocked"],
			], $dev);
			T::ok(isset($validated["error"]), "a publisher's create is refused rather than silently published");
		} finally {
			BigTreeJSONDB::update("modules", $module_id, $original);
			parity_delete_users($dev_id);
		}
	}

	/**
	 * A8: field *types* were validated, field *settings* were not — so the model
	 * could author a `matrix` field with no `columns`, which draw.php refuses to
	 * render at all.
	 */
	function test_parity_ai_refuses_unconfigurable_field_types() {
		if (!parity_db_available()) {
			return;
		}

		$templates = new TemplateService();
		$callouts = new CalloutService();
		$dev = (object)["id" => 1, "level" => 2, "permissions" => []];
		$id = "zz-audit5-tpl-" . bin2hex(random_bytes(3));

		$validated = $templates->aiValidateTemplateCreate([
			"id" => $id,
			"name" => "ZZ Audit5",
			"fields" => [
				["id" => "headline", "type" => "text", "title" => "Headline"],
				["id" => "team_members", "type" => "matrix", "title" => "Team"],
			],
		], $dev);

		T::ok(isset($validated["error"]), "create_template refuses a matrix field");
		T::ok(
			strpos((string)$validated["error"], "Developer → Templates") !== false,
			"and points at where it can be configured"
		);
		T::ok(!BigTreeJSONDB::exists("templates", $id), "nothing was written");

		$callout = $callouts->aiValidateCalloutCreate([
			"id" => "zz-audit5-co-" . bin2hex(random_bytes(3)),
			"name" => "ZZ Audit5",
			"fields" => [["id" => "rows", "type" => "one-to-many", "title" => "Rows"]],
		], $dev);
		T::ok(isset($callout["error"]), "create_callout refuses a one-to-many field");

		// A field retained by id keeps its stored settings, so it stays allowed.
		$existing = [[
			"id" => "team_members",
			"type" => "matrix",
			"title" => "Team",
			"settings" => ["columns" => [["id" => "name", "type" => "text"]]],
		]];
		T::equals(
			Resources::aiUnconfigurableFieldError([["id" => "team_members", "title" => "Team Members"]], $existing),
			null,
			"a matrix field carried over by id is not refused"
		);
		T::ok(
			Resources::aiUnconfigurableFieldError([["id" => "team_members", "type" => "matrix"]], []) !== null,
			"but the same field with no stored settings is"
		);
	}
