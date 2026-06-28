<?php
	use BigTree\Api\Json;

	function test_json_decode_strings() {
		T::equals(Json::decode('{"a":1}'), ["a" => 1], "object string → assoc array");
		T::equals(Json::decode('[1,2,3]'), [1, 2, 3], "array string → list");
		T::equals(Json::decode('{}'), [], "empty object string → []");
		T::equals(Json::decode('[]'), [], "empty array string → []");
	}

	function test_json_decode_already_array() {
		T::equals(Json::decode(["a" => 1]), ["a" => 1], "array passes through unchanged");
		T::equals(Json::decode([]), [], "empty array passes through");
	}

	function test_json_decode_empty_and_null_fall_back() {
		T::equals(Json::decode(null), [], "null → default []");
		T::equals(Json::decode(""), [], "empty string → default []");
		T::equals(Json::decode(null, ["d" => 1]), ["d" => 1], "null → supplied default");
		T::equals(Json::decode("", ["d" => 1]), ["d" => 1], "empty string → supplied default");
	}

	function test_json_decode_invalid_or_scalar_json_falls_back() {
		T::equals(Json::decode("not json"), [], "invalid JSON → default");
		T::equals(Json::decode("42"), [], "scalar JSON (int) → default, not 42");
		T::equals(Json::decode('"x"'), [], "scalar JSON (string) → default");
		T::equals(Json::decode(42), [], "non-string scalar → default");
	}
