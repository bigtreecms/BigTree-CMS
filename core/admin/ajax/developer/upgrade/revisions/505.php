<?php
	// BigTree — migration ledger (017 Phase 1)

	// This migration is idempotent: the same schema ships in core/setup/base.sql,
	// and every recording is guarded by the ledger's primary key, so re-running is
	// a no-op.

	// Idempotent: same table ships in base.sql (no FK by design).
	SQL::query("
		CREATE TABLE IF NOT EXISTS `bigtree_migrations` (
			`revision`    INT(11) UNSIGNED NOT NULL PRIMARY KEY,
			`name`        VARCHAR(255) NOT NULL DEFAULT '',
			`checksum`    CHAR(64) NOT NULL DEFAULT '',
			`applied_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`duration_ms` INT(11) UNSIGNED NULL DEFAULT NULL,
			`success`     TINYINT(1) NOT NULL DEFAULT 0
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
	");

	// Backfill: record every revision file already applied on this install (revision
	// number <= the stored bigtree-internal-revision BEFORE this runs) as a completed,
	// pre-ledger migration. Idempotent via the ledger PK inside MigrationService::record().
	$current = (int)$cms->getSetting("bigtree-internal-revision");

	foreach (glob(SERVER_ROOT . "core/admin/ajax/developer/upgrade/revisions/*.php") as $file) {
		$n = (int)basename($file, ".php");

		if ($n > 0 && $n <= $current) {
			\BigTree\Services\MigrationService::record($n, "", "pre-ledger");
		}
	}

	// Record this revision itself.
	\BigTree\Services\MigrationService::record(505, "Migration ledger", "");

	echo BigTree::json([
		"complete" => true,
		"response" => "Adding migration ledger (505)"
	]);

	// Dual-write: keep the integer in sync (Phase 1 compat — existing reads rely on it).
	$admin->updateInternalSettingValue("bigtree-internal-revision", 505);
