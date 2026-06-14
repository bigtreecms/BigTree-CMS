<?php
	/**
	 * BigTreeAutoModule::sanitizeData PHP-8 hardening (plan 012).
	 *
	 * sanitizeData runs on the active module-entry write path. Two PHP-8 hazards
	 * were fixed:
	 *   (A) reading $columns[$key] for a key that isn't a column raised
	 *       "Undefined array key" / "access offset on null" warnings;
	 *   (B) substr($val, 0, 3) on a null date/time value is a deprecation in
	 *       PHP 8.1+ (TypeError in PHP 9).
	 *
	 * These tests pass the description as the 3rd arg so no DB describe is needed,
	 * and run each call under a strict error handler that throws on
	 * E_WARNING|E_DEPRECATED — so any reintroduced warning fails the test.
	 */

	/** Run $fn with warnings/deprecations promoted to exceptions; always restore. */
	function _sanitize_strict(callable $fn) {
		set_error_handler(function ($errno, $errstr) {
			throw new \RuntimeException("PHP diagnostic: $errstr");
		}, E_WARNING | E_DEPRECATED | E_NOTICE);

		try {
			return $fn();
		} finally {
			restore_error_handler();
		}
	}

	function _sanitize_desc(): array {
		return ["columns" => [
			"when"  => ["allow_null" => "YES", "type" => "datetime"],
			"title" => ["allow_null" => "NO",  "type" => "varchar"],
		]];
	}

	function test_sanitize_data_null_datetime_no_warning() {
		$desc = _sanitize_desc();

		$out = _sanitize_strict(fn () => \BigTreeAutoModule::sanitizeData("x", ["when" => null, "title" => "Hi"], $desc));

		T::equals($out["when"], "NULL", "null datetime with allow_null YES becomes NULL (no substr warning)");
		T::equals($out["title"], "Hi", "varchar value passes through untouched");
	}

	function test_sanitize_data_non_column_key_passthrough() {
		$desc = _sanitize_desc();

		$out = _sanitize_strict(fn () => \BigTreeAutoModule::sanitizeData("x", ["ghost" => "v", "title" => "Hi"], $desc));

		T::equals($out["ghost"], "v", "non-column key passes through unchanged (no undefined-key warning)");
		T::equals($out["title"], "Hi", "real column alongside ghost key is preserved");
	}

	function test_sanitize_data_now_literal() {
		$desc = _sanitize_desc();

		$out = _sanitize_strict(fn () => \BigTreeAutoModule::sanitizeData("x", ["when" => "NOW"], $desc));

		T::equals($out["when"], "NOW()", "literal NOW becomes NOW()");
	}

	function test_sanitize_data_real_datetime_roundtrips() {
		$desc = _sanitize_desc();
		$input = "2024-03-04 05:06:07";

		$out = _sanitize_strict(fn () => \BigTreeAutoModule::sanitizeData("x", ["when" => $input], $desc));

		T::equals($out["when"], date("Y-m-d H:i:s", strtotime($input)), "real datetime round-trips through strtotime/date");
	}
