<?php
	/**
	 * Draw-time field defaults reach every created record.
	 *
	 * `default_today`, `default_now`, `default_checked` and a list's `allow-empty: No`
	 * were applied only by legacy draw files, so a REST or assistant create that
	 * omitted the field stored a blank. Both writers now seed them through the shared
	 * create seams (AutoModuleService::applyFormFieldDefaults,
	 * PageService::normalizePageResources), at create time only.
	 *
	 * Runs on a throwaway module + table and a throwaway template.
	 */

	use BigTree\Services\AutoModuleService;
	use BigTree\Services\PageService;
	use BigTree\Services\AI\ColumnDomain;

	/** The field list both fixtures share (form fields carry `column`, resources `id`). */
	function parity_a33_fields(string $key): array {
		$list = [["value" => "news", "description" => "News"], ["value" => "events", "description" => "Events"]];
		$fields = [
			["title" => "Title", "type" => "text", "settings" => []],
			["title" => "Published On", "type" => "date", "settings" => ["default_today" => "on"], "name" => "published_on"],
			["title" => "Starts At", "type" => "datetime", "settings" => ["default_now" => "on"], "name" => "starts_at"],
			["title" => "Featured", "type" => "checkbox", "settings" => ["default_checked" => "on"], "name" => "featured"],
			[
				"title" => "Flagged",
				"type" => "checkbox",
				"settings" => ["default_checked" => "on", "custom_value" => "yes"],
				"name" => "flagged",
			],
			[
				"title" => "Category",
				"type" => "list",
				"settings" => ["list_type" => "static", "allow-empty" => "No", "list" => $list],
				"name" => "category",
			],
			[
				"title" => "Ends On",
				"type" => "date",
				"settings" => ["default_today" => "on", "default" => "2030-01-01"],
				"name" => "ends_on",
			],
		];
		$out = [];

		foreach ($fields as $field) {
			$name = $field["name"] ?? "title";
			unset($field["name"]);
			$field[$key] = $name;

			if ($key === "column") {
				$field["id"] = $name;
			}

			$out[] = $field;
		}

		return $out;
	}

	/** @return array{0:string,1:string,2:string} [module id, form id, table] */
	function parity_a33_module(string $suffix): array {
		$table = "zz_audit33_{$suffix}";

		SQL::query("CREATE TABLE `{$table}` (`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT, "
			. "`title` VARCHAR(191) NOT NULL DEFAULT '', `published_on` DATE NULL, `starts_at` DATETIME NULL, "
			. "`featured` VARCHAR(10) NOT NULL DEFAULT '', `flagged` VARCHAR(10) NOT NULL DEFAULT '', "
			. "`category` VARCHAR(50) NOT NULL DEFAULT '', `ends_on` DATE NULL, "
			. "PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
		ColumnDomain::forget($table);

		$module_id = BigTreeJSONDB::insert("modules", [
			"name" => "ZZ Audit33 {$suffix}",
			"route" => "zz-audit33-{$suffix}",
			"class" => "",
			"gbp" => ["name" => "", "table" => "", "item_parser" => "", "other_table" => ""],
			"forms" => [[
				"id" => "form-{$suffix}",
				"title" => "Item",
				"table" => $table,
				"hooks" => ["edit" => "", "pre" => "", "post" => "", "publish" => ""],
				"fields" => parity_a33_fields("column"),
			]],
			"views" => [[
				"id" => "view-{$suffix}",
				"title" => "Items",
				"type" => "searchable",
				"table" => $table,
				"fields" => ["title" => ["width" => "", "title" => "Title", "parser" => "", "numeric" => false]],
				"settings" => ["filter" => "", "sort" => "`id` DESC", "per_page" => "15"],
				"actions" => ["edit" => "on"],
			]],
			"actions" => [[
				"id" => "action-{$suffix}",
				"name" => "View Items",
				"route" => "view-items",
				"in_nav" => "on",
				"level" => 0,
				"view" => "view-{$suffix}",
			]],
		]);

		return [(string)$module_id, "form-{$suffix}", $table];
	}

	function parity_a33_teardown(string $suffix, string $table): void {
		BigTreeJSONDB::delete("modules", "zz-audit33-{$suffix}", "route");

		try {
			if ($table !== "") {
				SQL::query("DELETE FROM bigtree_pending_changes WHERE `table` = ?", $table);
				SQL::query("DROP TABLE IF EXISTS `{$table}`");
			}

			SQL::query("DELETE FROM bigtree_module_view_cache WHERE view = ?", "view-{$suffix}");
		} catch (Throwable $e) {
			// disposable
		}

		ColumnDomain::forget($table);
	}

	/** Assert a stored row carries every draw-time default, for one writer. */
	function parity_a33_assert_seeded(array $row, string $writer): void {
		T::equals($row["published_on"] ?? "", date("Y-m-d"), "{$writer}: default_today stored today");
		T::ok(
			strpos((string)($row["starts_at"] ?? ""), date("Y-m-d")) === 0,
			"{$writer}: default_now stored a timestamp from today"
		);
		T::equals($row["featured"] ?? "", "on", "{$writer}: default_checked stored on");
		T::equals($row["flagged"] ?? "", "yes", "{$writer}: …or the field's custom value");
		T::equals($row["category"] ?? "", "news", "{$writer}: a no-blank list stored its first option");
		T::equals($row["ends_on"] ?? "", "2030-01-01", "{$writer}: an explicit default wins over default_today");
	}

	function test_parity_rest_and_ai_entry_creates_seed_draw_time_defaults() {
		if (!parity_db_available()) {

			return;
		}

		$suffix = substr(md5(uniqid("", true)), 0, 8);
		$table = "";
		$dev_id = 0;

		try {
			[$module_id, $form_id, $table] = parity_a33_module($suffix);
			$dev_id = parity_seed_user(["level" => 2]);
			$user = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
			$svc = new AutoModuleService();

			// REST, omitting every defaulted field.
			$body = (array)parity_data($svc->create(parity_request($dev_id, 2, ["id" => $module_id], [
				"title" => "REST entry",
				"__publish__" => true,
			], ["form" => $form_id])));
			$row = SQL::fetch("SELECT * FROM `{$table}` WHERE id = ?", (int)($body["id"] ?? 0));
			parity_a33_assert_seeded(is_array($row) ? $row : [], "REST");

			// REST never overwrites a value the caller sent.
			$body = (array)parity_data($svc->create(parity_request($dev_id, 2, ["id" => $module_id], [
				"title" => "REST supplied",
				"published_on" => "2020-05-05",
				"category" => "events",
				"__publish__" => true,
			], ["form" => $form_id])));
			$row = SQL::fetch("SELECT * FROM `{$table}` WHERE id = ?", (int)($body["id"] ?? 0));
			T::equals($row["published_on"] ?? "", "2020-05-05", "REST: a supplied date is kept");
			T::equals($row["category"] ?? "", "events", "REST: a supplied option is kept");

			// The assistant.
			$staged = $svc->aiValidateEntryCreate(["module_id" => $module_id, "data" => ["title" => "AI entry"]], $user);
			T::ok(!empty($staged["ok"]), "the AI create staged");

			$row_text = (string)($staged["preview"]["defaults_applied"] ?? "");
			T::ok(strpos($row_text, "Published On: " . date("Y-m-d")) !== false, "the card names the seeded date");
			T::ok(strpos($row_text, "Category: news") !== false, "…and the seeded list option");
			T::ok(strpos($row_text, "Ends On") === false, "…but not a field its explicit default fills");
			T::ok(strpos((string)($staged["summary"] ?? ""), "configured default") !== false, "the summary says so too");

			$result = $svc->aiCreateEntry($staged["payload"] ?? [], $user);
			$row = SQL::fetch("SELECT * FROM `{$table}` WHERE id = ?", (int)($result["entry_id"] ?? 0));
			parity_a33_assert_seeded(is_array($row) ? $row : [], "AI");
			T::ok(
				strpos((string)($result["note"] ?? ""), "configured defaults") !== false,
				"the approval result names the values written"
			);

			// A draft carries the seeded values through publish.
			$draft = $svc->aiValidateEntryCreate([
				"module_id" => $module_id,
				"data" => ["title" => "AI draft"],
				"save_as_draft" => true,
			], $user);
			$queued = $svc->aiCreateEntry($draft["payload"] ?? [], $user);
			$changes = SQL::fetchSingle(
				"SELECT changes FROM bigtree_pending_changes WHERE id = ?",
				(int)($queued["pending_id"] ?? 0)
			);
			$changes = is_string($changes) ? (array)json_decode($changes, true) : [];
			T::equals($queued["mode"] ?? "", "pending", "the draft was queued");
			T::equals($changes["published_on"] ?? "", date("Y-m-d"), "the queued draft carries the seeded date");
			T::equals($changes["category"] ?? "", "news", "…and the seeded option");
		} finally {
			parity_delete_users($dev_id);
			parity_a33_teardown($suffix, $table);
		}
	}

	function test_parity_ai_entry_writes_refuse_a_blank_on_a_no_blank_list() {
		if (!parity_db_available()) {

			return;
		}

		$suffix = substr(md5(uniqid("", true)), 0, 8);
		$table = "";
		$dev_id = 0;

		try {
			[$module_id, , $table] = parity_a33_module($suffix);
			$dev_id = parity_seed_user(["level" => 2]);
			$user = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
			$svc = new AutoModuleService();

			$staged = $svc->aiValidateEntryCreate([
				"module_id" => $module_id,
				"data" => ["title" => "Blank category", "category" => ""],
			], $user);
			T::ok(isset($staged["error"]), "a create with an explicit blank is refused");
			T::ok(strpos((string)($staged["error"] ?? ""), "blank option") !== false, "…and the error says why");

			$created = $svc->aiCreateEntry(
				($svc->aiValidateEntryCreate(["module_id" => $module_id, "data" => ["title" => "Fine"]], $user))["payload"] ?? [],
				$user
			);
			$entry_id = (int)($created["entry_id"] ?? 0);
			$update = $svc->aiValidateEntryUpdate([
				"module_id" => $module_id,
				"entry_id" => $entry_id,
				"data" => ["category" => ""],
			], $user);
			T::ok(isset($update["error"]), "an update that blanks the list is refused");

			// An update that doesn't touch a defaulted field never gains a value.
			SQL::query("UPDATE `{$table}` SET published_on = NULL WHERE id = ?", $entry_id);
			$edit = $svc->aiValidateEntryUpdate([
				"module_id" => $module_id,
				"entry_id" => $entry_id,
				"data" => ["title" => "Renamed"],
			], $user);
			$svc->aiUpdateEntry($edit["payload"] ?? [], $user);
			$row = SQL::fetch("SELECT * FROM `{$table}` WHERE id = ?", $entry_id);
			T::equals($row["title"] ?? "", "Renamed", "the edit landed");
			T::equals($row["published_on"] ?? null, null, "…and an update never seeds a draw-time default");
		} finally {
			parity_delete_users($dev_id);
			parity_a33_teardown($suffix, $table);
		}
	}

	function test_parity_page_creates_and_template_switches_seed_draw_time_defaults() {
		if (!parity_db_available()) {

			return;
		}

		$template = "zz-audit33-" . substr(md5(uniqid("", true)), 0, 8);
		$plain = $template . "-plain";
		$dev_id = 0;
		$pages = [];

		try {
			parity_seed_template($template, parity_a33_fields("id"));
			parity_seed_template($plain, [["id" => "title", "title" => "Title", "type" => "text", "settings" => []]]);
			$dev_id = parity_seed_user(["level" => 2]);
			$user = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
			$svc = new PageService();
			$resources = function (int $id): array {

				return (array)json_decode((string)SQL::fetchSingle("SELECT resources FROM bigtree_pages WHERE id = ?", $id), true);
			};

			// REST create, with no content at all.
			$body = (array)parity_data($svc->create(parity_request($dev_id, 2, [], [
				"parent" => 0,
				"nav_title" => "ZZ Audit33 REST " . bin2hex(random_bytes(3)),
				"template" => $template,
				"publish" => true,
			])));
			$pages[] = (int)($body["id"] ?? 0);
			parity_a33_assert_seeded($resources((int)($body["id"] ?? 0)), "REST page");

			// The assistant.
			$staged = $svc->aiValidatePageCreate([
				"parent" => 0,
				"nav_title" => "ZZ Audit33 AI " . bin2hex(random_bytes(3)),
				"template" => $template,
				"content" => ["title" => "Hello"],
			], $user);
			T::ok(!empty($staged["ok"]), "the AI page create staged");
			T::ok(
				strpos((string)($staged["preview"]["defaults_applied"] ?? ""), "Published On") !== false,
				"the page card names the seeded fields"
			);

			$result = $svc->aiCreatePage($staged["payload"] ?? [], $user);
			$pages[] = (int)($result["page_id"] ?? 0);
			parity_a33_assert_seeded($resources((int)($result["page_id"] ?? 0)), "AI page");
			T::ok(strpos((string)($result["note"] ?? ""), "configured defaults") !== false, "the page result names them");

			// A switch onto the template seeds the fields it brings in.
			$switch_page = parity_seed_page(["template" => $plain, "resources" => json_encode(["title" => "Old"])]);
			$pages[] = $switch_page;

			$switch = $svc->aiValidatePageContentUpdate([
				"id" => $switch_page,
				"template" => $template,
				"content" => ["title" => "Switched"],
			], $user);
			T::ok(!empty($switch["ok"]), "the template switch staged");
			T::ok(
				strpos((string)($switch["preview"]["defaults_applied"] ?? ""), "Published On") !== false,
				"the switch card names the fields it brings in with defaults"
			);
			$svc->aiUpdatePageContent($switch["payload"] ?? [], $user);
			parity_a33_assert_seeded($resources($switch_page), "template switch");

			// A same-template edit never seeds: a key missing from stored content is
			// content that predates the field, not a new page.
			$stored = $resources($switch_page);
			unset($stored["published_on"]);
			SQL::update("bigtree_pages", $switch_page, ["resources" => json_encode($stored)]);
			$same = $svc->aiValidatePageContentUpdate(["id" => $switch_page, "content" => ["title" => "Same"]], $user);
			T::ok(!empty($same["ok"]), "the same-template edit staged");
			T::ok(!isset($same["preview"]["defaults_applied"]), "…with no defaults row on its card");
			$svc->aiUpdatePageContent($same["payload"] ?? [], $user);
			T::equals($resources($switch_page)["title"] ?? "", "Same", "the same-template edit landed");
			T::equals(
				$resources($switch_page)["published_on"] ?? "",
				"",
				"…and never seeds a draw-time default onto content that predates the field"
			);

			// A no-blank list can't be blanked through the page path either.
			$blank = $svc->aiValidatePageContentUpdate(["id" => $switch_page, "content" => ["category" => ""]], $user);
			T::ok(isset($blank["error"]), "blanking a no-blank list on a page is refused");
		} finally {
			foreach ($pages as $page_id) {
				if ($page_id > 0) {
					parity_delete_page($page_id);
				}
			}

			parity_delete_users($dev_id);
			parity_delete_template($template);
			parity_delete_template($plain);
		}
	}
