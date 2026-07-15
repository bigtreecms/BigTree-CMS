<?php
	/**
	 * L1 parity: PendingChangeService approve/reject (p0/pending-changes.md).
	 */

	use BigTree\Services\PageService;
	use BigTree\Services\PendingChangeService;
	use BigTree\Api\Exceptions\AuthorizationException;

	function test_parity_pending_approve_new_page_creates_live() {
		if (!parity_db_available()) {
			return;
		}

		$pages = new PageService();
		$pending = new PendingChangeService();
		$editor_id = parity_seed_user([
			"level" => 0,
			"permissions" => [
				"page" => ["1" => "e"],
				"module" => [],
				"resources" => [],
				"module_gbp" => [],
			],
		]);
		$publisher_id = parity_seed_user(["level" => 1]);
		$pending_id = 0;
		$page_id = 0;
		$route = parity_unique_route("zz-pend");

		try {
			$created = parity_data($pages->create(parity_request(
				$editor_id,
				0,
				[],
				[
					"parent" => 1,
					"nav_title" => "Pending Approve Page",
					"title" => "Pending Approve Title",
					"route" => $route,
					"template" => "content",
					"publish" => true,
				],
				[],
				[
					"page" => ["1" => "e"],
					"module" => [],
					"resources" => [],
					"module_gbp" => [],
				]
			)));
			$pending_id = (int)$created["pending_change_id"];
			T::ok($pending_id > 0, "pending created by editor");

			$res = $pending->approve(parity_request($publisher_id, 1, ["id" => $pending_id]));
			T::equals($res->status, 204, "approve returns 204");
			T::ok(!SQL::exists("bigtree_pending_changes", $pending_id), "pending row removed");
			$pending_id = 0;

			$page_id = (int)SQL::fetchSingle(
				"SELECT id FROM bigtree_pages WHERE route = ? AND parent = 1",
				$route
			);
			T::ok($page_id > 0, "live page created on approve");
			$nav = SQL::fetchSingle("SELECT nav_title FROM bigtree_pages WHERE id = ?", $page_id);
			T::ok(stripos(html_entity_decode((string)$nav), "Pending Approve Page") !== false, "nav_title applied");
		} finally {
			parity_delete_pending($pending_id);
			parity_delete_page($page_id);
			parity_delete_users($editor_id, $publisher_id);
		}
	}

	function test_parity_pending_approve_page_edit_applies_changes() {
		if (!parity_db_available()) {
			return;
		}

		$pages = new PageService();
		$pending = new PendingChangeService();
		$dev_id = parity_seed_user(["level" => 2]);
		$editor_id = parity_seed_user(["level" => 0]);
		$page_id = 0;
		$pending_id = 0;
		$route = parity_unique_route("zz-pedit");

		try {
			$created = parity_data($pages->create(parity_request($dev_id, 2, [], [
				"parent" => 0,
				"nav_title" => "Edit Pending Target",
				"title" => "Original Live Title",
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

			$draft = parity_data($pages->update(parity_request(
				$editor_id,
				0,
				["id" => $page_id],
				["title" => "Approved Draft Title", "publish" => true],
				[],
				$perms
			)));
			$pending_id = (int)$draft["pending_change_id"];

			$pending->approve(parity_request($dev_id, 2, ["id" => $pending_id]));
			$pending_id = 0;

			$title = SQL::fetchSingle("SELECT title FROM bigtree_pages WHERE id = ?", $page_id);
			T::ok(
				stripos(html_entity_decode((string)$title), "Approved Draft Title") !== false,
				"live title updated after approve"
			);
		} finally {
			parity_delete_pending($pending_id);
			parity_delete_page($page_id);
			parity_delete_users($editor_id, $dev_id);
		}
	}

	function test_parity_pending_reject_discards_without_touching_live() {
		if (!parity_db_available()) {
			return;
		}

		$pages = new PageService();
		$pending = new PendingChangeService();
		$dev_id = parity_seed_user(["level" => 2]);
		$editor_id = parity_seed_user(["level" => 0]);
		$page_id = 0;
		$pending_id = 0;
		$route = parity_unique_route("zz-rej");

		try {
			$created = parity_data($pages->create(parity_request($dev_id, 2, [], [
				"parent" => 0,
				"nav_title" => "Reject Target",
				"title" => "Keep This Title",
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

			$draft = parity_data($pages->update(parity_request(
				$editor_id,
				0,
				["id" => $page_id],
				["title" => "Should Be Discarded", "publish" => true],
				[],
				$perms
			)));
			$pending_id = (int)$draft["pending_change_id"];

			$res = $pending->reject(parity_request($dev_id, 2, ["id" => $pending_id]));
			T::equals($res->status, 204, "reject returns 204");
			T::ok(!SQL::exists("bigtree_pending_changes", $pending_id), "pending gone");
			$pending_id = 0;

			$title = SQL::fetchSingle("SELECT title FROM bigtree_pages WHERE id = ?", $page_id);
			T::ok(
				stripos(html_entity_decode((string)$title), "Keep This Title") !== false,
				"live title unchanged after reject"
			);
		} finally {
			parity_delete_pending($pending_id);
			parity_delete_page($page_id);
			parity_delete_users($editor_id, $dev_id);
		}
	}

	function test_parity_pending_editor_cannot_approve() {
		if (!parity_db_available()) {
			return;
		}

		$pages = new PageService();
		$pending = new PendingChangeService();
		$editor_id = parity_seed_user([
			"level" => 0,
			"permissions" => [
				"page" => ["1" => "e"],
				"module" => [],
				"resources" => [],
				"module_gbp" => [],
			],
		]);
		$other_editor = parity_seed_user([
			"level" => 0,
			"permissions" => [
				"page" => ["1" => "e"],
				"module" => [],
				"resources" => [],
				"module_gbp" => [],
			],
		]);
		$pending_id = 0;
		$route = parity_unique_route("zz-noap");

		try {
			$created = parity_data($pages->create(parity_request(
				$editor_id,
				0,
				[],
				[
					"parent" => 1,
					"nav_title" => "No Approve",
					"route" => $route,
					"template" => "content",
				],
				[],
				[
					"page" => ["1" => "e"],
					"module" => [],
					"resources" => [],
					"module_gbp" => [],
				]
			)));
			$pending_id = (int)$created["pending_change_id"];

			T::throws(
				function () use ($pending, $other_editor, $pending_id) {
					$pending->approve(parity_request(
						$other_editor,
						0,
						["id" => $pending_id],
						[],
						[],
						[
							"page" => ["1" => "e"],
							"module" => [],
							"resources" => [],
							"module_gbp" => [],
						]
					));
				},
				AuthorizationException::class,
				"editor without p cannot approve"
			);
		} finally {
			parity_delete_pending($pending_id);
			// ensure no accidental live page
			$accidental = (int)SQL::fetchSingle(
				"SELECT id FROM bigtree_pages WHERE route = ? AND parent = 1",
				$route
			);
			parity_delete_page($accidental);
			parity_delete_users($editor_id, $other_editor);
		}
	}
