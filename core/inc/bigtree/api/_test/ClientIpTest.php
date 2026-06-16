<?php
	use BigTree\Api\Request;

	/**
	 * Pure tests (no DB) for Request::resolveForwardedIp — the trusted-proxy
	 * X-Forwarded-For resolver. The helper is private, so we reach it via
	 * reflection (mirrors MtmValidationTest).
	 */
	function _resolve_forwarded_ip(string $remote, array $trusted, string $xff): string {
		$ref = new ReflectionMethod(Request::class, "resolveForwardedIp");
		$ref->setAccessible(true);

		return $ref->invoke(null, $remote, $trusted, $xff);
	}

	function test_client_ip_untrusted_remote_ignores_xff() {
		// REMOTE_ADDR is not a trusted proxy → XFF is fully ignored.
		$out = _resolve_forwarded_ip("203.0.113.5", ["10.0.0.1"], "1.2.3.4");
		T::equals($out, "203.0.113.5", "untrusted remote returns REMOTE_ADDR, ignoring XFF");
	}

	function test_client_ip_trusted_remote_single_client() {
		// REMOTE_ADDR trusted, single client entry in XFF → return that client.
		$out = _resolve_forwarded_ip("10.0.0.1", ["10.0.0.1"], "9.9.9.9");
		T::equals($out, "9.9.9.9", "trusted remote + single XFF returns the client IP");
	}

	function test_client_ip_rightmost_untrusted_hop() {
		// XFF = "client, proxyA, proxyB" with proxyA/proxyB trusted → rightmost untrusted = client.
		$trusted = ["10.0.0.1", "10.0.0.2", "10.0.0.3"];
		$out = _resolve_forwarded_ip("10.0.0.1", $trusted, "9.9.9.9, 10.0.0.2, 10.0.0.3");
		T::equals($out, "9.9.9.9", "walks right-to-left skipping trusted proxies");
	}

	function test_client_ip_spoofed_leftmost_not_returned() {
		// XFF = "spoofed, client" with only the last hop trusted → return client, not spoofed.
		// Attacker prepends a benign-looking IP; the real client is the rightmost untrusted entry.
		$out = _resolve_forwarded_ip("10.0.0.1", ["10.0.0.1"], "1.2.3.4, 9.9.9.9");
		T::equals($out, "9.9.9.9", "spoofed leftmost entry is not returned");
	}

	function test_client_ip_all_hops_trusted_falls_back() {
		// Every XFF entry is a trusted proxy → no real client to extract → fall back to REMOTE_ADDR.
		$trusted = ["10.0.0.1", "10.0.0.2"];
		$out = _resolve_forwarded_ip("10.0.0.1", $trusted, "10.0.0.2, 10.0.0.1");
		T::equals($out, "10.0.0.1", "all-trusted XFF falls back to REMOTE_ADDR");
	}

	function test_client_ip_invalid_candidate_falls_back() {
		// Chosen (rightmost untrusted) entry is not a valid IP → fall back to REMOTE_ADDR.
		$out = _resolve_forwarded_ip("10.0.0.1", ["10.0.0.1"], "not-an-ip");
		T::equals($out, "10.0.0.1", "invalid IP at chosen position falls back to REMOTE_ADDR");
	}
