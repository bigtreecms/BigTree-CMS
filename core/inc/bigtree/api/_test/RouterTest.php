<?php
	use BigTree\Api\Router;
	use BigTree\Api\Exceptions\NotFoundException;

	function _routes() {
		return [
			"GET /users" => ["service" => ["X", "y"]],
			"GET /users/{id:int}" => ["service" => ["X", "y"]],
			"POST /users/{id:int}/password" => ["service" => ["X", "y"]],
			"GET /pages/{id:int}/revisions/{rev_id:int}" => ["service" => ["X", "y"]],
		];
	}

	function test_router_literal_match() {
		$m = Router::match("GET", "/users", _routes());
		T::equals($m["key"], "GET /users", "literal match");
	}

	function test_router_templated_match() {
		$m = Router::match("GET", "/users/42", _routes());
		T::equals($m["params"]["id"], 42, "id extracted as int");
	}

	function test_router_two_params() {
		$m = Router::match("GET", "/pages/5/revisions/9", _routes());
		T::equals($m["params"]["id"], 5, "first param");
		T::equals($m["params"]["rev_id"], 9, "second param");
	}

	function test_router_no_match() {
		T::throws(function () {
			Router::match("GET", "/nonexistent", _routes());
		}, NotFoundException::class, "no match → NotFoundException");
	}

	function test_router_wrong_method() {
		T::throws(function () {
			Router::match("POST", "/users", _routes());
		}, NotFoundException::class, "wrong method → NotFoundException");
	}

	function test_router_non_int_in_int_slot() {
		T::throws(function () {
			Router::match("GET", "/users/abc", _routes());
		}, NotFoundException::class, "string in {id:int} slot is rejected");
	}
