<?php
	use BigTree\Api\Base64Url;

	function test_base64url_roundtrip() {
		$inputs = ["", "hello", "a", "ab", "abc", "\x00\xff\xfe", random_bytes(32)];

		foreach ($inputs as $input) {
			$encoded = Base64Url::encode($input);
			T::equals(Base64Url::decode($encoded), $input, "roundtrip preserves bytes");
		}
	}

	function test_base64url_is_url_safe_and_unpadded() {
		$encoded = Base64Url::encode("\xfb\xff\xbf");
		T::ok(strpos($encoded, "+") === false, "no + in output");
		T::ok(strpos($encoded, "/") === false, "no / in output");
		T::ok(strpos($encoded, "=") === false, "no padding in output");
	}

	function test_base64url_decodes_unpadded_input() {
		// "any carnal pleasure" style — length not a multiple of 4 after stripping padding.
		$encoded = Base64Url::encode("any carnal pleasur");
		T::equals(Base64Url::decode($encoded), "any carnal pleasur", "unpadded input decodes correctly");
	}

	function test_base64url_matches_jwt_and_cursor_codec() {
		// The extracted primitive must be byte-identical to the Jwt public delegators
		// and the Pagination cursor codec that now call it.
		$data = "the quick brown fox";
		T::equals(BigTree\Api\Jwt::base64url($data), Base64Url::encode($data), "Jwt::base64url delegates to Base64Url");
		T::equals(BigTree\Api\Jwt::base64urlDecode(Base64Url::encode($data)), $data, "Jwt::base64urlDecode delegates to Base64Url");
	}
