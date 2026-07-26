<?php
	/**
	 * utf8mb4 everywhere — the schema guard and the round trip (plans/utf8mb4-migration.md
	 * Phase 3).
	 *
	 * Two halves:
	 *
	 *   V1 — the schema is utf8mb4 and no index exceeds InnoDB's 3072-byte key prefix
	 *        limit. The second half is the guard that stops the original bug coming
	 *        back: BigTree's `varchar(1024)` default width plus a full-column index is
	 *        exactly 3072 bytes at utf8mb3 and 4096 at utf8mb4, so a new
	 *        varchar(1024)+KEY pair would make the next conversion impossible.
	 *   V2 — a 4-byte character (an emoji) survives a write and a read on the three
	 *        surfaces audit #10 A2 named: a page field, a module entry column, and a
	 *        chat message. This is the test that would have caught the original bug,
	 *        where `SET NAMES 'utf8'` (utf8mb3) had MySQL truncate the value AT the
	 *        emoji and report success.
	 *
	 * Both halves need a converted database, and a checkout can be pointed at one that
	 * has not run revision 512 yet, so each test skips (loudly) unless `bigtree_pages`
	 * is already utf8mb4 — a fresh install from base.sql and a migrated install both
	 * qualify.
	 */

	use BigTree\Services\AIChatService;

	/** A single 4-byte character, and a 3-byte one that always worked, for contrast. */
	const UTF8MB4_FOUR_BYTE = "Spring Gala 🎉 tickets";
	const UTF8MB4_THREE_BYTE = "Spring Gala — “tickets”";

	/** True when the DB is reachable from this harness. */
	function utf8mb4_db_available(): bool {
		try {
			SQL::fetchSingle("SELECT 1");

			return true;
		} catch (Throwable $e) {
			echo "  (skipped — database unavailable in this harness: ".$e->getMessage().")\n";

			return false;
		}
	}

	/**
	 * True when this database has been converted. Revision 512 is what does it on an
	 * upgraded install; core/setup/base.sql declares it on a fresh one — so rather than
	 * reading the revision floor (a fresh install's floor sits below 512 by design),
	 * ask the schema.
	 */
	function utf8mb4_schema_converted(): bool {
		$collation = (string)SQL::fetchSingle(
			"SELECT TABLE_COLLATION FROM information_schema.TABLES
			 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bigtree_pages'"
		);

		if (strpos($collation, "utf8mb4") === 0) {

			return true;
		}

		echo "  (skipped — this database has not run revision 512 yet: bigtree_pages is ".$collation.")\n";

		return false;
	}

	/** V1: every table in the schema is utf8mb4. */
	function test_every_table_is_utf8mb4() {
		if (!utf8mb4_db_available() || !utf8mb4_schema_converted()) {

			return;
		}

		$offenders = SQL::fetchAllSingle(
			"SELECT CONCAT(TABLE_NAME, ' (', TABLE_COLLATION, ')') FROM information_schema.TABLES
			 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
			   AND TABLE_COLLATION NOT LIKE 'utf8mb4%'
			 ORDER BY TABLE_NAME ASC"
		);

		T::equals(implode(", ", $offenders), "", "every table is utf8mb4");
	}

	/**
	 * V1: the DATABASE's own default is utf8mb4 as well.
	 *
	 * The container, not the contents: this default is what every CREATE TABLE that
	 * names no charset inherits — an extension's installer, hand-written SQL, the
	 * ALTERs SQL::compareTables emits. Converting every table but leaving the schema
	 * default at utf8mb3 (or at latin1, which is what MySQL 5.7 gives a CREATE
	 * DATABASE with no clause) is a fix with a leak in it: the first table created
	 * afterwards reopens the hole. install.php names it at creation and revision 512
	 * ALTERs it.
	 */
	function test_database_default_charset_is_utf8mb4() {
		if (!utf8mb4_db_available() || !utf8mb4_schema_converted()) {

			return;
		}

		T::equals(
			(string)SQL::fetchSingle(
				"SELECT DEFAULT_CHARACTER_SET_NAME FROM information_schema.SCHEMATA
				 WHERE SCHEMA_NAME = DATABASE()"
			),
			"utf8mb4",
			"the database's own default character set is utf8mb4"
		);
	}

	/** V1: every string column is utf8mb4 too — a table default is not enough. */
	function test_every_string_column_is_utf8mb4() {
		if (!utf8mb4_db_available() || !utf8mb4_schema_converted()) {

			return;
		}

		$offenders = SQL::fetchAllSingle(
			"SELECT CONCAT(TABLE_NAME, '.', COLUMN_NAME, ' (', CHARACTER_SET_NAME, ')')
			 FROM information_schema.COLUMNS
			 WHERE TABLE_SCHEMA = DATABASE() AND CHARACTER_SET_NAME IS NOT NULL
			   AND CHARACTER_SET_NAME <> 'utf8mb4'
			 ORDER BY TABLE_NAME ASC, COLUMN_NAME ASC"
		);

		T::equals(implode(", ", $offenders), "", "every string column is utf8mb4");
	}

	/**
	 * V1: no index is wider than its engine's key prefix limit at utf8mb4 — 3072 bytes
	 * for InnoDB, 1000 for MyISAM (a legacy module table may still be MyISAM).
	 *
	 * The same information_schema query the migration plan was written from, as an
	 * assertion: a string index part costs 4 bytes per character at utf8mb4, and a
	 * non-string part is counted as a flat 8 bytes (an over-estimate — the answer only
	 * has to be a bound).
	 */
	function test_no_index_exceeds_the_key_prefix_limit() {
		if (!utf8mb4_db_available() || !utf8mb4_schema_converted()) {

			return;
		}

		$rows = SQL::fetchAll(
			"SELECT s.TABLE_NAME, s.INDEX_NAME, t.ENGINE,
			        SUM(CASE
			              WHEN c.CHARACTER_MAXIMUM_LENGTH IS NULL THEN 8
			              ELSE COALESCE(s.SUB_PART, c.CHARACTER_MAXIMUM_LENGTH) * 4
			            END) AS bytes
			 FROM information_schema.STATISTICS s
			 JOIN information_schema.TABLES t
			   ON t.TABLE_SCHEMA = s.TABLE_SCHEMA AND t.TABLE_NAME = s.TABLE_NAME
			 JOIN information_schema.COLUMNS c
			   ON c.TABLE_SCHEMA = s.TABLE_SCHEMA AND c.TABLE_NAME = s.TABLE_NAME
			  AND c.COLUMN_NAME = s.COLUMN_NAME
			 WHERE s.TABLE_SCHEMA = DATABASE() AND s.INDEX_TYPE = 'BTREE'
			 GROUP BY s.TABLE_NAME, s.INDEX_NAME, t.ENGINE
			 ORDER BY s.TABLE_NAME ASC, s.INDEX_NAME ASC"
		);
		$offenders = [];

		foreach ($rows as $row) {
			$limit = (strtoupper((string)$row["ENGINE"]) === "MYISAM") ? 1000 : 3072;

			if ((int)$row["bytes"] > $limit) {
				$offenders[] = $row["TABLE_NAME"].".".$row["INDEX_NAME"]." (".(int)$row["bytes"]." bytes)";
			}
		}

		T::ok(count($rows) > 0, "the index inventory was read");
		T::equals(implode(", ", $offenders), "", "no index exceeds its engine's key prefix limit at utf8mb4");
	}

	/** V2: a 4-byte character survives a page field. */
	function test_four_byte_character_round_trips_through_a_page() {
		if (!utf8mb4_db_available() || !utf8mb4_schema_converted()) {

			return;
		}

		$id = 0;

		try {
			$id = parity_seed_page([
				"nav_title" => UTF8MB4_FOUR_BYTE,
				"title" => UTF8MB4_THREE_BYTE,
			]);

			$row = SQL::fetch("SELECT nav_title, title FROM bigtree_pages WHERE id = ?", $id);

			T::equals($row["nav_title"], UTF8MB4_FOUR_BYTE, "a page's nav_title keeps its emoji");
			T::equals($row["title"], UTF8MB4_THREE_BYTE, "and its em-dash and curly quotes");
		} finally {
			parity_delete_page($id);
		}
	}

	/**
	 * V2: a 4-byte character survives a module entry column.
	 *
	 * A throwaway table created with the DDL ModuleService::create uses for a real
	 * module table, so the test is about the same column the admin would write to.
	 */
	function test_four_byte_character_round_trips_through_a_module_entry() {
		if (!utf8mb4_db_available() || !utf8mb4_schema_converted()) {

			return;
		}

		$table = "zz_utf8mb4_".strtolower(bin2hex(random_bytes(4)));

		try {
			SQL::query(
				"CREATE TABLE `$table` (
					`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
					`title` VARCHAR(255) NOT NULL DEFAULT '',
					PRIMARY KEY (`id`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
			);

			$id = (int)SQL::insert($table, ["title" => UTF8MB4_FOUR_BYTE]);

			T::equals(
				SQL::fetchSingle("SELECT title FROM `$table` WHERE id = ?", $id),
				UTF8MB4_FOUR_BYTE,
				"a module entry column keeps its emoji"
			);
		} finally {
			SQL::query("DROP TABLE IF EXISTS `$table`");
		}
	}

	/**
	 * V2: a 4-byte character survives a chat message.
	 *
	 * The one surface where the connection was the ONLY constraint — the three AI
	 * tables have been utf8mb4 since they were created — and the one where the writer
	 * is the user, not the model, so no sift-side check can help. Writes through the
	 * same SQL::insert call AIChatService::insertMessage makes (that method is
	 * private).
	 */
	function test_four_byte_character_round_trips_through_a_chat_message() {
		if (!utf8mb4_db_available() || !utf8mb4_schema_converted()) {

			return;
		}

		AIChatService::ensureTables();

		$conversation = 0;

		try {
			$now = date("Y-m-d H:i:s");
			$conversation = (int)SQL::insert(AIChatService::CONVERSATIONS_TABLE, [
				"user" => 0,
				"title" => UTF8MB4_FOUR_BYTE,
				"created_at" => $now,
				"updated_at" => $now,
			]);
			$message = (int)SQL::insert(AIChatService::MESSAGES_TABLE, [
				"conversation" => $conversation,
				"role" => "user",
				"content" => UTF8MB4_FOUR_BYTE,
				"tool_calls" => null,
				"tool_results" => null,
				"created_at" => $now,
			]);

			T::equals(
				SQL::fetchSingle("SELECT content FROM ".AIChatService::MESSAGES_TABLE." WHERE id = ?", $message),
				UTF8MB4_FOUR_BYTE,
				"a chat message keeps its emoji"
			);
			T::equals(
				SQL::fetchSingle("SELECT title FROM ".AIChatService::CONVERSATIONS_TABLE." WHERE id = ?", $conversation),
				UTF8MB4_FOUR_BYTE,
				"and so does the conversation title derived from it"
			);
		} finally {
			if ($conversation) {
				SQL::query("DELETE FROM ".AIChatService::MESSAGES_TABLE." WHERE conversation = ?", $conversation);
				SQL::query("DELETE FROM ".AIChatService::CONVERSATIONS_TABLE." WHERE id = ?", $conversation);
			}
		}
	}

	/** The connection itself is utf8mb4 — Phase 1, and what makes the above possible. */
	function test_connection_charset_is_utf8mb4() {
		if (!utf8mb4_db_available()) {

			return;
		}

		foreach (["character_set_client", "character_set_connection", "character_set_results"] as $variable) {
			$row = SQL::fetch("SHOW VARIABLES LIKE ?", $variable);

			T::equals($row["Value"], "utf8mb4", "$variable is utf8mb4");
		}
	}
