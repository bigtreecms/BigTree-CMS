<?php
	/**
	 * LinkService pure helpers (no DB required for most cases).
	 */

	use BigTree\Services\LinkService;

	function test_link_service_strip_multiple_root_tokens_noop_without_sites() {
		global $bigtree;

		$original = $bigtree["config"]["sites"] ?? null;
		$bigtree["config"]["sites"] = [];

		try {
			$input = "{wwwroot:site}/path/to/page";
			T::equals(
				LinkService::stripMultipleRootTokens($input),
				$input,
				"without multi-site config, stripMultipleRootTokens is a no-op"
			);
		} finally {
			if ($original === null) {
				unset($bigtree["config"]["sites"]);
			} else {
				$bigtree["config"]["sites"] = $original;
			}
		}
	}

	function test_link_service_strip_multiple_root_tokens_with_sites() {
		global $bigtree;

		$original = $bigtree["config"]["sites"] ?? null;
		$bigtree["config"]["sites"] = [
			"a" => ["www_root" => "https://a.example/", "static_root" => "https://a.example/static/"],
		];

		try {
			$input = "{wwwroot:a}/foo and {staticroot:a}/bar";
			$out = LinkService::stripMultipleRootTokens($input);
			T::equals(
				$out,
				"{wwwroot}/foo and {staticroot}/bar",
				"site-keyed root tokens collapse to unkeyed tokens"
			);
		} finally {
			if ($original === null) {
				unset($bigtree["config"]["sites"]);
			} else {
				$bigtree["config"]["sites"] = $original;
			}
		}
	}

	function test_link_service_url_exists_delegates() {
		// urlExists is a thin wrap of BigTree::urlExists — just ensure callable.
		T::ok(is_callable([LinkService::class, "urlExists"]), "urlExists is callable");
	}

	function test_link_service_auto_ipl_empty() {
		T::equals(LinkService::autoIPL(null), null, "autoIPL null passthrough");
		T::equals(LinkService::autoIPL(""), "", "autoIPL empty string passthrough");
	}
