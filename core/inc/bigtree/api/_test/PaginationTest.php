<?php
	use BigTree\Api\Pagination;
	use BigTree\Api\Exceptions\BadRequestException;

	function test_cursor_roundtrip() {
		$secret = "test-secret-at-least-32-bytes-long-string";
		$payload = ["id" => 12345];
		$cursor = Pagination::encodeCursor($payload, $secret);
		$decoded = Pagination::decodeCursor($cursor, $secret);
		T::equals($decoded["id"], 12345, "cursor round-trip preserves payload");
	}

	function test_cursor_tamper_detected() {
		$secret = "test-secret-at-least-32-bytes-long-string";
		$cursor = Pagination::encodeCursor(["id" => 12345], $secret);
		// Tamper with the body.
		[$body, $sig] = explode(".", $cursor, 2);
		$tampered = rtrim(strtr(base64_encode(json_encode(["id" => 99999])), "+/", "-_"), "=") . "." . $sig;
		T::throws(function () use ($tampered, $secret) {
			Pagination::decodeCursor($tampered, $secret);
		}, BadRequestException::class, "tampered cursor rejected");
	}

	function test_cursor_different_secret() {
		$cursor = Pagination::encodeCursor(["id" => 1], "secret-A-at-least-32-bytes-long-string");
		T::throws(function () use ($cursor) {
			Pagination::decodeCursor($cursor, "secret-B-at-least-32-bytes-long-string");
		}, BadRequestException::class, "cursor signed with different secret rejected");
	}
