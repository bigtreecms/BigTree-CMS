<?php
	use BigTree\Api\Request;

	function make_request(array $body = [], array $query = []): Request {
		$r = new Request();
		$r->body = $body;
		$r->query = $query;

		return $r;
	}

	function test_request_body_string() {
		$r = make_request(["email" => "  Tim@Example.com  ", "blank" => ""]);
		T::equals($r->bodyString("email"), "Tim@Example.com", "trims by default");
		T::equals(strtolower($r->bodyString("email")), "tim@example.com", "composes with strtolower");
		T::equals($r->bodyString("blank"), "", "empty string stays empty");
		T::equals($r->bodyString("missing"), "", "missing → default \"\"");
		T::equals($r->bodyString("missing", "fallback"), "fallback", "missing → supplied default");
		T::equals($r->bodyString("email", "", false), "  Tim@Example.com  ", "trim=false preserves whitespace");
	}

	function test_request_body_int() {
		$r = make_request(["parent" => "42", "zero" => "0", "null" => null]);
		T::equals($r->bodyInt("parent"), 42, "numeric string → int");
		T::equals($r->bodyInt("zero"), 0, "\"0\" → 0");
		T::equals($r->bodyInt("missing"), 0, "missing → default 0");
		T::equals($r->bodyInt("missing", 1), 1, "missing → supplied default");
		T::equals($r->bodyInt("null", 7), 7, "null → default (isset semantics)");
	}

	function test_request_body_bool() {
		$r = make_request(["on" => "1", "off" => "0", "blank" => "", "word" => "yes"]);
		T::equals($r->bodyBool("on"), true, "\"1\" → true");
		T::equals($r->bodyBool("off"), false, "\"0\" → false (!empty semantics)");
		T::equals($r->bodyBool("blank"), false, "empty string → false");
		T::equals($r->bodyBool("word"), true, "non-empty string → true");
		T::equals($r->bodyBool("missing"), false, "missing → false");
	}

	function test_request_body_array() {
		$r = make_request(["list" => [1, 2], "scalar" => "x"]);
		T::equals($r->bodyArray("list"), [1, 2], "array passes through");
		T::equals($r->bodyArray("scalar"), ["x"], "scalar wrapped in array");
		T::equals($r->bodyArray("missing"), [], "missing → []");
	}

	function test_request_body_list() {
		$r = make_request(["ints" => ["1", "2", "3"], "strs" => [1, 2, 3]]);
		T::equals($r->bodyList("ints", "int"), [1, 2, 3], "cast int");
		T::equals($r->bodyList("strs", "string"), ["1", "2", "3"], "cast string");
		T::equals($r->bodyList("ints"), [1, 2, 3], "default cast is int");
		T::equals($r->bodyList("missing"), [], "missing → []");
	}

	function test_request_body_map() {
		$r = make_request(["obj" => ["a" => 1], "scalar" => "x", "list" => [1, 2]]);
		T::equals($r->bodyMap("obj"), ["a" => 1], "array passes through");
		T::equals($r->bodyMap("list"), [1, 2], "list array passes through");
		T::equals($r->bodyMap("scalar"), [], "scalar rejected → [] (not wrapped like bodyArray)");
		T::equals($r->bodyMap("missing"), [], "missing → []");
	}

	function test_request_query_map() {
		$r = make_request([], ["obj" => ["a" => 1], "scalar" => "x"]);
		T::equals($r->queryMap("obj"), ["a" => 1], "array passes through");
		T::equals($r->queryMap("scalar"), [], "scalar rejected → []");
		T::equals($r->queryMap("missing"), [], "missing → []");
	}

	function test_request_query_accessors() {
		$r = make_request([], ["q" => "  term  ", "page" => "3", "flag" => "1"]);
		T::equals($r->queryString("q"), "term", "queryString trims");
		T::equals($r->queryInt("page"), 3, "queryInt casts");
		T::equals($r->queryInt("missing", 1), 1, "queryInt default");
		T::equals($r->queryBool("flag"), true, "queryBool !empty");
		T::equals($r->queryBool("missing"), false, "queryBool missing → false");
		T::equals($r->queryList("missing"), [], "queryList missing → []");
	}
