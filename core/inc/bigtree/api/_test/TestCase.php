<?php
	/**
	 * Minimal hand-rolled test harness. No PHPUnit dependency.
	 *
	 * Each *Test.php file in this directory declares one or more functions
	 * named test_*(). run.php discovers and invokes them. A failed assertion
	 * throws; the runner counts pass/fail and exits non-zero on any failure.
	 */

	class T {
		public static $passed = 0;
		public static $failed = 0;
		public static $current = "";

		public static function ok($cond, $label) {
			if ($cond) { self::$passed++; echo "  ✓ $label\n"; return; }

			self::$failed++;
			echo "  ✗ $label\n";
			throw new RuntimeException("Assertion failed: $label");
		}

		public static function equals($a, $b, $label) {
			self::ok($a === $b, "$label (expected " . var_export($b, true) . " got " . var_export($a, true) . ")");
		}

		public static function throws(callable $fn, $expected_class, $label) {
			try { $fn(); }
			catch (\Throwable $e) {
				if ($e instanceof $expected_class) { self::$passed++; echo "  ✓ $label\n"; return; }

				self::$failed++;
				echo "  ✗ $label (caught " . get_class($e) . ", expected $expected_class)\n";
				throw new RuntimeException("Expected $expected_class, got " . get_class($e) . ": " . $e->getMessage());
			}

			self::$failed++;
			echo "  ✗ $label (no exception thrown)\n";
			throw new RuntimeException("Expected $expected_class, no exception thrown");
		}
	}
