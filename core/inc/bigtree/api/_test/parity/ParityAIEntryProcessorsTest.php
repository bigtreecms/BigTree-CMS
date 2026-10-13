<?php
	/**
	 * Phase 1 (A1): the AI entry write path runs the same server-side field
	 * processors the REST write path does.
	 *
	 * Before this, aiCreateEntry/aiUpdateEntry called BigTreeAutoModule directly with
	 * the sifted data and never invoked applyRoute/applyGeocoding — so an AI-created
	 * entry in a routed module had no route at all (front-end detail URLs 404) and an
	 * address-bearing entry had no coordinates. `route` is deliberately not an
	 * AI-settable type, so the model can't paper over it either.
	 *
	 * Uses the News fixture module, whose form has a route field sourced from `title`.
	 */

	use BigTree\Services\AutoModuleService;
	use BigTree\Services\AI\ColumnDomain;

	function parity_ai_processors_ready(): bool {
		if (!parity_db_available()) {

			return false;
		}

		if (!SQL::tableExists("timber_news")) {
			echo "  (skipped — timber_news table missing)\n";

			return false;
		}

		if (!BigTreeJSONDB::exists("modules", parity_news_module_id())) {
			echo "  (skipped — News module missing from json-db)\n";

			return false;
		}

		return true;
	}

	/** Stage and immediately approve an AI entry create, returning the new live id. */
	function parity_ai_create_entry(AutoModuleService $svc, $user, array $data): array {
		$validated = $svc->aiValidateEntryCreate([
			"module_id" => parity_news_module_id(),
			"data" => $data,
		], $user);

		if (empty($validated["ok"])) {

			return ["error" => (string)($validated["error"] ?? $validated["denied"] ?? "unknown"), "id" => 0];
		}

		$result = $svc->aiCreateEntry($validated["payload"], $user);

		return ["error" => "", "id" => (int)($result["entry_id"] ?? 0), "mode" => (string)($result["mode"] ?? "")];
	}

	function test_parity_ai_entry_create_generates_route() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$svc = new AutoModuleService();
		$dev_id = parity_seed_user(["level" => 2]);
		$user = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$suffix = bin2hex(random_bytes(3));
		$title = "AI Route Fixture " . $suffix;
		$created = ["id" => 0];

		try {
			$created = parity_ai_create_entry($svc, $user, [
				"title" => $title,
				"date" => "2026-07-18",
				"blurb" => "Processor coverage",
				"content" => "<p>Body</p>",
			]);

			T::equals($created["error"], "", "AI entry create validated");
			T::ok($created["id"] > 0, "live entry written");

			$row = SQL::fetch("SELECT title, route FROM timber_news WHERE id = ?", $created["id"]);
			T::ok($row !== null, "row exists");
			T::equals($row["route"], BigTreeCMS::urlify($title), "route generated from the title source column");
		} finally {
			parity_delete_news_entries((int)$created["id"]);
			parity_delete_users($dev_id);
		}
	}

	function test_parity_ai_entry_create_route_is_made_unique() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$svc = new AutoModuleService();
		$dev_id = parity_seed_user(["level" => 2]);
		$user = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$suffix = bin2hex(random_bytes(3));
		$title = "AI Dupe Route " . $suffix;
		$first = ["id" => 0];
		$second = ["id" => 0];

		try {
			$first = parity_ai_create_entry($svc, $user, ["title" => $title, "date" => "2026-07-18"]);
			$second = parity_ai_create_entry($svc, $user, ["title" => $title, "date" => "2026-07-18"]);

			T::ok($first["id"] > 0 && $second["id"] > 0, "both entries written");

			$base = BigTreeCMS::urlify($title);
			$route_one = SQL::fetchSingle("SELECT route FROM timber_news WHERE id = ?", $first["id"]);
			$route_two = SQL::fetchSingle("SELECT route FROM timber_news WHERE id = ?", $second["id"]);

			T::equals($route_one, $base, "first entry takes the bare route");
			T::equals($route_two, $base . "-2", "second entry is de-duplicated at write time");
		} finally {
			parity_delete_news_entries((int)$first["id"], (int)$second["id"]);
			parity_delete_users($dev_id);
		}
	}

	/**
	 * B11: renaming an entry must NOT re-slug it.
	 *
	 * The SPA submits the slug it loaded and never regenerates one, so an editor
	 * renaming an entry keeps its URL. The AI path used to regenerate whenever a
	 * source column changed — and entries have no route history and no redirect, so
	 * "fix the typo in the headline" silently 404'd every link to the article.
	 */
	function test_parity_ai_entry_update_keeps_the_route_when_the_title_changes() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$svc = new AutoModuleService();
		$dev_id = parity_seed_user(["level" => 2]);
		$user = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$suffix = bin2hex(random_bytes(3));
		$created = ["id" => 0];

		try {
			$created = parity_ai_create_entry($svc, $user, [
				"title" => "AI Original Title " . $suffix,
				"date" => "2026-07-18",
			]);
			T::ok($created["id"] > 0, "entry created");

			$new_title = "AI Renamed Title " . $suffix;
			$validated = $svc->aiValidateEntryUpdate([
				"module_id" => parity_news_module_id(),
				"entry_id" => $created["id"],
				"data" => ["title" => $new_title],
			], $user);

			T::ok(!empty($validated["ok"]), "update validated");
			$svc->aiUpdateEntry($validated["payload"], $user);

			$row = SQL::fetch("SELECT title, route FROM timber_news WHERE id = ?", $created["id"]);
			T::equals($row["title"], $new_title, "title updated");
			T::equals(
				$row["route"],
				BigTreeCMS::urlify("AI Original Title " . $suffix),
				"the live entry keeps its original route — nothing linking to it breaks"
			);

			// `route` is a derived field type the assistant can't author either, so
			// an entry's URL only ever moves when a human moves it in the admin.
			$reslug = $svc->aiValidateEntryUpdate([
				"module_id" => parity_news_module_id(),
				"entry_id" => $created["id"],
				"data" => ["route" => "ai-deliberate-route"],
			], $user);

			T::ok(isset($reslug["error"]), "the assistant can't set a route column directly either");
		} finally {
			parity_delete_news_entries((int)$created["id"]);
			parity_delete_users($dev_id);
		}
	}

	function test_parity_ai_entry_update_leaves_route_alone_when_source_unchanged() {
		if (!parity_ai_processors_ready()) {
			return;
		}

		$svc = new AutoModuleService();
		$dev_id = parity_seed_user(["level" => 2]);
		$user = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$suffix = bin2hex(random_bytes(3));
		$created = ["id" => 0];

		try {
			$title = "AI Stable Route " . $suffix;
			$created = parity_ai_create_entry($svc, $user, ["title" => $title, "date" => "2026-07-18"]);
			T::ok($created["id"] > 0, "entry created");

			$before = SQL::fetchSingle("SELECT route FROM timber_news WHERE id = ?", $created["id"]);

			// Editing an unrelated column must not re-route a live entry — that would
			// silently 404 every existing inbound link to it.
			$validated = $svc->aiValidateEntryUpdate([
				"module_id" => parity_news_module_id(),
				"entry_id" => $created["id"],
				"data" => ["blurb" => "Only the blurb changed"],
			], $user);

			T::ok(!empty($validated["ok"]), "update validated");
			$svc->aiUpdateEntry($validated["payload"], $user);

			$row = SQL::fetch("SELECT blurb, route FROM timber_news WHERE id = ?", $created["id"]);
			T::equals($row["blurb"], "Only the blurb changed", "blurb updated");
			T::equals($row["route"], $before, "route untouched when no source column changed");
		} finally {
			parity_delete_news_entries((int)$created["id"]);
			parity_delete_users($dev_id);
		}
	}

	/**
	 * Audit #30 E1: the geocoding legs of the same processor pass.
	 *
	 * The route legs above are about a value the write *generates*; these are about
	 * one it *fetches*, and the difference is that the fetch can fail — which, on an
	 * install where Developer → System → Geocoding was never filled in, is what it
	 * does on every save.
	 *
	 * Driven through the fixture module and the stub geocoder from
	 * AIGeocodingDisclosureTest, so both the success and the failure leg run without
	 * a socket.
	 */

	/** Stage and approve an AI create against a geocoding fixture module. */
	function parity_ai_geocoding_create(AutoModuleService $svc, string $module_id, $user, array $data): array {
		$validated = $svc->aiValidateEntryCreate(["module_id" => $module_id, "data" => $data], $user);

		if (empty($validated["ok"])) {

			return ["error" => (string)($validated["error"] ?? $validated["denied"] ?? "unknown"), "id" => 0];
		}

		$result = $svc->aiCreateEntry($validated["payload"], $user);

		return [
			"error" => "",
			"id" => (int)($result["entry_id"] ?? 0),
			"mode" => (string)($result["mode"] ?? ""),
			"note" => (string)($result["note"] ?? ""),
			"staged" => $validated,
		];
	}

	/** Stage and approve an AI update against a geocoding fixture module. */
	function parity_ai_geocoding_update(
		AutoModuleService $svc,
		string $module_id,
		$user,
		int $entry_id,
		array $data
	): array {
		$validated = $svc->aiValidateEntryUpdate([
			"module_id" => $module_id,
			"entry_id" => $entry_id,
			"data" => $data,
		], $user);

		if (empty($validated["ok"])) {

			return ["error" => (string)($validated["error"] ?? $validated["denied"] ?? "unknown"), "note" => ""];
		}

		$result = $svc->aiUpdateEntry($validated["payload"], $user);

		return ["error" => "", "note" => (string)($result["note"] ?? ""), "staged" => $validated];
	}

	function test_parity_ai_entry_create_writes_the_coordinates_a_geocode_returned() {
		if (!parity_db_available()) {

			return;
		}

		$suffix = substr(md5(uniqid("", true)), 0, 8);
		$table = "";
		$dev_id = 0;

		try {
			[$module_id, , $table] = ai_geocoding_module($suffix);
			$dev_id = parity_seed_user(["level" => 2]);
			$user = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
			$stub = ai_geocoding_stub(["latitude" => "41.8781", "longitude" => "-87.6298"]);
			$svc = new AutoModuleService();
			$created = parity_ai_geocoding_create($svc, $module_id, $user, [
				"title" => "Chicago Office",
				"street" => "233 S Wacker Dr",
				"city" => "Chicago",
			]);

			T::equals($created["error"], "", "the create staged");
			T::ok($created["id"] > 0, "the entry was written");
			T::equals(
				$stub->addresses[0] ?? "",
				"233 S Wacker Dr, Chicago",
				"the address was built from the field's own source columns, in order"
			);

			$row = SQL::fetch("SELECT latitude, longitude FROM `{$table}` WHERE id = ?", $created["id"]);

			T::equals($row["latitude"] ?? "", "41.8781", "latitude written from the lookup");
			T::equals($row["longitude"] ?? "", "-87.6298", "longitude written from the lookup");
			T::ok(strpos($created["note"], "Geocoding failed") === false, "and the result says nothing about failure");
		} finally {
			parity_delete_users($dev_id);
			ai_geocoding_teardown($suffix, $table);
		}
	}

	function test_parity_ai_entry_create_stores_empty_coordinates_when_the_geocode_fails() {
		if (!parity_db_available()) {

			return;
		}

		$suffix = substr(md5(uniqid("", true)), 0, 8);
		$table = "";
		$dev_id = 0;

		try {
			[$module_id, , $table] = ai_geocoding_module($suffix);
			$dev_id = parity_seed_user(["level" => 2]);
			$user = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
			ai_geocoding_stub(false, "REQUEST_DENIED");
			$svc = new AutoModuleService();
			$created = parity_ai_geocoding_create($svc, $module_id, $user, [
				"title" => "Nowhere Office",
				"street" => "1 Imaginary Way",
				"city" => "Nowhere",
			]);

			T::equals($created["error"], "", "the create still staged — an entry without coordinates is valid");
			T::ok($created["id"] > 0, "…and still wrote the entry: disclose, never refuse");

			$row = SQL::fetch("SELECT latitude, longitude FROM `{$table}` WHERE id = ?", $created["id"]);

			T::equals($row["latitude"] ?? "x", "", "latitude stored empty rather than left to chance");
			T::equals($row["longitude"] ?? "x", "", "longitude stored empty");

			// The line legacy showed the editor, on the surface that replaced it.
			T::ok(
				strpos($created["note"], "Geocoding failed") !== false,
				"the approval result says the lookup failed (" . $created["note"] . ")"
			);
			T::ok(
				strpos($created["note"], "REQUEST_DENIED") !== false,
				"…quoting the geocoder's own error, which is what names the actual cause"
			);
		} finally {
			parity_delete_users($dev_id);
			ai_geocoding_teardown($suffix, $table);
		}
	}

	/**
	 * A1, the finding leg: an update that moves the address and can't geocode it
	 * must not leave the *old* address's coordinates on the row.
	 *
	 * Fails with the finding reintroduced (applyGeocoding writing nothing on
	 * failure): the row keeps 41.8781 / -87.6298 under a Boston address, and the
	 * card, the result and the audit trail all read exactly as a successful write.
	 */
	function test_parity_ai_entry_update_clears_coordinates_when_the_address_moved() {
		if (!parity_db_available()) {

			return;
		}

		$suffix = substr(md5(uniqid("", true)), 0, 8);
		$table = "";
		$dev_id = 0;

		try {
			[$module_id, , $table] = ai_geocoding_module($suffix);
			$dev_id = parity_seed_user(["level" => 2]);
			$user = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
			ai_geocoding_stub(["latitude" => "41.8781", "longitude" => "-87.6298"]);
			$svc = new AutoModuleService();
			$created = parity_ai_geocoding_create($svc, $module_id, $user, [
				"title" => "Head Office",
				"street" => "233 S Wacker Dr",
				"city" => "Chicago",
			]);

			T::ok($created["id"] > 0, "the entry was created with coordinates");

			ai_geocoding_stub(false, "REQUEST_DENIED");
			$updated = parity_ai_geocoding_update($svc, $module_id, $user, (int)$created["id"], [
				"street" => "1 City Hall Sq",
				"city" => "Boston",
			]);

			T::equals($updated["error"], "", "the update staged");

			$row = SQL::fetch("SELECT street, city, latitude, longitude FROM `{$table}` WHERE id = ?", $created["id"]);

			T::equals($row["city"] ?? "", "Boston", "the address moved");
			T::equals($row["latitude"] ?? "x", "", "the old address's latitude was cleared, not re-asserted");
			T::equals($row["longitude"] ?? "x", "", "…and its longitude");
			T::ok(
				strpos($updated["note"], "Geocoding failed") !== false,
				"and the result says so (" . $updated["note"] . ")"
			);
		} finally {
			parity_delete_users($dev_id);
			ai_geocoding_teardown($suffix, $table);
		}
	}

	/**
	 * D1's other half: an edit that doesn't touch the address keeps the coordinates
	 * it already had. A coordinate that no longer matches its address is wrong data;
	 * one that still matches is right data a flaky service shouldn't erase.
	 */
	function test_parity_ai_entry_update_keeps_coordinates_when_the_address_did_not_move() {
		if (!parity_db_available()) {

			return;
		}

		$suffix = substr(md5(uniqid("", true)), 0, 8);
		$table = "";
		$dev_id = 0;

		try {
			[$module_id, , $table] = ai_geocoding_module($suffix);
			$dev_id = parity_seed_user(["level" => 2]);
			$user = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
			ai_geocoding_stub(["latitude" => "41.8781", "longitude" => "-87.6298"]);
			$svc = new AutoModuleService();
			$created = parity_ai_geocoding_create($svc, $module_id, $user, [
				"title" => "Head Office",
				"street" => "233 S Wacker Dr",
				"city" => "Chicago",
			]);

			T::ok($created["id"] > 0, "the entry was created with coordinates");

			ai_geocoding_stub(false, "REQUEST_DENIED");
			$updated = parity_ai_geocoding_update($svc, $module_id, $user, (int)$created["id"], [
				"title" => "Head Office (renamed)",
			]);

			T::equals($updated["error"], "", "the update staged");

			$row = SQL::fetch("SELECT title, latitude, longitude FROM `{$table}` WHERE id = ?", $created["id"]);

			T::equals($row["title"] ?? "", "Head Office (renamed)", "the unrelated column changed");
			T::equals($row["latitude"] ?? "", "41.8781", "the coordinates its address earned are kept");
			T::equals($row["longitude"] ?? "", "-87.6298", "…both of them");
			T::ok(
				strpos($updated["note"], "kept the coordinates it already had") !== false,
				"and the result says which of the two things happened (" . $updated["note"] . ")"
			);
		} finally {
			parity_delete_users($dev_id);
			ai_geocoding_teardown($suffix, $table);
		}
	}

	/**
	 * The same two update legs through REST, so both writers of the record are
	 * asserted rather than only the shared helper — and so the SPA's own editor gets
	 * back the "Geocoding: …" line the legacy admin printed on this save.
	 */
	function test_parity_rest_entry_update_applies_the_same_failed_geocode_rule() {
		if (!parity_db_available()) {

			return;
		}

		$suffix = substr(md5(uniqid("", true)), 0, 8);
		$table = "";
		$dev_id = 0;

		try {
			[$module_id, $form_id, $table] = ai_geocoding_module($suffix);
			$dev_id = parity_seed_user(["level" => 2]);
			ai_geocoding_stub(["latitude" => "41.8781", "longitude" => "-87.6298"]);
			$svc = new AutoModuleService();
			$create = $svc->create(parity_request($dev_id, 2, ["id" => $module_id], [
				"title" => "Head Office",
				"street" => "233 S Wacker Dr",
				"city" => "Chicago",
				"__publish__" => true,
			], ["form" => $form_id]));
			$body = (array)parity_data($create);
			$entry_id = (int)($body["id"] ?? 0);

			T::ok($entry_id > 0, "REST created the entry");
			T::ok(!isset($body["warnings"]), "a successful lookup carries no warning");

			// An edit that moves the address, with the geocoder refusing.
			ai_geocoding_stub(false, "REQUEST_DENIED");
			$moved = $svc->update(parity_request($dev_id, 2, [
				"id" => $module_id,
				"eid" => (string)$entry_id,
			], [
				"title" => "Head Office",
				"street" => "1 City Hall Sq",
				"city" => "Boston",
				"__publish__" => true,
			], ["form" => $form_id]));
			$moved_body = (array)parity_data($moved);
			$row = SQL::fetch("SELECT city, latitude, longitude FROM `{$table}` WHERE id = ?", $entry_id);

			T::equals($row["city"] ?? "", "Boston", "REST moved the address");
			T::equals($row["latitude"] ?? "x", "", "…and cleared the coordinates it no longer describes");
			T::ok(
				is_array($moved_body["warnings"] ?? null)
					&& strpos((string)($moved_body["warnings"][0] ?? ""), "REQUEST_DENIED") !== false,
				"…and told the SPA why, the way the legacy admin did"
			);

			// …and an edit that doesn't touch the address keeps them.
			SQL::query("UPDATE `{$table}` SET latitude = ?, longitude = ? WHERE id = ?", "42.3601", "-71.0589", $entry_id);
			$renamed = $svc->update(parity_request($dev_id, 2, [
				"id" => $module_id,
				"eid" => (string)$entry_id,
			], [
				"title" => "Head Office (renamed)",
				"street" => "1 City Hall Sq",
				"city" => "Boston",
				"__publish__" => true,
			], ["form" => $form_id]));
			$kept = SQL::fetch("SELECT latitude, longitude FROM `{$table}` WHERE id = ?", $entry_id);

			T::equals($kept["latitude"] ?? "", "42.3601", "an unrelated REST edit keeps the coordinates");
			T::ok(
				strpos((string)(((array)parity_data($renamed))["warnings"][0] ?? ""), "kept the coordinates") !== false,
				"…and says that is what happened rather than staying silent"
			);
		} finally {
			parity_delete_users($dev_id);
			ai_geocoding_teardown($suffix, $table);
		}
	}

	/**
	 * Audit #31 E1: the form's `pre` hook runs on both writers of an entry.
	 *
	 * Legacy's forms/process.php ran it before anything else: its output was merged
	 * over the submission, and the keys no form field produced were copied straight
	 * onto the entry. The remaster ran it only on embed-form submission, so a module
	 * whose `pre` hook computes a column the form doesn't show stored every entry —
	 * from the SPA or from the assistant — without it.
	 *
	 * The fixture hook does both things a `pre` hook is for: it rewrites a value the
	 * form carries (`title`, title-cased) and fills one it doesn't (`sort_key`).
	 */

	/** The fixture module's `pre` hook. Idempotent, so update legs can assert it too. */
	function parity_ai_pre_hook(array $input): array {
		$title = ucwords(strtolower(trim((string)($input["title"] ?? ""))));

		return ["title" => $title, "sort_key" => strtolower($title), "id" => 999999, "not_a_column" => "x"];
	}

	/** A `pre` hook that throws — developer code is allowed to be broken. */
	function parity_ai_pre_hook_throws(array $input): array {
		throw new RuntimeException("audit31 pre hook exploded");
	}

	/**
	 * A throwaway module whose one form has a `pre` hook and a column the form
	 * doesn't carry.
	 *
	 * @return array{0:string,1:string,2:string} [module id, form id, table]
	 */
	function parity_ai_pre_hook_module(string $suffix, string $hook): array {
		$table = "zz_audit31_{$suffix}";

		SQL::query("CREATE TABLE `{$table}` (`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT, "
			. "`title` VARCHAR(191) NOT NULL DEFAULT '', `sort_key` VARCHAR(191) NOT NULL DEFAULT '', "
			. "PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
		ColumnDomain::forget($table);

		$module_id = BigTreeJSONDB::insert("modules", [
			"name" => "ZZ Audit31 {$suffix}",
			"route" => "zz-audit31-{$suffix}",
			"class" => "",
			"gbp" => ["name" => "", "table" => "", "item_parser" => "", "other_table" => ""],
			"forms" => [[
				"id" => "form-{$suffix}",
				"title" => "Item",
				"table" => $table,
				"hooks" => ["edit" => "", "pre" => $hook, "post" => "", "publish" => ""],
				"fields" => [
					["id" => "title", "title" => "Title", "type" => "text", "column" => "title", "settings" => []],
				],
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

	function parity_ai_pre_hook_teardown(string $suffix, string $table): void {
		BigTreeJSONDB::delete("modules", "zz-audit31-{$suffix}", "route");

		try {
			if ($table !== "") {
				SQL::query("DELETE FROM bigtree_pending_changes WHERE `table` = ?", $table);
			}

			SQL::query("DROP TABLE IF EXISTS `{$table}`");
			SQL::query("DELETE FROM bigtree_module_view_cache WHERE view = ?", "view-{$suffix}");
		} catch (Throwable $e) {
			// disposable
		}

		ColumnDomain::forget($table);
	}

	/** The `changes` blob of a queued change, decoded. */
	function parity_ai_pre_hook_change(int $change_id): array {
		$changes = SQL::fetchSingle("SELECT changes FROM bigtree_pending_changes WHERE id = ?", $change_id);

		return is_string($changes) ? (array)json_decode($changes, true) : [];
	}

	function test_parity_rest_entry_writes_run_the_forms_pre_hook() {
		if (!parity_db_available()) {

			return;
		}

		$suffix = substr(md5(uniqid("", true)), 0, 8);
		$table = "";
		$dev_id = 0;

		try {
			[$module_id, $form_id, $table] = parity_ai_pre_hook_module($suffix, "parity_ai_pre_hook");
			$dev_id = parity_seed_user(["level" => 2]);
			$svc = new AutoModuleService();

			// Live create.
			$body = (array)parity_data($svc->create(parity_request($dev_id, 2, ["id" => $module_id], [
				"title" => "  the QUICK fox",
				"__publish__" => true,
			], ["form" => $form_id])));
			$entry_id = (int)($body["id"] ?? 0);
			$row = SQL::fetch("SELECT * FROM `{$table}` WHERE id = ?", $entry_id);

			T::ok($entry_id > 0 && $entry_id !== 999999, "REST created the entry under its own id, not the hook's");
			T::equals($row["title"] ?? "", "The Quick Fox", "REST stored the hook's rewrite of a form value");
			T::equals($row["sort_key"] ?? "", "the quick fox", "REST stored the column only the hook sets");

			// Live update.
			parity_data($svc->update(parity_request($dev_id, 2, ["id" => $module_id, "eid" => (string)$entry_id], [
				"title" => "a SLOW dog",
				"__publish__" => true,
			], ["form" => $form_id])));
			$row = SQL::fetch("SELECT * FROM `{$table}` WHERE id = ?", $entry_id);

			T::equals($row["sort_key"] ?? "", "a slow dog", "REST update re-ran the hook");

			// Draft create.
			$draft = (array)parity_data($svc->create(parity_request($dev_id, 2, ["id" => $module_id], [
				"title" => "draft ENTRY",
			], ["form" => $form_id])));
			$changes = parity_ai_pre_hook_change((int)($draft["pending_id"] ?? 0));

			T::equals($changes["title"] ?? "", "Draft Entry", "a REST draft carries the hook's rewrite");
			T::equals($changes["sort_key"] ?? "", "draft entry", "…and the hook-only column");
			T::ok(!array_key_exists("not_a_column", $changes), "…but never a key that is no column");
		} finally {
			parity_delete_users($dev_id);
			parity_ai_pre_hook_teardown($suffix, $table);
		}
	}

	function test_parity_ai_entry_writes_run_the_forms_pre_hook() {
		if (!parity_db_available()) {

			return;
		}

		$suffix = substr(md5(uniqid("", true)), 0, 8);
		$table = "";
		$dev_id = 0;

		try {
			[$module_id, $form_id, $table] = parity_ai_pre_hook_module($suffix, "parity_ai_pre_hook");
			$dev_id = parity_seed_user(["level" => 2]);
			$user = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
			$svc = new AutoModuleService();
			$schema = $svc->aiModuleSchema($module_id, $form_id, $user);

			T::equals(
				$schema["schema"]["form_hooks"] ?? null,
				["pre"],
				"get_module_schema names the hooks the form runs, never the callable"
			);

			// Live create.
			$created = parity_ai_geocoding_create($svc, $module_id, $user, ["title" => "the QUICK fox"]);
			$row = SQL::fetch("SELECT * FROM `{$table}` WHERE id = ?", $created["id"]);

			T::equals($created["error"], "", "the create staged");
			T::ok(
				strpos((string)($created["staged"]["preview"]["warning"] ?? ""), "pre-processing hook") !== false,
				"the card discloses the hook it will not run at staging"
			);
			T::ok(
				strpos((string)($created["staged"]["summary"] ?? ""), "pre-processing hook") !== false,
				"…and so does the summary"
			);
			T::equals($row["title"] ?? "", "The Quick Fox", "the assistant's entry carries the hook's rewrite");
			T::equals($row["sort_key"] ?? "", "the quick fox", "…and the column only the hook sets");
			T::ok(strpos($created["note"], "sort_key") !== false, "the approval result names the column the hook set");

			// Live update: the hook sees the whole row and its output is written.
			SQL::query("UPDATE `{$table}` SET sort_key = '' WHERE id = ?", $created["id"]);
			$updated = parity_ai_geocoding_update($svc, $module_id, $user, $created["id"], ["title" => "a SLOW dog"]);
			$row = SQL::fetch("SELECT * FROM `{$table}` WHERE id = ?", $created["id"]);

			T::equals($updated["error"], "", "the update staged");
			T::equals($row["title"] ?? "", "A Slow Dog", "the assistant's update carries the hook's rewrite");
			T::equals($row["sort_key"] ?? "", "a slow dog", "…and refills the hook-only column");

			// Draft create.
			$validated = $svc->aiValidateEntryCreate([
				"module_id" => $module_id,
				"data" => ["title" => "draft ENTRY"],
				"save_as_draft" => true,
			], $user);
			$result = $svc->aiCreateEntry($validated["payload"] ?? [], $user);
			$changes = parity_ai_pre_hook_change((int)($result["pending_id"] ?? 0));

			T::equals($result["mode"] ?? "", "pending", "the draft was queued");
			T::equals($changes["sort_key"] ?? "", "draft entry", "an assistant draft carries the hook-only column");
			T::ok(!array_key_exists("id", $changes), "…and never the hook's id");
		} finally {
			parity_delete_users($dev_id);
			parity_ai_pre_hook_teardown($suffix, $table);
		}
	}

	function test_parity_a_throwing_pre_hook_never_fails_the_save() {
		if (!parity_db_available()) {

			return;
		}

		$suffix = substr(md5(uniqid("", true)), 0, 8);
		$table = "";
		$dev_id = 0;
		$log_file = tempnam(sys_get_temp_dir(), "audit31");
		$previous_log = ini_set("error_log", $log_file);

		try {
			[$module_id, $form_id, $table] = parity_ai_pre_hook_module($suffix, "parity_ai_pre_hook_throws");
			$dev_id = parity_seed_user(["level" => 2]);
			$user = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
			$svc = new AutoModuleService();
			$body = (array)parity_data($svc->create(parity_request($dev_id, 2, ["id" => $module_id], [
				"title" => "rest",
				"__publish__" => true,
			], ["form" => $form_id])));
			$created = parity_ai_geocoding_create($svc, $module_id, $user, ["title" => "assistant"]);

			T::ok((int)($body["id"] ?? 0) > 0, "REST saved the entry despite the hook throwing");
			T::ok($created["id"] > 0, "…and so did the assistant");
			T::ok(
				strpos((string)file_get_contents($log_file), "audit31 pre hook exploded") !== false,
				"…and the failure was logged rather than lost"
			);
		} finally {
			ini_set("error_log", $previous_log === false ? "" : $previous_log);
			@unlink($log_file);
			parity_delete_users($dev_id);
			parity_ai_pre_hook_teardown($suffix, $table);
		}
	}
