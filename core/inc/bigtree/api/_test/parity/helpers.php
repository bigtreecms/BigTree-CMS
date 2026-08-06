<?php
	/**
	 * Shared helpers for Phase 1 L1 parity suites (p0/* oracles).
	 *
	 * Loaded by run.php before any parity/*Test.php file.
	 *
	 * Cleanup contract:
	 *  - Seed helpers register fixtures in a process-local registry.
	 *  - run.php calls parity_cleanup_tracked() after every test_*() so a failed
	 *    assertion (or a missing try/finally) cannot leave SQL/JSON-DB rows behind.
	 *  - parity_sweep_artifacts() also removes anything that matches the throwaway
	 *    naming conventions (zz_ / ZZ / zzparity / @test.local) — a safety net for
	 *    fixtures created outside the seed helpers (AI tools, JsonStore inserts,
	 *    prior interrupted runs). Called at suite start and end.
	 */

	use BigTree\Api\Request;
	use BigTree\Api\Response;

	/** @var array<string,array<int|string,true>> */
	$GLOBALS["__parity_fixtures"] = [
		"users" => [],
		"pages" => [],
		"tags" => [],
		"pending" => [],
		"news" => [],
		"settings" => [],
		"jsondb" => [], // "store\0id" => true
		"tables" => [],
	];

	/** True when the DB is reachable from this harness. */
	function parity_db_available(): bool {
		try {
			SQL::fetchSingle("SELECT 1");

			return true;
		} catch (Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return false;
		}
	}

	/** Clear request-scoped legacy admin so the next fixture actor rebinds cleanly. */
	function parity_reset_legacy_admin(): void {
		if (class_exists(\BigTree\Services\LegacyAdmin::class, false)) {
			\BigTree\Services\LegacyAdmin::clear();
		}

		unset($GLOBALS["admin"]);
	}

	/**
	 * Build a Request with an authenticated actor, route params, body, and query.
	 *
	 * @param array|object $permissions Permission map (page/module/resources/module_gbp)
	 */
	function parity_request(
		int $actor_id,
		int $actor_level,
		array $route_params = [],
		array $body = [],
		array $query = [],
		$permissions = []
	): Request {
		// Multi-test process: ensure BigTreeAutoModule sees this actor, not a
		// prior fixture user left on the LegacyAdmin singleton.
		parity_reset_legacy_admin();

		$req = new Request();
		$req->user = (object)[
			"id" => $actor_id,
			"level" => $actor_level,
			"permissions" => $permissions,
			"email" => "parity-actor-{$actor_id}@test.local",
			"name" => "Parity Actor {$actor_id}",
		];
		$req->route_params = $route_params;
		$req->body = $body;
		$req->query = $query;

		return $req;
	}

	/** Unwrap Response envelope data (or null for 204). */
	function parity_data(Response $response) {
		if ($response->status === 204 || $response->body === null) {
			return null;
		}

		if (is_array($response->body) && array_key_exists("data", $response->body)) {
			return $response->body["data"];
		}

		return $response->body;
	}

	// ── Fixture registry ───────────────────────────────────────────────────

	/** Remember a SQL/JSON fixture so parity_cleanup_tracked() can remove it. */
	function parity_track(string $kind, $id): void {
		if ($id === null || $id === "" || $id === 0 || $id === "0") {
			return;
		}

		if ($kind === "jsondb") {
			return;
		}

		$GLOBALS["__parity_fixtures"][$kind][(string)$id] = true;
	}

	/** Remember a JSON-DB row (store + id). */
	function parity_track_jsondb(string $store, string $id): void {
		if ($store === "" || $id === "") {
			return;
		}

		$GLOBALS["__parity_fixtures"]["jsondb"][$store . "\0" . $id] = true;
	}

	/** Remember a throwaway SQL table name for DROP TABLE. */
	function parity_track_table(string $table): void {
		if ($table === "" || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
			return;
		}

		$GLOBALS["__parity_fixtures"]["tables"][$table] = true;
	}

	function parity_untrack(string $kind, $id): void {
		unset($GLOBALS["__parity_fixtures"][$kind][(string)$id]);
	}

	function parity_untrack_jsondb(string $store, string $id): void {
		unset($GLOBALS["__parity_fixtures"]["jsondb"][$store . "\0" . $id]);
	}

	/**
	 * Delete every fixture registered since the last cleanup (or suite start).
	 * Safe to call when the registry is empty. Best-effort: never throws.
	 */
	function parity_cleanup_tracked(): void {
		$fixtures = $GLOBALS["__parity_fixtures"];

		foreach (array_keys($fixtures["tables"] ?? []) as $table) {
			parity_drop_table((string)$table);
		}

		foreach (array_keys($fixtures["jsondb"] ?? []) as $key) {
			$parts = explode("\0", (string)$key, 2);

			if (count($parts) === 2) {
				parity_delete_jsondb($parts[0], $parts[1]);
			}
		}

		foreach (array_keys($fixtures["settings"] ?? []) as $id) {
			parity_delete_setting((string)$id);
		}

		foreach (array_keys($fixtures["news"] ?? []) as $id) {
			parity_delete_news_entries((int)$id);
		}

		foreach (array_keys($fixtures["pending"] ?? []) as $id) {
			parity_delete_pending((int)$id);
		}

		foreach (array_keys($fixtures["tags"] ?? []) as $id) {
			parity_delete_tags((int)$id);
		}

		foreach (array_keys($fixtures["pages"] ?? []) as $id) {
			parity_delete_page((int)$id);
		}

		foreach (array_keys($fixtures["users"] ?? []) as $id) {
			parity_delete_users((int)$id);
		}

		$GLOBALS["__parity_fixtures"] = [
			"users" => [],
			"pages" => [],
			"tags" => [],
			"pending" => [],
			"news" => [],
			"settings" => [],
			"jsondb" => [],
			"tables" => [],
		];

		parity_reset_legacy_admin();
	}

	/**
	 * Whether a JSON-DB row looks like a throwaway test artifact.
	 *
	 * Suites consistently prefix throwaways with zz / ZZ (underscore, hyphen, or
	 * space): "zz_parity_*", "zz-parity-*", "ZZ Probe Group", "zzparity…",
	 * "ZZ_jsonstore_*", scaffold tables "zz_scaffold_*", e2e "zz_e2e_*", etc.
	 */
	function parity_is_test_artifact(array $item): bool {
		foreach (["id", "name", "route", "table"] as $key) {
			$value = (string)($item[$key] ?? "");

			if ($value === "") {
				continue;
			}

			// Leading zz/ZZ with optional separator covers every suite convention.
			if (preg_match('/^zz([_\-\s]|$)/i', $value)) {
				return true;
			}

			if (stripos($value, "zzparity") !== false || stripos($value, "zz_parity") !== false) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Convention-based sweep: remove leftover test artifacts from JSON-DB and SQL
	 * even when no registry entry exists (interrupted runs, missing finally blocks).
	 *
	 * @return int Number of artifacts removed (best-effort count)
	 */
	function parity_sweep_artifacts(): int {
		$removed = 0;

		$stores = [
			"modules",
			"module-groups",
			"callouts",
			"callout-groups",
			"templates",
			"settings",
			"feeds",
			"field-types",
		];

		foreach ($stores as $store) {
			try {
				if (!class_exists("BigTreeJSONDB", false)) {
					break;
				}

				// Always re-read from disk so a stale in-process cache cannot hide rows.
				BigTreeJSONDB::$Cache = [];
				$items = BigTreeJSONDB::getAll($store);

				foreach ($items as $item) {
					if (!is_array($item) || empty($item["id"]) || !parity_is_test_artifact($item)) {
						continue;
					}

					$id = (string)$item["id"];

					// Modules may own a throwaway SQL table — drop it with the definition.
					if ($store === "modules") {
						$table = (string)($item["table"] ?? "");

						if ($table !== "" && preg_match('/^zz[_a-z0-9]+$/i', $table)) {
							parity_drop_table($table);
						}
					}

					parity_delete_jsondb($store, $id);
					$removed++;
				}
			} catch (Throwable $e) {
				// best-effort
			}
		}

		try {
			SQL::fetchSingle("SELECT 1");
		} catch (Throwable $e) {
			parity_reset_legacy_admin();

			return $removed;
		}

		try {
			// Throwaway SQL tables (scaffold / e2e / smoke / unit).
			$rows = SQL::fetchAll("SHOW TABLES");

			foreach ($rows as $row) {
				$table = (string)array_values($row)[0];

				if (preg_match('/^zz[_a-z0-9]+$/i', $table)) {
					parity_drop_table($table);
					$removed++;
				}
			}
		} catch (Throwable $e) {
			// best-effort
		}

		try {
			$pages = SQL::fetchAll(
				"SELECT id FROM bigtree_pages
				 WHERE nav_title LIKE 'ZZ%' OR title LIKE 'ZZ%'
				    OR route LIKE 'zz-%' OR route LIKE 'zz\\_%' OR path LIKE 'zz-%'"
			);

			foreach ($pages as $page) {
				parity_delete_page((int)$page["id"]);
				$removed++;
			}
		} catch (Throwable $e) {
			// best-effort
		}

		try {
			$users = SQL::fetchAll(
				"SELECT id FROM bigtree_users
				 WHERE email LIKE 'zz\\_%@%' OR email LIKE 'zz\\_parity\\_%'
				    OR name LIKE 'ZZ %' OR name LIKE 'ZZ\\_%' OR name LIKE 'ZZ Parity%'"
			);

			foreach ($users as $user) {
				parity_delete_users((int)$user["id"]);
				$removed++;
			}
		} catch (Throwable $e) {
			// best-effort
		}

		try {
			$tags = SQL::fetchAll(
				"SELECT id FROM bigtree_tags WHERE tag LIKE 'zz%' OR tag LIKE 'ZZ%'"
			);

			foreach ($tags as $tag) {
				parity_delete_tags((int)$tag["id"]);
				$removed++;
			}
		} catch (Throwable $e) {
			// best-effort
		}

		try {
			$news = SQL::fetchAll(
				"SELECT id FROM timber_news WHERE title LIKE 'ZZ%' OR title LIKE 'zz%'"
			);

			foreach ($news as $entry) {
				parity_delete_news_entries((int)$entry["id"]);
				$removed++;
			}
		} catch (Throwable $e) {
			// timber_news may not exist on every install
		}

		try {
			// Pending changes left by interrupted editor-level publishes.
			$pending = SQL::fetchAll(
				"SELECT id FROM bigtree_pending_changes
				 WHERE title LIKE 'ZZ%' OR title LIKE 'zz%'
				    OR user IN (
				    	SELECT id FROM bigtree_users
				    	WHERE email LIKE 'zz\\_%@%' OR name LIKE 'ZZ %'
				    )"
			);

			foreach ($pending as $change) {
				parity_delete_pending((int)$change["id"]);
				$removed++;
			}
		} catch (Throwable $e) {
			// best-effort
		}

		try {
			// Settings value rows without a JSON-DB definition (or leftover zz ids).
			$settings = SQL::fetchAll(
				"SELECT id FROM bigtree_settings WHERE id LIKE 'zz\\_%' OR id LIKE 'zzparity%'"
			);

			foreach ($settings as $setting) {
				parity_delete_setting((string)$setting["id"]);
				$removed++;
			}
		} catch (Throwable $e) {
			// best-effort
		}

		parity_reset_legacy_admin();

		return $removed;
	}

	/** DROP TABLE IF EXISTS for a throwaway name (validated). */
	function parity_drop_table(string $table): void {
		if ($table === "" || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
			return;
		}

		// Only drop tables the suites are allowed to create.
		if (!preg_match('/^zz[_a-z0-9]+$/i', $table)) {
			return;
		}

		try {
			SQL::query("DROP TABLE IF EXISTS `{$table}`");
			parity_untrack("tables", $table);
		} catch (Throwable $e) {
			// best-effort
		}
	}

	/** Delete a JSON-DB row if present (any store). */
	function parity_delete_jsondb(string $store, string ...$ids): void {
		foreach ($ids as $id) {
			if ($id === "") {
				continue;
			}

			try {
				if (class_exists("BigTreeJSONDB", false)) {
					// Prefer a fresh disk read so a stale negative cache cannot
					// make us skip a row that is still on disk.
					unset(BigTreeJSONDB::$Cache[$store]);
					BigTreeJSONDB::delete($store, $id);
				}

				parity_untrack_jsondb($store, $id);
			} catch (Throwable $e) {
				// best-effort
			}
		}
	}

	/**
	 * Insert a throwaway bigtree_users row. Auto-tracked for suite cleanup.
	 *
	 * @param array<string,mixed> $overrides
	 */
	function parity_seed_user(array $overrides = []): int {
		$row = array_merge([
			"email" => "zz_parity_" . uniqid("", true) . "@test.local",
			"password" => password_hash("ParityPass-1!", PASSWORD_DEFAULT),
			"new_hash" => "on",
			"2fa_secret" => "",
			"2fa_login_token" => "",
			"name" => "ZZ Parity Fixture",
			"company" => "",
			"level" => 0,
			"permissions" => "{}",
			"alerts" => "",
			"daily_digest" => "",
			"timezone" => "UTC",
			"change_password_hash" => "",
			"token_version" => 1,
		], $overrides);

		if (is_array($row["permissions"])) {
			$row["permissions"] = json_encode($row["permissions"]);
		}

		if (is_array($row["alerts"] ?? null)) {
			$row["alerts"] = json_encode($row["alerts"]);
		}

		$id = (int)SQL::insert("bigtree_users", $row);
		parity_track("users", $id);

		return $id;
	}

	/** Unique route segment for throwaway pages. */
	function parity_unique_route(string $prefix = "zz-parity"): string {
		return $prefix . "-" . strtolower(bin2hex(random_bytes(4)));
	}

	/**
	 * Insert a throwaway live page directly, supplying every NOT NULL / no-default
	 * column. For fixtures that just need a real page id to point at.
	 */
	function parity_seed_page(array $overrides = []): int {
		$route = parity_unique_route();
		$id = SQL::insert("bigtree_pages", array_merge([
			"parent" => 0,
			"nav_title" => "ZZ Parity Page",
			"title" => "ZZ Parity Page",
			"trunk" => "",
			"in_nav" => "on",
			"route" => $route,
			"path" => $route,
			"meta_keywords" => "",
			"meta_description" => "",
			"seo_invisible" => "",
			"external" => "",
			"template" => "",
			"resources" => "",
			"archived" => "",
			"archived_inherited" => "",
			"max_age" => 0,
			"last_edited_by" => 0,
			"ga_page_views" => 0,
			"created_at" => "NOW()",
			"updated_at" => "NOW()",
		], $overrides));

		$id = (int)$id;
		parity_track("pages", $id);

		return $id;
	}

	/** Delete a live page if it still exists (ignores missing). */
	function parity_delete_page(int $id): void {
		if ($id < 1) {
			return;
		}

		try {
			// Children first (simple one-level; cascadeDelete handles deeper if service used)
			$children = SQL::fetchAll("SELECT id FROM bigtree_pages WHERE parent = ?", $id);

			foreach ($children as $child) {
				parity_delete_page((int)$child["id"]);
			}

			SQL::query("DELETE FROM bigtree_tags_rel WHERE `table` = 'bigtree_pages' AND entry = ?", $id);
			SQL::query("DELETE FROM bigtree_open_graph WHERE `table` = 'bigtree_pages' AND entry = ?", $id);
			SQL::query("DELETE FROM bigtree_page_revisions WHERE page = ?", $id);
			SQL::query("DELETE FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND item_id = ?", $id);
			SQL::query("DELETE FROM bigtree_resource_allocation WHERE `table` = 'bigtree_pages' AND entry = ?", $id);
			SQL::query("DELETE FROM bigtree_resource_allocation WHERE `table` = 'bigtree_pages' AND entry = ?", "p" . $id);
			SQL::delete("bigtree_pages", $id);
		} catch (Throwable $e) {
			// best-effort cleanup
		}

		parity_untrack("pages", $id);
	}

	/**
	 * Turn the News form's tagging and Open Graph sections on for the duration of a
	 * test, returning a closure that puts the module definition back.
	 *
	 * A form opts into each with its own flag, and both the SPA's FormRenderer and
	 * (since audit #6 B5) the AI write path refuse to touch a relation the form
	 * doesn't offer — so any fixture that tags an entry has to enable it first, the
	 * same way a developer would.
	 */
	function parity_enable_news_relations(): callable {
		$module_id = parity_news_module_id();
		$original = BigTreeJSONDB::get("modules", $module_id);
		$patched = $original;

		foreach ($patched["forms"] as $form_key => $form) {
			$patched["forms"][$form_key]["tagging"] = "on";
			$patched["forms"][$form_key]["open_graph"] = "on";
		}

		BigTreeJSONDB::update("modules", $module_id, $patched);

		return function () use ($module_id, $original): void {
			BigTreeJSONDB::update("modules", $module_id, $original);
		};
	}

	/** Delete pending changes by id list. */
	function parity_delete_pending(int ...$ids): void {
		foreach ($ids as $id) {
			if ($id < 1) {
				continue;
			}

			try {
				$row = SQL::fetch("SELECT `table` FROM bigtree_pending_changes WHERE id = ?", $id);

				if ($row) {
					SQL::query(
						"DELETE FROM bigtree_resource_allocation WHERE `table` = ? AND entry = ?",
						$row["table"],
						"p" . $id
					);
				}

				SQL::delete("bigtree_pending_changes", $id);
			} catch (Throwable $e) {
				// best-effort
			}

			parity_untrack("pending", $id);
		}
	}

	/** Delete tags by id (and their rel rows). */
	function parity_delete_tags(int ...$ids): void {
		foreach ($ids as $id) {
			if ($id < 1) {
				continue;
			}

			try {
				SQL::query("DELETE FROM bigtree_tags_rel WHERE tag = ?", $id);
				SQL::delete("bigtree_tags", $id);
			} catch (Throwable $e) {
				// best-effort
			}

			parity_untrack("tags", $id);
		}
	}

	/** Delete users by id. */
	function parity_delete_users(int ...$ids): void {
		foreach ($ids as $id) {
			if ($id < 1) {
				continue;
			}

			try {
				SQL::delete("bigtree_users", $id);
			} catch (Throwable $e) {
				// best-effort
			}

			parity_untrack("users", $id);
		}
	}

	/**
	 * Remove a setting definition + value created for tests.
	 * Uses JSON-DB when present so custom/json-db is restored.
	 */
	function parity_delete_setting(string $id): void {
		if ($id === "") {
			return;
		}

		try {
			if (class_exists("BigTreeJSONDB", false)) {
				// Bust a stale cache so exists()/delete() re-read the on-disk store.
				unset(BigTreeJSONDB::$Cache["settings"]);
				BigTreeJSONDB::delete("settings", $id);
			}
		} catch (Throwable $e) {
			// continue
		}

		try {
			SQL::delete("bigtree_settings", $id);
			SQL::query("DELETE FROM bigtree_resource_allocation WHERE `table` = 'bigtree_settings' AND entry = ?", $id);
		} catch (Throwable $e) {
			// best-effort
		}

		parity_untrack("settings", $id);
	}

	/** News module id from example-site fixture (json-db). */
	function parity_news_module_id(): string {
		return "modules-15c3df733b7e8c";
	}

	/** Delete timber_news rows by id. */
	function parity_delete_news_entries(int ...$ids): void {
		foreach ($ids as $id) {
			if ($id < 1) {
				continue;
			}

			try {
				SQL::delete("timber_news", $id);
				SQL::query("DELETE FROM bigtree_tags_rel WHERE `table` = 'timber_news' AND entry = ?", $id);
				SQL::query("DELETE FROM bigtree_module_view_cache WHERE id = ?", $id);
				SQL::query(
					"DELETE FROM bigtree_pending_changes WHERE `table` = 'timber_news' AND item_id = ?",
					$id
				);
			} catch (Throwable $e) {
				// best-effort
			}

			parity_untrack("news", $id);
		}
	}

	/** Track a pending-change id created outside seed helpers. */
	function parity_track_pending(int $id): void {
		parity_track("pending", $id);
	}

	/** Track a tag id created outside seed helpers. */
	function parity_track_tag(int $id): void {
		parity_track("tags", $id);
	}

	/** Track a news entry id created outside seed helpers. */
	function parity_track_news(int $id): void {
		parity_track("news", $id);
	}

	/** Track a setting id created outside seed helpers. */
	function parity_track_setting(string $id): void {
		parity_track("settings", $id);
	}

	/**
	 * Insert a throwaway module-group. Auto-tracked.
	 *
	 * @param array<string,mixed> $overrides
	 */
	function parity_seed_module_group(array $overrides = []): string {
		$suffix = bin2hex(random_bytes(3));
		$id = BigTreeJSONDB::insert("module-groups", array_merge([
			"name" => "ZZ Parity Group " . $suffix,
			"route" => "zz-parity-group-" . $suffix,
		], $overrides));

		if ($id) {
			parity_track_jsondb("module-groups", (string)$id);
		}

		return (string)$id;
	}

	/**
	 * Insert a throwaway callout definition. Auto-tracked.
	 *
	 * @param array<int,array<string,mixed>> $fields
	 */
	function parity_seed_callout(string $id, array $fields = []): void {
		if ($id === "") {
			return;
		}

		BigTreeJSONDB::insert("callouts", [
			"id" => $id,
			"name" => "Parity Callout",
			"description" => "",
			"level" => 0,
			"resources" => $fields,
			"display_field" => "headline",
			"display_default" => "",
			"position" => 0,
		]);
		parity_track_jsondb("callouts", $id);
	}

	function parity_delete_callout(string $id): void {
		parity_delete_jsondb("callouts", $id);
	}

	/**
	 * Insert a throwaway template definition. Auto-tracked.
	 *
	 * @param array<int,array<string,mixed>> $resources
	 */
	function parity_seed_template(string $id, array $resources = []): void {
		if ($id === "") {
			return;
		}

		BigTreeJSONDB::insert("templates", [
			"id" => $id,
			"name" => "Parity Template",
			"module" => "",
			"resources" => $resources,
			"level" => 0,
			"routed" => "",
			"hooks" => [],
			"position" => 0,
		]);
		parity_track_jsondb("templates", $id);
	}

	function parity_delete_template(string $id): void {
		parity_delete_jsondb("templates", $id);
	}
