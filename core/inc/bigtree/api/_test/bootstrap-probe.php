<?php
	/**
	 * Minimal bootstrap probe: boots BigTree without touching BigTreeAdmin and
	 * reports whether admin.php was included. Used by AdminNotLoadedTest and
	 * as a manual smoke check (php core/inc/bigtree/api/_test/bootstrap-probe.php).
	 *
	 * Exit 0 + prints "admin_loaded=0" when admin.php is absent (desired after Phase 1).
	 * Prints "admin_loaded=1" if something during bootstrap forced the load.
	 */

	chdir(__DIR__ . "/../../../../..");
	define("BIGTREE_API_TEST", true);

	$bigtree = ["config" => ["debug" => false]];
	$server_root = getcwd() . "/";

	if (file_exists($server_root . "custom/environment.php")) {
		include $server_root . "custom/environment.php";
		include $server_root . "custom/settings.php";
	}

	require __DIR__ . "/../../../../bootstrap.php";

	$included = get_included_files();
	$admin_loaded = false;

	foreach ($included as $file) {
		if (str_ends_with(str_replace("\\", "/", $file), "/inc/bigtree/admin.php")) {
			$admin_loaded = true;
			break;
		}
	}

	echo "admin_loaded=" . ($admin_loaded ? "1" : "0") . "\n";
	echo "BigTreeAdmin_exists=" . (class_exists("BigTreeAdmin", false) ? "1" : "0") . "\n";
	echo "BigTreeAdminBase_exists=" . (class_exists("BigTreeAdminBase", false) ? "1" : "0") . "\n";

	exit($admin_loaded ? 1 : 0);
