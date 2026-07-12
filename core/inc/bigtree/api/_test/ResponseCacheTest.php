<?php
	/**
	 * Response::cacheFor / noCache — the Cache-Control ritual helpers from API
	 * consolidation round 6, finding #3. header() is already fluent; these just
	 * encode the two private-cache idioms so call sites stay one-liners.
	 */

	use BigTree\Api\Response;

	function test_response_cache_for_sets_private_max_age() {
		$r = Response::ok(["x" => 1])->cacheFor(60);

		T::equals($r->headers["Cache-Control"], "private, max-age=60", "cacheFor sets private max-age");
		T::ok($r instanceof Response, "cacheFor returns the Response for chaining");
	}

	function test_response_no_cache_sets_private_no_cache() {
		$r = Response::ok(["x" => 1])->noCache();

		T::equals($r->headers["Cache-Control"], "private, no-cache", "noCache sets private no-cache");
	}

	function test_response_cache_helpers_chain_with_header() {
		$r = Response::ok([])
			->header("ETag", '"abc"')
			->cacheFor(15);

		T::equals($r->headers["ETag"], '"abc"', "ETag survives cacheFor chain");
		T::equals($r->headers["Cache-Control"], "private, max-age=15", "cacheFor still applies after header()");
	}
