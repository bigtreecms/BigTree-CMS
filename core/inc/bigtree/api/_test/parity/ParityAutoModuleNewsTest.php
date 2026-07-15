<?php
	/**
	 * L1 parity: AutoModuleService News fixture CRUD (p0/auto-modules-news.md).
	 */

	use BigTree\Services\AutoModuleService;
	use BigTree\Services\PendingChangeService;
	use BigTree\Api\Exceptions\AuthorizationException;

	function test_parity_news_create_publish() {
		if (!parity_db_available()) {
			return;
		}

		if (!SQL::tableExists("timber_news")) {
			echo "  (skipped — timber_news table missing)\n";

			return;
		}

		if (!BigTreeJSONDB::exists("modules", parity_news_module_id())) {
			echo "  (skipped — News module missing from json-db)\n";

			return;
		}

		$svc = new AutoModuleService();
		$dev_id = parity_seed_user(["level" => 2]);
		$entry_id = 0;
		$suffix = bin2hex(random_bytes(3));

		try {
			$res = $svc->create(parity_request(
				$dev_id,
				2,
				["id" => parity_news_module_id()],
				[
					"title" => "QA News Item " . $suffix,
					"route" => "qa-news-" . $suffix,
					"date" => "2026-07-14",
					"blurb" => "Short blurb",
					"content" => "<p>Full story content</p>",
					"__publish__" => true,
				]
			));
			T::equals($res->status, 201, "create publish 201");
			$data = parity_data($res);
			T::ok(empty($data["pending"]), "not pending");
			$entry_id = (int)($data["id"] ?? 0);
			T::ok($entry_id > 0, "live entry id");

			$row = SQL::fetch("SELECT * FROM timber_news WHERE id = ?", $entry_id);
			T::ok($row !== null, "row in timber_news");
			T::ok(strpos((string)$row["title"], "QA News Item") !== false, "title stored");
			T::equals($row["route"], "qa-news-" . $suffix, "route stored");
			T::ok(strpos((string)$row["date"], "2026-07-14") === 0, "date stored");
			T::ok(strpos((string)$row["content"], "Full story") !== false, "content stored");
		} finally {
			parity_delete_news_entries($entry_id);
			parity_delete_users($dev_id);
		}
	}

	function test_parity_news_create_editor_pending() {
		if (!parity_db_available()) {
			return;
		}

		if (!SQL::tableExists("timber_news") || !BigTreeJSONDB::exists("modules", parity_news_module_id())) {
			echo "  (skipped — News fixture unavailable)\n";

			return;
		}

		$svc = new AutoModuleService();
		$module_id = parity_news_module_id();
		$editor_id = parity_seed_user([
			"level" => 0,
			"permissions" => [
				"page" => [],
				"module" => [$module_id => "e"],
				"resources" => [],
				"module_gbp" => [],
			],
		]);
		$pending_id = 0;
		$suffix = bin2hex(random_bytes(3));

		try {
			$res = $svc->create(parity_request(
				$editor_id,
				0,
				["id" => $module_id],
				[
					"title" => "Editor News " . $suffix,
					"route" => "ed-news-" . $suffix,
					"date" => "2026-07-14",
					"blurb" => "Draft blurb",
					"content" => "<p>Draft</p>",
					"__publish__" => true,
				],
				[],
				[
					"page" => [],
					"module" => [$module_id => "e"],
					"resources" => [],
					"module_gbp" => [],
				]
			));
			$data = parity_data($res);
			T::ok(!empty($data["pending"]), "pending response");
			$pending_id = (int)$data["pending_id"];
			T::ok($pending_id > 0, "pending_id");

			$live = SQL::fetchSingle(
				"SELECT id FROM timber_news WHERE route = ?",
				"ed-news-" . $suffix
			);
			T::ok(!$live, "no live row for editor draft");

			$pc = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $pending_id);
			T::ok($pc !== null, "pending change row");
			T::equals($pc["table"], "timber_news", "pending table timber_news");
			T::equals((string)$pc["module"], $module_id, "module id on pending");
		} finally {
			parity_delete_pending($pending_id);
			parity_delete_users($editor_id);
		}
	}

	function test_parity_news_update_and_delete() {
		if (!parity_db_available()) {
			return;
		}

		if (!SQL::tableExists("timber_news") || !BigTreeJSONDB::exists("modules", parity_news_module_id())) {
			echo "  (skipped — News fixture unavailable)\n";

			return;
		}

		$svc = new AutoModuleService();
		$dev_id = parity_seed_user(["level" => 2]);
		$module_id = parity_news_module_id();
		$entry_id = 0;
		$suffix = bin2hex(random_bytes(3));

		try {
			$created = parity_data($svc->create(parity_request($dev_id, 2, ["id" => $module_id], [
				"title" => "News Upd " . $suffix,
				"route" => "news-upd-" . $suffix,
				"date" => "2026-07-01",
				"content" => "<p>v1</p>",
				"__publish__" => true,
			])));
			$entry_id = (int)$created["id"];

			$svc->update(parity_request($dev_id, 2, [
				"id" => $module_id,
				"eid" => (string)$entry_id,
			], [
				"title" => "News Upd " . $suffix . " Edited",
				"route" => "news-upd-" . $suffix,
				"date" => "2026-07-01",
				"content" => "<p>v2</p>",
				"__publish__" => true,
			]));

			$title = SQL::fetchSingle("SELECT title FROM timber_news WHERE id = ?", $entry_id);
			T::ok(strpos((string)$title, "Edited") !== false, "title updated on live row");

			$content = SQL::fetchSingle("SELECT content FROM timber_news WHERE id = ?", $entry_id);
			T::ok(strpos((string)$content, "v2") !== false, "content updated");

			$del = $svc->delete(parity_request($dev_id, 2, [
				"id" => $module_id,
				"eid" => (string)$entry_id,
			]));
			T::equals($del->status, 204, "delete 204");
			T::ok(!SQL::exists("timber_news", $entry_id), "entry deleted");
			$entry_id = 0;
		} finally {
			parity_delete_news_entries($entry_id);
			parity_delete_users($dev_id);
		}
	}

	function test_parity_news_approve_pending_entry() {
		if (!parity_db_available()) {
			return;
		}

		if (!SQL::tableExists("timber_news") || !BigTreeJSONDB::exists("modules", parity_news_module_id())) {
			echo "  (skipped — News fixture unavailable)\n";

			return;
		}

		$svc = new AutoModuleService();
		$pendingSvc = new PendingChangeService();
		$module_id = parity_news_module_id();
		$editor_id = parity_seed_user([
			"level" => 0,
			"permissions" => [
				"page" => [],
				"module" => [$module_id => "e"],
				"resources" => [],
				"module_gbp" => [],
			],
		]);
		$publisher_id = parity_seed_user(["level" => 1]);
		$pending_id = 0;
		$entry_id = 0;
		$suffix = bin2hex(random_bytes(3));
		$route = "news-appr-" . $suffix;

		try {
			$created = parity_data($svc->create(parity_request(
				$editor_id,
				0,
				["id" => $module_id],
				[
					"title" => "Approve News " . $suffix,
					"route" => $route,
					"date" => "2026-07-14",
					"content" => "<p>Pending story</p>",
					"__publish__" => true,
				],
				[],
				[
					"page" => [],
					"module" => [$module_id => "e"],
					"resources" => [],
					"module_gbp" => [],
				]
			)));
			$pending_id = (int)$created["pending_id"];

			$pendingSvc->approve(parity_request($publisher_id, 1, ["id" => $pending_id]));
			$pending_id = 0;

			$entry_id = (int)SQL::fetchSingle("SELECT id FROM timber_news WHERE route = ?", $route);
			T::ok($entry_id > 0, "live news row after approve");
		} finally {
			parity_delete_pending($pending_id);
			parity_delete_news_entries($entry_id);
			parity_delete_users($editor_id, $publisher_id);
		}
	}

	function test_parity_news_denied_without_module_access() {
		if (!parity_db_available()) {
			return;
		}

		if (!SQL::tableExists("timber_news") || !BigTreeJSONDB::exists("modules", parity_news_module_id())) {
			echo "  (skipped — News fixture unavailable)\n";

			return;
		}

		$svc = new AutoModuleService();
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
						["id" => parity_news_module_id()],
						[
							"title" => "Denied",
							"content" => "<p>x</p>",
							"__publish__" => true,
						],
						[],
						["page" => [], "module" => [], "resources" => [], "module_gbp" => []]
					));
				},
				AuthorizationException::class,
				"no module grant → create denied"
			);
		} finally {
			parity_delete_users($editor_id);
		}
	}
