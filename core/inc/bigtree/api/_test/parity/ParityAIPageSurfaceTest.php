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
	use BigTree\Services\SearchService;

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

	/**
	 * A3: update_page treated `route` as plain text.
	 *
	 * The value went straight to uniqueRoute(), which uniquifies but does not
	 * sanitize, so "About Us!" was stored complete with its space and punctuation.
	 * Create derives routes through urlify; an edit has to normalize the same way,
	 * and the proposal card has to show what will actually be stored.
	 */
	function test_parity_ai_update_page_urlifies_a_supplied_route() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		[$user_id, $user] = parity_surface_user();
		$page_id = 0;

		try {
			$result = parity_surface_create($svc, $user, [
				"parent" => 0,
				"nav_title" => "AI Route " . bin2hex(random_bytes(3)),
				"content" => parity_surface_default_content(),
			]);
			$page_id = $result["page_id"];
			T::ok($page_id > 0, "fixture page created");

			$validated = $svc->aiValidatePageUpdate(["id" => $page_id, "route" => "About Us!"], $user);

			T::ok(!empty($validated["ok"]), "the route change validates");
			T::equals($validated["preview"]["changes"]["route"]["to"], "about-us", "the diff shows the normalized route");

			$svc->aiUpdatePage($validated["payload"], $user);

			$stored = (string)SQL::fetchSingle("SELECT route FROM bigtree_pages WHERE id = ?", $page_id);
			T::ok(strpos($stored, "about-us") === 0, "the stored route is urlified (got \"{$stored}\")");
			T::ok(strpos($stored, " ") === false, "no whitespace survives into the route");
			T::ok(strpos($stored, "!") === false, "no punctuation survives into the route");

			// A route made only of characters urlify strips leaves nothing to store.
			$empty = $svc->aiValidatePageUpdate(["id" => $page_id, "route" => "!!!"], $user);
			T::ok(isset($empty["error"]), "a route that urlifies to nothing is refused");
		} finally {
			parity_delete_page($page_id);
			parity_delete_users($user_id);
		}
	}

	/**
	 * A3: nav_title is what the nav, the breadcrumb and the pending-change card all
	 * render, and nothing falls back to it — "" was a legitimate-looking diff that
	 * left a live page labelled by nothing at all.
	 */
	function test_parity_ai_update_page_refuses_an_empty_nav_title() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		[$user_id, $user] = parity_surface_user();
		$page_id = 0;

		try {
			$result = parity_surface_create($svc, $user, [
				"parent" => 0,
				"nav_title" => "AI Nav Title " . bin2hex(random_bytes(3)),
				"content" => parity_surface_default_content(),
			]);
			$page_id = $result["page_id"];
			T::ok($page_id > 0, "fixture page created");

			foreach (["", "   "] as $blank) {
				$validated = $svc->aiValidatePageUpdate(["id" => $page_id, "nav_title" => $blank], $user);
				T::ok(isset($validated["error"]), "an empty nav_title is refused");
			}

			$renamed = $svc->aiValidatePageUpdate(["id" => $page_id, "nav_title" => "zz Renamed"], $user);
			T::ok(!empty($renamed["ok"]), "a real nav_title still validates");
		} finally {
			parity_delete_page($page_id);
			parity_delete_users($user_id);
		}
	}

	/**
	 * B1: page tools can address an unpublished NEW draft by "p{change_id}".
	 *
	 * An editor's own create_page always lands in the pending queue, and the page
	 * tools took integer live ids only — so "actually, change the header on that
	 * draft" was a dead end, the exact scenario that motivated draft addressing for
	 * module entries in the first place. The edit must amend the queued change in
	 * place and write nothing live.
	 */
	function test_parity_ai_can_amend_its_own_page_draft() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		$editor_id = parity_seed_user(["level" => 0, "permissions" => ["page" => [0 => "e"]]]);
		$editor = (object)["id" => $editor_id, "level" => 0, "permissions" => ["page" => [0 => "e"]]];
		[$publisher_id, $publisher] = parity_surface_user();
		$change_id = 0;
		$live_page_id = 0;

		try {
			$created = parity_surface_create($svc, $editor, [
				"parent" => 0,
				"nav_title" => "AI Draft Page " . bin2hex(random_bytes(3)),
				"content" => parity_surface_default_content(),
			]);

			$change_id = (int)($created["created"]["pending_change_id"] ?? 0);

			if ($change_id < 1) {
				echo "  (skipped — editor's create did not land as a draft)\n";

				return;
			}

			$draft_id = "p" . $change_id;

			$validated = $svc->aiValidatePageUpdate([
				"id" => $draft_id,
				"nav_title" => "zz Draft Renamed",
			], $editor);

			T::ok(!empty($validated["ok"]), "the draft edit validates");
			T::ok(!empty($validated["preview"]["is_draft"]), "the preview says it's a draft");
			T::ok(
				strpos((string)$validated["summary"], "draft") !== false,
				"the summary says the draft is being updated, not published"
			);

			$result = $svc->aiUpdatePage($validated["payload"], $editor);

			T::equals($result["mode"], "pending", "approving amends the draft rather than publishing");
			T::equals((int)$result["pending_change_id"], $change_id, "it amends the same queued change");

			$stored = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $change_id);
			$changes = json_decode((string)$stored["changes"], true);
			T::equals($changes["nav_title"], "zz Draft Renamed", "the draft's nav_title was amended");
			T::ok(isset($changes["resources"]), "the draft's untouched content survived the amend");

			// Nothing may have been written live by any of this.
			$live = (int)SQL::fetchSingle(
				"SELECT COUNT(*) FROM bigtree_pages WHERE nav_title = ?", "zz Draft Renamed"
			);
			T::equals($live, 0, "no live page was created or written");

			// The read side has to address the draft the same way the write side does:
			// being able to edit an id that get_page reports as nonexistent is the
			// asymmetry draft addressing exists to remove.
			$detail = (new SearchService())->getPageDetail($draft_id, $editor);

			T::ok(!isset($detail["error"]), "get_page can read the draft back");
			T::ok(!empty($detail["payload"]["is_draft"]), "the payload marks it as a draft");
			T::equals($detail["payload"]["id"], $draft_id, "it reads back under the same p-prefixed id");
			T::equals(
				(int)$detail["payload"]["pending_change_id"], $change_id, "it names the queued change"
			);
			T::equals(
				$detail["payload"]["nav_title"], "zz Draft Renamed", "it reflects the amended nav_title"
			);
			T::ok(!isset($detail["artifact"]), "a draft resolves no artifact — there's no live page to link to");

			// A live page still reads exactly as it did before drafts were addressable.
			$live_page_id = parity_surface_create($svc, $publisher, [
				"parent" => 0,
				"nav_title" => "zz AI Live Read " . bin2hex(random_bytes(3)),
				"content" => parity_surface_default_content(),
			])["page_id"];

			T::ok($live_page_id > 0, "a live page was created to read back");

			$live_detail = (new SearchService())->getPageDetail($live_page_id, $publisher);

			T::ok(!isset($live_detail["error"]), "a live page still reads by numeric id");
			T::equals((int)$live_detail["payload"]["id"], $live_page_id, "under its numeric id");
			T::ok(empty($live_detail["payload"]["is_draft"]), "and is not marked a draft");
			T::ok(isset($live_detail["artifact"]), "and still carries its navigable artifact");

			// A draft that doesn't exist is a recoverable error, not a crash — on both
			// the read and the write side.
			$missing = $svc->aiValidatePageUpdate(["id" => "p99999999", "nav_title" => "x"], $editor);
			T::ok(isset($missing["error"]), "an unknown draft id is a recoverable error");
			T::ok(
				isset((new SearchService())->getPageDetail("p99999999", $editor)["error"]),
				"and get_page reports it too rather than crashing"
			);

			foreach (["px", "12x", "p"] as $bad) {
				$rejected = $svc->aiValidatePageUpdate(["id" => $bad, "nav_title" => "x"], $editor);
				T::ok(isset($rejected["error"]), "\"{$bad}\" is refused as a page id");

				$read = (new SearchService())->getPageDetail($bad, $editor);
				T::ok(isset($read["error"]), "\"{$bad}\" is refused by get_page too");
			}
		} finally {
			parity_delete_pending($change_id);
			parity_delete_page($live_page_id);
			parity_delete_users($editor_id, $publisher_id);
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
