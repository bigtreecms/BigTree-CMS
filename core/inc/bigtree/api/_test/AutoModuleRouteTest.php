<?php
	/**
	 * Uniqueness handling in AutoModuleService::applyRoute.
	 *
	 * applyRoute() generates a unique route for a "route" field, appending
	 * -2, -3, … until a free route is found, capped at 1000 attempts. A prior
	 * off-by-one let the loop discard the final candidate (`original-999`) at
	 * the cap boundary and blank the route even though it was free.
	 *
	 * This seeds a throwaway table so that the bare route plus -2..-998 all
	 * collide while -999 is free, then drives the private applyRoute via
	 * reflection (MtmValidationTest pattern) and asserts it yields
	 * `original-999`, not "". Skips cleanly when no DB is reachable.
	 */

	use BigTree\Services\AutoModuleService;

	/** Build a one-form module with a single unique "route" field on $column. */
	function _amrt_module(string $table, string $column): array {
		return [
			"forms" => [[
				"table" => $table,
				"fields" => [[
					"type" => "route",
					"column" => $column,
					"settings" => [],
				]],
			]],
		];
	}

	/** Invoke the private applyRoute helper, returning the resolved route. */
	function _amrt_apply(array $module, string $table, string $base, int $edit_id = 0): string {
		$svc = new AutoModuleService();
		$ref = new ReflectionMethod($svc, "applyRoute");
		$ref->setAccessible(true);

		$data = ["route" => $base];
		$ref->invokeArgs($svc, [$module, $table, &$data, $edit_id]);

		return (string)$data["route"];
	}

	/**
	 * When the bare route and -2..-998 all collide but -999 is free, the last
	 * candidate must be validated and returned — not blanked.
	 */
	function test_apply_route_uses_last_free_candidate_at_cap() {
		try {
			SQL::fetchSingle("SELECT 1");
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return;
		}

		$table = "zz_amrt_" . substr(str_replace(".", "", uniqid("", true)), 0, 12);

		try {
			SQL::query(
				"CREATE TABLE `$table` (
					`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
					`route` VARCHAR(191) NOT NULL DEFAULT ''
				)"
			);

			$base = "widget";
			$original = BigTreeCMS::urlify($base);

			// Seed the bare route and every suffixed candidate from -2 through
			// -998 so the generator is forced all the way to the cap boundary,
			// leaving original-999 as the only free route.
			SQL::insert($table, ["route" => $original]);

			for ($x = 2; $x <= 998; $x++) {
				SQL::insert($table, ["route" => $original . "-" . $x]);
			}

			// Sanity: the candidate we expect to win is genuinely free.
			T::ok(
				!SQL::exists($table, ["route" => $original . "-999"]),
				"original-999 is free before applyRoute runs"
			);

			$module = _amrt_module($table, "route");
			$result = _amrt_apply($module, $table, $base);

			T::equals(
				$result,
				$original . "-999",
				"applyRoute returns the last free candidate, not a blank route"
			);
		} finally {
			SQL::query("DROP TABLE IF EXISTS `$table`");
		}
	}

	/** With no collisions, the bare route is used unchanged. */
	function test_apply_route_keeps_free_route() {
		try {
			SQL::fetchSingle("SELECT 1");
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return;
		}

		$table = "zz_amrt_" . substr(str_replace(".", "", uniqid("", true)), 0, 12);

		try {
			SQL::query(
				"CREATE TABLE `$table` (
					`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
					`route` VARCHAR(191) NOT NULL DEFAULT ''
				)"
			);

			$base = "gadget";
			$original = BigTreeCMS::urlify($base);

			$module = _amrt_module($table, "route");
			$result = _amrt_apply($module, $table, $base);

			T::equals($result, $original, "free bare route is kept unchanged");
		} finally {
			SQL::query("DROP TABLE IF EXISTS `$table`");
		}
	}
