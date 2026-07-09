<?php
	/**
	 * Test runner. Bootstraps BigTree (database + config + autoloaders),
	 * then discovers and runs every test_*() function in *Test.php files
	 * in this directory.
	 *
	 * Usage:  php core/inc/bigtree/api/_test/run.php
	 */

	chdir(__DIR__ . "/../../../../..");
	define("BIGTREE_API_TEST", true);

	// Mirror core/launch.php's config setup — bootstrap.php expects
	// $bigtree["config"] to be populated already (the web front controller
	// does this). Without it the DB never connects and DB-backed tests skip.
	$bigtree = ["config" => ["debug" => false]];
	$server_root = getcwd() . "/";

	if (file_exists($server_root . "custom/environment.php")) {
		include $server_root . "custom/environment.php";
		include $server_root . "custom/settings.php";
	}

	require __DIR__ . "/../../../../bootstrap.php";
	require __DIR__ . "/TestCase.php";

	$files = glob(__DIR__ . "/*Test.php");

	if (!$files) {
		echo "No tests found.\n";
		exit(0);
	}

	foreach ($files as $file) {
		echo "\n=== " . basename($file, ".php") . " ===\n";
		require_once $file;
	}

	$functions = get_defined_functions()["user"];

	foreach ($functions as $fn) {
		if (strpos($fn, "test_") === 0) {
			T::$current = $fn;
			echo "\n— $fn —\n";

			try {
				$fn();
			} catch (Throwable $e) {
				echo "  ! " . $e->getMessage() . "\n";
			}
		}
	}

	echo "\n----------------------------------------\n";
	echo "Passed: " . T::$passed . "    Failed: " . T::$failed . "\n";
	exit(T::$failed > 0 ? 1 : 0);
