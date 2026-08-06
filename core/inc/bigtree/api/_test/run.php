<?php
	/**
	 * Test runner. Bootstraps BigTree (database + config + autoloaders),
	 * then discovers and runs every test_*() function in *Test.php files
	 * in this directory.
	 *
	 * Usage:  php core/inc/bigtree/api/_test/run.php
	 *
	 * Cleanup: parity helpers register throwaway fixtures; after every test the
	 * runner frees the registry, and a convention-based sweep at suite start/end
	 * removes anything left by interrupted runs or tests that create rows outside
	 * the seed helpers (AI tools, JsonStore inserts, e2e/smoke leftovers).
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

	// Phase 1 parity helpers (request builders, cleanup) before *Test.php load.
	if (file_exists(__DIR__ . "/parity/helpers.php")) {
		require_once __DIR__ . "/parity/helpers.php";
	}

	$files = array_merge(
		glob(__DIR__ . "/*Test.php") ?: [],
		glob(__DIR__ . "/parity/*Test.php") ?: []
	);
	$files = array_values(array_unique($files));
	sort($files);

	if (!$files) {
		echo "No tests found.\n";
		exit(0);
	}

	foreach ($files as $file) {
		$label = str_replace(__DIR__ . "/", "", $file);
		$label = preg_replace('/\.php$/', "", $label);
		echo "\n=== " . $label . " ===\n";
		require_once $file;
	}

	// Recover the local install from any previous interrupted run before we
	// start creating more throwaways on top of it.
	if (function_exists("parity_sweep_artifacts")) {
		$swept = parity_sweep_artifacts();

		if ($swept > 0) {
			echo "\n(swept {$swept} leftover test artifact(s) from a previous run)\n";
		}
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
			} finally {
				// Always free fixtures this test registered, even when an
				// assertion failed and the test's own try/finally was incomplete.
				if (function_exists("parity_cleanup_tracked")) {
					parity_cleanup_tracked();
				}
			}
		}
	}

	// Catch anything created outside the registry (service creates, AI tools,
	// mid-test inserts that never called parity_track_*).
	if (function_exists("parity_sweep_artifacts")) {
		$swept = parity_sweep_artifacts();

		if ($swept > 0) {
			echo "\n(swept {$swept} leftover test artifact(s) after suite)\n";
		}
	}

	echo "\n----------------------------------------\n";
	echo "Passed: " . T::$passed . "    Failed: " . T::$failed . "\n";
	exit(T::$failed > 0 ? 1 : 0);
