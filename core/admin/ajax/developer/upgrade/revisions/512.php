<?php
	// BigTree 5.0 — utf8mb4 everywhere (plans/utf8mb4-migration.md)
	//
	// Every connection used to run `SET NAMES 'utf8'` — utf8mb3 — so no 4-byte
	// character (every emoji, ranges of CJK) could cross the wire, and because
	// sql_mode is '' MySQL truncated the value at that character instead of erroring.
	// SQL::connect now sets utf8mb4; this revision brings the schema along.
	//
	// The obvious `CONVERT TO CHARACTER SET utf8mb4` fails on BigTree's own schema
	// convention rather than on anything to do with character sets: varchar(1024) is
	// the default "big enough" width and those columns are indexed directly. At
	// utf8mb3 a full-column index on one is 1024 * 3 = 3072 bytes — exactly InnoDB's
	// key prefix limit, with zero bytes to spare. At utf8mb4 it is 4096 and the ALTER
	// is rejected. So the ordering here is NOT negotiable: narrow the indexed columns
	// first, convert the table second.
	//
	// 191 is the target width: 191 * 4 = 764 bytes, which clears InnoDB's 3072-byte
	// limit and also MyISAM's much tighter 1000-byte one (a legacy module table may
	// still be MyISAM).
	//
	// Driven from information_schema rather than from a table list, so module tables,
	// extension tables and anything a site added are all covered — "everywhere" means
	// everywhere. Batched one table per page, and every step is guarded by its own
	// state check, so a run interrupted on a large table can simply be re-run.
	//
	// The database's own default charset is converted too, not only its tables —
	// that default is what a future CREATE TABLE naming no charset inherits, so
	// leaving it behind would let the estate drift straight back.
	//
	// A fresh install is born in this end state (core/setup/base.sql declares
	// utf8mb4 / utf8mb4_general_ci and the narrowed widths), so there this sweep
	// finds nothing to do and passes through as a no-op.

	use BigTree\Services\MigrationService;

	MigrationService::begin(512);

	// The width an over-wide indexed varchar narrows to, and the prefix an index part
	// that cannot narrow is cut to.
	$bigtree_512_prefix = 191;

	/**
	 * Columns that deliberately STAY wide and get a (191) prefix index instead of
	 * being narrowed, because their contents are not structurally bounded: visitor
	 * -supplied URLs, cache keys, and routes derived from a free-text title through
	 * BigTreeCMS::urlify() (which has no length cap of its own). Mirrors exactly what
	 * core/setup/base.sql declares, so an upgraded install and a fresh one converge.
	 *
	 * Every other column is measured before it is narrowed (step 2 below) — no
	 * customer's value is ever truncated to make an index fit.
	 *
	 * @return array<string,string[]> table => columns
	 */
	function bigtree_512_keep_wide(): array {
		return [
			"bigtree_404s" => ["broken_url", "get_vars", "redirect_url"],
			"bigtree_caches" => ["key"],
			"bigtree_pages" => ["route"],
			"bigtree_route_history" => ["old_route"],
			"bigtree_tags" => ["route"],
		];
	}

	/**
	 * Regenerable caches. `CONVERT TO CHARACTER SET` cannot run in place — it copies
	 * the table — and on a busy install these two are among the largest here. Both
	 * refill on demand (BigTreeAutoModule rebuilds a view cache whenever it finds it
	 * empty; a cache table is a cache), so emptying them first turns the two worst
	 * tables in this sweep into instant operations.
	 *
	 * @return string[]
	 */
	function bigtree_512_disposable(): array {
		return ["bigtree_caches", "bigtree_module_view_cache"];
	}

	/**
	 * Run one DDL statement, turning any MySQL complaint into an error that names the
	 * statement (R2 — a row-size or key-length refusal has to be readable, not a
	 * generic failure). mysqli throws by default under PHP 8; older configurations
	 * report through SQL::$ErrorLog instead, so check both.
	 */
	function bigtree_512_ddl(string $statement): void {
		$errors_before = count(SQL::$ErrorLog);

		try {
			SQL::query($statement);
		} catch (\Throwable $e) {

			throw new Exception($e->getMessage() . " — statement: " . $statement);
		}

		if (count(SQL::$ErrorLog) > $errors_before) {

			throw new Exception(SQL::$ErrorLog[count(SQL::$ErrorLog) - 1] . " — statement: " . $statement);
		}
	}

	/**
	 * Every base table in this database, name-ordered. That order is what pages this
	 * revision, so it has to be stable across pages — it is, because the list does not
	 * shrink as tables are converted (a converted table is simply a no-op when its
	 * page runs again).
	 *
	 * @return string[]
	 */
	function bigtree_512_tables(): array {
		return SQL::fetchAllSingle(
			"SELECT TABLE_NAME FROM information_schema.TABLES
			 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
			 ORDER BY TABLE_NAME ASC"
		);
	}

	/**
	 * The string columns of a table, keyed by name.
	 *
	 * @return array<string,array{type:string,length:int,charset:string,collation:string}>
	 */
	function bigtree_512_string_columns(string $table): array {
		$columns = [];
		$rows = SQL::fetchAll(
			"SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, CHARACTER_SET_NAME, COLLATION_NAME
			 FROM information_schema.COLUMNS
			 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CHARACTER_SET_NAME IS NOT NULL",
			$table
		);

		foreach ($rows as $row) {
			$columns[$row["COLUMN_NAME"]] = [
				"type" => strtolower((string)$row["DATA_TYPE"]),
				"length" => (int)$row["CHARACTER_MAXIMUM_LENGTH"],
				"charset" => (string)$row["CHARACTER_SET_NAME"],
				"collation" => (string)$row["COLLATION_NAME"],
			];
		}

		return $columns;
	}

	/**
	 * A table's indexes: name => [unique, type, columns => [[column, sub_part], ...]].
	 * SHOW INDEX rather than information_schema because it reports Sub_part in the
	 * same shape an ALTER has to re-declare it in.
	 *
	 * @return array<string,array{unique:bool,type:string,columns:array<int,array{column:?string,sub_part:?int}>}>
	 */
	function bigtree_512_indexes(string $table): array {
		$indexes = [];

		foreach (SQL::fetchAll("SHOW INDEX FROM `$table`") as $row) {
			$name = (string)$row["Key_name"];

			if (!isset($indexes[$name])) {
				$indexes[$name] = [
					"unique" => ((int)$row["Non_unique"] === 0),
					"type" => strtoupper((string)$row["Index_type"]),
					"columns" => [],
				];
			}

			$indexes[$name]["columns"][(int)$row["Seq_in_index"]] = [
				"column" => $row["Column_name"] === null ? null : (string)$row["Column_name"],
				"sub_part" => $row["Sub_part"] === null ? null : (int)$row["Sub_part"],
			];
		}

		foreach ($indexes as $name => $index) {
			ksort($indexes[$name]["columns"]);
			$indexes[$name]["columns"] = array_values($indexes[$name]["columns"]);
		}

		return $indexes;
	}

	/**
	 * Each column's definition exactly as MySQL states it, keyed by column name and
	 * including the backticked name — i.e. ready to hand to MODIFY COLUMN. Read out of
	 * SHOW CREATE TABLE rather than assembled from information_schema so NOT NULL,
	 * DEFAULT, CHARACTER SET, COLLATE, COMMENT and ON UPDATE all survive the MODIFY
	 * untouched and only the width is rewritten.
	 *
	 * @return array<string,string>
	 */
	function bigtree_512_column_definitions(string $table): array {
		$row = SQL::fetch("SHOW CREATE TABLE `$table`");
		$create = is_array($row) ? (string)end($row) : "";
		$definitions = [];

		foreach (explode("\n", $create) as $line) {
			$line = rtrim(trim($line), ",");

			// A column line starts with the backticked column name; KEY / UNIQUE KEY /
			// CONSTRAINT lines and the trailing ") ENGINE=..." never do.
			if (!preg_match('/^`((?:[^`]|``)+)`\s+\S/', $line, $match)) {
				continue;
			}

			$definitions[str_replace("``", "`", $match[1])] = $line;
		}

		return $definitions;
	}

	/**
	 * What one index costs in bytes once the table is utf8mb4, alongside the parts as
	 * an ALTER would have to re-declare them.
	 *
	 * $shrink caps every string part at $prefix characters, which is how step 3 asks
	 * "would prefixing make this fit?". `shrunk` names the columns that cap actually
	 * changed, so the caller can report or refuse them.
	 *
	 * A part on a non-string column (an int, a date) is counted as a flat 8 bytes —
	 * a deliberate over-estimate, since the answer only feeds a safety check.
	 *
	 * @param array{columns:array<int,array{column:?string,sub_part:?int}>} $index
	 * @param array<string,array{type:string,length:int,charset:string,collation:string}> $columns
	 *
	 * @return array{bytes:int,parts:string[],shrunk:string[],functional:bool}
	 */
	function bigtree_512_index_cost(array $index, array $columns, int $prefix, bool $shrink): array {
		$bytes = 0;
		$parts = [];
		$shrunk = [];

		foreach ($index["columns"] as $part) {
			$column = $part["column"];

			// An expression index has no column to re-declare.
			if ($column === null) {

				return ["bytes" => 0, "parts" => [], "shrunk" => [], "functional" => true];
			}

			$length = $part["sub_part"];

			if (isset($columns[$column])) {
				$effective = $length ?? $columns[$column]["length"];

				if ($shrink && $effective > $prefix) {
					$effective = $length = $prefix;
					$shrunk[] = $column;
				}

				// Every string column is 4 bytes per character once converted.
				$bytes += $effective * 4;
			} else {
				$bytes += 8;
			}

			$parts[] = "`$column`" . ($length === null ? "" : "($length)");
		}

		return ["bytes" => $bytes, "parts" => $parts, "shrunk" => $shrunk, "functional" => false];
	}

	/**
	 * Is this table already utf8mb4 (whatever its collation)?
	 */
	function bigtree_512_is_utf8mb4(string $table): bool {
		$collation = (string)SQL::fetchSingle(
			"SELECT TABLE_COLLATION FROM information_schema.TABLES
			 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
			$table
		);

		if (strpos($collation, "utf8mb4_") !== 0) {

			return false;
		}

		foreach (bigtree_512_string_columns($table) as $column) {
			if ($column["charset"] !== "utf8mb4") {

				return false;
			}
		}

		return true;
	}

	/**
	 * Does this table still need converting? True unless the table default and every
	 * string column are already utf8mb4 / utf8mb4_general_ci.
	 */
	function bigtree_512_needs_conversion(string $table): bool {
		$collation = (string)SQL::fetchSingle(
			"SELECT TABLE_COLLATION FROM information_schema.TABLES
			 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
			$table
		);

		if ($collation !== "utf8mb4_general_ci") {

			return true;
		}

		foreach (bigtree_512_string_columns($table) as $column) {
			if ($column["collation"] !== "utf8mb4_general_ci") {

				return true;
			}
		}

		return false;
	}

	/**
	 * One column's definition with its CHARACTER SET / COLLATE clause removed and
	 * everything else — type, nullability, default, comment, ON UPDATE — left exactly
	 * as MySQL states it. Returns null if the definition cannot be parsed.
	 *
	 * A MODIFY COLUMN that names no character set takes the TABLE's default, which is
	 * how step 4 converts a column without recording a per-column charset a fresh
	 * install would not have. Both clauses sit directly after the type, so they are
	 * stripped from the front of the remainder rather than by searching the whole
	 * definition — a quoted DEFAULT can contain anything.
	 */
	function bigtree_512_definition_without_charset(string $definition): ?string {
		if (!preg_match('/^(`(?:[^`]|``)+`\s+)([a-z]+(?:\([^)]*\))?)(.*)$/is', $definition, $match)) {

			return null;
		}

		$rest = preg_replace('/^\s+CHARACTER SET \S+/i', "", $match[3]);
		$rest = preg_replace('/^\s+COLLATE \S+/i', "", $rest);

		return $match[1].$match[2].$rest;
	}

	/**
	 * Can this table be converted column-by-column, or does it need MySQL's own
	 * CONVERT TO (and the type widening that comes with it)?
	 *
	 * utf8mb3, utf8mb4 and ascii all encode the same characters in the same bytes at
	 * or below the BMP, so re-declaring a column as utf8mb4 cannot make a stored value
	 * longer. latin1 (and friends) transcode to MORE bytes, so a TEXT column near its
	 * 65,535-byte ceiling really can need the larger type MySQL picks — and sql_mode
	 * is '' here, so a type that no longer fits would truncate rather than error. Let
	 * MySQL decide in that case.
	 */
	function bigtree_512_convertible_in_place(string $table): bool {
		foreach (bigtree_512_string_columns($table) as $column) {
			if (!in_array($column["charset"], ["utf8mb3", "utf8", "utf8mb4", "ascii"], true)) {

				return false;
			}
		}

		return true;
	}

	/**
	 * The one table this sweep leaves at whatever utf8mb4 collation it already has.
	 *
	 * `bigtree_ai_embeddings` has been utf8mb4 since it was created, so a CONVERT TO
	 * would only swap general_ci for the server default collation it picked up (on
	 * MySQL 8+, utf8mb4_0900_ai_ci) — and it is the one table here that can be
	 * gigabytes, carries a VECTOR column plus (on MariaDB) a vector index, and is
	 * never joined to anything on a string column, so there is no "illegal mix of
	 * collations" to avoid. Rebuilding it would be all cost. A fresh install creates
	 * it as utf8mb4_general_ci; this is the one place the two paths differ.
	 */
	function bigtree_512_collation_only_skip(string $table): bool {
		return ($table === "bigtree_ai_embeddings" && bigtree_512_is_utf8mb4($table));
	}

	/**
	 * Narrow, re-prefix and convert ONE table. Returns what it did (an empty list when
	 * the table was already in the end state), and throws with a message naming the
	 * table when it cannot proceed without either losing data or changing what an
	 * index means.
	 *
	 * @return string[]
	 */
	function bigtree_512_convert_table(string $table, int $prefix): array {
		$actions = [];
		$engine = strtoupper((string)SQL::fetchSingle(
			"SELECT ENGINE FROM information_schema.TABLES
			 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
			$table
		));

		// MyISAM's key limit is 1000 bytes, not InnoDB's 3072 — a real install's
		// legacy module tables may still be MyISAM.
		$key_limit = ($engine === "MYISAM") ? 1000 : 3072;

		// R1: empty the regenerable caches before touching them, so the table copy the
		// ALTERs below force is a copy of nothing.
		if (in_array($table, bigtree_512_disposable(), true) && SQL::fetchSingle("SELECT COUNT(*) FROM `$table`")) {
			bigtree_512_ddl("TRUNCATE TABLE `$table`");
			$actions[] = "emptied (regenerable cache)";
		}

		$columns = bigtree_512_string_columns($table);
		$indexes = bigtree_512_indexes($table);
		$keep_wide = bigtree_512_keep_wide()[$table] ?? [];

		// 1. Which indexes does utf8mb4 push past the limit? Only those need touching:
		//    an index that already fits keeps its columns and its widths exactly as the
		//    site declared them.
		$over_limit = [];

		foreach ($indexes as $name => $index) {
			// Only a BTREE index has a key prefix limit to break, and only a BTREE
			// index can be re-declared with a prefix. A FULLTEXT index covers its
			// column whole and reports no Sub_part, so costing its parts the way a
			// BTREE part is costed makes a `text` column read as 65535 * 4 = 262,140
			// bytes — and step 3 would then "fix" it by replacing the FULLTEXT index
			// with a BTREE prefix index, which MySQL accepts without complaint and
			// which breaks every MATCH ... AGAINST against that table. Core ships no
			// FULLTEXT index, but M1 sweeps module, extension and site tables too.
			// SPATIAL and vector indexes are the same story.
			if ($index["type"] !== "BTREE") {
				continue;
			}

			$cost = bigtree_512_index_cost($index, $columns, $prefix, false);

			if (!$cost["functional"] && $cost["bytes"] > $key_limit) {
				$over_limit[$name] = $index;
			}
		}

		// 2. Narrow what fits (M2). Only a varchar an over-limit index covers in FULL is
		//    a candidate: there the declared width IS the index's width, so the width is
		//    what has to give. A column that is ALREADY prefix-indexed keeps its width
		//    and only its prefix shrinks in step 3 — which is why `bigtree_caches`.`key`
		//    stays a varchar(10000) rather than becoming a truncated column. And even a
		//    candidate is measured first: one whose longest stored value is over 191
		//    characters is left alone for step 3 to prefix.
		$definitions = bigtree_512_column_definitions($table);
		$candidates = [];

		foreach ($over_limit as $index) {
			foreach ($index["columns"] as $part) {
				$column = $part["column"];

				if ($column === null || $part["sub_part"] !== null || !isset($columns[$column])) {
					continue;
				}

				if ($columns[$column]["type"] !== "varchar" || $columns[$column]["length"] <= $prefix) {
					continue;
				}

				if (in_array($column, $keep_wide, true)) {
					continue;
				}

				$candidates[$column] = true;
			}
		}

		foreach (array_keys($candidates) as $column) {
			$longest = SQL::fetchSingle("SELECT MAX(CHAR_LENGTH(`$column`)) FROM `$table`");

			if ($longest !== null && (int)$longest > $prefix) {
				continue;
			}

			if (!isset($definitions[$column])) {

				throw new Exception("revision 512: could not read the definition of `$table`.`$column`.");
			}

			$narrowed = preg_replace(
				'/^(`(?:[^`]|``)+`\s+)varchar\(\d+\)/i',
				'${1}varchar(' . $prefix . ')',
				$definitions[$column],
				1
			);

			bigtree_512_ddl("ALTER TABLE `$table` MODIFY COLUMN $narrowed");
			$actions[] = "`$column` narrowed to varchar($prefix)";
		}

		// 3. Whatever is still over the limit gets its wide string parts cut to a
		//    (191) prefix.
		$columns = bigtree_512_string_columns($table);

		foreach ($over_limit as $name => $index) {
			$cost = bigtree_512_index_cost($index, $columns, $prefix, false);

			if ($cost["bytes"] <= $key_limit) {
				continue;
			}

			$capped = bigtree_512_index_cost($index, $columns, $prefix, true);

			if ($capped["bytes"] > $key_limit) {

				throw new Exception(
					"revision 512 stopped: index `$name` on `$table` needs " . $capped["bytes"] . " bytes at "
					. "utf8mb4 even with $prefix-character prefixes, over this engine's $key_limit-byte limit. "
					. "Drop or shorten that index by hand, then re-run the upgrade."
				);
			}

			// M3: a prefix changes what a PRIMARY KEY or a UNIQUE key means, so stop and
			// say which column is in the way rather than quietly redefining uniqueness.
			// Only reachable when the data really is longer than 191 characters —
			// anything shorter was narrowed in step 2.
			if ($name === "PRIMARY" || $index["unique"]) {

				throw new Exception(
					"revision 512 stopped: `$table`.`" . implode("`, `", $capped["shrunk"]) . "` holds values longer "
					. "than $prefix characters, and " . ($name === "PRIMARY" ? "the PRIMARY KEY" : "the UNIQUE key `$name`")
					. " cannot be cut to a prefix without changing what uniqueness means there. Shorten those values "
					. "(or convert this table by hand), then re-run the upgrade."
				);
			}

			bigtree_512_ddl(
				"ALTER TABLE `$table` DROP INDEX `$name`, ADD INDEX `$name` (" . implode(", ", $capped["parts"]) . ")"
			);
			$actions[] = "index `$name` prefixed at $prefix";
		}

		// 4. utf8mb3 is a strict subset of utf8mb4, so for those tables this changes no
		//    stored bytes — it is a metadata and index rebuild, not a transcoding.
		//    utf8mb4_general_ci, never utf8mb4_0900_ai_ci (R4): that one is MySQL 8+
		//    only, so it would break MariaDB, and a half-and-half estate throws
		//    "Illegal mix of collations" on any join between two of these tables.
		//
		//    Declared per column in ONE ALTER rather than with CONVERT TO CHARACTER
		//    SET, because CONVERT also PROMOTES every text column a size (TEXT becomes
		//    MEDIUMTEXT, to keep the character capacity while the bytes per character
		//    quadruple). base.sql says TEXT, so a converted install would no longer
		//    match a fresh one — and every table here would drift from its own
		//    definition in the Module Designer's table diffing.
		if (bigtree_512_needs_conversion($table)) {
			if (bigtree_512_collation_only_skip($table)) {
				$actions[] = "left at its existing utf8mb4 collation (vector store)";
			} elseif (!bigtree_512_convertible_in_place($table)) {
				bigtree_512_ddl(
					"ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
				);
				$actions[] = "converted to utf8mb4 (MySQL may widen text columns)";
			} else {
				// The table default FIRST. On its own this touches no existing column
				// (it is instant, metadata only) — but it is what the MODIFYs below then
				// inherit, and inheriting is what keeps the columns free of a per-column
				// charset clause a fresh install would not have. Interrupted in between,
				// the columns are still utf8mb3 and a re-run picks up here.
				bigtree_512_ddl(
					"ALTER TABLE `$table` DEFAULT CHARACTER SET = utf8mb4, COLLATE = utf8mb4_general_ci"
				);

				// Re-read: step 2 rewrote some of these definitions.
				$definitions = bigtree_512_column_definitions($table);
				$clauses = [];

				foreach (bigtree_512_string_columns($table) as $column => $meta) {
					if ($meta["collation"] === "utf8mb4_general_ci") {
						continue;
					}

					$stripped = isset($definitions[$column])
						? bigtree_512_definition_without_charset($definitions[$column])
						: null;

					if ($stripped === null) {

						throw new Exception(
							"revision 512: could not rewrite `$table`.`$column` as utf8mb4. Convert this table by "
							."hand (ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE "
							."utf8mb4_general_ci), then re-run the upgrade."
						);
					}

					$clauses[] = "MODIFY COLUMN ".$stripped;
				}

				// One ALTER for every column, so the table is copied once.
				if ($clauses) {
					bigtree_512_ddl("ALTER TABLE `$table` ".implode(", ", $clauses));
				}

				$actions[] = "converted to utf8mb4";
			}
		}

		return $actions;
	}

	/**
	 * The DATABASE's own default character set — the container, not the contents.
	 *
	 * Not cosmetic: this default is what every later CREATE TABLE that names no
	 * charset inherits (an extension's installer, a developer's SQL, the ALTERs
	 * SQL::compareTables emits). Leaving it at utf8mb3 — or at latin1, which is what
	 * MySQL 5.7 hands a `CREATE DATABASE` with no clause — means the estate starts
	 * drifting back the day after this migration runs. core/setup/install.php now
	 * names it at creation; this is the same fix for a database that already exists.
	 *
	 * @return string|null what it did, or null when the default was already right
	 */
	function bigtree_512_convert_database(): ?string {
		$database = (string)SQL::fetchSingle("SELECT DATABASE()");
		$collation = (string)SQL::fetchSingle(
			"SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?",
			$database
		);

		if ($collation === "utf8mb4_general_ci") {

			return null;
		}

		try {
			bigtree_512_ddl(
				"ALTER DATABASE `".str_replace("`", "``", $database)."` "
				."CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
			);
		} catch (\Throwable $e) {

			throw new Exception(
				"revision 512 stopped: the database default is ".($collation ?: "unreadable")." and could not be "
				."changed (".$e->getMessage()."). ALTER DATABASE needs the ALTER privilege on the schema itself, "
				."not just on its tables — run ALTER DATABASE `".$database."` CHARACTER SET utf8mb4 COLLATE "
				."utf8mb4_general_ci as a user who has it, then re-run the upgrade."
			);
		}

		return "database default set to utf8mb4";
	}

	$bigtree_512_tables = bigtree_512_tables();
	$bigtree_512_total = count($bigtree_512_tables);

	// A varchar foreign key whose parent table has not been converted yet would fail
	// MySQL's charset compatibility check mid-sweep; disabling the check for the
	// conversion is MySQL's own documented answer to that. Session-scoped, so it
	// lapses with this request.
	SQL::query("SET foreign_key_checks = 0");

	// The container before its contents. Idempotent, so it costs one information_schema
	// read per page of the sweep after the first.
	try {
		$bigtree_512_database_action = bigtree_512_convert_database();
	} catch (\Throwable $e) {
		echo BigTree::json([
			"complete" => false,
			"error" => $e->getMessage(),
		]);

		die();
	}

	// First call: report how many pages (one table each) this will take.
	if (empty($_GET["page"])) {
		if (!$bigtree_512_total) {
			MigrationService::finish(512);

			echo BigTree::json([
				"complete" => true,
				"response" => "Converted the database to utf8mb4 (512)",
			]);
		} else {
			echo BigTree::json([
				"complete" => false,
				"response" => ($bigtree_512_database_action ? "utf8mb4: ".$bigtree_512_database_action.". " : "")
					."Converting ".$bigtree_512_total." table(s) to utf8mb4...",
				"pages" => $bigtree_512_total,
			]);
		}

		die();
	}

	$bigtree_512_page = intval($_GET["page"]);
	$bigtree_512_pages = intval($_GET["total_pages"]) ?: $bigtree_512_total;
	$bigtree_512_table = $bigtree_512_tables[$bigtree_512_page - 1] ?? null;
	$bigtree_512_response = "Converting to utf8mb4: finalizing...";

	if ($bigtree_512_table !== null) {
		try {
			$bigtree_512_actions = bigtree_512_convert_table($bigtree_512_table, $bigtree_512_prefix);
		} catch (\Throwable $e) {
			echo BigTree::json([
				"complete" => false,
				"error" => $e->getMessage(),
			]);

			die();
		}

		// M6: report per table rather than presenting one opaque operation.
		$bigtree_512_response = "utf8mb4: `".$bigtree_512_table."` "
			.($bigtree_512_actions ? implode(", ", $bigtree_512_actions) : "already converted")
			." (".$bigtree_512_page." of ".$bigtree_512_pages.")";
	}

	if ($bigtree_512_page < $bigtree_512_pages) {
		echo BigTree::json([
			"complete" => false,
			"response" => $bigtree_512_response,
		]);

		die();
	}

	// Last page: don't take the sweep's word for it. The page count was fixed on the
	// first call, so a table created since then has no page of its own — check the
	// schema itself and leave the revision unfinished (the ledger row stays
	// success=0) rather than record a conversion that missed something.
	$bigtree_512_missed = [];

	foreach (bigtree_512_tables() as $bigtree_512_remaining) {
		if (bigtree_512_needs_conversion($bigtree_512_remaining) && !bigtree_512_collation_only_skip($bigtree_512_remaining)) {
			$bigtree_512_missed[] = $bigtree_512_remaining;
		}
	}

	if ($bigtree_512_missed) {
		echo BigTree::json([
			"complete" => false,
			"error" => "revision 512: these tables are still not utf8mb4 — "
				.implode(", ", $bigtree_512_missed)
				.". They most likely appeared after this migration counted its work; run the upgrade again.",
		]);

		die();
	}

	MigrationService::finish(512);

	echo BigTree::json([
		"complete" => true,
		"response" => "Converted the database to utf8mb4 (512)",
	]);
