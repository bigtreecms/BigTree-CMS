<?php
	use BigTree\Api\Validator;
	use BigTree\Api\Exceptions\ValidationException;

	function test_validator_required_fields() {
		T::throws(function () {
			Validator::validate([], ["email" => "required|email"]);
		}, ValidationException::class, "required field absent → throws");
	}

	function test_validator_email_format() {
		T::throws(function () {
			Validator::validate(["email" => "not-an-email"], ["email" => "required|email"]);
		}, ValidationException::class, "bad email → throws");

		$out = Validator::validate(["email" => "a@b.com"], ["email" => "required|email"]);
		T::equals($out["email"], "a@b.com", "good email passes");
	}

	function test_validator_int_cast() {
		$out = Validator::validate(["page" => "42"], ["page" => "int|min:1|max:100"]);
		T::equals($out["page"], 42, "int rule casts string to int");
	}

	function test_validator_unknown_field_strict() {
		T::throws(function () {
			Validator::validate(["email" => "a@b.com", "weird" => "x"], ["email" => "required|email"], false);
		}, ValidationException::class, "unknown field in strict mode → throws");
	}

	function test_validator_unknown_field_lenient() {
		$out = Validator::validate(["email" => "a@b.com", "weird" => "x"], ["email" => "required|email"], true);
		T::equals($out["weird"], "x", "unknown field in lenient mode is passed through");
	}

	function test_validator_in_rule() {
		$out = Validator::validate(["level" => "1"], ["level" => "int|in:0,1,2"]);
		T::equals($out["level"], 1, "in: rule accepts allowed value");
		T::throws(function () {
			Validator::validate(["level" => 9], ["level" => "int|in:0,1,2"]);
		}, ValidationException::class, "in: rule rejects disallowed value");
	}

	function test_validator_min_max_strings() {
		T::throws(function () {
			Validator::validate(["name" => "ab"], ["name" => "string|min:3"]);
		}, ValidationException::class, "min:3 rejects 2-char string");
		$out = Validator::validate(["name" => "abcd"], ["name" => "string|min:3"]);
		T::equals($out["name"], "abcd", "min:3 accepts 4-char string");
	}
