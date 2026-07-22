<?php
	/**
	 * Audit #4 Phase 3 (B2, B4, B5): what the assistant can read back.
	 *
	 * get_page returned six fields against a write surface of fourteen, so "is this
	 * page hidden from search?" or "when does it expire?" could only be answered by
	 * proposing an edit and reading the staged diff's `from` values — and a page's
	 * tags were invisible entirely. The key-parity assertion below is the guard that
	 * keeps the two surfaces from drifting apart again.
	 */

	use BigTree\Services\AI\Tools\UpdatePageTool;
	use BigTree\Services\AI\ProposalStore;
	use BigTree\Services\DashboardService;
	use BigTree\Services\PageService;
	use BigTree\Services\SearchService;

	function test_parity_ai_get_page_reads_every_field_update_page_writes() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		$search = new SearchService();
		[$user_id, $user] = parity_surface_user();
		$tag = "zz ai read tag " . bin2hex(random_bytes(3));
		$tag_id = (int)SQL::insert("bigtree_tags", ["tag" => $tag, "route" => str_replace(" ", "-", $tag), "usage_count" => 0]);
		$page_id = 0;

		try {
			$created = parity_surface_create($svc, $user, [
				"parent" => 0,
				"nav_title" => "ZZ AI Read " . bin2hex(random_bytes(3)),
				"content" => parity_surface_default_content(),
				"meta_keywords" => "alpha, beta",
				"seo_invisible" => true,
				"in_nav" => false,
				"max_age" => 30,
				"expire_at" => "2030-01-01 00:00:00",
				"og_title" => "Read Me",
				"og_description" => "Read this description.",
				"tags" => [$tag],
			]);

			$page_id = (int)$created["page_id"];
			T::ok($page_id > 0, "the fixture page was created");

			$detail = $search->getPageDetail($page_id, $user);
			T::ok(!isset($detail["error"]), "the page reads back");

			$payload = $detail["payload"];

			// The guard: one list drives both surfaces, so neither can grow without
			// the other.
			foreach (PageService::AI_PAGE_FIELDS as $field) {
				T::ok(array_key_exists($field, $payload), "get_page exposes {$field}");
			}

			$properties = (new UpdatePageTool($svc, new ProposalStore()))
				->definition($user)["function"]["parameters"]["properties"];

			foreach (PageService::AI_PAGE_FIELDS as $field) {
				T::ok(isset($properties[$field]), "update_page accepts {$field}");
			}

			// Values, not just keys — a payload of empty strings would pass the above.
			T::equals($payload["seo_invisible"], true, "seo_invisible reads back as set");
			T::equals($payload["in_nav"], false, "in_nav reads back as set");
			T::equals($payload["meta_keywords"], "alpha, beta", "meta_keywords reads back");
			T::equals($payload["max_age"], 30, "max_age reads back");
			T::equals($payload["og_title"], "Read Me", "the Open Graph title reads back");
			T::equals($payload["expire_at"], "2030-01-01 00:00:00", "the expiry reads back");
			T::equals($payload["tags"], [$tag], "the page's current tags read back");
		} finally {
			SQL::query("DELETE FROM bigtree_tags_rel WHERE `table` = 'bigtree_pages' AND entry = ?", $page_id);
			SQL::query("DELETE FROM bigtree_open_graph WHERE `table` = 'bigtree_pages' AND entry = ?", $page_id);
			parity_delete_page($page_id);
			parity_delete_tags($tag_id);
			parity_delete_users($user_id);
		}
	}

	function test_parity_ai_update_page_sets_and_clears_max_age() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		[$user_id, $user] = parity_surface_user();
		$page_id = 0;

		try {
			$created = parity_surface_create($svc, $user, [
				"parent" => 0,
				"nav_title" => "ZZ AI MaxAge " . bin2hex(random_bytes(3)),
				"content" => parity_surface_default_content(),
			]);

			$page_id = (int)$created["page_id"];
			T::ok($page_id > 0, "the fixture page was created");

			$negative = $svc->aiValidatePageUpdate(["id" => $page_id, "max_age" => -5], $user);
			T::ok(isset($negative["error"]), "a negative max_age is refused");

			$validated = $svc->aiValidatePageUpdate(["id" => $page_id, "max_age" => 90], $user);
			T::ok(!empty($validated["ok"]), "setting max_age validates");
			T::equals($validated["preview"]["changes"]["max_age"]["to"], 90, "the diff shows the new age");

			$svc->aiUpdatePage($validated["payload"], $user);
			T::equals(
				(int)SQL::fetchSingle("SELECT max_age FROM bigtree_pages WHERE id = ?", $page_id),
				90,
				"max_age was written"
			);

			$cleared = $svc->aiValidatePageUpdate(["id" => $page_id, "max_age" => 0], $user);
			T::ok(!empty($cleared["ok"]), "clearing max_age validates");
			$svc->aiUpdatePage($cleared["payload"], $user);
			T::equals(
				(int)SQL::fetchSingle("SELECT max_age FROM bigtree_pages WHERE id = ?", $page_id),
				0,
				"max_age was cleared"
			);

			// A staged payload sits in the proposal store for up to 24h and is never
			// trusted on the way back out, however it was staged.
			$smuggled = $svc->aiUpdatePage([
				"id" => (string)$page_id,
				"changes" => ["max_age" => -5],
			], $user);

			T::equals($smuggled["mode"], "error", "a negative max_age in a stored payload is refused at approval");
			T::equals(
				(int)SQL::fetchSingle("SELECT max_age FROM bigtree_pages WHERE id = ?", $page_id),
				0,
				"the live row was not touched"
			);

			$blanked = $svc->aiUpdatePage([
				"id" => (string)$page_id,
				"changes" => ["nav_title" => ""],
			], $user);

			T::equals($blanked["mode"], "error", "a blanked nav_title is refused at approval too");
		} finally {
			parity_delete_page($page_id);
			parity_delete_users($user_id);
		}
	}

	function test_parity_ai_content_alerts_respect_view_permission() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		$dashboard = new DashboardService();
		[$owner_id, $owner] = parity_surface_user();
		$outsider_id = parity_seed_user(["level" => 0]);
		$outsider = (object)["id" => $outsider_id, "level" => 0, "permissions" => []];
		$page_id = 0;

		try {
			$created = parity_surface_create($svc, $owner, [
				"parent" => 0,
				"nav_title" => "ZZ AI Stale " . bin2hex(random_bytes(3)),
				"content" => parity_surface_default_content(),
				"max_age" => 1,
			]);

			$page_id = (int)$created["page_id"];
			T::ok($page_id > 0, "the fixture page was created");

			// Age it past its own threshold; updated_at is set on write.
			SQL::query("UPDATE bigtree_pages SET updated_at = DATE_SUB(NOW(), INTERVAL 10 DAY) WHERE id = ?", $page_id);

			$alerts = $dashboard->aiContentAlerts($owner, 25);
			$ids = array_column($alerts["stale"], "page_id");
			T::ok(in_array($page_id, $ids, true), "a page past its max_age is reported as stale");

			$row = $alerts["stale"][array_search($page_id, $ids, true)];
			T::equals($row["max_age_days"], 1, "the row carries the page's own threshold");
			T::ok($row["age_days"] >= 10, "the row carries how stale the page is");

			// An editor with no grant on the page must not see it at all.
			$blind = $dashboard->aiContentAlerts($outsider, 25);
			T::ok(
				!in_array($page_id, array_column($blind["stale"], "page_id"), true),
				"a user without view access doesn't see the page"
			);

			// A page with no max_age is never reported.
			SQL::query("UPDATE bigtree_pages SET max_age = 0 WHERE id = ?", $page_id);
			$none = $dashboard->aiContentAlerts($owner, 25);
			T::ok(
				!in_array($page_id, array_column($none["stale"], "page_id"), true),
				"clearing max_age stops the alert"
			);
		} finally {
			parity_delete_page($page_id);
			parity_delete_users($owner_id, $outsider_id);
		}
	}
