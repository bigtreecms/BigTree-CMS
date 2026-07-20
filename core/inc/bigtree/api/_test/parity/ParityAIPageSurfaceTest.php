<?php
	/**
	 * Phase 4 (B1): page-surface parity — scheduling on update, tags at create,
	 * external-link pages, and the scalar Open Graph subset.
	 *
	 * create_page could schedule a page at birth but update_page couldn't change or
	 * clear the schedule; "create a page and tag it X" cost two proposals and the
	 * second couldn't even be staged until the page existed; external links and OG
	 * had no coverage at all. These drive the real write path for each.
	 */

	use BigTree\Services\PageService;

	/** Stage and approve a page create, returning the new page id. */
	function parity_surface_create(PageService $svc, $user, array $args): array {
		$validated = $svc->aiValidatePageCreate($args, $user);

		if (empty($validated["ok"])) {

			return ["validated" => $validated, "page_id" => 0];
		}

		$created = $svc->aiCreatePage($validated["payload"], $user);

		return [
			"validated" => $validated,
			"created" => $created,
			"page_id" => (int)($created["page_id"] ?? 0),
		];
	}

	function parity_surface_user(): array {
		$id = parity_seed_user(["level" => 2]);

		return [$id, (object)["id" => $id, "level" => 2, "permissions" => []]];
	}

	/**
	 * Content satisfying the required simple fields of the template create_page would
	 * pick by default, so these fixtures clear the required-field gate and exercise
	 * the surface actually under test.
	 */
	function parity_surface_default_content(): array {
		$ref = new ReflectionMethod(PageService::class, "aiDefaultTemplate");
		$ref->setAccessible(true);
		$template = BigTreeJSONDB::get("templates", (string)$ref->invoke(new PageService()));
		$content = [];

		foreach (($template["resources"] ?? []) as $resource) {
			if (strpos((string)($resource["settings"]["validation"] ?? ""), "required") !== false) {
				$content[(string)$resource["id"]] = "<p>Fixture content</p>";
			}
		}

		return $content;
	}

	function test_parity_ai_update_page_sets_and_clears_a_schedule() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		[$user_id, $user] = parity_surface_user();
		$page_id = 0;

		try {
			$result = parity_surface_create($svc, $user, [
				"parent" => 0,
				"nav_title" => "AI Schedule " . bin2hex(random_bytes(3)),
				"content" => parity_surface_default_content(),
			]);
			$page_id = $result["page_id"];
			T::ok($page_id > 0, "fixture page created");

			// Set a window.
			$validated = $svc->aiValidatePageUpdate([
				"id" => $page_id,
				"publish_at" => "2030-08-01",
				"expire_at" => "2030-09-01",
			], $user);
			T::ok(!empty($validated["ok"]), "scheduling an existing page validates");

			$svc->aiUpdatePage($validated["payload"], $user);
			$row = SQL::fetch("SELECT publish_at, expire_at FROM bigtree_pages WHERE id = ?", $page_id);
			T::equals(substr((string)$row["publish_at"], 0, 10), "2030-08-01", "publish_at stored");
			T::equals(substr((string)$row["expire_at"], 0, 10), "2030-09-01", "expire_at stored");

			// Clearing is the half that was impossible before.
			$cleared = $svc->aiValidatePageUpdate(["id" => $page_id, "publish_at" => "", "expire_at" => ""], $user);
			T::ok(!empty($cleared["ok"]), "clearing the schedule validates");

			$svc->aiUpdatePage($cleared["payload"], $user);
			$row = SQL::fetch("SELECT publish_at, expire_at FROM bigtree_pages WHERE id = ?", $page_id);
			T::equals($row["publish_at"], null, "publish_at cleared to null, not a zero date");
			T::equals($row["expire_at"], null, "expire_at cleared to null");
		} finally {
			parity_delete_page($page_id);
			parity_delete_users($user_id);
		}
	}

	function test_parity_ai_update_page_rejects_an_inverted_schedule() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		[$user_id, $user] = parity_surface_user();
		$page_id = 0;

		try {
			$page_id = parity_surface_create($svc, $user, [
				"parent" => 0,
				"nav_title" => "AI Schedule Order " . bin2hex(random_bytes(3)),
				"content" => parity_surface_default_content(),
				"publish_at" => "2030-08-01",
				"expire_at" => "2030-09-01",
			])["page_id"];
			T::ok($page_id > 0, "fixture page created with a window");

			// Only publish_at is supplied, but it must still be checked against the
			// expire_at already on the page.
			$bad = $svc->aiValidatePageUpdate(["id" => $page_id, "publish_at" => "2030-10-01"], $user);
			T::ok(isset($bad["error"]), "moving publish_at past the stored expire_at is refused");

			$unparseable = $svc->aiValidatePageUpdate(["id" => $page_id, "publish_at" => "sometime soon"], $user);
			T::ok(isset($unparseable["error"]), "an unparseable date is refused, not stored as a zero date");
		} finally {
			parity_delete_page($page_id);
			parity_delete_users($user_id);
		}
	}

	function test_parity_ai_create_page_attaches_tags_in_one_proposal() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		[$user_id, $user] = parity_surface_user();
		$page_id = 0;
		$tag_name = "zz ai parity " . bin2hex(random_bytes(3));
		$tag_ids = [];

		try {
			$result = parity_surface_create($svc, $user, [
				"parent" => 0,
				"nav_title" => "AI Tagged Page " . bin2hex(random_bytes(3)),
				"content" => parity_surface_default_content(),
				"tags" => [$tag_name],
			]);
			$page_id = $result["page_id"];
			T::ok($page_id > 0, "page created");
			T::equals($result["validated"]["preview"]["new_tags"], [$tag_name], "the preview flags the tag as new");

			$tag_id = (int)SQL::fetchSingle("SELECT id FROM bigtree_tags WHERE tag = ?", $tag_name);
			T::ok($tag_id > 0, "the new tag was created at approval");
			$tag_ids[] = $tag_id;

			$linked = SQL::fetchSingle(
				"SELECT id FROM bigtree_tags_rel WHERE `table` = 'bigtree_pages' AND entry = ? AND tag = ?",
				(string)$page_id,
				$tag_id
			);
			T::ok((int)$linked > 0, "the tag is attached to the new page");
		} finally {
			parity_delete_page($page_id);
			parity_delete_tags(...$tag_ids);
			parity_delete_users($user_id);
		}
	}

	function test_parity_ai_create_page_refuses_new_tags_for_non_admins() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		$editor_id = parity_seed_user(["level" => 0]);
		$editor = (object)["id" => $editor_id, "level" => 0, "permissions" => ["page" => [0 => "e"]]];

		try {
			$validated = $svc->aiValidatePageCreate([
				"parent" => 0,
				"nav_title" => "AI Editor Tagged " . bin2hex(random_bytes(3)),
				"content" => parity_surface_default_content(),
				"tags" => ["zz nonexistent " . bin2hex(random_bytes(3))],
			], $editor);

			T::ok(isset($validated["denied"]), "coining a tag stays administrator-only at create");
			T::ok(strpos((string)$validated["denied"], "administrators") !== false, "the refusal says why");
		} finally {
			parity_delete_users($editor_id);
		}
	}

	function test_parity_ai_creates_an_external_link_page() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		[$user_id, $user] = parity_surface_user();
		$page_id = 0;

		try {
			$result = parity_surface_create($svc, $user, [
				"parent" => 0,
				"nav_title" => "AI Careers Link " . bin2hex(random_bytes(3)),
				"external" => "https://example.com/careers",
				"new_window" => true,
			]);
			$page_id = $result["page_id"];
			T::ok($page_id > 0, "external link page created");

			$row = SQL::fetch("SELECT template, `external`, new_window FROM bigtree_pages WHERE id = ?", $page_id);
			T::equals($row["external"], "https://example.com/careers", "the URL is stored");
			T::equals($row["template"], "", "an external link gets no template — the one legitimate blank-template case");
			T::ok($row["new_window"] !== "", "new_window is set");
		} finally {
			parity_delete_page($page_id);
			parity_delete_users($user_id);
		}
	}

	function test_parity_ai_external_link_rules_are_enforced() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		[$user_id, $user] = parity_surface_user();

		try {
			$both = $svc->aiValidatePageCreate([
				"parent" => 0,
				"nav_title" => "AI Bad Link " . bin2hex(random_bytes(3)),
				"external" => "https://example.com/",
				"template" => "content",
			], $user);
			T::ok(isset($both["error"]), "a page can't be both templated and an external link");

			// An external link has no template, so it has no content fields — supplied
			// content would be silently dropped rather than stored.
			$with_content = $svc->aiValidatePageCreate([
				"parent" => 0,
				"nav_title" => "AI Link With Content " . bin2hex(random_bytes(3)),
				"external" => "https://example.com/",
				"content" => ["page_content" => "<p>Body</p>"],
			], $user);
			T::ok(isset($with_content["error"]), "content on an external link is refused, not silently dropped");

			foreach (["javascript:alert(1)", "example.com", "data:text/html,x"] as $bad_url) {
				$bad = $svc->aiValidatePageCreate([
					"parent" => 0,
					"nav_title" => "AI Bad Link " . bin2hex(random_bytes(3)),
					"external" => $bad_url,
				], $user);
				T::ok(isset($bad["error"]), "\"{$bad_url}\" is refused as an external URL");
			}
		} finally {
			parity_delete_users($user_id);
		}
	}

	function test_parity_ai_open_graph_round_trips_and_preserves_the_image() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		[$user_id, $user] = parity_surface_user();
		$page_id = 0;

		try {
			$page_id = parity_surface_create($svc, $user, [
				"parent" => 0,
				"nav_title" => "AI OG Page " . bin2hex(random_bytes(3)),
				"content" => parity_surface_default_content(),
				"og_title" => "Shared title",
				"og_description" => "Shared description",
			])["page_id"];
			T::ok($page_id > 0, "page created with OG fields");

			$og = SQL::fetch("SELECT title, description, image FROM bigtree_open_graph WHERE `table` = 'bigtree_pages' AND entry = ?", $page_id);
			T::equals($og["title"], "Shared title", "og title stored");
			T::equals($og["description"], "Shared description", "og description stored");

			// Someone picks an image in the admin; the assistant then edits only the
			// title. syncOpenGraph replaces the whole row, so the image must survive.
			SQL::query(
				"UPDATE bigtree_open_graph SET image = ? WHERE `table` = 'bigtree_pages' AND entry = ?",
				"files/hero.jpg",
				$page_id
			);

			$validated = $svc->aiValidatePageUpdate(["id" => $page_id, "og_title" => "Rewritten title"], $user);
			T::ok(!empty($validated["ok"]), "editing only the og title validates");

			$svc->aiUpdatePage($validated["payload"], $user);
			$og = SQL::fetch("SELECT title, description, image FROM bigtree_open_graph WHERE `table` = 'bigtree_pages' AND entry = ?", $page_id);
			T::equals($og["title"], "Rewritten title", "og title updated");
			T::equals($og["description"], "Shared description", "untouched og description survived");
			T::equals($og["image"], "files/hero.jpg", "the image chosen in the admin survived");
		} finally {
			parity_delete_page($page_id);
			parity_delete_users($user_id);
		}
	}
