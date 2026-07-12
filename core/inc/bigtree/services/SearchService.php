<?php
	namespace BigTree\Services;

	use BigTree\Api\Request;
	use BigTree\Api\Sanitize;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTreeJSONDB;
	use SQL;

	/**
	 * Federated global search. Returns one consolidated result set across the
	 * domains the user can see — primarily for the SPA's nav-bar search box.
	 *
	 * Each domain returns up to {limit} matches (default 10). Results are grouped
	 * by type so the SPA can render headings.
	 *
	 * Domains queried in v1:
	 *   - pages       (bigtree_pages.nav_title / title), permission-filtered per page
	 *   - tags        (bigtree_tags.tag)
	 *   - users       (level >= 1 only; bigtree_users.name / email)
	 *   - modules     (bigtree_modules JSONDB.name / route), filtered to user-visible
	 *   - module entries (the search column of each user-visible module's primary view)
	 *
	 * Module-entry search uses BigTreeAutoModule::getSearchResults capped at 5 per
	 * module to avoid runaway result sets — the SPA can drill into a single module
	 * for full results.
	 */
	class SearchService {
		const DEFAULT_PER_DOMAIN = 10;
		const MODULE_ENTRY_CAP = 5;

		public function search(Request $request) {
			$q = $request->queryString("q");

			if ($q === "") {
				throw new BadRequestException("q required", "missing_query");
			}
			$limit = max(1, min(50, $request->queryInt("limit", self::DEFAULT_PER_DOMAIN)));

			$only = isset($request->query["types"])
				? array_filter(array_map("trim", explode(",", (string)$request->query["types"])))
				: ["pages", "tags", "users", "modules", "entries"];

			$results = [];
			$counts = [];

			if (in_array("pages", $only, true)) {
				$rows = $this->searchPages($q, $limit, $request->user);
				$results["pages"] = $rows;
				$counts["pages"] = count($rows);
			}

			if (in_array("tags", $only, true)) {
				$rows = $this->searchTags($q, $limit);
				$results["tags"] = $rows;
				$counts["tags"] = count($rows);
			}

			if (in_array("users", $only, true) && (int)$request->user->level >= 1) {
				$rows = $this->searchUsers($q, $limit);
				$results["users"] = $rows;
				$counts["users"] = count($rows);
			}

			if (in_array("modules", $only, true)) {
				$rows = $this->searchModules($q, $limit, $request->user);
				$results["modules"] = $rows;
				$counts["modules"] = count($rows);
			}

			if (in_array("entries", $only, true)) {
				$rows = $this->searchModuleEntries($q, $limit, $request->user);
				$results["entries"] = $rows;
				$counts["entries"] = count($rows);
			}

			return Response::ok($results, [
				"query" => $q,
				"counts" => $counts,
				"total" => array_sum($counts),
			]);
		}

		// — per-domain searchers —

		private function searchPages($q, $limit, $user) {
			// Delegate to PageService so federated search and GET /pages/search
			// share the overfetch-then-filter semantics and the same presenter.
			return (new PageService())->searchRows($q, $user, (int)$limit);
		}

		private function searchTags($q, $limit) {
			$like = Sanitize::likeTerm($q, true);
			$limit = max(1, (int)$limit);
			// Federated search also matches metaphone — richer than TagService::search.
			$rows = SQL::fetchAll(
				"SELECT id, tag, route, usage_count FROM bigtree_tags
				 WHERE tag LIKE ? OR metaphone LIKE ?
				 ORDER BY usage_count DESC LIMIT $limit",
				$like, $like
			);

			return array_map([TagService::class, "presentRow"], $rows);
		}

		private function searchUsers($q, $limit) {
			$like = Sanitize::likeTerm($q);
			$limit = max(1, (int)$limit);
			$rows = SQL::fetchAll(
				"SELECT id, name, email, level FROM bigtree_users
				 WHERE name LIKE ? OR email LIKE ? OR company LIKE ?
				 ORDER BY name ASC LIMIT $limit",
				$like, $like, $like
			);

			return array_map(function ($r) {

				return [
					"id" => (int)$r["id"],
					"name" => $r["name"],
					"email" => $r["email"],
					"level" => (int)$r["level"],
				];
			}, $rows);
		}

		private function searchModules($q, $limit, $user) {
			$all = BigTreeJSONDB::getAll("modules", "name", "ASC");
			$ql = strtolower($q);
			$kept = [];

			foreach ($all as $m) {
				if (!PermissionService::userHasModuleAccess($user, (int)$m["id"], "v")) {
					continue;
				}
				$hay = strtolower(($m["name"] ?? "") . " " . ($m["route"] ?? ""));

				if (strpos($hay, $ql) === false) {
					continue;
				}
				$kept[] = [
					"id" => (int)$m["id"],
					"name" => $m["name"] ?? "",
					"route" => $m["route"] ?? "",
					"icon" => $m["icon"] ?? "",
				];

				if (count($kept) >= $limit) {
					break;
				}
			}

			return $kept;
		}

		private function searchModuleEntries($q, $limit_per_module, $user) {
			$all = BigTreeJSONDB::getAll("modules");
			$results = [];
			$module_count = 0;
			$max_modules = 5; // cap how many modules we sweep to keep latency bounded

			foreach ($all as $m) {
				if ($module_count >= $max_modules) {
					break;
				}

				if (!PermissionService::userHasModuleAccess($user, (int)$m["id"], "v")) {
					continue;
				}

				if (empty($m["table"])) {
					continue;
				}

				$view = \BigTreeAutoModule::getViewForTable($m["table"]);

				if (!$view) {
					continue;
				}

				$module_count++;

				try {
					$search = \BigTreeAutoModule::getSearchResults($view, 1, $q, "id DESC", false);
				} catch (\Throwable $e) {
					continue;
				}

				$items = array_slice($search["results"] ?? [], 0, self::MODULE_ENTRY_CAP);

				if (!$items) {
					continue;
				}

				$results[] = [
					"module" => ["id" => (int)$m["id"], "name" => $m["name"] ?? "", "route" => $m["route"] ?? ""],
					"items" => array_values($items),
				];
			}

			return $results;
		}
	}
