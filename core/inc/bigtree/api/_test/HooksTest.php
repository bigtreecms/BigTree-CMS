<?php
	use BigTree\Api\Hooks;

	function test_hooks_no_cache_no_op() {
		// Force an empty registry — Hooks::run should pass $data through unchanged.
		// We can't easily wipe the real cache file mid-test, so we rely on the
		// loadRegistry returning [] for a missing path; if a real cache exists
		// this test will skip its assertion gracefully.
		$result = Hooks::run("nonexistent.type.that.no.one.hooks", "", ["x" => 1]);
		T::equals($result["x"], 1, "unhooked event returns data unchanged");
	}

	function test_hooks_fire_prefixes_with_api() {
		$result = Hooks::fire("nonexistent.event", ["y" => 2]);
		T::equals($result["y"], 2, "fire() passes data through when no hook registered");
	}

	/**
	 * The ambient default context (set by the Kernel/Authenticate middleware from
	 * the resolved actor) is merged UNDER every fire() call's per-call context, so
	 * hook files see user_id without each call site passing it, and a per-call key
	 * still wins. Observed through a fixture hook (fixtures/hook_capture.php) that
	 * echoes the hoisted context locals back into $data.
	 *
	 * Registers the fixture by merging into the on-disk registry and restores the
	 * exact original bytes in a finally, so the live callout registry is untouched.
	 */
	function test_hooks_fire_merges_default_context() {
		$cache_path = SERVER_ROOT . Hooks::CACHE_FILE;
		$original = file_exists($cache_path) ? file_get_contents($cache_path) : null;
		$registry = $original ? (json_decode($original, true) ?: []) : [];
		$registry["api.test.capture"] = ["core/inc/bigtree/api/_test/fixtures/hook_capture.php"];

		try {
			file_put_contents($cache_path, json_encode($registry));
			Hooks::clearCache();
			Hooks::clearDefaultContext();

			// Default fills in a context key no call site passed.
			Hooks::setDefaultContext(["user_id" => 42]);
			$r = Hooks::fire("test.capture", []);
			T::equals($r["seen_user_id"], 42, "default context supplies user_id to the hook");
			T::equals($r["seen_previous"], "__unset__", "unset context keys stay absent");

			// Per-call context wins over the default; extra per-call keys pass through.
			$r = Hooks::fire("test.capture", [], ["user_id" => 7, "previous" => "p"]);
			T::equals($r["seen_user_id"], 7, "per-call user_id overrides the default");
			T::equals($r["seen_previous"], "p", "per-call context key passes through");

			// clearDefaultContext resets, so nothing is supplied.
			Hooks::clearDefaultContext();
			$r = Hooks::fire("test.capture", []);
			T::equals($r["seen_user_id"], "__unset__", "cleared default context supplies nothing");
		} finally {
			if ($original !== null) {
				file_put_contents($cache_path, $original);
			} else {
				@unlink($cache_path);
			}

			Hooks::clearCache();
			Hooks::clearDefaultContext();
		}
	}
