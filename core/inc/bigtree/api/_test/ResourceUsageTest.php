<?php
	/**
	 * ResourceService::usage batches its allocation lookups (issue #13 / plan 013).
	 *
	 * The pre-refactor code resolved each allocation row with its own per-row fetch
	 * (a page lookup, a setting lookup, or a module-item lookup), an N+1 for a
	 * heavily-reused asset. The refactor buckets allocations by destination kind and
	 * batch-fetches each bucket (live pages with one IN(...) query, live module
	 * entries with one IN(...) query per table, settings from the cached JSON DB),
	 * then rebuilds the usage list from the in-memory maps.
	 *
	 * This test proves "same result": for a resource allocated to two live pages, a
	 * setting, two entries of one module table, and one deleted (missing) target, the
	 * usage list has one entry per allocation IN THE ORIGINAL ORDER with the exact
	 * titles, locations, statuses, and the "Deleted …"/status:none fallback the
	 * per-row version produced.
	 *
	 * Seeds throwaway rows (a resource, pages, a throwaway module table, a JSON DB
	 * setting, allocations) and removes every one of them in a finally block. Skips
	 * on an unavailable DB like the sibling DB-seeding tests.
	 *
	 * Allocations are returned ORDER BY updated_at DESC, so the seed code stamps a
	 * strictly increasing updated_at per allocation and the assertions walk them in
	 * the reverse insertion order (newest first).
	 */

	use BigTree\Api\Request;
	use BigTree\Services\ResourceService;

	/** Insert a bigtree_pages row supplying every NOT NULL / no-default column. */
	function _resusage_insert_page(string $nav_title, string $title, string $archived = ""): int {
		$id = SQL::insert("bigtree_pages", [
			"parent" => 0,
			"nav_title" => $nav_title,
			"title" => $title,
			"trunk" => "",
			"in_nav" => "on",
			"route" => "resusage-" . uniqid(),
			"path" => "",
			"meta_keywords" => "",
			"meta_description" => "",
			"seo_invisible" => "",
			"resources" => "",
			"archived" => $archived,
			"archived_inherited" => "",
			"max_age" => 0,
			"last_edited_by" => 0,
			"ga_page_views" => 0,
			"created_at" => "NOW()",
			"updated_at" => "NOW()",
		]);

		return (int)$id;
	}

	/** Insert a throwaway resource row (FK target for allocations). */
	function _resusage_insert_resource(): int {
		$id = SQL::insert("bigtree_resources", [
			"file" => "resusage-" . uniqid() . ".png",
			"name" => "ZZ resusage fixture",
			"date" => "NOW()",
			"metadata" => "",
			"crops" => "",
			"thumbs" => "",
		]);

		return (int)$id;
	}

	/**
	 * Allocate $entry in $table to $resource with an explicit updated_at so the
	 * DESC ordering of the usage list is deterministic. Smaller $seconds_ago is
	 * newer, so it sorts EARLIER in the updated_at-DESC usage list.
	 */
	function _resusage_allocate(int $resource, string $table, string $entry, int $seconds_ago): void {
		SQL::query(
			"INSERT INTO bigtree_resource_allocation (`table`, entry, resource, updated_at) VALUES (?, ?, ?, DATE_SUB(NOW(), INTERVAL ? SECOND))",
			$table, $entry, $resource, $seconds_ago
		);
	}

	/** Build a minimal Request whose route id resolves usage() to $resource. */
	function _resusage_request(int $resource): Request {
		$req = new Request();
		$req->route_params = ["id" => (string)$resource];

		return $req;
	}

	function test_resource_usage_batches_and_matches_per_row() {
		try {
			SQL::fetchSingle("SELECT id FROM bigtree_resources LIMIT 1");
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return;
		}

		$ns = "ZZ_resusage_" . uniqid();
		$module_table = "zz_resusage_items_" . substr(md5($ns), 0, 12);

		$resource = null;
		$page_ids = [];
		$setting_id = $ns . "_setting";
		$deleted_page_id = 2000000123; // a page id that does not exist
		$setting_seeded = false;

		try {
			$resource = _resusage_insert_resource();

			// Two live pages: one normal, one archived.
			$page_one_nav = $ns . "_page_one";
			$p1 = _resusage_insert_page($page_one_nav, "Page One Title");
			$page_ids[] = $p1;
			$p2 = _resusage_insert_page("", "Page Two (no nav_title)", "on");
			$page_ids[] = $p2;

			// Make sure the "deleted" page id truly does not exist.
			T::ok(
				!SQL::exists("bigtree_pages", $deleted_page_id),
				"chosen deleted page id is genuinely absent"
			);

			// A JSON DB setting (where BigTreeAdmin::getSetting reads name/system).
			BigTreeJSONDB::insert("settings", [
				"id" => $setting_id,
				"name" => "Resusage Setting Name",
				"system" => "",
				"encrypted" => "",
				"locked" => "",
			]);
			$setting_seeded = true;

			// A throwaway module table with two live entries. With no matching JSON DB
			// view, resolveModuleForTable falls back to id=null/name=$table → link=null.
			SQL::query("CREATE TABLE `$module_table` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `title` varchar(255) DEFAULT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
			$m1 = (int)SQL::insert($module_table, ["title" => "Module Entry Alpha"]);
			$m2 = (int)SQL::insert($module_table, ["title" => "Module Entry Beta"]);

			// Allocate everything with strictly-increasing age so the updated_at-DESC
			// usage list comes back in this exact order (newest first):
			//   page_one, page_two, setting, module_alpha, module_beta, deleted_page
			$order = 0;
			_resusage_allocate($resource, "bigtree_pages", (string)$p1, 10 + $order++);
			_resusage_allocate($resource, "bigtree_pages", (string)$p2, 10 + $order++);
			_resusage_allocate($resource, "bigtree_settings", $setting_id, 10 + $order++);
			_resusage_allocate($resource, $module_table, (string)$m1, 10 + $order++);
			_resusage_allocate($resource, $module_table, (string)$m2, 10 + $order++);
			_resusage_allocate($resource, "bigtree_pages", (string)$deleted_page_id, 10 + $order++);

			$service = new ResourceService();
			$response = $service->usage(_resusage_request($resource));
			$usages = $response->body["data"];

			// One entry per allocation, in the DESC (insertion) order seeded above.
			T::equals(count($usages), 6, "one usage entry per allocation");

			// 0 — live page with a nav_title.
			T::equals($usages[0]["location"], "Pages", "page one location");
			T::equals($usages[0]["title"], $page_one_nav, "page one uses nav_title (preferred over title)");
			T::equals($usages[0]["status"], "published", "page one is published");
			T::equals($usages[0]["link"], ["kind" => "page", "entry" => (string)$p1], "page one link descriptor");

			// 1 — archived live page with an empty nav_title (falls back to title).
			T::equals($usages[1]["location"], "Pages", "page two location");
			T::equals($usages[1]["title"], "Page Two (no nav_title)", "page two falls back to title");
			T::equals($usages[1]["status"], "archived", "page two archived flag → archived status");
			T::equals($usages[1]["link"], ["kind" => "page", "entry" => (string)$p2], "page two link descriptor");

			// 2 — setting (no pending/archived lifecycle; non-system → setting link).
			T::equals($usages[2]["location"], "Settings", "setting location");
			T::equals($usages[2]["title"], "Resusage Setting Name", "setting uses name");
			T::equals($usages[2]["status"], "published", "setting is published");
			T::equals($usages[2]["link"], ["kind" => "setting", "entry" => $setting_id], "setting link descriptor");

			// 3 — first module entry (no JSON DB view → location is the table, link null).
			T::equals($usages[3]["location"], $module_table, "module alpha location is table fallback");
			T::equals($usages[3]["title"], "Module Entry Alpha", "module alpha title");
			T::equals($usages[3]["status"], "published", "module alpha published");
			T::ok($usages[3]["link"] === null, "module alpha has no link (no resolvable module)");

			// 4 — second module entry from the SAME batched table.
			T::equals($usages[4]["location"], $module_table, "module beta location is table fallback");
			T::equals($usages[4]["title"], "Module Entry Beta", "module beta title");
			T::equals($usages[4]["status"], "published", "module beta published");
			T::ok($usages[4]["link"] === null, "module beta has no link");

			// 5 — deleted (missing) page → "Deleted …" fallback with status none.
			T::equals($usages[5]["location"], "Pages", "deleted page location still Pages");
			T::equals($usages[5]["title"], "Deleted Page (" . $deleted_page_id . ")", "deleted page fallback title");
			T::equals($usages[5]["status"], "none", "deleted page status is none");
			T::ok($usages[5]["link"] === null, "deleted page has no link");
		} finally {
			if ($resource !== null) {
				// CASCADE removes the allocation rows when the resource is deleted, but
				// be explicit so a failed FK cascade still cleans up.
				SQL::query("DELETE FROM bigtree_resource_allocation WHERE resource = ?", $resource);
				SQL::query("DELETE FROM bigtree_resources WHERE id = ?", $resource);
			}

			foreach ($page_ids as $pid) {
				SQL::query("DELETE FROM bigtree_pages WHERE id = ?", $pid);
			}

			if ($setting_seeded) {
				BigTreeJSONDB::delete("settings", $setting_id);
			}

			SQL::query("DROP TABLE IF EXISTS `$module_table`");
		}
	}
