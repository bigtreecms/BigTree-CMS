<?php
	/**
	 * L1 parity: PageService create publish/draft, update, archive, delete (p0/pages.md).
	 */

	use BigTree\Services\PageService;
	use BigTree\Api\Exceptions\AuthorizationException;

	function test_parity_pages_create_publish_inserts_live_row() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$page_id = 0;
		$route = parity_unique_route();

		try {
			$res = $svc->create(parity_request($dev_id, 2, [], [
				"parent" => 0,
				"nav_title" => "QA Parity Page",
				"title" => "QA Parity Page Title",
				"route" => $route,
				"in_nav" => true,
				"template" => "content",
				"resources" => ["page_content" => "<p>Hello QA</p>"],
				"meta_description" => "QA meta",
				"publish" => true,
			]));
			T::equals($res->status, 201, "create publish returns 201");
			$data = parity_data($res);
			T::ok(empty($data["pending"]), "not a pending response");
			$page_id = (int)$data["id"];
			T::ok($page_id > 0, "live page id returned");

			$row = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $page_id);
			T::ok($row !== null, "row exists");
			// safeEncode may entity-encode; compare decoded-ish
			T::ok(stripos(html_entity_decode($row["nav_title"]), "QA Parity Page") !== false, "nav_title stored");
			T::equals($row["route"], $route, "route stored");
			T::equals((int)$row["parent"], 0, "parent root");
			T::equals($row["template"], "content", "template");
			T::equals((int)$row["last_edited_by"], $dev_id, "last_edited_by actor");
			T::ok($row["archived"] === "" || $row["archived"] === null, "not archived");
		} finally {
			parity_delete_page($page_id);
			parity_delete_users($dev_id);
		}
	}

	function test_parity_pages_create_as_editor_pending_only() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		// Editor with publisher grant nowhere; e on parent page 1 (About)
		$editor_id = parity_seed_user([
			"level" => 0,
			"permissions" => [
				"page" => ["1" => "e"],
				"module" => [],
				"resources" => [],
				"module_gbp" => [],
			],
		]);
		$pending_id = 0;
		$route = parity_unique_route("zz-ed");

		try {
			$res = $svc->create(parity_request(
				$editor_id,
				0,
				[],
				[
					"parent" => 1,
					"nav_title" => "Editor Draft Page",
					"title" => "Editor Draft",
					"route" => $route,
					"template" => "content",
					"publish" => true, // ignored without publisher rank
				],
				[],
				[
					"page" => ["1" => "e"],
					"module" => [],
					"resources" => [],
					"module_gbp" => [],
				]
			));
			T::equals($res->status, 201, "draft create returns 201");
			$data = parity_data($res);
			T::ok(!empty($data["pending"]), "pending flag set");
			$pending_id = (int)$data["pending_change_id"];
			T::ok($pending_id > 0, "pending_change_id present");

			$live = SQL::fetchSingle(
				"SELECT id FROM bigtree_pages WHERE route = ? AND parent = 1",
				$route
			);
			T::ok(!$live, "no live page for editor draft");

			$pending = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $pending_id);
			T::ok($pending !== null, "pending row exists");
			T::equals($pending["table"], "bigtree_pages", "pending table pages");
			T::equals($pending["type"], "NEW", "type NEW");
			T::equals((int)$pending["pending_page_parent"], 1, "pending parent");
			T::equals((int)$pending["user"], $editor_id, "pending user is editor");
		} finally {
			parity_delete_pending($pending_id);
			parity_delete_users($editor_id);
		}
	}

	function test_parity_pages_update_publisher_live() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$page_id = 0;
		$route = parity_unique_route();

		try {
			$created = parity_data($svc->create(parity_request($dev_id, 2, [], [
				"parent" => 0,
				"nav_title" => "Update Me",
				"title" => "Original Title",
				"route" => $route,
				"template" => "content",
				"publish" => true,
			])));
			$page_id = (int)$created["id"];

			$res = $svc->update(parity_request($dev_id, 2, ["id" => $page_id], [
				"title" => "Updated Title",
				"publish" => true,
			]));
			T::equals($res->status, 200, "update returns 200");

			$title = SQL::fetchSingle("SELECT title FROM bigtree_pages WHERE id = ?", $page_id);
			T::ok(stripos(html_entity_decode((string)$title), "Updated Title") !== false, "live title updated");
		} finally {
			parity_delete_page($page_id);
			parity_delete_users($dev_id);
		}
	}

	function test_parity_pages_update_editor_pending() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$editor_id = parity_seed_user([
			"level" => 0,
			"permissions" => ["page" => [], "module" => [], "resources" => [], "module_gbp" => []],
		]);
		$page_id = 0;
		$pending_id = 0;
		$route = parity_unique_route();

		try {
			// Create live page as dev, then grant editor e on that page via permissions map
			$created = parity_data($svc->create(parity_request($dev_id, 2, [], [
				"parent" => 0,
				"nav_title" => "Live For Edit",
				"title" => "Original",
				"route" => $route,
				"template" => "content",
				"publish" => true,
			])));
			$page_id = (int)$created["id"];

			$perms = [
				"page" => [(string)$page_id => "e"],
				"module" => [],
				"resources" => [],
				"module_gbp" => [],
			];
			SQL::update("bigtree_users", $editor_id, ["permissions" => json_encode($perms)]);

			$res = $svc->update(parity_request(
				$editor_id,
				0,
				["id" => $page_id],
				["title" => "Draft Title", "publish" => true],
				[],
				$perms
			));
			$data = parity_data($res);
			T::ok(!empty($data["pending"]), "editor update is pending");
			$pending_id = (int)$data["pending_change_id"];

			$live_title = SQL::fetchSingle("SELECT title FROM bigtree_pages WHERE id = ?", $page_id);
			T::ok(stripos(html_entity_decode((string)$live_title), "Original") !== false, "live title unchanged");
			T::ok(SQL::exists("bigtree_pending_changes", $pending_id), "pending change stored");
		} finally {
			parity_delete_pending($pending_id);
			parity_delete_page($page_id);
			parity_delete_users($editor_id, $dev_id);
		}
	}

	function test_parity_pages_archive_unarchive() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$page_id = 0;
		$route = parity_unique_route();

		try {
			$created = parity_data($svc->create(parity_request($dev_id, 2, [], [
				"parent" => 0,
				"nav_title" => "Archive Me",
				"route" => $route,
				"template" => "content",
				"publish" => true,
			])));
			$page_id = (int)$created["id"];

			$svc->archive(parity_request($dev_id, 2, ["id" => $page_id]));
			$archived = SQL::fetchSingle("SELECT archived FROM bigtree_pages WHERE id = ?", $page_id);
			T::equals($archived, "on", "archived flag on");

			$svc->unarchive(parity_request($dev_id, 2, ["id" => $page_id]));
			$archived = SQL::fetchSingle("SELECT archived FROM bigtree_pages WHERE id = ?", $page_id);
			T::ok($archived === "" || $archived === null, "archived cleared");
		} finally {
			parity_delete_page($page_id);
			parity_delete_users($dev_id);
		}
	}

	function test_parity_pages_delete() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$route = parity_unique_route();

		try {
			$created = parity_data($svc->create(parity_request($dev_id, 2, [], [
				"parent" => 0,
				"nav_title" => "Delete Me",
				"route" => $route,
				"template" => "content",
				"publish" => true,
			])));
			$page_id = (int)$created["id"];

			$res = $svc->delete(parity_request($dev_id, 2, ["id" => $page_id]));
			T::equals($res->status, 204, "delete 204");
			T::ok(!SQL::exists("bigtree_pages", $page_id), "page gone");
		} finally {
			parity_delete_users($dev_id);
		}
	}

	function test_parity_pages_access_denied_without_grant() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		$editor_id = parity_seed_user([
			"level" => 0,
			"permissions" => ["page" => [], "module" => [], "resources" => [], "module_gbp" => []],
		]);

		try {
			T::throws(
				function () use ($svc, $editor_id) {
					$svc->create(parity_request(
						$editor_id,
						0,
						[],
						[
							"parent" => 1,
							"nav_title" => "Nope",
							"template" => "content",
							"publish" => true,
						],
						[],
						["page" => [], "module" => [], "resources" => [], "module_gbp" => []]
					));
				},
				AuthorizationException::class,
				"no page grant → cannot create child"
			);
		} finally {
			parity_delete_users($editor_id);
		}
	}

	function test_parity_pages_route_collision_suffix() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$ids = [];
		$base = parity_unique_route("zz-col");

		try {
			$a = parity_data($svc->create(parity_request($dev_id, 2, [], [
				"parent" => 0,
				"nav_title" => "Collision A",
				"route" => $base,
				"template" => "content",
				"publish" => true,
			])));
			$ids[] = (int)$a["id"];

			$b = parity_data($svc->create(parity_request($dev_id, 2, [], [
				"parent" => 0,
				"nav_title" => "Collision B",
				"route" => $base,
				"template" => "content",
				"publish" => true,
			])));
			$ids[] = (int)$b["id"];

			T::equals($a["route"], $base, "first keeps base route");
			T::ok($b["route"] !== $base, "second gets unique suffix");
			T::ok(strpos($b["route"], $base) === 0, "suffix based on base");
		} finally {
			foreach ($ids as $id) {
				parity_delete_page($id);
			}
			parity_delete_users($dev_id);
		}
	}
