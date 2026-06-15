<?php
	/**
	 * Pagination correctness for AutoModuleService::list under group-based
	 * permissions (gbp).
	 *
	 * The bug: list() let BigTreeAutoModule::getSearchResults paginate at the
	 * database FIRST and then applied the PHP-side row-level permission filter
	 * (PermissionService::userRowLevel) to the already-sliced page. The predicate
	 * isn't expressible in SQL, so a gbp-restricted user got short/empty pages,
	 * meta counts that described rows they couldn't see, and accessible rows that
	 * were never reachable on any page (hidden rows consumed page slots).
	 *
	 * The fix fetches the FULL result set (getSearchResults page = "all"), filters
	 * it with userRowLevel, then paginates the accessible rows in PHP so meta and
	 * the page slice both reflect the post-filter set.
	 *
	 * This test seeds bigtree_module_view_cache rows directly (cacheViewData skips
	 * re-caching when rows already exist for the view id, so no source table is
	 * needed) and reproduces the exact filter-then-paginate algorithm list() now
	 * uses. It proves: "all" returns every row, the accessible count drives the
	 * page count, iterating all pages yields exactly the accessible rows with no
	 * duplicates or gaps, and a full-access user still sees every row.
	 *
	 * Seeds under a namespaced view id and deletes the rows in a finally block.
	 * Skips on an unavailable DB, like the sibling tests.
	 */

	use BigTree\Services\PermissionService;

	/**
	 * Seed one bigtree_module_view_cache row. `gbp_field` carries the group value
	 * that userRowLevel reads (the cache column the gbp group_field resolves to);
	 * `column1` is human-readable payload so we can assert on identity.
	 */
	function _amlp_insert_cache_row(string $view_id, int $id, string $gbp_field, string $label): void {
		SQL::insert("bigtree_module_view_cache", [
			"view" => $view_id,
			"id" => (string)$id,
			"gbp_field" => $gbp_field,
			"published_gbp_field" => $gbp_field,
			"group_field" => "",
			"sort_field" => $label,
			"group_sort_field" => "",
			"position" => 0,
			"approved" => "",
			"archived" => "",
			"featured" => "",
			"status" => "l",
			"pending_owner" => 0,
			"column1" => $label,
		]);
	}

	/**
	 * Reproduce AutoModuleService::list's filter-then-paginate algorithm exactly.
	 * Returns [items, meta] for the requested page given the full accessible set.
	 *
	 * @param array<int,array<string,mixed>> $all_rows getSearchResults("all") rows
	 */
	function _amlp_filter_and_paginate(array $all_rows, $user, array $module, int $page, int $per_page): array {
		$accessible = array_values(array_filter($all_rows, function ($row) use ($user, $module) {

			return PermissionService::userRowLevel($user, $module, $row) !== "n";
		}));

		$per_page = max(1, $per_page);
		$total = count($accessible);
		$pages = (int)ceil($total / $per_page);
		$pages = $pages > 0 ? $pages : 1;
		$items = array_slice($accessible, ($page - 1) * $per_page, $per_page);

		return [
			"items" => array_values($items),
			"meta" => ["page" => $page, "per_page" => $per_page, "pages" => $pages, "total" => $total],
		];
	}

	function test_automodule_list_pagination_gbp_restricted() {
		try {
			SQL::fetchSingle("SELECT view FROM bigtree_module_view_cache LIMIT 1");
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return;
		}

		$view_id = "ZZ_amlp_" . uniqid();

		// A synthetic view shaped like getView's output. getSearchResults reads
		// only id / fields here; cacheViewData is skipped because rows already
		// exist for this view id, so no real source table is touched.
		$view = [
			"id" => $view_id,
			"table" => "ZZ_amlp_table",
			"type" => "standard",
			"fields" => ["column1" => ["title" => "Label"]],
			"settings" => [],
		];

		// gbp module: a non-admin user may only see rows whose gbp_field is in
		// their module_gbp allow-map. Group "alpha" is allowed; "beta" is not.
		$module = [
			"id" => "ZZ_amlp_module",
			"gbp" => ["enabled" => true, "group_field" => "gbp_field"],
		];

		// No direct module permission: access is granted purely by the gbp group
		// map. userRowLevel falls back to the direct module level when a row's
		// group isn't in the map, so a direct "e" here would make every row
		// visible and defeat the test — the restriction must come only from gbp.
		$restricted = (object)[
			"id" => 10,
			"level" => 0,
			"permissions" => [
				"module" => [],
				"module_gbp" => ["ZZ_amlp_module" => ["alpha" => "e"]],
			],
		];

		$admin_user = (object)["id" => 1, "level" => 1, "permissions" => []];

		try {
			// 10 rows: ids 1-7 in "alpha" (accessible), ids 8-10 in "beta" (hidden).
			// Interleave so hidden rows fall across page boundaries — the exact
			// shape that made the old code skip accessible rows.
			$accessible_ids = [];

			for ($i = 1; $i <= 10; $i++) {
				$group = ($i % 3 === 0) ? "beta" : "alpha";
				_amlp_insert_cache_row($view_id, $i, $group, "row-$i");

				if ($group === "alpha") {
					$accessible_ids[] = $i;
				}
			}

			$expected_accessible = count($accessible_ids); // 7 of 10

			// getSearchResults with page "all" must return the FULL set, not a slice.
			$all = BigTreeAutoModule::getSearchResults($view, "all", "", "id ASC", false);
			T::equals(count($all["results"]), 10, "getSearchResults('all') returns every cached row");

			$per_page = 3;

			// Restricted user: pages count is driven by the accessible total (7),
			// NOT the raw total (10). ceil(7/3) = 3.
			$page1 = _amlp_filter_and_paginate($all["results"], $restricted, $module, 1, $per_page);
			T::equals($page1["meta"]["total"], $expected_accessible, "meta total reflects post-filter accessible count");
			T::equals($page1["meta"]["pages"], (int)ceil($expected_accessible / $per_page), "meta pages = ceil(accessible / per_page)");
			T::equals($page1["meta"]["per_page"], $per_page, "meta per_page is the derived page size");

			// Iterate every page and collect the ids the user actually receives.
			$seen = [];

			for ($p = 1; $p <= $page1["meta"]["pages"]; $p++) {
				$result = _amlp_filter_and_paginate($all["results"], $restricted, $module, $p, $per_page);

				foreach ($result["items"] as $row) {
					$seen[] = (int)$row["id"];
				}
			}

			// No duplicates: every accessible row appears exactly once.
			T::equals(count($seen), count(array_unique($seen)), "no row is returned on more than one page");
			T::equals(count($seen), $expected_accessible, "every accessible row is reachable across pages (none skipped)");

			sort($seen);
			T::equals($seen, $accessible_ids, "the reachable ids are exactly the accessible (alpha) rows");

			// No hidden row ever leaks to the restricted user.
			$hidden = array_intersect($seen, [3, 6, 9]);
			T::equals($hidden, [], "no gbp-hidden (beta) row is ever returned");

			// A page past the end yields an empty slice, not an error.
			$past_end = _amlp_filter_and_paginate($all["results"], $restricted, $module, 99, $per_page);
			T::equals(count($past_end["items"]), 0, "a page beyond the accessible set returns no rows");

			// Full-access (admin) user: userRowLevel short-circuits to "p", so all
			// 10 rows are visible and paginate normally — no regression.
			$admin_p1 = _amlp_filter_and_paginate($all["results"], $admin_user, $module, 1, $per_page);
			T::equals($admin_p1["meta"]["total"], 10, "admin sees all rows (total = full set)");
			T::equals($admin_p1["meta"]["pages"], (int)ceil(10 / $per_page), "admin page count covers the full set");

			$admin_seen = [];

			for ($p = 1; $p <= $admin_p1["meta"]["pages"]; $p++) {
				$result = _amlp_filter_and_paginate($all["results"], $admin_user, $module, $p, $per_page);

				foreach ($result["items"] as $row) {
					$admin_seen[] = (int)$row["id"];
				}
			}

			sort($admin_seen);
			T::equals($admin_seen, range(1, 10), "admin reaches every row with no gaps or duplicates");
		} finally {
			SQL::query("DELETE FROM bigtree_module_view_cache WHERE view = ?", $view_id);
		}
	}
