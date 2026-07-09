<?php
	/**
	 * IP restriction policy helpers (BigTreeAdmin::getSecurityPolicy /
	 * isIPBannedByPolicy / isIPAllowedByPolicy).
	 *
	 * These back both the legacy admin's initSecurity() gate and the API
	 * kernel's pre-auth gate, so the two stacks parse the policy's list
	 * strings identically. banned_ips is a newline-separated list of exact
	 * addresses; allowed_ips is a newline-separated list of "begin, end"
	 * ranges (empty list = everyone allowed).
	 *
	 * The helpers read $bigtree["security-policy"] when it is already an
	 * array, so tests install fixture policies directly and restore the
	 * original value when done.
	 */

	function _ip_policy_skip_if_legacy_unavailable() {
		if (!class_exists("BigTreeAdmin", false)) {
			echo "  (skipped — BigTreeAdmin not loaded in standalone harness)\n";

			return true;
		}

		return false;
	}

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
		if (_ip_policy_skip_if_legacy_unavailable()) {
			return;
		}

		_ip_policy_with(["banned_ips" => "10.0.0.5\n 192.168.1.20 "], function () {
			T::ok(BigTreeAdmin::isIPBannedByPolicy(ip2long("10.0.0.5")), "exact match on first line is banned");
			T::ok(BigTreeAdmin::isIPBannedByPolicy(ip2long("192.168.1.20")), "whitespace around an entry is trimmed");
			T::ok(!BigTreeAdmin::isIPBannedByPolicy(ip2long("10.0.0.6")), "non-listed IP is not banned");
		});

		_ip_policy_with([], function () {
			T::ok(!BigTreeAdmin::isIPBannedByPolicy(ip2long("10.0.0.5")), "empty policy bans nobody");
		});
	}

	function test_ip_policy_allowed_ranges() {
		if (_ip_policy_skip_if_legacy_unavailable()) {
			return;
		}

		_ip_policy_with([], function () {
			T::ok(BigTreeAdmin::isIPAllowedByPolicy(ip2long("203.0.113.9")), "no allowed list means everyone is allowed");
		});

		_ip_policy_with(["allowed_ips" => "192.168.1.1, 192.168.1.128\n10.0.0.1, 10.0.0.255"], function () {
			T::ok(BigTreeAdmin::isIPAllowedByPolicy(ip2long("192.168.1.64")), "IP inside the first range is allowed");
			T::ok(BigTreeAdmin::isIPAllowedByPolicy(ip2long("10.0.0.200")), "IP inside the second range is allowed");
			T::ok(BigTreeAdmin::isIPAllowedByPolicy(ip2long("192.168.1.1")), "range begin boundary is allowed");
			T::ok(BigTreeAdmin::isIPAllowedByPolicy(ip2long("10.0.0.255")), "range end boundary is allowed");
			T::ok(!BigTreeAdmin::isIPAllowedByPolicy(ip2long("192.168.2.5")), "IP outside every range is rejected");
		});
	}
