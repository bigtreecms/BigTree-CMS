<?php
	/**
	 * Tracks whether BigTreeAdminBase / admin.php is eagerly loaded at bootstrap.
	 *
	 * After Phase 1, a clean bootstrap must not include admin.php. The main
	 * test harness may load admin.php indirectly (other tests / service use),
	 * so the authoritative check runs via a subprocess against bootstrap-probe.php.
	 */

	function test_admin_footprint() {
		$probe = __DIR__ . "/bootstrap-probe.php";
		$cmd = escapeshellarg(PHP_BINARY) . " " . escapeshellarg($probe) . " 2>&1";
		$output = [];
		$exit = 0;
		exec($cmd, $output, $exit);
		$joined = implode("\n", $output);

		T::ok(
			strpos($joined, "admin_loaded=0") !== false,
			"clean bootstrap does not include admin.php (got: " . trim($joined) . ")"
		);
		T::ok(
			strpos($joined, "BigTreeAdmin_exists=0") !== false,
			"BigTreeAdmin is not declared until first use"
		);
		T::ok(
			strpos($joined, "BigTreeAdminBase_exists=0") !== false,
			"BigTreeAdminBase is not loaded until first BigTreeAdmin reference"
		);
	}
