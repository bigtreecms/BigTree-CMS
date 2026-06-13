<?php
	/**
	 * Memoization of the resource-folder-tree walk in
	 * PermissionService::userFolderLevel.
	 *
	 * The file manager resolves access for every folder in a listing, and every
	 * folder walks the same ancestor chain to the root. PermissionService caches
	 * the id → parent lookup (self::$folder_parent_cache) so the identical parent
	 * rows are not re-fetched once per sibling. This is the resource-folder twin of
	 * the page-tree cache exercised by PermissionCacheTest.
	 *
	 * These tests prove "same result, fewer queries":
	 *   - inherited permissions resolve identically to the pre-cache behavior,
	 *   - the private cache populates the shared ancestors after one resolve and a
	 *     sibling resolve does not grow the cache for those ancestors,
	 *   - an orphan folder (parent row missing) still resolves to "e".
	 *
	 * Seeds a throwaway folder chain in bigtree_resource_folders under a namespaced
	 * name and deletes every seeded row in a finally block. Skips on an unavailable
	 * DB.
	 */

	use BigTree\Services\PermissionService;

	/** Insert a bigtree_resource_folders row. Returns the new row id. */
	function _foldercache_insert_folder(int $parent, string $name): int {
		$id = SQL::insert("bigtree_resource_folders", [
			"parent" => $parent,
			"name" => $name,
		]);

		return (int)$id;
	}

	/** Read the private static cache via reflection. */
	function _foldercache_cache(): array {
		$prop = new ReflectionProperty(PermissionService::class, "folder_parent_cache");
		$prop->setAccessible(true);

		return $prop->getValue();
	}

	/** Reset the private static cache so each test starts clean. */
	function _foldercache_reset(): void {
		$prop = new ReflectionProperty(PermissionService::class, "folder_parent_cache");
		$prop->setAccessible(true);
		$prop->setValue(null, []);
	}

	function test_folder_cache_inherited_results_unchanged() {
		try {
			SQL::fetchSingle("SELECT id FROM bigtree_resource_folders LIMIT 1");
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return;
		}

		$ns = "ZZ_foldercache_" . uniqid();
		$ids = [];

		try {
			// root(0) → A → B → C, plus three leaf siblings under C.
			$a = _foldercache_insert_folder(0, $ns . "_A");
			$ids[] = $a;
			$b = _foldercache_insert_folder($a, $ns . "_B");
			$ids[] = $b;
			$c = _foldercache_insert_folder($b, $ns . "_C");
			$ids[] = $c;

			$leaf1 = _foldercache_insert_folder($c, $ns . "_leaf1");
			$ids[] = $leaf1;
			$leaf2 = _foldercache_insert_folder($c, $ns . "_leaf2");
			$ids[] = $leaf2;
			$leaf3 = _foldercache_insert_folder($c, $ns . "_leaf3");
			$ids[] = $leaf3;

			// Non-admin publisher: explicit "p" at A (children inherit p), explicit
			// "n" override on leaf3.
			$user = (object)["id" => 1, "level" => 0, "permissions" => ["resources" => [
				$a => "p",
				$leaf3 => "n",
			]]];

			_foldercache_reset();

			// leaf1 / leaf2 inherit "p" by walking leaf → C → B → A (explicit "p").
			T::equals(PermissionService::userFolderLevel($user, $leaf1), "p", "leaf1 inherits publisher from ancestor A");
			T::equals(PermissionService::userFolderLevel($user, $leaf2), "p", "sibling leaf2 resolves identically (inherited p)");

			// Explicit "n" override on a leaf wins over inherited "p".
			T::equals(PermissionService::userFolderLevel($user, $leaf3), "n", "explicit n on leaf3 overrides inherited p");

			// Intermediate nodes resolve consistently.
			T::equals(PermissionService::userFolderLevel($user, $c), "p", "C inherits publisher from A");
			T::equals(PermissionService::userFolderLevel($user, $b), "p", "B inherits publisher from A");
			T::equals(PermissionService::userFolderLevel($user, $a), "p", "A has explicit publisher");

			// A user with no resources map at all: everything resolves to the folder
			// root default "e".
			$nobody = (object)["id" => 2, "level" => 0, "permissions" => ["resources" => []]];
			_foldercache_reset();
			T::equals(PermissionService::userFolderLevel($nobody, $leaf1), "e", "no-perm user gets folder root default e on leaf1");
			T::equals(PermissionService::userFolderLevel($nobody, $leaf2), "e", "no-perm user gets e on sibling leaf2");
		} finally {
			foreach ($ids as $id) {
				SQL::query("DELETE FROM bigtree_resource_folders WHERE id = ?", $id);
			}
		}
	}

	function test_folder_cache_populates_and_reuses_ancestors() {
		try {
			SQL::fetchSingle("SELECT id FROM bigtree_resource_folders LIMIT 1");
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return;
		}

		$ns = "ZZ_foldercache_" . uniqid();
		$ids = [];

		try {
			$a = _foldercache_insert_folder(0, $ns . "_A");
			$ids[] = $a;
			$b = _foldercache_insert_folder($a, $ns . "_B");
			$ids[] = $b;
			$c = _foldercache_insert_folder($b, $ns . "_C");
			$ids[] = $c;

			$leaf1 = _foldercache_insert_folder($c, $ns . "_leaf1");
			$ids[] = $leaf1;
			$leaf2 = _foldercache_insert_folder($c, $ns . "_leaf2");
			$ids[] = $leaf2;

			$user = (object)["id" => 1, "level" => 0, "permissions" => ["resources" => [$a => "p"]]];

			_foldercache_reset();

			// Resolving leaf1 caches the whole chain leaf1, C, B (the walk stops at A).
			PermissionService::userFolderLevel($user, $leaf1);
			$after_first = _foldercache_cache();

			T::ok(array_key_exists($leaf1, $after_first), "cache holds leaf1 after first resolve");
			T::ok(array_key_exists($c, $after_first), "cache holds shared ancestor C after first resolve");
			T::ok(array_key_exists($b, $after_first), "cache holds shared ancestor B after first resolve");
			T::equals($after_first[$leaf1], $c, "cached leaf1 → C parent mapping is correct");
			T::equals($after_first[$c], $b, "cached C → B parent mapping is correct");
			T::equals($after_first[$b], $a, "cached B → A parent mapping is correct");

			// Resolving sibling leaf2 must reuse the cached ancestors. The only new
			// key is leaf2 itself; the shared-ancestor entries (C, B) are unchanged.
			PermissionService::userFolderLevel($user, $leaf2);
			$after_second = _foldercache_cache();

			T::ok(array_key_exists($leaf2, $after_second), "cache holds leaf2 after sibling resolve");
			T::equals(count($after_second), count($after_first) + 1, "sibling resolve only adds leaf2 — shared ancestors reused, not re-queried");
			T::equals($after_second[$c], $b, "shared ancestor C mapping stable across siblings");
			T::equals($after_second[$b], $a, "shared ancestor B mapping stable across siblings");
		} finally {
			foreach ($ids as $id) {
				SQL::query("DELETE FROM bigtree_resource_folders WHERE id = ?", $id);
			}
		}
	}

	function test_folder_cache_orphan_resolves_to_e() {
		try {
			SQL::fetchSingle("SELECT id FROM bigtree_resource_folders LIMIT 1");
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return;
		}

		$ns = "ZZ_foldercache_" . uniqid();
		$ids = [];

		try {
			// Folder whose parent points at an id that does not exist (orphan).
			$missing_parent = 2000000000;
			$orphan = _foldercache_insert_folder($missing_parent, $ns . "_orphan");
			$ids[] = $orphan;

			$user = (object)["id" => 1, "level" => 0, "permissions" => ["resources" => []]];

			_foldercache_reset();

			// Walk: orphan → missing_parent. folderParent(missing_parent) → null → "e".
			T::equals(PermissionService::userFolderLevel($user, $orphan), "e", "orphan folder (missing parent row) resolves to e");

			$cache = _foldercache_cache();
			T::ok(array_key_exists($missing_parent, $cache), "cache records the missing parent lookup");
			T::ok($cache[$missing_parent] === null, "missing parent caches as null (the orphan branch)");
		} finally {
			foreach ($ids as $id) {
				SQL::query("DELETE FROM bigtree_resource_folders WHERE id = ?", $id);
			}
		}
	}
