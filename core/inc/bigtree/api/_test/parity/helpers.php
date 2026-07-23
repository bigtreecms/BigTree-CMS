<?php
	/**
	 * Shared helpers for Phase 1 L1 parity suites (p0/* oracles).
	 *
	 * Loaded by run.php before any parity/*Test.php file.
	 */

	use BigTree\Api\Request;
	use BigTree\Api\Response;

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

	/**
	 * Insert a throwaway bigtree_users row. Caller must delete.
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

		return (int)SQL::insert("bigtree_users", $row);
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

		return (int)$id;
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
		}
	}

	/**
	 * Remove a setting definition + value created for tests.
	 * Uses JSON-DB when present so custom/json-db is restored.
	 */
	function parity_delete_setting(string $id): void {
		try {
			if (class_exists("BigTreeJSONDB", false) && BigTreeJSONDB::exists("settings", $id)) {
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
		}
	}
