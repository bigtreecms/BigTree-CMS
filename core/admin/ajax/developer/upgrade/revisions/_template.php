<?php
	/**
	 * Revision template — copy to N.php (the next integer) and fill in.
	 *
	 * Each revision runs in the admin AJAX upgrade context with $cms, $admin, and
	 * SERVER_ROOT available, and MUST be idempotent (re-running it is a no-op). The
	 * runner's checksum/drift detection (017 Phase 2a) relies on each new revision
	 * recording a REAL checksum via MigrationService::checksumFor() — see below.
	 *
	 * SELF-RECORDING CONVENTION (017 Phase 2b):
	 *
	 *   - Call MigrationService::begin(N) at the TOP, before doing any work. This
	 *     writes a success=0 "in-flight" marker (a crash before finish() leaves it,
	 *     so a resume knows the revision was mid-flight). begin() is idempotent and
	 *     safe to call on EVERY page of a batched revision.
	 *   - Do the guarded/idempotent DDL + (batched) backfill as before.
	 *   - Call MigrationService::finish(N) on completion:
	 *        * single-shot revision: once, at the end;
	 *        * batched revision: ONLY on the branch that echoes {complete:true}
	 *          (the last page).
	 *     finish() flips the row to success=1, records duration_ms, and MIRRORS the
	 *     legacy bigtree-internal-revision integer (never lowering it).
	 *   - Do NOT call $admin->updateInternalSettingValue(...) yourself — finish()
	 *     owns the integer now. Do NOT use record() in a new revision (that is the
	 *     505 backfill primitive); use begin()/finish().
	 *
	 * Either the CLI runner (core/admin/migrate-run.php) or the legacy AJAX upgrade
	 * flow can apply a revision; begin()/finish() keep the ledger + integer
	 * consistent regardless of which one runs it.
	 *
	 * IDEMPOTENCY CHECKLIST (017 §4c):
	 *
	 *   1. Guard every DDL statement so re-running is safe:
	 *        - CREATE TABLE IF NOT EXISTS for new tables.
	 *        - SHOW COLUMNS LIKE before ALTER TABLE ... ADD COLUMN.
	 *        - SHOW INDEX before ALTER TABLE ... ADD INDEX / ADD KEY.
	 *   2. Guard data backfills with existence checks (e.g. skip rows that already
	 *      have the target value) so a partial/repeat run does not double-write.
	 *   3. Keep schema DDL and large data backfills in SEPARATE revisions.
	 *   4. BATCH large backfills (see revisions/503.php) so AJAX upgrades can
	 *      paginate; do not load an unbounded result set into memory.
	 */

	// --- 0. Mark this revision as started (begin is idempotent, safe per page) -
	//
	// Replace N with this file's revision number.

	// \BigTree\Services\MigrationService::begin(N);

	// --- 1. Guarded schema DDL ------------------------------------------------

	// New table — IF NOT EXISTS makes the CREATE a no-op on re-run.
	// SQL::query("
	//     CREATE TABLE IF NOT EXISTS `bigtree_example` (
	//         `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
	//     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
	// ");

	// Add a column — guard with SHOW COLUMNS so the ALTER only runs once.
	// if (!SQL::fetch("SHOW COLUMNS FROM `bigtree_example` LIKE 'label'")) {
	//     SQL::query("ALTER TABLE `bigtree_example` ADD COLUMN `label` VARCHAR(255) NOT NULL DEFAULT ''");
	// }

	// Add an index — guard with SHOW INDEX so the ADD only runs once.
	// if (!SQL::fetch("SHOW INDEX FROM `bigtree_example` WHERE Key_name = 'label'")) {
	//     SQL::query("ALTER TABLE `bigtree_example` ADD INDEX `label` (`label`)");
	// }

	// --- 2. Existence-guarded backfill ---------------------------------------

	// foreach (SQL::fetchAll("SELECT id FROM `bigtree_example` WHERE `label` = ''") as $row) {
	//     SQL::update("bigtree_example", $row["id"], ["label" => "default"]);
	// }

	// --- 3. Finish: mark applied (success=1) + report completion --------------
	//
	// SINGLE-SHOT: call finish(N) once here, then echo the completion JSON.
	// finish() flips the ledger row to success=1, records duration_ms, and mirrors
	// the legacy bigtree-internal-revision integer. Do NOT call
	// updateInternalSettingValue yourself.

	// \BigTree\Services\MigrationService::finish(N);

	// echo BigTree::json([
	//     "complete" => true,
	//     "response" => "Short migration name (N)"
	// ]);

	// --- 4. BATCHED variant (see revisions/503.php for the full pattern) -------
	//
	// For a paginated revision, call begin(N) at the top (step 0) on every page,
	// then call finish(N) ONLY on the final-page / {complete:true} branch:
	//
	//   if ($page < $total_pages) {
	//       echo BigTree::json(["complete" => false, "response" => "..."]);
	//   } else {
	//       \BigTree\Services\MigrationService::finish(N);
	//       echo BigTree::json(["complete" => true, "response" => "..."]);
	//   }
	//
	// The first call (no $_GET["page"]) reports the page count:
	//   echo BigTree::json(["complete" => false, "response" => "...", "pages" => $total_pages]);
