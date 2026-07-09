<?php
	/**
	 * Tests for BigTree\Api\DownloadToken — the signed, short-lived token behind
	 * the public backup/package download routes (Round 4 finding #5, which
	 * extracted the twin flows from SystemService::downloadBackup and
	 * ExtensionService::downloadPackage).
	 *
	 * Pins the redemption contract the SPA depends on: the bound resource id is
	 * enforced under a caller-supplied claim key, exp is enforced, uid is minted
	 * but not enforced (bearer-style — public route, no authenticated user), and
	 * the missing / invalid / mismatch / expired code_strings are exact. Pure:
	 * uses Jwt::currentSecret(), no DB. Style follows BackupTokenTest.php.
	 */

	use BigTree\Api\DownloadToken;
	use BigTree\Api\Pagination;
	use BigTree\Api\Jwt;
	use BigTree\Api\Exceptions\AuthenticationException;

	function download_token_catch_code(callable $fn): string {
		try {
			$fn();
		} catch (AuthenticationException $e) {
			return $e->code_string;
		}

		return "";
	}

	function test_download_token_round_trips() {
		$id = str_repeat("a", 32);
		$token = DownloadToken::mint(["uid" => 1, "bid" => $id], 600);
		$payload = DownloadToken::validate($token, "bid", $id, "Token does not match this backup", "token_backup_mismatch");

		T::equals($payload["bid"], $id, "validate returns the decoded claims");
		T::equals((int)$payload["uid"], 1, "uid claim survives the round trip");
	}

	function test_download_token_exp_is_set_from_ttl() {
		$before = time();
		$token = DownloadToken::mint(["eid" => "x"], 600);
		$payload = Pagination::decodeCursor($token, Jwt::currentSecret());

		T::ok($payload["exp"] >= $before + 600 && $payload["exp"] <= time() + 600, "exp is now + ttl, set by mint()");
	}

	function test_download_token_missing_rejected() {
		T::equals(download_token_catch_code(function () {
			DownloadToken::validate("", "bid", "abc", "nope", "token_backup_mismatch");
		}), "missing_token", "empty token → missing_token");
	}

	function test_download_token_invalid_signature_rejected() {
		$token = DownloadToken::mint(["bid" => "abc"], 600);
		$tampered = substr($token, 0, -1) . (substr($token, -1) === "A" ? "B" : "A");

		T::equals(download_token_catch_code(function () use ($tampered) {
			DownloadToken::validate($tampered, "bid", "abc", "nope", "token_backup_mismatch");
		}), "invalid_token", "tampered signature → invalid_token");
	}

	function test_download_token_binding_mismatch_uses_caller_code() {
		$token = DownloadToken::mint(["eid" => "pkg-1"], 600);

		// The mismatch code_string is caller-supplied so each resource keeps its
		// own SPA-matched contract (backup: token_backup_mismatch, pkg: token_mismatch).
		T::equals(download_token_catch_code(function () use ($token) {
			DownloadToken::validate($token, "eid", "pkg-2", "Token does not match this package", "token_mismatch");
		}), "token_mismatch", "bound-id mismatch → caller's mismatch code");
	}

	function test_download_token_expired_rejected() {
		$id = str_repeat("e", 32);
		$token = DownloadToken::mint(["bid" => $id], -1);

		T::equals(download_token_catch_code(function () use ($token, $id) {
			DownloadToken::validate($token, "bid", $id, "Token does not match this backup", "token_backup_mismatch");
		}), "token_expired", "expired token → token_expired");
	}

	function test_download_token_uid_not_enforced() {
		// Bearer semantics: the token binds to the resource id, not the user. A
		// token minted with uid=1 validates for any redeemer of the same id.
		$id = str_repeat("b", 32);
		$token = DownloadToken::mint(["uid" => 1, "bid" => $id], 600);
		$payload = DownloadToken::validate($token, "bid", $id, "nope", "token_backup_mismatch");

		T::equals((int)$payload["uid"], 1, "uid is minted for audit but not enforced at redemption");
	}
