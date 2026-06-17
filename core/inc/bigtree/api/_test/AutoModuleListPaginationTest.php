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

	use BigTree\Services\AutoModuleService;
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

	/**
	 * Fast-path equivalence for a NON-gbp module: list() lets the database
	 * paginate (getSearchResults($view, $page, …)) instead of loading the full
	 * set. This must return byte-identical page slices and the same meta.pages
	 * as a full-load-then-slice would. We verify by comparing each DB-paginated
	 * page against array_slice over the full "all" set in the same sort order.
	 */
	function test_automodule_list_pagination_fast_path_non_gbp() {
		try {
			SQL::fetchSingle("SELECT view FROM bigtree_module_view_cache LIMIT 1");
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return;
		}

		$view_id = "ZZ_amlp_fp_" . uniqid();
		$per_page = 3;

		// per_page lives in the view setting so getSearchResults' page-mode
		// derivation matches list()'s — proving meta.pages and the slices agree.
		$view = [
			"id" => $view_id,
			"table" => "ZZ_amlp_table",
			"type" => "standard",
			"fields" => ["column1" => ["title" => "Label"]],
			"settings" => ["per_page" => $per_page],
		];

		// gbp DISABLED — userRowLevel resolves identically for every row, so the
		// fast path is taken and per-row filtering is a no-op.
		$module = [
			"id" => "ZZ_amlp_fp_module",
			"gbp" => ["enabled" => false],
		];

		$n = 10;

		try {
			for ($i = 1; $i <= $n; $i++) {
				_amlp_insert_cache_row($view_id, $i, "", "row-$i");
			}

			// The reference: the full ordered set as list()'s old code would load it.
			$all = BigTreeAutoModule::getSearchResults($view, "all", "", "id ASC", false);
			T::equals(count($all["results"]), $n, "fast-path reference: 'all' returns every cached row");

			$expected_pages = (int)ceil($n / $per_page); // ceil(10/3) = 4
			$seen = [];

			for ($p = 1; $p <= $expected_pages; $p++) {
				// This is the exact call list()'s fast path makes: page number,
				// not "all".
				$dbpage = BigTreeAutoModule::getSearchResults($view, $p, "", "id ASC", false);

				// meta.pages list() would report on the fast path = $results["pages"].
				T::equals((int)$dbpage["pages"], $expected_pages, "fast-path page $p reports pages = ceil(N/per_page)");

				// The DB-paginated slice must equal the full-set slice for that page.
				$expected_slice = array_slice($all["results"], ($p - 1) * $per_page, $per_page);
				$db_ids = array_map(fn($r) => (int)$r["id"], $dbpage["results"]);
				$expected_ids = array_map(fn($r) => (int)$r["id"], $expected_slice);
				T::equals($db_ids, $expected_ids, "fast-path page $p slice matches full-load-then-slice");

				foreach ($dbpage["results"] as $row) {
					$seen[] = (int)$row["id"];
				}
			}

			// No rows dropped: the union of every page is exactly the full set.
			T::equals(count($seen), count(array_unique($seen)), "fast path returns no row twice");
			sort($seen);
			T::equals($seen, range(1, $n), "fast path reaches every row across pages (none dropped)");
		} finally {
			SQL::query("DELETE FROM bigtree_module_view_cache WHERE view = ?", $view_id);
		}
	}

	/**
	 * Admin (level > 0) on a gbp-ENABLED, non-grouped module: userRowLevel
	 * short-circuits to "p" for every row, so the fast path is taken (gbp is
	 * enabled but the user is admin). The admin must see all rows, correctly
	 * DB-paginated, identical to a full-load-then-slice.
	 */
	function test_automodule_list_pagination_fast_path_admin_on_gbp() {
		try {
			SQL::fetchSingle("SELECT view FROM bigtree_module_view_cache LIMIT 1");
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return;
		}

		$view_id = "ZZ_amlp_adm_" . uniqid();
		$per_page = 4;

		$view = [
			"id" => $view_id,
			"table" => "ZZ_amlp_table",
			"type" => "standard",
			"fields" => ["column1" => ["title" => "Label"]],
			"settings" => ["per_page" => $per_page],
		];

		// gbp ENABLED — but an admin (level > 0) reaches userRowLevel's level>0
		// short-circuit, so list()'s branch condition ($gbp_enabled && level===0)
		// is false and the fast path runs.
		$module = [
			"id" => "ZZ_amlp_adm_module",
			"gbp" => ["enabled" => true, "group_field" => "gbp_field"],
		];

		$admin_user = (object)["id" => 1, "level" => 1, "permissions" => []];

		// Sanity: confirm the branch the fast path depends on. An admin sees every
		// row (userRowLevel never returns "n"), regardless of gbp grouping.
		$probe_row = ["id" => 1, "gbp_field" => "beta"];
		T::equals(
			PermissionService::userRowLevel($admin_user, $module, $probe_row),
			"p",
			"admin (level>0) resolves to 'p' on a gbp row — fast path is correct for admins"
		);

		$n = 9;

		try {
			// Mixed groups; an admin must still see every one of them.
			for ($i = 1; $i <= $n; $i++) {
				$group = ($i % 2 === 0) ? "beta" : "alpha";
				_amlp_insert_cache_row($view_id, $i, $group, "row-$i");
			}

			$all = BigTreeAutoModule::getSearchResults($view, "all", "", "id ASC", false);
			T::equals(count($all["results"]), $n, "admin reference: 'all' returns every cached row");

			$expected_pages = (int)ceil($n / $per_page); // ceil(9/4) = 3
			$seen = [];

			for ($p = 1; $p <= $expected_pages; $p++) {
				$dbpage = BigTreeAutoModule::getSearchResults($view, $p, "", "id ASC", false);
				T::equals((int)$dbpage["pages"], $expected_pages, "admin fast-path page $p reports pages = ceil(N/per_page)");

				$expected_slice = array_slice($all["results"], ($p - 1) * $per_page, $per_page);
				$db_ids = array_map(fn($r) => (int)$r["id"], $dbpage["results"]);
				$expected_ids = array_map(fn($r) => (int)$r["id"], $expected_slice);
				T::equals($db_ids, $expected_ids, "admin fast-path page $p slice matches full-load-then-slice");

				foreach ($dbpage["results"] as $row) {
					$seen[] = (int)$row["id"];
				}
			}

			T::equals(count($seen), count(array_unique($seen)), "admin fast path returns no row twice");
			sort($seen);
			T::equals($seen, range(1, $n), "admin sees every row across pages (gbp enabled, fast path)");
		} finally {
			SQL::query("DELETE FROM bigtree_module_view_cache WHERE view = ?", $view_id);
		}
	}

	/**
	 * AutoModuleService::list whitelists the client-supplied `sort` query param
	 * (plan 033) before forwarding it to BigTreeAutoModule::getSearchResults, which
	 * concatenates the sort field into ORDER BY unescaped. safeSort accepts only the
	 * view's own field keys plus the legacy specials "id"/"_status_" (and the
	 * "position desc, id asc" ordering verbatim), normalizing the direction to
	 * ASC/DESC; anything else falls back to "id DESC". These are pure unit
	 * assertions on the private helper via reflection (the pattern MtmValidationTest
	 * uses), so they run WITHOUT a database.
	 */
	function test_automodule_safe_sort_neutralizes_injection() {
		$svc = new AutoModuleService();
		$ref = new ReflectionMethod($svc, "safeSort");
		$ref->setAccessible(true);

		$view = ["fields" => ["title" => ["title" => "Title"], "column1" => ["title" => "Label"]]];

		// Old-format branch: payload starts with "column" (the legacy guard's hole)
		// but is not a declared field key, so it is rejected → "id DESC".
		T::equals(
			$ref->invoke($svc, "column1,(SELECT(SLEEP(9)))", $view),
			"id DESC",
			"old-format ORDER BY injection is neutralized to id DESC"
		);

		// Backtick branch: spaces allowed in the payload, still rejected → "id DESC".
		T::equals(
			$ref->invoke($svc, "`column1,(SELECT(SLEEP(9)))` ASC", $view),
			"id DESC",
			"backtick-format ORDER BY injection is neutralized to id DESC"
		);

		// A field-like token that is not a declared key also falls back.
		T::equals(
			$ref->invoke($svc, "nonexistent_col DESC", $view),
			"id DESC",
			"an undeclared sort field falls back to id DESC"
		);
	}

	function test_automodule_safe_sort_preserves_legitimate_sorts() {
		$svc = new AutoModuleService();
		$ref = new ReflectionMethod($svc, "safeSort");
		$ref->setAccessible(true);

		$view = ["fields" => ["title" => ["title" => "Title"]]];

		// A declared field key with an explicit direction passes through.
		T::equals($ref->invoke($svc, "title ASC", $view), "title ASC", "declared field + ASC is preserved");

		// Direction is normalized to upper-case.
		T::equals($ref->invoke($svc, "title asc", $view), "title ASC", "lower-case direction is normalized to ASC");

		// Anything that isn't ASC normalizes to DESC.
		T::equals($ref->invoke($svc, "title", $view), "title DESC", "missing direction defaults to DESC");

		// The legacy specials are accepted without being view-field keys.
		T::equals($ref->invoke($svc, "id DESC", $view), "id DESC", "id DESC passes through unchanged");
		T::equals($ref->invoke($svc, "_status_ ASC", $view), "_status_ ASC", "the _status_ special is accepted");

		// The legacy manual-ordering special string passes verbatim.
		T::equals(
			$ref->invoke($svc, "position desc, id asc", $view),
			"position desc, id asc",
			"the position desc, id asc ordering passes through verbatim"
		);

		// A view with no fields still accepts the specials and rejects everything else.
		T::equals($ref->invoke($svc, "title ASC", []), "id DESC", "a fieldless view rejects unknown fields");
		T::equals($ref->invoke($svc, "id ASC", []), "id ASC", "a fieldless view still accepts id");
	}
