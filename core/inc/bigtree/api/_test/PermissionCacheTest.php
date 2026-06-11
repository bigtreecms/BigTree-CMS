<?php
	/**
	 * Memoization of the page-tree walk in PermissionService::userPageLevel.
	 *
	 * The page list resolves access for every sibling in a folder, and every
	 * sibling walks the same ancestor chain to the root. PermissionService caches
	 * the id → parent lookup (self::$page_parent_cache) so the identical parent
	 * rows are not re-fetched once per sibling.
	 *
	 * These tests prove "same result, fewer queries":
	 *   - inherited permissions resolve identically to the pre-cache behavior,
	 *   - the private cache populates the shared ancestors after one resolve and a
	 *     sibling resolve does not grow the cache for those ancestors,
	 *   - an orphan page (parent row missing) still resolves to "n".
	 *
	 * Seeds a throwaway page chain in bigtree_pages under a namespaced nav_title
	 * and deletes every seeded row in a finally block. Skips on an unavailable DB.
	 */

	use BigTree\Services\PermissionService;

	/**
	 * Insert a bigtree_pages row supplying every NOT NULL / no-default column.
	 * Returns the new row id.
	 */
	function _permcache_insert_page(int $parent, string $nav_title): int {
		$id = SQL::insert("bigtree_pages", [
			"parent" => $parent,
			"nav_title" => $nav_title,
			"title" => $nav_title,
			"trunk" => "",
			"in_nav" => "on",
			"route" => "permcache-" . uniqid(),
			"path" => "",
			"meta_keywords" => "",
			"meta_description" => "",
			"seo_invisible" => "",
			"resources" => "",
			"archived" => "",
			"archived_inherited" => "",
			"max_age" => 0,
			"last_edited_by" => 0,
			"ga_page_views" => 0,
			"created_at" => "NOW()",
			"updated_at" => "NOW()",
		]);

		return (int)$id;
	}

	/** Read the private static cache via reflection. */
	function _permcache_cache(): array {
		$prop = new ReflectionProperty(PermissionService::class, "page_parent_cache");
		$prop->setAccessible(true);

		return $prop->getValue();
	}

	/** Reset the private static cache so each test starts clean. */
	function _permcache_reset(): void {
		$prop = new ReflectionProperty(PermissionService::class, "page_parent_cache");
		$prop->setAccessible(true);
		$prop->setValue(null, []);
	}

	function test_permission_cache_inherited_results_unchanged() {
		try {
			SQL::fetchSingle("SELECT id FROM bigtree_pages LIMIT 1");
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return;
		}

		$ns = "ZZ_permcache_" . uniqid();
		$ids = [];

		try {
			// root(0) → A → B → C, plus three leaf siblings under C.
			$a = _permcache_insert_page(0, $ns . "_A");
			$ids[] = $a;
			$b = _permcache_insert_page($a, $ns . "_B");
			$ids[] = $b;
			$c = _permcache_insert_page($b, $ns . "_C");
			$ids[] = $c;

			$leaf1 = _permcache_insert_page($c, $ns . "_leaf1");
			$ids[] = $leaf1;
			$leaf2 = _permcache_insert_page($c, $ns . "_leaf2");
			$ids[] = $leaf2;
			$leaf3 = _permcache_insert_page($c, $ns . "_leaf3");
			$ids[] = $leaf3;

			// Non-admin editor: explicit "e" at A, explicit "n" at B's subtree via leaf3.
			// Children of A inherit "e"; leaf3 has an explicit "n" override.
			$user = (object)["id" => 1, "level" => 0, "permissions" => ["page" => [
				$a => "e",
				$leaf3 => "n",
			]]];

			_permcache_reset();

			// leaf1 / leaf2 inherit "e" by walking leaf → C → B → A (explicit "e").
			T::equals(PermissionService::userPageLevel($user, $leaf1), "e", "leaf1 inherits editor from ancestor A");
			T::equals(PermissionService::userPageLevel($user, $leaf2), "e", "sibling leaf2 resolves identically (inherited e)");

			// Explicit "n" override on a leaf wins over inherited "e".
			T::equals(PermissionService::userPageLevel($user, $leaf3), "n", "explicit n on leaf3 overrides inherited e");

			// Intermediate nodes resolve consistently.
			T::equals(PermissionService::userPageLevel($user, $c), "e", "C inherits editor from A");
			T::equals(PermissionService::userPageLevel($user, $b), "e", "B inherits editor from A");
			T::equals(PermissionService::userPageLevel($user, $a), "e", "A has explicit editor");

			// A user with no page map at all: everything resolves to "n".
			$nobody = (object)["id" => 2, "level" => 0, "permissions" => ["page" => []]];
			_permcache_reset();
			T::equals(PermissionService::userPageLevel($nobody, $leaf1), "n", "no-perm user gets n on leaf1");
			T::equals(PermissionService::userPageLevel($nobody, $leaf2), "n", "no-perm user gets n on sibling leaf2");
		} finally {
			foreach ($ids as $id) {
				SQL::query("DELETE FROM bigtree_pages WHERE id = ?", $id);
			}
		}
	}

	function test_permission_cache_populates_and_reuses_ancestors() {
		try {
			SQL::fetchSingle("SELECT id FROM bigtree_pages LIMIT 1");
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return;
		}

		$ns = "ZZ_permcache_" . uniqid();
		$ids = [];

		try {
			$a = _permcache_insert_page(0, $ns . "_A");
			$ids[] = $a;
			$b = _permcache_insert_page($a, $ns . "_B");
			$ids[] = $b;
			$c = _permcache_insert_page($b, $ns . "_C");
			$ids[] = $c;

			$leaf1 = _permcache_insert_page($c, $ns . "_leaf1");
			$ids[] = $leaf1;
			$leaf2 = _permcache_insert_page($c, $ns . "_leaf2");
			$ids[] = $leaf2;

			$user = (object)["id" => 1, "level" => 0, "permissions" => ["page" => [$a => "e"]]];

			_permcache_reset();

			// Resolving leaf1 caches the whole chain leaf1, C, B (the walk stops at A).
			PermissionService::userPageLevel($user, $leaf1);
			$after_first = _permcache_cache();

			T::ok(array_key_exists($leaf1, $after_first), "cache holds leaf1 after first resolve");
			T::ok(array_key_exists($c, $after_first), "cache holds shared ancestor C after first resolve");
			T::ok(array_key_exists($b, $after_first), "cache holds shared ancestor B after first resolve");
			T::equals($after_first[$leaf1], $c, "cached leaf1 → C parent mapping is correct");
			T::equals($after_first[$c], $b, "cached C → B parent mapping is correct");
			T::equals($after_first[$b], $a, "cached B → A parent mapping is correct");

			// Resolving sibling leaf2 must reuse the cached ancestors. The only new
			// key is leaf2 itself; the shared-ancestor entries (C, B) are unchanged.
			PermissionService::userPageLevel($user, $leaf2);
			$after_second = _permcache_cache();

			T::ok(array_key_exists($leaf2, $after_second), "cache holds leaf2 after sibling resolve");
			T::equals(count($after_second), count($after_first) + 1, "sibling resolve only adds leaf2 — shared ancestors reused, not re-queried");
			T::equals($after_second[$c], $b, "shared ancestor C mapping stable across siblings");
			T::equals($after_second[$b], $a, "shared ancestor B mapping stable across siblings");
		} finally {
			foreach ($ids as $id) {
				SQL::query("DELETE FROM bigtree_pages WHERE id = ?", $id);
			}
		}
	}

	function test_permission_cache_orphan_resolves_to_n() {
		try {
			SQL::fetchSingle("SELECT id FROM bigtree_pages LIMIT 1");
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return;
		}

		$ns = "ZZ_permcache_" . uniqid();
		$ids = [];

		try {
			// Child whose parent points at an id that does not exist (orphan).
			$missing_parent = 2000000000;
			$orphan = _permcache_insert_page($missing_parent, $ns . "_orphan");
			$ids[] = $orphan;

			$user = (object)["id" => 1, "level" => 0, "permissions" => ["page" => []]];

			_permcache_reset();

			// Walk: orphan → missing_parent. pageParent(missing_parent) → null → "n".
			T::equals(PermissionService::userPageLevel($user, $orphan), "n", "orphan page (missing parent row) resolves to n");

			$cache = _permcache_cache();
			T::ok(array_key_exists($missing_parent, $cache), "cache records the missing parent lookup");
			T::ok($cache[$missing_parent] === null, "missing parent caches as null (the orphan branch)");
		} finally {
			foreach ($ids as $id) {
				SQL::query("DELETE FROM bigtree_pages WHERE id = ?", $id);
			}
		}
	}
