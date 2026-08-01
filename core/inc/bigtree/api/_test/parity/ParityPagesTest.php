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

	// The assistant's create-page validation defaults a missing template to the
	// same one the "Add page" screen preselects (first non-routed template),
	// rather than staging an empty template — which is only valid for external
	// links the assistant can't create.
	function test_parity_pages_ai_validate_defaults_template() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$user = (object)["id" => $dev_id, "level" => 2, "permissions" => []];

		try {
			$templates = BigTreeJSONDB::getAll("templates", "position", "DESC");
			T::ok(!empty($templates), "site has templates to default to");

			$expected = "";

			foreach ($templates as $t) {
				if (empty($t["routed"])) {
					$expected = (string)$t["id"];

					break;
				}
			}

			if ($expected === "") {
				$expected = (string)$templates[0]["id"];
			}

			// Supply content for the default template's required field(s) so the
			// validation focuses on the template default, not the required check.
			// Only the fields the assistant can actually set: supplying a complex one
			// (an image, a callouts region) is a recoverable error in its own right.
			$schema_ref = new ReflectionMethod(PageService::class, "aiTemplateResourceSchema");
			$schema_ref->setAccessible(true);
			$content = [];

			foreach ($schema_ref->invoke($svc, $expected) as $resource_id => $resource) {
				$content[(string)$resource_id] = "Filled by parity test";
			}

			$res = $svc->aiValidatePageCreate([
				"parent" => 0,
				"nav_title" => "AI Default Template Page",
				"content" => $content,
			], $user);

			T::ok(!empty($res["ok"]), "validation ok without a template");
			T::equals($res["payload"]["template"], $expected, "payload defaults to preselected template");
			T::equals($res["preview"]["template"], $expected, "preview shows defaulted template");
			T::ok($res["payload"]["template"] !== "", "template is not left blank");
		} finally {
			parity_delete_users($dev_id);
		}
	}

	// A template with required content fields can't be staged empty: the assistant's
	// validation returns a recoverable error naming the missing fields (mirroring the
	// "Add page" screen's required check), and only stages once content is supplied —
	// which is then carried on the payload for the eventual write.
	function test_parity_pages_ai_validate_requires_template_content() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$user = (object)["id" => $dev_id, "level" => 2, "permissions" => []];

		try {
			// Find a template with at least one required simple field to exercise the
			// gate (the stock "content" template's page_header is required). Collect
			// *every* required id, not just the first — the gate reports all of them,
			// so supplying one of several would never satisfy it.
			$target = null;
			$required_ids = [];

			foreach (BigTreeJSONDB::getAll("templates", "position", "DESC") as $t) {
				$found = [];

				foreach (($t["resources"] ?? []) as $r) {
					$rules = (string)(($r["settings"]["validation"] ?? ""));

					if (strpos($rules, "required") !== false) {
						$found[] = (string)$r["id"];
					}
				}

				if ($found) {
					$target = (string)$t["id"];
					$required_ids = $found;

					break;
				}
			}

			if ($target === null) {
				T::ok(true, "no required-field template installed — enforcement path not exercised");

				return;
			}

			$required_id = $required_ids[0];
			$content = array_fill_keys($required_ids, "<p>Assistant-authored content</p>");

			// No content → recoverable error, nothing staged.
			$missing = $svc->aiValidatePageCreate([
				"parent" => 0,
				"nav_title" => "AI Needs Content Page",
				"template" => $target,
			], $user);

			T::ok(isset($missing["error"]), "missing required content is an error");
			T::ok(empty($missing["ok"]), "not staged when required content missing");
			T::ok(strpos((string)$missing["error"], "required") !== false, "error mentions required fields");

			// Content supplied → validates, and the content rides on the payload.
			$ok = $svc->aiValidatePageCreate([
				"parent" => 0,
				"nav_title" => "AI Needs Content Page",
				"template" => $target,
				"content" => $content,
			], $user);

			T::ok(!empty($ok["ok"]), "validates once required content is supplied");
			T::equals(
				$ok["payload"]["resources"][$required_id],
				"<p>Assistant-authored content</p>",
				"content carried on the payload for the write"
			);
			T::ok(!empty($ok["preview"]["fields"]), "preview lists the content fields");

			// Approving the proposal writes the content onto the live page (dev is a
			// publisher) — the whole point of collecting it up front.
			$created = $svc->aiCreatePage($ok["payload"], $user);
			$page_id = (int)($created["page_id"] ?? 0);

			try {
				T::equals($created["mode"], "published", "publisher writes live");
				T::ok($page_id > 0, "live page id returned");

				$row = SQL::fetch("SELECT template, resources FROM bigtree_pages WHERE id = ?", $page_id);
				T::equals($row["template"], $target, "row stored the template");

				$stored = json_decode((string)$row["resources"], true);
				T::equals(
					$stored[$required_id] ?? null,
					"<p>Assistant-authored content</p>",
					"content persisted to the page's resources"
				);
			} finally {
				parity_delete_page($page_id);
			}
		} finally {
			parity_delete_users($dev_id);
		}
	}

	/**
	 * Audit #14 A1/A2, end to end: a relative date is resolved by the server, disclosed
	 * on the card, and stored as the resolved value — with nothing but page columns
	 * reaching the row.
	 *
	 * The schedule snapshot the approval re-check needs rides on the payload, and the
	 * create payload is replayed wholesale onto the page row, so this also pins that it
	 * is stripped before the write (the failure that would otherwise be a stray column
	 * in an INSERT).
	 */
	function test_parity_pages_ai_resolves_a_relative_schedule_server_side() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new PageService();
		$dev_id = parity_seed_user(["level" => 2]);
		$user = (object)["id" => $dev_id, "level" => 2, "permissions" => []];
		$page_id = 0;

		try {
			$schema_ref = new ReflectionMethod(PageService::class, "aiTemplateResourceSchema");
			$schema_ref->setAccessible(true);
			$content = [];

			foreach ($schema_ref->invoke($svc, "content") as $resource_id => $resource) {
				$content[(string)$resource_id] = "Filled by parity test";
			}

			$staged = $svc->aiValidatePageCreate([
				"parent" => 0,
				"nav_title" => "ZZ Audit14 Scheduled",
				"route" => parity_unique_route("zz-audit14"),
				"template" => "content",
				"content" => $content,
				// The words a user would say, which the tool descriptions now ask for.
				"publish_at" => "next Monday",
			], $user);

			T::ok(!empty($staged["ok"]), "a relative publish date stages");

			$expected = date("Y-m-d H:i:s", (int)strtotime("next Monday"));
			T::equals($staged["payload"]["publish_at"], $expected, "the payload carries the server-resolved value");
			T::ok(
				strpos((string)$staged["preview"]["publish_at"], $expected) === 0,
				"the card shows the resolved date"
			);
			T::ok(
				strpos((string)$staged["preview"]["publish_at"], "next Monday") !== false,
				"and the words it was resolved from, so the approver knows who did the arithmetic"
			);
			T::ok(
				empty($staged["preview"]["warning"]) || strpos((string)$staged["preview"]["warning"], "already passed") === false,
				"a future date carries no past-schedule warning"
			);

			$created = $svc->aiCreatePage($staged["payload"], $user);
			$page_id = (int)($created["page_id"] ?? 0);

			try {
				T::equals((string)$created["mode"], "published", "the approval writes live");
				T::equals(
					(string)SQL::fetchSingle("SELECT publish_at FROM bigtree_pages WHERE id = ?", $page_id),
					$expected,
					"and the row holds the resolved datetime"
				);
			} finally {
				parity_delete_page($page_id);
			}

			// A window that has already gone by is disclosed, not refused: "take it down
			// now" is a real request, and the person approving is the one who has to know.
			$backdated = $svc->aiValidatePageCreate([
				"parent" => 0,
				"nav_title" => "ZZ Audit14 Expired",
				"route" => parity_unique_route("zz-audit14"),
				"template" => "content",
				"content" => $content,
				"publish_at" => "2020-01-01 00:00:00",
				"expire_at" => "2020-06-01 00:00:00",
			], $user);

			T::ok(!empty($backdated["ok"]), "a window entirely in the past still stages");
			T::ok(
				strpos((string)($backdated["preview"]["warning"] ?? ""), "already passed") !== false,
				"and the card says so"
			);
			// Already past at staging, so the approval must not refuse it — the user
			// approved it knowing.
			T::equals(
				\BigTree\Services\AI\TemporalContext::scheduleDrift(
					$backdated["payload"]["__schedule_snapshot__"],
					$backdated["payload"]
				),
				null,
				"a knowingly backdated window is not refused at approval"
			);

			// The snapshot rides on the payload and must not reach the queue row:
			// pendingChangeFields copies the whole payload into `changes`, which
			// get_pending_change reads back and the draft editor renders.
			$draft = $svc->aiValidatePageCreate([
				"parent" => 0,
				"nav_title" => "ZZ Audit14 Draft Schedule",
				"route" => parity_unique_route("zz-audit14"),
				"template" => "content",
				"content" => $content,
				"publish_at" => "next Monday",
				"save_as_draft" => true,
			], $user);

			T::ok(!empty($draft["ok"]), "a scheduled draft stages");
			$queued = $svc->aiCreatePage($draft["payload"], $user);
			$change_id = (int)($queued["pending_change_id"] ?? 0);

			try {
				T::equals((string)$queued["mode"], "pending", "the draft is queued rather than published");

				$changes = json_decode(
					(string)SQL::fetchSingle("SELECT changes FROM bigtree_pending_changes WHERE id = ?", $change_id),
					true
				);
				T::ok(
					!array_key_exists("__schedule_snapshot__", (array)$changes),
					"and the queue row carries no reserved payload key"
				);
				T::equals(
					(string)($changes["publish_at"] ?? ""),
					$expected,
					"while the resolved schedule itself is stored on the draft"
				);
			} finally {
				parity_delete_pending($change_id);
			}
		} finally {
			parity_delete_users($dev_id);
		}
	}
