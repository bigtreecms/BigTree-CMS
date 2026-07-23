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
