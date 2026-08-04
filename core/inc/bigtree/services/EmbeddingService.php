<?php
	namespace BigTree\Services;

	use BigTree\Api\Json;
	use BigTree\Api\Flag;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTreeAI;
	use BigTreeCMS;
	use BigTreeJSONDB;
	use SQL;

	/**
	 * Optional vector index for admin search (MySQL 9+ / MariaDB 11.7+).
	 *
	 * Indexes live pages, public settings, and module table rows into
	 * bigtree_ai_embeddings. Fail-open on embed/API errors so editorial saves
	 * never block.
	 */
	class EmbeddingService {

		const TABLE = "bigtree_ai_embeddings";
		const STATUS_SETTING = "bigtree-internal-ai-embeddings-status";
		// Short-TTL cache of query→vector so debounced quick-search doesn't re-embed
		// the same prefix on every keystroke.
		const QUERY_CACHE = "org.bigtreecms.ai-query-embedding";
		const QUERY_CACHE_TTL = 900;
		const CHUNK_SOFT_MAX = 6000;
		const CHUNK_SIZE = 4000;
		const CHUNK_OVERLAP = 200;
		const REINDEX_BATCH = 12;
		const TEXT_FIELD_TYPES = [
			"text", "textarea", "html", "callouts", "matrix", "list", "checkbox",
			"route", "one-to-many",
		];

		// — capability —

		public static function isSupported(): bool {
			return BigTreeAI::vectorStoreSupported();
		}

		public static function tableReady(): bool {
			if (!self::isSupported()) {
				return false;
			}

			try {
				return (bool)SQL::tableExists(self::TABLE);
			} catch (\Throwable $e) {
				return false;
			}
		}

		/**
		 * Create bigtree_ai_embeddings (+ best-effort ANN index) when the DB
		 * supports VECTOR but the table doesn't exist yet. Idempotent and safe to
		 * call on hot paths — returns quickly once the table is ready.
		 *
		 * This is the canonical location for the embeddings DDL: revision 506 and
		 * SystemConfigureService::updateAI() both call it, so a host that gains
		 * VECTOR support after migration 506 ran can still get the table created by
		 * saving AI config or pressing "Rebuild index". (install.php keeps a
		 * standalone copy for fresh base installs — keep the two in sync.)
		 */
		public static function ensureTable(): bool {
			if (!self::isSupported()) {
				return false;
			}

			if (self::tableReady()) {
				return true;
			}

			try {
				if (!SQL::tableExists(self::TABLE)) {
					SQL::query(self::createTableSql());
				}

				self::ensureVectorIndex();
				$ready = (bool)SQL::tableExists(self::TABLE);
				self::writeStatus(["table_ready" => $ready]);

				return $ready;
			} catch (\Throwable $e) {
				self::recordError($e->getMessage());

				return false;
			}
		}

		/** Canonical CREATE TABLE for bigtree_ai_embeddings (fixed VECTOR(n) for v1). */
		private static function createTableSql(): string {
			$dimensions = (int)BigTreeAI::EMBEDDING_DIMENSIONS;

			return "
				CREATE TABLE IF NOT EXISTS `" . self::TABLE . "` (
					`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
					`source_type` VARCHAR(32) NOT NULL,
					`source_id` VARCHAR(191) NOT NULL,
					`module_id` VARCHAR(191) NULL,
					`module_route` VARCHAR(191) NULL,
					`table_name` VARCHAR(191) NULL,
					`chunk_index` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
					`title` VARCHAR(500) NOT NULL DEFAULT '',
					`content_text` MEDIUMTEXT NOT NULL,
					`content_hash` CHAR(64) NOT NULL DEFAULT '',
					`embedding` VECTOR($dimensions) NOT NULL,
					`model` VARCHAR(100) NOT NULL DEFAULT '',
					`updated_at` DATETIME NOT NULL,
					PRIMARY KEY (`id`),
					UNIQUE KEY `source_chunk_model` (`source_type`, `source_id`, `chunk_index`, `model`),
					KEY `source` (`source_type`, `source_id`),
					KEY `module` (`module_id`),
					KEY `updated` (`updated_at`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
			";
		}

		/** Best-effort MariaDB ANN index (syntax varies; ORDER BY distance works without it). */
		private static function ensureVectorIndex(): void {
			try {
				$dialect = BigTreeAI::vectorDialect();

				if ($dialect === "mariadb") {
					$has = SQL::fetch(
						"SHOW INDEX FROM `" . self::TABLE . "` WHERE Key_name = 'embedding_vec'"
					);

					if (!$has) {
						SQL::query(
							"ALTER TABLE `" . self::TABLE . "`
							 ADD VECTOR INDEX `embedding_vec` (`embedding`) M=16 DISTANCE=cosine"
						);
					}
				}
				// MySQL 9 vector indexes (if/when available) can be added here later.
			} catch (\Throwable $e) {
				// Non-fatal — ORDER BY DISTANCE still works without an ANN index.
			}
		}

		public static function isEnabled(): bool {
			if (!self::tableReady()) {
				return false;
			}

			$ai = new BigTreeAI();

			return $ai->isFeatureEnabled("embeddings");
		}

		/** @var bool Guard so the client connection is flushed at most once per request. */
		private static $response_flushed = false;

		/**
		 * Run a fail-open index update *after* the HTTP response is flushed, so an
		 * editorial save doesn't wait on the OpenAI embeddings round trip. No-op
		 * when embeddings are disabled; when fastcgi_finish_request() is unavailable
		 * (mod_php / CLI) the work still runs at shutdown, just without early flush.
		 */
		public static function deferIndex(callable $fn): void {
			if (!self::isEnabled()) {
				return;
			}

			register_shutdown_function(function () use ($fn) {
				self::flushResponse();

				try {
					$fn();
				} catch (\Throwable $e) {
					self::recordError($e->getMessage());
				}
			});
		}

		/** Flush + close the client connection once so deferred work runs in the background. */
		private static function flushResponse(): void {
			if (self::$response_flushed) {
				return;
			}

			self::$response_flushed = true;

			if (function_exists("fastcgi_finish_request")) {
				@fastcgi_finish_request();
			}
		}

		// — public index API (fail-open) —

		public static function indexPage(array $page): void {
			if (!self::isEnabled()) {
				return;
			}

			try {
				$id = (int)($page["id"] ?? 0);

				if ($id < 1) {
					return;
				}

				if (Flag::isOn($page["archived"] ?? "")) {
					self::deletePage($id);

					return;
				}

				$title = trim((string)($page["nav_title"] ?? $page["title"] ?? ""));
				$body = self::plainTextFromPage($page);
				$text = trim($title . "\n" . (string)($page["path"] ?? "") . "\n"
					. (string)($page["meta_description"] ?? "") . "\n" . $body);

				self::upsertChunks("page", (string)$id, $title, $text, [
					"module_id" => null,
					"module_route" => null,
					"table_name" => "bigtree_pages",
				]);
			} catch (\Throwable $e) {
				self::recordError($e->getMessage());
			}
		}

		public static function deletePage(int $id): void {
			if (!self::tableReady()) {
				return;
			}

			try {
				SQL::query(
					"DELETE FROM `" . self::TABLE . "` WHERE source_type = ? AND source_id = ?",
					"page",
					(string)$id
				);
			} catch (\Throwable $e) {
				self::recordError($e->getMessage());
			}
		}

		public static function indexSetting(string $id, $value): void {
			if (!self::isEnabled()) {
				return;
			}

			if (strpos($id, "bigtree-internal-") === 0) {
				return;
			}

			try {
				$def = BigTreeJSONDB::get("settings", $id);

				if (!$def || !empty($def["encrypted"]) || !empty($def["system"])) {
					return;
				}

				$title = trim((string)($def["name"] ?? $id));
				$text = self::plainTextFromValue($value);

				if ($text === "") {
					self::deleteSetting($id);

					return;
				}

				self::upsertChunks("setting", $id, $title, $title . "\n" . $text, [
					"module_id" => null,
					"module_route" => null,
					"table_name" => "bigtree_settings",
				]);
			} catch (\Throwable $e) {
				self::recordError($e->getMessage());
			}
		}

		public static function deleteSetting(string $id): void {
			if (!self::tableReady()) {
				return;
			}

			try {
				SQL::query(
					"DELETE FROM `" . self::TABLE . "` WHERE source_type = ? AND source_id = ?",
					"setting",
					$id
				);
			} catch (\Throwable $e) {
				self::recordError($e->getMessage());
			}
		}

		public static function indexModuleEntry(string $table, $entry_id, array $row = []): void {
			if (!self::isEnabled()) {
				return;
			}

			if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
				return;
			}

			try {
				if (!$row) {
					$row = SQL::fetch("SELECT * FROM `$table` WHERE id = ?", $entry_id) ?: [];
				}

				if (!$row) {
					self::deleteModuleEntry($table, $entry_id);

					return;
				}

				// Archived content is off the site, so it is out of the index —
				// mirroring indexPage, which has refused archived pages since the
				// index shipped. Nothing removed an archived entry's vector, so it
				// stayed semantically searchable forever and came back to the model
				// looking like live content.
				if (Flag::isOn($row["archived"] ?? "")) {
					self::deleteModuleEntry($table, $entry_id);

					return;
				}

				$meta = self::moduleMetaForTable($table);
				$title = self::guessRowTitle($row);
				$text = self::plainTextFromRow($row, $table);

				if ($text === "" && $title === "") {
					return;
				}

				self::upsertChunks(
					"module_entry",
					$table . ":" . $entry_id,
					$title !== "" ? $title : (string)$entry_id,
					($title !== "" ? $title . "\n" : "") . $text,
					[
						"module_id" => $meta["id"] ?? null,
						"module_route" => $meta["route"] ?? null,
						"table_name" => $table,
					]
				);
			} catch (\Throwable $e) {
				self::recordError($e->getMessage());
			}
		}

		public static function deleteModuleEntry(string $table, $entry_id): void {
			if (!self::tableReady()) {
				return;
			}

			try {
				SQL::query(
					"DELETE FROM `" . self::TABLE . "` WHERE source_type = ? AND source_id = ?",
					"module_entry",
					$table . ":" . $entry_id
				);
			} catch (\Throwable $e) {
				self::recordError($e->getMessage());
			}
		}

		/**
		 * Semantic nearest-neighbor search, permission-filtered.
		 *
		 * @return array{pages:list,entries:list,settings:list}
		 */
		public static function search(string $query, int $limit, $user): array {
			$empty = ["pages" => [], "entries" => [], "settings" => []];

			if (!self::isEnabled() || trim($query) === "") {
				return $empty;
			}

			$ai = new BigTreeAI();
			$q = trim($query);
			$model = $ai->resolvedEmbeddingModel();
			$vector = self::cachedQueryEmbedding($ai, $q, $model);

			if ($vector === null) {
				return $empty;
			}

			$limit = max(1, min(50, $limit));

			try {
				$rows = self::distanceQuery($vector, $model, $limit * 3);
			} catch (\Throwable $e) {
				self::recordError($e->getMessage());

				return $empty;
			}

			// Batch-fetch the page rows referenced by page hits to avoid N+1 lookups.
			$page_rows = self::fetchPageRows($rows);
			// Same, for module-entry hits: the archived flag and the group-based
			// permission field both live on the row, and neither is in the index.
			$entry_rows = self::fetchEntryRows($rows);

			$out = $empty;
			$seen_pages = [];
			$seen_entries = [];
			$seen_settings = [];

			foreach ($rows as $row) {
				$type = (string)($row["source_type"] ?? "");

				if ($type === "page") {
					$id = (int)$row["source_id"];

					if (isset($seen_pages[$id]) || !PermissionService::userHasPageAccess($user, $id, "v")) {
						continue;
					}

					$page = $page_rows[$id] ?? null;

					if (!$page || Flag::isOn($page["archived"] ?? "")) {
						continue;
					}

					$seen_pages[$id] = true;
					$out["pages"][] = [
						"id" => (int)$page["id"],
						"nav_title" => $page["nav_title"] ?: (string)($row["title"] ?? ""),
						"path" => $page["path"],
						"archived" => false,
						// Cosine distance from the query vector (lower = closer). Used by SearchService ranking.
						"_distance" => isset($row["dist"]) ? (float)$row["dist"] : null,
					];
				} elseif ($type === "module_entry") {
					$source = (string)$row["source_id"];
					$parts = explode(":", $source, 2);

					if (count($parts) !== 2) {
						continue;
					}

					[$table, $eid] = $parts;
					$mid = (string)($row["module_id"] ?? "");

					if ($mid === "" || !PermissionService::userHasModuleAccess($user, $mid, "v")) {
						continue;
					}

					$key = $source;

					if (isset($seen_entries[$key])) {
						continue;
					}

					// The live row, not the indexed copy: a vector outlives the row it
					// describes (deleted or archived since it was written), and the
					// group field the per-row permission reads is not in the index.
					$entry_row = $entry_rows[$key] ?? null;

					if (!$entry_row || Flag::isOn($entry_row["archived"] ?? "")) {
						continue;
					}

					$route = (string)($row["module_route"] ?? "");
					$name = $route;
					// $mid is non-empty here (empty values continue above).
					$mod = BigTreeJSONDB::get("modules", $mid);

					if ($mod) {
						$name = (string)($mod["name"] ?? $route);
						$route = (string)($mod["route"] ?? $route);
					}

					// Per-row group-based permission, the same check the keyword path
					// applies (SearchService::searchModuleEntries) and the same one
					// REST's own list uses. userHasModuleAccess above answers "may this
					// user open the module at all", which on a group-based module is
					// the best of any group grant — so an editor scoped to one group
					// passed it for every other group's rows. Applied before the cap,
					// so the cap counts rows the caller may actually see.
					$filter_rows = $mod && !empty($mod["gbp"]["enabled"])
						&& PermissionService::level($user) === 0;

					if ($filter_rows && PermissionService::userRowLevel($user, $mod, $entry_row) === "n") {
						continue;
					}

					$seen_entries[$key] = true;
					$entry_item = [
						"id" => $eid,
						"column1" => (string)($row["title"] ?? $eid),
						"column2" => (string)($row["title"] ?? ""),
						"_distance" => isset($row["dist"]) ? (float)$row["dist"] : null,
					];

					// Group under module for SPA shape.
					$found = false;

					foreach ($out["entries"] as &$group) {
						if ((string)$group["module"]["id"] === $mid) {
							$group["items"][] = $entry_item;
							$found = true;

							break;
						}
					}
					unset($group);

					if (!$found) {
						$out["entries"][] = [
							"module" => ["id" => $mid, "name" => $name, "route" => $route],
							"items" => [$entry_item],
						];
					}
				} elseif ($type === "setting" && (int)$user->level >= 1) {
					$sid = (string)$row["source_id"];

					if (isset($seen_settings[$sid])) {
						continue;
					}

					$seen_settings[$sid] = true;
					$out["settings"][] = [
						"id" => $sid,
						"name" => (string)($row["title"] ?? $sid),
						"_distance" => isset($row["dist"]) ? (float)$row["dist"] : null,
					];
				}

				if (count($out["pages"]) + count($out["entries"]) + count($out["settings"]) >= $limit) {
					break;
				}
			}

			return $out;
		}

		/**
		 * One page of a full reindex (used by migration 507 + configure rebuild).
		 *
		 * @return array{complete:bool,page:int,pages:int,indexed:int,response:string}
		 */
		public static function reindexAllBatch(int $page = 0, int $batch_size = self::REINDEX_BATCH): array {
			if (!self::tableReady()) {
				return [
					"complete" => true,
					"page" => 0,
					"pages" => 0,
					"indexed" => 0,
					"response" => "Embeddings table not available",
				];
			}

			$ai = new BigTreeAI();

			if (!$ai->isEmbeddingsConfigured()) {
				return [
					"complete" => true,
					"page" => 0,
					"pages" => 0,
					"indexed" => 0,
					"response" => "Embeddings not configured — enable in Configure → AI",
				];
			}

			$segments = self::buildReindexSegments();
			$total_pages = 0;

			foreach ($segments as $seg) {
				if ($seg["count"] > 0) {
					$total_pages += (int)ceil($seg["count"] / $batch_size);
				}
			}

			if ($total_pages < 1) {
				self::writeStatus(["last_backfill_at" => date("Y-m-d H:i:s")]);

				return [
					"complete" => true,
					"page" => 0,
					"pages" => 0,
					"indexed" => 0,
					"response" => "Nothing to index",
				];
			}

			// page=0 is the "how many pages" probe used by migrations.
			if ($page < 1) {
				return [
					"complete" => false,
					"page" => 0,
					"pages" => $total_pages,
					"indexed" => 0,
					"response" => "AI embeddings reindex ($total_pages batches)",
				];
			}

			$resolved = self::resolveReindexPage($page, $segments, $batch_size);

			if (!$resolved) {
				self::writeStatus(["last_backfill_at" => date("Y-m-d H:i:s")]);

				return [
					"complete" => true,
					"page" => $page,
					"pages" => $total_pages,
					"indexed" => 0,
					"response" => "AI embeddings reindex complete",
				];
			}

			$indexed = self::processReindexBatch($resolved["segment"], $resolved["batch"], $batch_size);
			$complete = $page >= $total_pages;

			if ($complete) {
				self::writeStatus(["last_backfill_at" => date("Y-m-d H:i:s")]);
			}

			$status = BigTreeCMS::getSetting(self::STATUS_SETTING);
			$last_error = is_array($status) ? (string)($status["last_error"] ?? "") : "";
			$response = $complete
				? "AI embeddings reindex complete"
				: "Indexed batch $page / $total_pages (" . $resolved["segment"]["label"] . ")";

			if ($complete && $last_error !== "") {
				$response .= " — last error: " . $last_error;
			}

			return [
				"complete" => $complete,
				"page" => $page,
				"pages" => $total_pages,
				"indexed" => $indexed,
				"response" => $response,
			];
		}

		/** Configure API: one reindex page. */
		public function reindex(Request $request) {
			if (!self::isSupported()) {
				throw new BadRequestException(
					"Vector embeddings require MySQL 9+ or MariaDB 11.7+",
					"embeddings_unsupported"
				);
			}

			if (!self::tableReady() && !self::ensureTable()) {
				throw new BadRequestException(
					"Embeddings table could not be created — check database permissions",
					"embeddings_table_missing"
				);
			}

			$page = max(0, (int)($request->body["page"] ?? $request->query["page"] ?? 0));
			$result = self::reindexAllBatch($page, self::REINDEX_BATCH);

			return Response::ok($result);
		}

		// — internals —

		/**
		 * Embed a search query, reusing a short-TTL cache keyed on model + text so
		 * a debounced quick-search doesn't pay for the same embed on every keystroke.
		 *
		 * @return list<float>|null Null when the query is blank or the embed failed.
		 */
		private static function cachedQueryEmbedding(BigTreeAI $ai, string $query, string $model): ?array {
			if ($query === "") {
				return null;
			}

			$cache_key = hash("sha256", $model . "\n" . $query);
			$cached = BigTreeCMS::cacheGet(self::QUERY_CACHE, $cache_key, self::QUERY_CACHE_TTL);

			if (is_array($cached) && isset($cached[0])) {
				return array_map("floatval", $cached);
			}

			$vectors = $ai->embed($query);

			if ($vectors === false || empty($vectors[0])) {
				return null;
			}

			BigTreeCMS::cachePut(self::QUERY_CACHE, $cache_key, $vectors[0]);

			return $vectors[0];
		}

		/**
		 * Fetch every page row referenced by page-type hits in one query (avoids an
		 * N+1 SELECT per result).
		 *
		 * @param list<array<string,mixed>> $rows
		 * @return array<int,array<string,mixed>> Keyed by page id.
		 */
		private static function fetchPageRows(array $rows): array {
			$ids = [];

			foreach ($rows as $row) {
				if ((string)($row["source_type"] ?? "") === "page") {
					$id = (int)($row["source_id"] ?? 0);

					if ($id > 0) {
						$ids[$id] = true;
					}
				}
			}

			if (!$ids) {
				return [];
			}

			$ids = array_keys($ids);
			$placeholders = implode(",", array_fill(0, count($ids), "?"));
			$page_rows = SQL::fetchAll(
				"SELECT id, nav_title, path, archived FROM bigtree_pages WHERE id IN ($placeholders)",
				...$ids
			) ?: [];
			$map = [];

			foreach ($page_rows as $page) {
				$map[(int)$page["id"]] = $page;
			}

			return $map;
		}

		/**
		 * Fetch every module-entry row referenced by module_entry hits, one query per
		 * distinct table (avoids an N+1 SELECT per result).
		 *
		 * Only the columns the read filter needs are selected: `id`, `archived` where
		 * the table has it, and the group field of every group-based module that maps
		 * to the table — `SELECT *` here would drag every body column of up to 150
		 * rows through memory to answer two boolean questions.
		 *
		 * @param list<array<string,mixed>> $rows
		 * @return array<string,array<string,mixed>> Keyed by the hit's "table:id" source id.
		 */
		private static function fetchEntryRows(array $rows): array {
			$wanted = [];

			foreach ($rows as $row) {
				if ((string)($row["source_type"] ?? "") !== "module_entry") {
					continue;
				}

				$parts = explode(":", (string)($row["source_id"] ?? ""), 2);

				if (count($parts) !== 2 || !preg_match('/^[a-zA-Z0-9_]+$/', $parts[0]) || $parts[1] === "") {
					continue;
				}

				$wanted[$parts[0]][$parts[1]] = true;
			}

			if (!$wanted) {
				return [];
			}

			$group_fields = self::groupFieldsByTable(array_keys($wanted));
			$map = [];
			// describeTable is a SHOW CREATE TABLE per call and this is the debounced
			// quick-search path, so the shapes are memoized for the request.
			static $described = [];

			foreach ($wanted as $table => $ids) {
				if (!array_key_exists($table, $described)) {
					try {
						$described[$table] = SQL::describeTable($table);
					} catch (\Throwable $e) {
						$described[$table] = null;
					}
				}

				$description = $described[$table];

				if (!$description || !isset($description["columns"]["id"])) {
					continue;
				}

				$columns = ["id"];

				foreach (array_merge(["archived"], $group_fields[$table] ?? []) as $column) {
					// The group field comes out of a module's JSON definition, so it is
					// checked against the table's real columns *and* the identifier
					// pattern before it is concatenated into a SELECT.
					if (isset($description["columns"][$column]) && !in_array($column, $columns, true)
						&& preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
						$columns[] = $column;
					}
				}

				$ids = array_keys($ids);
				$select = "`" . implode("`, `", $columns) . "`";
				$placeholders = implode(",", array_fill(0, count($ids), "?"));

				try {
					$fetched = SQL::fetchAll(
						"SELECT $select FROM `$table` WHERE id IN ($placeholders)",
						...$ids
					) ?: [];
				} catch (\Throwable $e) {
					continue;
				}

				foreach ($fetched as $entry) {
					$map[$table . ":" . $entry["id"]] = $entry;
				}
			}

			return $map;
		}

		/**
		 * The group-based-permission group field(s) each of these tables is subject
		 * to. A table can back more than one module, so the value is a list.
		 *
		 * @param list<string> $tables
		 * @return array<string,list<string>>
		 */
		private static function groupFieldsByTable(array $tables): array {
			$fields = [];

			foreach (BigTreeJSONDB::getAll("modules") as $module) {
				// Same shape-tolerance as PermissionService::userRowLevel — a module
				// definition is hand-editable JSON, so nothing here assumes a key is
				// the type it ought to be.
				$gbp = is_array($module["gbp"] ?? null) ? $module["gbp"] : [];
				$group_field = (string)($gbp["group_field"] ?? "");

				if (empty($gbp["enabled"]) || $group_field === "") {
					continue;
				}

				$views = is_array($module["views"] ?? null) ? $module["views"] : [];
				$forms = is_array($module["forms"] ?? null) ? $module["forms"] : [];

				foreach (array_merge($views, $forms) as $sub) {
					$table = is_array($sub) ? (string)($sub["table"] ?? "") : "";

					if ($table !== "" && in_array($table, $tables, true)
						&& !in_array($group_field, $fields[$table] ?? [], true)) {
						$fields[$table][] = $group_field;
					}
				}
			}

			return $fields;
		}

		/**
		 * @param list<float> $vector
		 * @return list<array<string,mixed>>
		 */
		private static function distanceQuery(array $vector, string $model, int $limit): array {
			$literal = self::vectorToLiteral($vector);
			$dialect = BigTreeAI::vectorDialect();
			$limit = max(1, min(100, $limit));

			if ($dialect === "mariadb") {
				$sql = "SELECT source_type, source_id, module_id, module_route, table_name, title, chunk_index,
					VEC_DISTANCE_COSINE(embedding, VEC_FromText(?)) AS dist
					FROM `" . self::TABLE . "`
					WHERE model = ?
					ORDER BY dist ASC
					LIMIT $limit";
			} else {
				$sql = "SELECT source_type, source_id, module_id, module_route, table_name, title, chunk_index,
					DISTANCE(embedding, STRING_TO_VECTOR(?), 'COSINE') AS dist
					FROM `" . self::TABLE . "`
					WHERE model = ?
					ORDER BY dist ASC
					LIMIT $limit";
			}

			return SQL::fetchAll($sql, $literal, $model) ?: [];
		}

		/**
		 * @param array<string,mixed> $meta
		 */
		private static function upsertChunks(
			string $source_type,
			string $source_id,
			string $title,
			string $text,
			array $meta
		): void {
			$ai = new BigTreeAI();
			$model = $ai->resolvedEmbeddingModel();

			if ($model === "" || !$ai->isEmbeddingsConfigured()) {
				return;
			}

			$chunks = self::chunkText($text);
			$existing = SQL::fetchAll(
				"SELECT chunk_index, content_hash FROM `" . self::TABLE . "`
				 WHERE source_type = ? AND source_id = ? AND model = ?",
				$source_type,
				$source_id,
				$model
			) ?: [];
			$existing_by_chunk = [];

			foreach ($existing as $row) {
				$existing_by_chunk[(int)$row["chunk_index"]] = (string)$row["content_hash"];
			}

			$keep = [];
			$to_embed = [];
			$to_embed_idx = [];

			foreach ($chunks as $i => $chunk) {
				$hash = hash("sha256", $model . "\n" . $chunk);
				$keep[] = $i;

				if (($existing_by_chunk[$i] ?? null) === $hash) {
					continue;
				}

				$to_embed[] = $chunk;
				$to_embed_idx[] = $i;
			}

			// Drop obsolete chunks.
			if ($keep) {
				$placeholders = implode(",", array_fill(0, count($keep), "?"));
				$args = array_merge([$source_type, $source_id, $model], $keep);
				SQL::query(
					"DELETE FROM `" . self::TABLE . "`
					 WHERE source_type = ? AND source_id = ? AND model = ?
					 AND chunk_index NOT IN ($placeholders)",
					...$args
				);
			} else {
				SQL::query(
					"DELETE FROM `" . self::TABLE . "` WHERE source_type = ? AND source_id = ? AND model = ?",
					$source_type,
					$source_id,
					$model
				);

				return;
			}

			if (!$to_embed) {
				return;
			}

			$vectors = $ai->embed($to_embed);

			if ($vectors === false) {
				self::recordError($ai->Error ?: "embed failed");

				return;
			}

			foreach ($to_embed_idx as $j => $chunk_index) {
				$chunk = $to_embed[$j];
				$vec = $vectors[$j] ?? null;

				if (!is_array($vec)) {
					continue;
				}

				// Pad/truncate to fixed dimensions.
				$vec = self::normalizeDimensions($vec);
				$hash = hash("sha256", $model . "\n" . $chunk);
				self::writeChunkRow(
					$source_type,
					$source_id,
					$chunk_index,
					$title,
					$chunk,
					$hash,
					$vec,
					$model,
					$meta
				);
			}
		}

		/**
		 * @param list<float> $vector
		 * @param array<string,mixed> $meta
		 */
		private static function writeChunkRow(
			string $source_type,
			string $source_id,
			int $chunk_index,
			string $title,
			string $content,
			string $hash,
			array $vector,
			string $model,
			array $meta
		): void {
			$literal = self::vectorToLiteral($vector);
			$dialect = BigTreeAI::vectorDialect();
			$from_fn = $dialect === "mariadb" ? "VEC_FromText" : "STRING_TO_VECTOR";
			$title = mb_substr($title, 0, 500);

			// Single upsert on the source_chunk_model unique key — avoids the
			// SELECT-then-write race where two concurrent saves of the same entry
			// both miss the SELECT and collide on INSERT.
			SQL::query(
				"INSERT INTO `" . self::TABLE . "`
					(source_type, source_id, module_id, module_route, table_name,
					 chunk_index, title, content_text, content_hash, embedding, model, updated_at)
				 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, $from_fn(?), ?, NOW())
				 ON DUPLICATE KEY UPDATE
					module_id = ?, module_route = ?, table_name = ?,
					title = ?, content_text = ?, content_hash = ?,
					embedding = $from_fn(?), updated_at = NOW()",
				// INSERT values
				$source_type,
				$source_id,
				$meta["module_id"] ?? null,
				$meta["module_route"] ?? null,
				$meta["table_name"] ?? null,
				$chunk_index,
				$title,
				$content,
				$hash,
				$literal,
				$model,
				// ON DUPLICATE KEY UPDATE values
				$meta["module_id"] ?? null,
				$meta["module_route"] ?? null,
				$meta["table_name"] ?? null,
				$title,
				$content,
				$hash,
				$literal
			);
		}

		/** @param list<float> $vector */
		private static function vectorToLiteral(array $vector): string {
			$parts = [];

			foreach ($vector as $v) {
				$parts[] = rtrim(rtrim(sprintf("%.8F", (float)$v), "0"), ".");
			}

			return "[" . implode(",", $parts) . "]";
		}

		/** @param list<float> $vector @return list<float> */
		private static function normalizeDimensions(array $vector): array {
			$n = BigTreeAI::EMBEDDING_DIMENSIONS;

			if (count($vector) === $n) {
				return $vector;
			}

			if (count($vector) > $n) {
				return array_slice($vector, 0, $n);
			}

			return array_pad($vector, $n, 0.0);
		}

		/** @return list<string> */
		private static function chunkText(string $text): array {
			$text = trim(preg_replace('/\s+/u', " ", $text) ?? $text);

			if ($text === "") {
				return [];
			}

			if (mb_strlen($text) <= self::CHUNK_SOFT_MAX) {
				return [$text];
			}

			$chunks = [];
			$len = mb_strlen($text);
			$offset = 0;

			while ($offset < $len) {
				$chunks[] = mb_substr($text, $offset, self::CHUNK_SIZE);
				$offset += self::CHUNK_SIZE - self::CHUNK_OVERLAP;

				if (count($chunks) >= 20) {
					break;
				}
			}

			return $chunks;
		}

		public static function plainTextFromPage(array $page): string {
			$resources = $page["resources"] ?? [];

			if (is_string($resources)) {
				$resources = Json::decode($resources);
			}

			$chunks = [];
			self::collectPlainText($resources, $chunks);

			return trim(implode(" ", $chunks));
		}

		private static function plainTextFromValue($value): string {
			if (is_string($value) || is_numeric($value)) {
				return trim(html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, "UTF-8"));
			}

			if (is_array($value)) {
				$chunks = [];
				self::collectPlainText($value, $chunks);

				return trim(implode(" ", $chunks));
			}

			return "";
		}

		private static function plainTextFromRow(array $row, string $table): string {
			$columns = self::textColumnsForTable($table);
			$chunks = [];

			foreach ($columns as $col) {
				if (!array_key_exists($col, $row)) {
					continue;
				}

				$val = $row[$col];

				if (is_string($val) && $val !== "" && ($val[0] === "{" || $val[0] === "[")) {
					$decoded = json_decode($val, true);

					if (is_array($decoded)) {
						self::collectPlainText($decoded, $chunks);

						continue;
					}
				}

				$text = self::plainTextFromValue($val);

				if ($text !== "") {
					$chunks[] = $text;
				}
			}

			// Fallback: all string columns if form fields unknown.
			if (!$chunks) {
				foreach ($row as $key => $val) {
					if ($key === "id" || is_array($val) || is_object($val)) {
						continue;
					}

					$text = self::plainTextFromValue($val);

					if ($text !== "" && mb_strlen($text) > 2) {
						$chunks[] = $text;
					}
				}
			}

			return trim(implode(" ", $chunks));
		}

		/**
		 * Recursively collect stripped plain-text leaves from a decoded resource
		 * tree. Shared with SearchService (which used to keep a copy-paste twin).
		 *
		 * @param list<string> $chunks
		 */
		public static function collectPlainText($value, array &$chunks): void {
			if (is_string($value)) {
				$stripped = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, "UTF-8"));

				if ($stripped !== "") {
					$chunks[] = $stripped;
				}

				return;
			}

			if (is_array($value)) {
				foreach ($value as $child) {
					self::collectPlainText($child, $chunks);
				}
			}
		}

		/** @return list<string> */
		private static function textColumnsForTable(string $table): array {
			static $cache = [];

			if (isset($cache[$table])) {
				return $cache[$table];
			}

			$cols = [];

			foreach (BigTreeJSONDB::getAll("modules") as $m) {
				foreach (array_merge($m["forms"] ?? [], $m["embeddable-forms"] ?? []) as $form) {
					if (($form["table"] ?? "") !== $table) {
						continue;
					}

					foreach ($form["fields"] ?? [] as $field) {
						$type = (string)($field["type"] ?? "");
						$col = (string)($field["column"] ?? "");

						if ($col !== "" && in_array($type, self::TEXT_FIELD_TYPES, true)) {
							$cols[] = $col;
						}
					}
				}
			}

			$cache[$table] = array_values(array_unique($cols));

			return $cache[$table];
		}

		/** @return array{id?:string,route?:string,name?:string} */
		private static function moduleMetaForTable(string $table): array {
			foreach (BigTreeJSONDB::getAll("modules") as $m) {
				foreach ($m["views"] ?? [] as $view) {
					if (($view["table"] ?? "") === $table) {
						return [
							"id" => (string)($m["id"] ?? ""),
							"route" => (string)($m["route"] ?? ""),
							"name" => (string)($m["name"] ?? ""),
						];
					}
				}

				foreach ($m["forms"] ?? [] as $form) {
					if (($form["table"] ?? "") === $table) {
						return [
							"id" => (string)($m["id"] ?? ""),
							"route" => (string)($m["route"] ?? ""),
							"name" => (string)($m["name"] ?? ""),
						];
					}
				}
			}

			return [];
		}

		private static function guessRowTitle(array $row): string {
			foreach (["title", "name", "nav_title", "headline", "subject"] as $key) {
				if (!empty($row[$key]) && is_scalar($row[$key])) {
					return trim((string)$row[$key]);
				}
			}

			return "";
		}

		/** @return list<array{type:string,label:string,count:int,table?:string}> */
		private static function buildReindexSegments(): array {
			$segments = [
				[
					"type" => "pages",
					"label" => "pages",
					"count" => (int)SQL::fetchSingle(
						"SELECT COUNT(*) FROM bigtree_pages WHERE archived = '' OR archived IS NULL OR archived = 'off'"
					),
				],
			];

			$settings = BigTreeJSONDB::getAll("settings");
			$public = 0;

			foreach ($settings as $s) {
				if (strpos((string)($s["id"] ?? ""), "bigtree-internal-") === 0) {
					continue;
				}

				if (!empty($s["encrypted"]) || !empty($s["system"])) {
					continue;
				}

				$public++;
			}

			$segments[] = ["type" => "settings", "label" => "settings", "count" => $public];

			$seen = [];

			foreach (BigTreeJSONDB::getAll("modules") as $m) {
				foreach (array_merge($m["views"] ?? [], $m["forms"] ?? []) as $piece) {
					$table = (string)($piece["table"] ?? "");

					if ($table === "" || isset($seen[$table]) || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
						continue;
					}

					if (!SQL::tableExists($table)) {
						continue;
					}

					$seen[$table] = true;
					$segments[] = [
						"type" => "module_table",
						"label" => "module $table",
						"table" => $table,
						"count" => (int)SQL::fetchSingle("SELECT COUNT(*) FROM `$table`"),
					];
				}
			}

			return $segments;
		}

		/**
		 * @param list<array{type:string,label:string,count:int,table?:string}> $segments
		 * @return array{segment:array,batch:int}|null
		 */
		private static function resolveReindexPage(int $page, array $segments, int $batch_size): ?array {
			$page_index = 1;

			foreach ($segments as $segment) {
				if ($segment["count"] <= 0) {
					continue;
				}

				$segment_pages = (int)ceil($segment["count"] / $batch_size);

				if ($page <= $page_index + $segment_pages - 1) {
					return [
						"segment" => $segment,
						"batch" => $page - $page_index + 1,
					];
				}

				$page_index += $segment_pages;
			}

			return null;
		}

		/** @param array{type:string,label:string,count:int,table?:string} $segment */
		private static function processReindexBatch(array $segment, int $batch, int $batch_size): int {
			$offset = max(0, ($batch - 1) * $batch_size);
			$indexed = 0;

			if ($segment["type"] === "pages") {
				$rows = SQL::fetchAll(
					"SELECT * FROM bigtree_pages
					 WHERE archived = '' OR archived IS NULL OR archived = 'off'
					 ORDER BY id ASC LIMIT $batch_size OFFSET $offset"
				) ?: [];

				foreach ($rows as $page) {
					self::indexPage($page);
					$indexed++;
				}
			} elseif ($segment["type"] === "settings") {
				$all = [];

				foreach (BigTreeJSONDB::getAll("settings") as $s) {
					$id = (string)($s["id"] ?? "");

					if ($id === "" || strpos($id, "bigtree-internal-") === 0) {
						continue;
					}

					if (!empty($s["encrypted"]) || !empty($s["system"])) {
						continue;
					}

					$all[] = $id;
				}

				$slice = array_slice($all, $offset, $batch_size);

				foreach ($slice as $id) {
					$row = SettingService::readSetting($id);
					$value = is_array($row) ? ($row["value"] ?? "") : "";
					self::indexSetting($id, $value);
					$indexed++;
				}
			} elseif ($segment["type"] === "module_table") {
				$table = (string)($segment["table"] ?? "");

				if ($table === "" || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
					return 0;
				}

				$rows = SQL::fetchAll(
					"SELECT * FROM `$table` ORDER BY id ASC LIMIT $batch_size OFFSET $offset"
				) ?: [];

				foreach ($rows as $row) {
					self::indexModuleEntry($table, $row["id"] ?? 0, $row);
					$indexed++;
				}
			}

			return $indexed;
		}

		/** @param array<string,mixed> $patch */
		private static function writeStatus(array $patch): void {
			$current = BigTreeCMS::getSetting(self::STATUS_SETTING);

			if (!is_array($current)) {
				$current = [];
			}

			$next = array_merge([
				"supported" => self::isSupported(),
				"table_ready" => self::tableReady(),
				"dimensions" => BigTreeAI::EMBEDDING_DIMENSIONS,
				"last_backfill_at" => null,
				"last_error" => null,
			], $current, $patch);

			SettingService::updateInternalValue(self::STATUS_SETTING, $next, false);
		}

		private static function recordError(string $message): void {
			self::writeStatus([
				"last_error" => mb_substr($message, 0, 500),
			]);
		}
	}
