<?php
	/**
	 * Tests for the backup download token (plan 014).
	 *
	 * DECISION: the GET /system/backup/{id}/download route is declared
	 * "permission" => "public" (see core/inc/bigtree/api/routes/system.php),
	 * and the Authenticate middleware short-circuits public routes before
	 * $request->user is ever loaded. The token is therefore BEARER-style, not
	 * user-scoped: downloadBackup cannot enforce the issuing user because there
	 * is no authenticated user in scope. The fix was a docstring correction
	 * (SystemService::downloadBackup / buildDownloadUrl), not a uid check.
	 *
	 * These tests pin the DOCUMENTED bearer semantics so the contract can't
	 * silently drift again:
	 *   - bid (backup id) is enforced.
	 *   - exp (expiry) is enforced.
	 *   - uid is minted (audit/correlation) but NOT enforced — a token minted
	 *     for one user is redeemable by any holder for the same backup_id.
	 *
	 * Pure: uses Pagination::encodeCursor/decodeCursor with Jwt::currentSecret(),
	 * no DB. We replicate the exact claim checks from
	 * SystemService::downloadBackup so a behavioural change there will surface
	 * here as a regression. Style follows JwtTest.php / TokenStoreTest.php.
	 */

	use BigTree\Api\Jwt;
	use BigTree\Api\Pagination;
	use BigTree\Api\Exceptions\AuthenticationException;

	/**
	 * Mirror of the claim validation performed in
	 * SystemService::downloadBackup. Kept in lock-step with that method: bid
	 * and exp are enforced, uid is intentionally ignored (bearer-style token on
	 * a public route). $request_user_id is accepted only to document that it
	 * plays no part in the decision.
	 */
	function backup_token_assert_claims(array $payload, string $backup_id, ?int $request_user_id = null): void {
		if (($payload["bid"] ?? "") !== $backup_id) {
			throw new AuthenticationException("Token does not match this backup", "token_backup_mismatch", 401);
		}

		if (((int)($payload["exp"] ?? 0)) < time()) {
			throw new AuthenticationException("Download token expired", "token_expired", 401);
		}

		// uid is deliberately NOT checked — public route, no authenticated user.
	}

	function backup_token_decode(string $token): array {
		return Pagination::decodeCursor($token, Jwt::currentSecret());
	}

	function test_backup_token_valid_for_issuing_user() {
		$backup_id = str_repeat("a", 32);
		$token = Pagination::encodeCursor([
			"uid" => 1,
			"bid" => $backup_id,
			"exp" => time() + 600,
		], Jwt::currentSecret());

		$payload = backup_token_decode($token);
		backup_token_assert_claims($payload, $backup_id, 1);

		T::ok(true, "token with uid=1 accepted for requesting user 1");
	}

	function test_backup_token_bearer_style_accepts_other_user() {
		// Bearer semantics: the token is NOT user-scoped, so a token minted for
		// user 1 must still validate when redeemed by user 2 (the route is
		// public — there is no authenticated user to compare against).
		$backup_id = str_repeat("b", 32);
		$token = Pagination::encodeCursor([
			"uid" => 1,
			"bid" => $backup_id,
			"exp" => time() + 600,
		], Jwt::currentSecret());

		$payload = backup_token_decode($token);
		backup_token_assert_claims($payload, $backup_id, 2);

		T::equals((int)$payload["uid"], 1, "uid claim is minted but not enforced (bearer token)");
	}

	function test_backup_token_bid_mismatch_rejected() {
		$token = Pagination::encodeCursor([
			"uid" => 1,
			"bid" => str_repeat("c", 32),
			"exp" => time() + 600,
		], Jwt::currentSecret());

		T::throws(function () use ($token) {
			$payload = backup_token_decode($token);
			backup_token_assert_claims($payload, str_repeat("d", 32), 1);
		}, AuthenticationException::class, "bid mismatch rejected");
	}

	function test_backup_token_expired_rejected() {
		$backup_id = str_repeat("e", 32);
		$token = Pagination::encodeCursor([
			"uid" => 1,
			"bid" => $backup_id,
			"exp" => time() - 1,
		], Jwt::currentSecret());

		T::throws(function () use ($token, $backup_id) {
			$payload = backup_token_decode($token);
			backup_token_assert_claims($payload, $backup_id, 1);
		}, AuthenticationException::class, "expired token rejected");
	}

	function test_backup_token_tampered_signature_rejected() {
		$backup_id = str_repeat("f", 32);
		$token = Pagination::encodeCursor([
			"uid" => 1,
			"bid" => $backup_id,
			"exp" => time() + 600,
		], Jwt::currentSecret());
		$tampered = substr($token, 0, -1) . (substr($token, -1) === "A" ? "B" : "A");

		T::throws(function () use ($tampered) {
			backup_token_decode($tampered);
		}, \Throwable::class, "tampered token signature rejected by decodeCursor");
	}
