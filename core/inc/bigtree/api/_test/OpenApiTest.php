<?php
	use BigTree\Api\OpenApi;

	function test_openapi_basic_structure() {
		$spec = OpenApi::build([
			"GET /pages" => ["permission" => ["level" => 0], "query" => ["parent" => "int|min:0"]],
		]);
		T::equals($spec["openapi"], "3.0.3", "openapi version present");
		T::equals(isset($spec["paths"]["/pages"]["get"]), true, "GET /pages mapped");
	}

	function test_openapi_path_param_stripped() {
		$spec = OpenApi::build(["GET /pages/{id:int}" => ["permission" => ["level" => 0]]]);
		T::equals(isset($spec["paths"]["/pages/{id}"]["get"]), true, "{id:int} → {id}");
		// path param recorded with integer type
		$params = $spec["paths"]["/pages/{id}"]["get"]["parameters"];
		T::equals($params[0]["name"], "id", "path param name");
		T::equals($params[0]["in"], "path", "path param location");
	}

	function test_openapi_body_schema() {
		$spec = OpenApi::build([
			"POST /auth/login" => ["permission" => "public", "body" => [
				"email" => "required|email|max:255", "password" => "required|string|max:255", "remember" => "bool",
			]],
		]);
		$schema = $spec["paths"]["/auth/login"]["post"]["requestBody"]["content"]["application/json"]["schema"];
		T::equals($schema["properties"]["email"]["format"], "email", "email format mapped");
		T::equals(in_array("email", $schema["required"], true), true, "required collected");
		T::equals($schema["properties"]["remember"]["type"], "boolean", "bool mapped");
	}

	function test_openapi_security_for_protected_route() {
		$pub = OpenApi::build(["GET /a" => ["permission" => "public"]]);
		$prot = OpenApi::build(["GET /b" => ["permission" => ["level" => 0]]]);
		T::equals(isset($pub["paths"]["/a"]["get"]["security"]), false, "public route: no security");
		T::equals(isset($prot["paths"]["/b"]["get"]["security"]), true, "protected route: security set");
	}
