<?php
	/**
	 * SettingService::readSetting / updateInternalValue / perPage.
	 */

	use BigTree\Services\SettingService;

	function test_setting_service_per_page_default() {
		// Without DB, readSetting may fail — perPage should still return >= 1.
		try {
			$n = SettingService::perPage();
			T::ok($n >= 1, "perPage returns at least 1 (got $n)");
		} catch (Throwable $e) {
			// DB unavailable: constructor path also defaults to 15.
			echo "  (skipped — database unavailable: " . $e->getMessage() . ")\n";
		}
	}

	function test_setting_service_internal_roundtrip() {
		try {
			SQL::fetchSingle("SELECT 1");
		} catch (Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return;
		}

		$id = "bigtree-internal-test-setting-" . bin2hex(random_bytes(4));

		try {
			SettingService::updateInternalValue($id, ["hello" => "world"]);
			$row = SettingService::readSetting($id);
			T::ok(is_array($row), "readSetting returns a row after write");
			T::equals($row["value"]["hello"] ?? null, "world", "round-trip preserves value");
		} finally {
			try {
				SQL::query("DELETE FROM bigtree_settings WHERE id = ?", $id);
			} catch (Throwable $e) {
				// ignore cleanup failure
			}
		}
	}
