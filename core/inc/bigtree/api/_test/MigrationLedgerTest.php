<?php
	/**
	 * Migration ledger (017 Phase 1).
	 *
	 * Proves the additive ledger behaves correctly without anything reading it yet:
	 *   - MigrationService::record() inserts a success=1 row with the given
	 *     values and a non-null applied_at,
	 *   - record() is idempotent (PK on `revision`): re-recording updates the row
	 *     in place rather than inserting a duplicate,
	 *   - the 505 backfill records every revision <= a chosen floor and none above
	 *     it (mirrored deterministically via record(), the same primitive 505.php
	 *     uses), and re-running yields the same row count.
	 *
	 * Seeds throwaway rows in a high, namespaced revision range that cannot collide
	 * with real revisions (201–505) and deletes them in a finally block. Skips on an
	 * unavailable DB.
	 */

	use BigTree\Services\MigrationService;

	/** Floor of the throwaway revision range used by these tests (well above real revisions). */
	const MIGLEDGER_BASE = 900000;

	/** Delete every seeded ledger row in the throwaway range. */
	function _migledger_cleanup(): void {
		SQL::query("DELETE FROM bigtree_migrations WHERE revision >= ?", MIGLEDGER_BASE);
	}

	function test_migration_record_inserts_success_row() {
		try {
			SQL::query("SELECT 1 FROM bigtree_migrations LIMIT 1");
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return;
		}

		$revision = MIGLEDGER_BASE + 1;

		try {
			_migledger_cleanup();

			MigrationService::record($revision, "Test migration", "abc123");

			$row = SQL::fetch("SELECT * FROM bigtree_migrations WHERE revision = ?", $revision);

			T::ok(is_array($row), "record() inserts a ledger row");
			T::equals((int)$row["revision"], $revision, "revision stored exactly");
			T::equals($row["name"], "Test migration", "name stored exactly");
			T::equals($row["checksum"], "abc123", "checksum stored exactly");
			T::equals((int)$row["success"], 1, "success is 1 (records a completed migration)");
			T::ok(!is_null($row["applied_at"]) && $row["applied_at"] !== "", "applied_at is non-null");
		} finally {
			_migledger_cleanup();
		}
	}

	function test_migration_record_null_checksum_stores_empty_string() {
		try {
			SQL::query("SELECT 1 FROM bigtree_migrations LIMIT 1");
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return;
		}

		$revision = MIGLEDGER_BASE + 2;

		try {
			_migledger_cleanup();

			MigrationService::record($revision);

			$row = SQL::fetch("SELECT * FROM bigtree_migrations WHERE revision = ?", $revision);

			T::equals($row["name"], "", "default name is empty string");
			T::equals($row["checksum"], "", "null checksum stored as empty string");
			T::equals((int)$row["success"], 1, "success is 1");
		} finally {
			_migledger_cleanup();
		}
	}

	function test_migration_record_is_idempotent() {
		try {
			SQL::query("SELECT 1 FROM bigtree_migrations LIMIT 1");
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return;
		}

		$revision = MIGLEDGER_BASE + 3;

		try {
			_migledger_cleanup();

			MigrationService::record($revision, "First", "one");
			MigrationService::record($revision, "Second", "two");

			$count = (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_migrations WHERE revision = ?", $revision);
			$row = SQL::fetch("SELECT * FROM bigtree_migrations WHERE revision = ?", $revision);

			T::equals($count, 1, "re-recording the same revision does not duplicate (PK)");
			T::equals($row["name"], "Second", "ON DUPLICATE KEY UPDATE refreshes name in place");
			T::equals($row["checksum"], "two", "ON DUPLICATE KEY UPDATE refreshes checksum in place");
			T::equals((int)$row["success"], 1, "success stays 1");
		} finally {
			_migledger_cleanup();
		}
	}

	function test_migration_backfill_records_at_or_below_floor_only() {
		try {
			SQL::query("SELECT 1 FROM bigtree_migrations LIMIT 1");
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return;
		}

		// Mirror the 505.php backfill loop deterministically over a synthetic set of
		// "revision files". Floor is the stored bigtree-internal-revision; revisions
		// <= floor are backfilled, those above are not.
		$floor = MIGLEDGER_BASE + 20;
		$synthetic = [
			MIGLEDGER_BASE + 10,
			MIGLEDGER_BASE + 15,
			MIGLEDGER_BASE + 20, // == floor, included
			MIGLEDGER_BASE + 25, // above floor, excluded
			MIGLEDGER_BASE + 30, // above floor, excluded
		];

		try {
			_migledger_cleanup();

			$run_backfill = function () use ($synthetic, $floor) {
				foreach ($synthetic as $n) {
					if ($n > 0 && $n <= $floor) {
						MigrationService::record($n, "", "pre-ledger");
					}
				}
			};

			$run_backfill();

			$rows = SQL::fetchAllSingle(
				"SELECT revision FROM bigtree_migrations WHERE revision >= ? ORDER BY revision ASC",
				MIGLEDGER_BASE
			);
			$rows = array_map("intval", $rows);

			T::equals($rows, [MIGLEDGER_BASE + 10, MIGLEDGER_BASE + 15, MIGLEDGER_BASE + 20], "backfill records exactly the revisions <= floor");

			$checksum = SQL::fetchSingle("SELECT checksum FROM bigtree_migrations WHERE revision = ?", MIGLEDGER_BASE + 10);
			T::equals($checksum, "pre-ledger", "backfilled rows carry the pre-ledger checksum");

			// Re-running the backfill is idempotent — same row count.
			$before = (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_migrations WHERE revision >= ?", MIGLEDGER_BASE);
			$run_backfill();
			$after = (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_migrations WHERE revision >= ?", MIGLEDGER_BASE);

			T::equals($after, $before, "re-running the backfill yields the same row count (idempotent)");
		} finally {
			_migledger_cleanup();
		}
	}
