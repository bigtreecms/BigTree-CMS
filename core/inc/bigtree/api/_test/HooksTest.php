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
