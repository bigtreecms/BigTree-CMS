<?php
	/**
	 * Migration runner read-side (017 Phase 2a).
	 *
	 * Exercises the additive, read-only analysis on MigrationService:
	 *   - checksumFor() returns a 64-hex digest for a real file and "" for missing,
	 *   - pending()'s FLOOR semantics: nothing pending when the integer is at/above
	 *     a revision (even with an empty ledger); a revision below the integer with
	 *     no ledger row IS pending; a success=1 row above the integer is NOT pending,
	 *   - verifyIntegrity() flags drift on a wrong stored checksum, stays quiet when
	 *     the stored real checksum matches, and skips ""/"pre-ledger" sentinels.
	 *
	 * None of these EXECUTE a migration (that is Phase 2b). Seeds throwaway ledger
	 * rows in a high, namespaced range that cannot collide with real revisions, and
	 * temporarily reseeds bigtree-internal-revision — restoring both in finally.
	 * Skips cleanly when the ledger table is absent from the harness DB.
	 */

	use BigTree\Services\MigrationService;

	/** Floor of the throwaway revision range (well above the real 201–505 range). */
	const MIGRUNNER_BASE = 910000;

	/** True if the bigtree_migrations table is queryable in this harness. */
	function _migrunner_has_table(): bool {
		try {
			SQL::query("SELECT 1 FROM bigtree_migrations LIMIT 1");

			return true;
		} catch (\Throwable $e) {
			return false;
		}
	}

	/** Delete every seeded ledger row in the throwaway range. */
	function _migrunner_cleanup(): void {
		SQL::query("DELETE FROM bigtree_migrations WHERE revision >= ?", MIGRUNNER_BASE);
	}

	/**
	 * Restore a real ledger row to its snapshot, or delete it if it never existed.
	 *
	 * Tests that exercise a real revision (e.g. 505) must leave the ledger exactly
	 * as found: re-record the original values if there was a row, otherwise remove
	 * the row the test created.
	 */
	function _migrunner_restore_row(int $revision, $snapshot): void {
		if ($snapshot) {
			MigrationService::record($revision, (string)$snapshot["name"], (string)$snapshot["checksum"]);

			return;
		}

		SQL::query("DELETE FROM bigtree_migrations WHERE revision = ?", $revision);
	}

	function test_migration_checksum_for_real_and_missing_file() {
		// 505.php is a known real revision file; this needs no DB.
		$real = MigrationService::checksumFor(505);

		T::ok((bool)preg_match('/^[0-9a-f]{64}$/', $real), "checksumFor() returns a 64-char hex digest for a real revision file");

		$missing = MigrationService::checksumFor(MIGRUNNER_BASE + 1);

		T::equals($missing, "", "checksumFor() returns \"\" for a missing revision file");
	}

	function test_migration_pending_floor_empty_ledger_at_target() {
		if (!_migrunner_has_table()) {
			echo "  (skipped — bigtree_migrations not present in this harness)\n";

			return;
		}

		$original = BigTreeCMS::getSetting("bigtree-internal-revision");

		try {
			_migrunner_cleanup();

			// Integer at the target with no NEW ledger rows in our range: the floor
			// counts everything <= the integer as applied, so nothing is pending.
			BigTreeAdmin::updateInternalSettingValue("bigtree-internal-revision", BIGTREE_REVISION);

			T::equals(MigrationService::pending(), [], "pending() is [] when the integer is at the target (floor applies)");
		} finally {
			BigTreeAdmin::updateInternalSettingValue("bigtree-internal-revision", (int)$original);
			_migrunner_cleanup();
		}
	}

	function test_migration_pending_revision_below_integer_with_no_ledger_row() {
		if (!_migrunner_has_table()) {
			echo "  (skipped — bigtree_migrations not present in this harness)\n";

			return;
		}

		$on_disk = MigrationService::onDiskRevisions();

		if (count($on_disk) < 2) {
			echo "  (skipped — need at least two on-disk revisions)\n";

			return;
		}

		$original = BigTreeCMS::getSetting("bigtree-internal-revision");

		// Pick the highest real revision and the one before it; seed the integer
		// below the highest so the highest becomes pending (no ledger row for it).
		$highest = $on_disk[count($on_disk) - 1];
		$below = $on_disk[count($on_disk) - 2];

		// Snapshot any real ledger row for $highest so we can restore it exactly
		// (and not fabricate one if it never existed).
		$highest_before = SQL::fetch("SELECT * FROM bigtree_migrations WHERE revision = ?", $highest);

		try {
			_migrunner_cleanup();

			// Ensure no success=1 ledger row exists for $highest during this test.
			SQL::query("DELETE FROM bigtree_migrations WHERE revision = ?", $highest);
			BigTreeAdmin::updateInternalSettingValue("bigtree-internal-revision", $below);

			T::ok(in_array($highest, MigrationService::pending(), true), "a revision above the integer with no ledger row is pending");
			T::ok(!in_array($below, MigrationService::pending(), true), "a revision at/below the integer is not pending (floor)");
		} finally {
			BigTreeAdmin::updateInternalSettingValue("bigtree-internal-revision", (int)$original);
			_migrunner_restore_row($highest, $highest_before);
			_migrunner_cleanup();
		}
	}

	function test_migration_pending_success_row_above_integer_not_pending() {
		if (!_migrunner_has_table()) {
			echo "  (skipped — bigtree_migrations not present in this harness)\n";

			return;
		}

		$on_disk = MigrationService::onDiskRevisions();

		if (!$on_disk) {
			echo "  (skipped — no on-disk revisions)\n";

			return;
		}

		$original = BigTreeCMS::getSetting("bigtree-internal-revision");
		$highest = $on_disk[count($on_disk) - 1];
		$highest_before = SQL::fetch("SELECT * FROM bigtree_migrations WHERE revision = ?", $highest);

		try {
			_migrunner_cleanup();

			// Integer below the highest revision, but a success=1 ledger row exists for
			// it — the ledger clause marks it applied even though it is above the floor.
			BigTreeAdmin::updateInternalSettingValue("bigtree-internal-revision", $highest - 1);
			MigrationService::record($highest, "applied", MigrationService::PRE_LEDGER);

			T::ok(!in_array($highest, MigrationService::pending(), true), "a success=1 ledger row marks a revision applied even above the integer floor");
		} finally {
			BigTreeAdmin::updateInternalSettingValue("bigtree-internal-revision", (int)$original);
			_migrunner_restore_row($highest, $highest_before);
			_migrunner_cleanup();
		}
	}

	function test_migration_verify_integrity_matches_real_checksum() {
		if (!_migrunner_has_table()) {
			echo "  (skipped — bigtree_migrations not present in this harness)\n";

			return;
		}

		// verifyIntegrity() recomputes checksumFor(revision), so we record a REAL
		// revision number (505) with its REAL checksum and restore the row after.
		$revision = 505;
		$before = SQL::fetch("SELECT * FROM bigtree_migrations WHERE revision = ?", $revision);

		try {
			MigrationService::record($revision, "Migration ledger", MigrationService::checksumFor($revision));

			$drift = MigrationService::verifyIntegrity();
			$flagged = array_values(array_filter($drift, fn($d) => (int)$d["revision"] === $revision));

			T::equals($flagged, [], "verifyIntegrity() does not flag a revision whose stored real checksum matches the file");
		} finally {
			_migrunner_restore_row($revision, $before);
		}
	}

	function test_migration_verify_integrity_flags_wrong_checksum() {
		if (!_migrunner_has_table()) {
			echo "  (skipped — bigtree_migrations not present in this harness)\n";

			return;
		}

		$revision = 505;
		$before = SQL::fetch("SELECT * FROM bigtree_migrations WHERE revision = ?", $revision);
		$wrong = str_repeat("d", 64);

		try {
			MigrationService::record($revision, "tampered", $wrong);

			$drift = MigrationService::verifyIntegrity();
			$flagged = array_values(array_filter($drift, fn($d) => (int)$d["revision"] === $revision));

			T::equals(count($flagged), 1, "verifyIntegrity() flags a revision whose stored checksum no longer matches the file");
			T::equals($flagged[0]["status"], "drift", "the flagged entry has status \"drift\"");
			T::equals($flagged[0]["stored"], $wrong, "the flagged entry reports the stored (wrong) checksum");
		} finally {
			_migrunner_restore_row($revision, $before);
		}
	}

	function test_migration_verify_integrity_skips_sentinels() {
		if (!_migrunner_has_table()) {
			echo "  (skipped — bigtree_migrations not present in this harness)\n";

			return;
		}

		// Seed throwaway revisions (no on-disk file) with the unverifiable sentinels:
		// "" and "pre-ledger". Neither should produce a drift/missing entry even
		// though their files do not exist.
		$empty_rev = MIGRUNNER_BASE + 5;
		$preledger_rev = MIGRUNNER_BASE + 6;

		try {
			_migrunner_cleanup();

			MigrationService::record($empty_rev, "empty checksum", "");
			MigrationService::record($preledger_rev, "pre-ledger", MigrationService::PRE_LEDGER);

			$drift = MigrationService::verifyIntegrity();
			$flagged = array_filter($drift, fn($d) => in_array((int)$d["revision"], [$empty_rev, $preledger_rev], true));

			T::equals($flagged, [], "verifyIntegrity() skips \"\" and \"pre-ledger\" sentinels (no false drift)");
		} finally {
			_migrunner_cleanup();
		}
	}

	/**
	 * base.sql is the END STATE: a fresh install is installed up to date, not
	 * installed and then upgraded.
	 *
	 * So the revision floor it seeds has to equal BIGTREE_REVISION. When the two
	 * drift, a brand new install has pending revisions on its first admin visit —
	 * which is the symptom; the disease is a revision that created or altered a table
	 * without the same change landing in base.sql, so the file no longer describes the
	 * schema the code expects. (Found at 512: the floor sat at 507 while revisions
	 * 508 and 509 created the three AI assistant tables that base.sql never declared.)
	 *
	 * A pure file read — no database, so it runs everywhere the suite does.
	 */
	function test_base_sql_revision_floor_matches_the_code_revision() {
		$path = SERVER_ROOT."core/setup/base.sql";
		$sql = file_get_contents($path);

		T::ok($sql !== false, "core/setup/base.sql is readable");

		if ($sql === false) {

			return;
		}

		$found = preg_match("/'bigtree-internal-revision'\s*,\s*'(\d+)'/", $sql, $match);

		T::equals($found, 1, "base.sql seeds bigtree-internal-revision");

		if (!$found) {

			return;
		}

		T::equals(
			(int)$match[1],
			BIGTREE_REVISION,
			"base.sql's revision floor matches BIGTREE_REVISION (add new table DDL to base.sql "
			."and move the floor in the same commit)"
		);
	}
