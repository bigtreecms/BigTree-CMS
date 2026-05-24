<?php
	use BigTree\Api\Jwt;
	use BigTree\Api\Exceptions\AuthenticationException;

	function test_jwt_roundtrip() {
		$secret = "test-secret-at-least-32-bytes-long-string";
		$now = time();
		$claims = ["iss" => "bigtree", "aud" => "bigtree-admin", "sub" => 1, "iat" => $now, "exp" => $now + 60];
		$token = Jwt::encode($claims, $secret);
		$decoded = Jwt::decode($token, $secret, "bigtree", "bigtree-admin");
		T::equals($decoded["sub"], 1, "decoded sub matches");
	}

	function test_jwt_signature_tampered() {
		$secret = "test-secret-at-least-32-bytes-long-string";
		$claims = ["iss" => "bigtree", "aud" => "bigtree-admin", "sub" => 1, "iat" => time(), "exp" => time() + 60];
		$token = Jwt::encode($claims, $secret);
		// Flip the last char.
		$tampered = substr($token, 0, -1) . (substr($token, -1) === "A" ? "B" : "A");
		T::throws(function () use ($tampered, $secret) {
			Jwt::decode($tampered, $secret, "bigtree", "bigtree-admin");
		}, AuthenticationException::class, "tampered signature is rejected");
	}

	function test_jwt_alg_none_rejected() {
		// Craft a token with alg: none and no signature.
		$header = Jwt::base64url(json_encode(["alg" => "none", "typ" => "JWT"]));
		$payload = Jwt::base64url(json_encode(["iss" => "bigtree", "aud" => "bigtree-admin", "sub" => 1, "iat" => time(), "exp" => time() + 60]));
		$token = "$header.$payload.";
		T::throws(function () use ($token) {
			Jwt::decode($token, "test-secret-at-least-32-bytes-long-string", "bigtree", "bigtree-admin");
		}, AuthenticationException::class, "alg:none is rejected");
	}

	function test_jwt_alg_confusion_rejected() {
		// Token whose header says HS256 but is unsigned/garbage.
		$header = Jwt::base64url(json_encode(["alg" => "HS256", "typ" => "JWT"]));
		$payload = Jwt::base64url(json_encode(["iss" => "bigtree", "aud" => "bigtree-admin", "sub" => 1, "iat" => time(), "exp" => time() + 60]));
		$token = "$header.$payload.invalidsig";
		T::throws(function () use ($token) {
			Jwt::decode($token, "test-secret-at-least-32-bytes-long-string", "bigtree", "bigtree-admin");
		}, AuthenticationException::class, "wrong signature with declared HS256 rejected");
	}

	function test_jwt_expired() {
		$secret = "test-secret-at-least-32-bytes-long-string";
		$claims = ["iss" => "bigtree", "aud" => "bigtree-admin", "sub" => 1, "iat" => time() - 3600, "exp" => time() - 3600];
		$token = Jwt::encode($claims, $secret);
		T::throws(function () use ($token, $secret) {
			Jwt::decode($token, $secret, "bigtree", "bigtree-admin");
		}, AuthenticationException::class, "expired token rejected");
	}

	function test_jwt_wrong_issuer() {
		$secret = "test-secret-at-least-32-bytes-long-string";
		$claims = ["iss" => "evil", "aud" => "bigtree-admin", "sub" => 1, "iat" => time(), "exp" => time() + 60];
		$token = Jwt::encode($claims, $secret);
		T::throws(function () use ($token, $secret) {
			Jwt::decode($token, $secret, "bigtree", "bigtree-admin");
		}, AuthenticationException::class, "wrong issuer rejected");
	}

	function test_jwt_wrong_audience() {
		$secret = "test-secret-at-least-32-bytes-long-string";
		$claims = ["iss" => "bigtree", "aud" => "other-app", "sub" => 1, "iat" => time(), "exp" => time() + 60];
		$token = Jwt::encode($claims, $secret);
		T::throws(function () use ($token, $secret) {
			Jwt::decode($token, $secret, "bigtree", "bigtree-admin");
		}, AuthenticationException::class, "wrong audience rejected");
	}

	function test_jwt_secret_rotation() {
		$previous = "previous-secret-at-least-32-bytes-long-str";
		$current = "current-secret-at-least-32-bytes-long-str-x";
		$claims = ["iss" => "bigtree", "aud" => "bigtree-admin", "sub" => 1, "iat" => time(), "exp" => time() + 60];
		$token_via_previous = Jwt::encode($claims, $previous);
		// Verifier accepts both during cutover.
		$decoded = Jwt::decode($token_via_previous, [$current, $previous], "bigtree", "bigtree-admin");
		T::equals($decoded["sub"], 1, "token signed with previous secret still validates");
	}
