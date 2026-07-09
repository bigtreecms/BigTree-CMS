<?php
	namespace BigTree\Api;

	use BigTree\Api\Exceptions\AuthenticationException;

	/**
	 * Signed, short-lived token for the public file-download routes (DB backups,
	 * built extension packages). Those routes are declared "public" so a browser
	 * <a download> link works, which means the Authenticate middleware short-
	 * circuits them before $request->user is loaded — there is no authenticated
	 * user to gate on. The token is therefore BEARER-style: anyone holding a
	 * valid, unexpired token bound to the right resource id may redeem it.
	 *
	 * A minted token carries:
	 *   - uid: the issuing user, for audit/correlation only. It is NOT enforced
	 *     at redemption (public route, no user in scope). Do not add a claim here
	 *     expecting it to gate access unless validate() also checks it.
	 *   - a resource-binding claim (bid = backup id, eid = extension id) that
	 *     validate() compares against the id from the request.
	 *   - exp: absolute expiry (unix time); validate() rejects anything past it.
	 *
	 * The token codec is Pagination::encodeCursor/decodeCursor — HMAC-SHA256 over
	 * the JSON claims with hash_equals (constant-time) verification, keyed by the
	 * current JWT secret. It is borrowed from the pagination cursor because the
	 * primitive is identical; this class names that dependency for what it is so a
	 * future change (single-use tokens, rotation) has one place to land.
	 */
	final class DownloadToken {
		/**
		 * Mint a token from $claims (the resource-binding claim + any audit
		 * metadata) with a $ttl-second lifetime. The exp claim is always set here
		 * so callers cannot forget it.
		 */
		public static function mint(array $claims, int $ttl): string {
			$claims["exp"] = time() + $ttl;

			return Pagination::encodeCursor($claims, Jwt::currentSecret());
		}

		/**
		 * Verify a raw token and return its claims, or throw. Enforces, in order:
		 * present, well-signed, bound to $expected under $bind_key, unexpired. The
		 * mismatch message/code are supplied by the caller so each resource keeps
		 * its own SPA-matched error contract ($mismatch_code); the missing /
		 * invalid / expired codes are shared and fixed here.
		 *
		 * @param string $bind_key        Claim key carrying the resource id ("bid"/"eid").
		 * @param string $expected        Resource id from the request the token must match.
		 * @param string $mismatch_message Human message when the binding claim does not match.
		 * @param string $mismatch_code   code_string for the mismatch AuthenticationException.
		 */
		public static function validate(string $raw, string $bind_key, string $expected, string $mismatch_message, string $mismatch_code): array {
			if ($raw === "") {
				throw new AuthenticationException("Missing download token", "missing_token");
			}

			try {
				$payload = Pagination::decodeCursor($raw, Jwt::currentSecret());
			} catch (\Throwable $e) {
				throw new AuthenticationException("Invalid download token", "invalid_token");
			}

			if (($payload[$bind_key] ?? "") !== $expected) {
				throw new AuthenticationException($mismatch_message, $mismatch_code);
			}

			if (((int)($payload["exp"] ?? 0)) < time()) {
				throw new AuthenticationException("Download token expired", "token_expired");
			}

			return $payload;
		}
	}
