<?php
	/**
	 * IP restriction policy helpers (SecurityPolicyService::getSecurityPolicy /
	 * isIPBannedByPolicy / isIPAllowedByPolicy).
	 *
	 * These back both initSecurity() on the BigTreeAdmin facade (still used by
	 * the SPA admin router / bar session path) and the API kernel's pre-auth
	 * gate, so both stacks parse the policy's list strings identically.
	 * banned_ips is a newline-separated list of exact addresses; allowed_ips
	 * is a newline-separated list of "begin, end" ranges (empty list =
	 * everyone allowed).
	 *
	 * The helpers read $bigtree["security-policy"] when it is already an
	 * array, so tests install fixture policies directly and restore the
	 * original value when done.
	 */

	use BigTree\Services\SecurityPolicyService;

	function _ip_policy_with(array $policy, callable $fn) {
		global $bigtree;

		$original = $bigtree["security-policy"] ?? null;
		$bigtree["security-policy"] = $policy;

		try {
			$fn();
		} finally {
			if ($original === null) {
				unset($bigtree["security-policy"]);
			} else {
				$bigtree["security-policy"] = $original;
			}
		}
	}

	function test_ip_policy_banned_list() {
		_ip_policy_with(["banned_ips" => "10.0.0.5\n 192.168.1.20 "], function () {
			T::ok(SecurityPolicyService::isIPBannedByPolicy(ip2long("10.0.0.5")), "exact match on first line is banned");
			T::ok(SecurityPolicyService::isIPBannedByPolicy(ip2long("192.168.1.20")), "whitespace around an entry is trimmed");
			T::ok(!SecurityPolicyService::isIPBannedByPolicy(ip2long("10.0.0.6")), "non-listed IP is not banned");
		});

		_ip_policy_with([], function () {
			T::ok(!SecurityPolicyService::isIPBannedByPolicy(ip2long("10.0.0.5")), "empty policy bans nobody");
		});
	}

	function test_ip_policy_allowed_ranges() {
		_ip_policy_with([], function () {
			T::ok(SecurityPolicyService::isIPAllowedByPolicy(ip2long("203.0.113.9")), "no allowed list means everyone is allowed");
		});

		_ip_policy_with(["allowed_ips" => "192.168.1.1, 192.168.1.128\n10.0.0.1, 10.0.0.255"], function () {
			T::ok(SecurityPolicyService::isIPAllowedByPolicy(ip2long("192.168.1.64")), "IP inside the first range is allowed");
			T::ok(SecurityPolicyService::isIPAllowedByPolicy(ip2long("10.0.0.200")), "IP inside the second range is allowed");
			T::ok(SecurityPolicyService::isIPAllowedByPolicy(ip2long("192.168.1.1")), "range begin boundary is allowed");
			T::ok(SecurityPolicyService::isIPAllowedByPolicy(ip2long("10.0.0.255")), "range end boundary is allowed");
			T::ok(!SecurityPolicyService::isIPAllowedByPolicy(ip2long("192.168.2.5")), "IP outside every range is rejected");
		});
	}
