<?php
	/**
	 * Headless migration STATUS / DRY-RUN command (017 Phase 2a).
	 *
	 * Prints the current applied integer, the BIGTREE_REVISION target, the list of
	 * revisions that WOULD run (MigrationService::pending()), and any checksum
	 * drift (MigrationService::verifyIntegrity()). It does NOT apply migrations —
	 * actually running them is Phase 2b (plan 032). Safe to run anytime.
	 *
	 * Usage:  php core/admin/migrate-status.php
	 *
	 * Exit code: non-zero ONLY when checksum drift is detected (so CI can gate on
	 * tampering); 0 otherwise.
	 */

	use BigTree\Services\MigrationService;

	// Mirror cron-run.php's bootstrap so SQL / BigTreeCMS / constants are loaded.
	$server_root = str_replace("core/admin/migrate-status.php", "", strtr(__FILE__, "\\", "/"));

	include $server_root . "custom/environment.php";
	include $server_root . "custom/settings.php";
	include $server_root . "core/bootstrap.php";

	$current = (int)BigTreeCMS::getSetting("bigtree-internal-revision");
	$target = BIGTREE_REVISION;

	echo "================================================================\n";
	echo " BigTree migration status\n";
	echo " DRY RUN — this command does not apply migrations (see Phase 2b)\n";
	echo "================================================================\n";
	echo "\n";
	echo "Applied revision (bigtree-internal-revision): " . $current . "\n";
	echo "Target revision  (BIGTREE_REVISION):          " . $target . "\n";
	echo "\n";

	// The ledger arrives with revision 505 (base.sql). On an install whose DB
	// predates it, there is nothing to read — say so plainly and exit 0.
	try {
		SQL::query("SELECT 1 FROM bigtree_migrations LIMIT 1");
	} catch (\Throwable $e) {
		echo "Migration ledger (bigtree_migrations) not found — run the upgrade to\n";
		echo "revision 505 to create it. Nothing to report.\n";

		exit(0);
	}

	$pending = MigrationService::pending();
	$drift = MigrationService::verifyIntegrity();


	if ($pending) {
		echo "Pending revisions (would run): " . implode(", ", $pending) . "\n";
	} else {
		echo "Pending revisions (would run): none — up to date\n";
	}

	echo "\n";

	if ($drift) {
		echo "Checksum integrity: DRIFT DETECTED\n";

		foreach ($drift as $entry) {
			echo "  - revision " . $entry["revision"] . " [" . $entry["status"] . "]\n";
			echo "      stored: " . $entry["stored"] . "\n";
			echo "      actual: " . ($entry["actual"] !== "" ? $entry["actual"] : "(file missing)") . "\n";
		}

		echo "\n";
		echo "Exiting non-zero: a recorded revision's checksum no longer matches its file.\n";

		exit(1);
	}

	echo "Checksum integrity: OK — all verifiable revisions match their files\n";

	exit(0);
