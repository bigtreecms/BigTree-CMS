<?php
	use BigTree\Services\UserService;

	return [
		"GET /users" => [
			"service" => [UserService::class, "list"],
			"permission" => ["level" => 1],
			"query" => [
				"page" => "int|min:1",
				"per_page" => "int|min:1|max:100",
				"q" => "string|max:200",
			],
		],

		"POST /users" => [
			"service" => [UserService::class, "create"],
			"permission" => ["level" => 1],
			"body" => [
				// 191: `bigtree_users`.`email` is varchar(191) as of revision 512 (it is
				// indexed, and 191 * 4 bytes is what utf8mb4 leaves room for). Refusing
				// a longer address here beats MySQL truncating it silently.
				"email" => "required|email|max:191",
				"name" => "required|string|max:255",
				"company" => "string|max:255",
				"level" => "int|in:0,1,2",
				"password" => "string|min:8|max:255",
				"timezone" => "string|max:64",
				"daily_digest" => "bool",
				"alerts" => "array",
				"permissions" => "array",
			],
			"allow_unknown" => false,
			"audit" => ["table" => "bigtree_users", "type" => "created", "entry" => "%id%"],
		],

		"GET /users/me" => [
			"service" => [UserService::class, "me"],
			"permission" => ["level" => 0],
		],

		"GET /users/{id:int}" => [
			"service" => [UserService::class, "get"],
			"permission" => ["any" => [["level" => 1], ["self" => "id"]]],
		],

		"PATCH /users/{id:int}" => [
			"service" => [UserService::class, "update"],
			"permission" => ["any" => [["level" => 1], ["self" => "id"]]],
			"body" => [
				"email" => "email|max:191",
				"name" => "string|max:255",
				"company" => "string|max:255",
				"level" => "int|in:0,1,2",
				"timezone" => "string|max:64",
				"daily_digest" => "bool",
				"alerts" => "array",
				"permissions" => "array",
			],
			"audit" => ["table" => "bigtree_users", "type" => "updated", "entry" => "%id%"],
		],

		"DELETE /users/{id:int}" => [
			"service" => [UserService::class, "delete"],
			"permission" => ["level" => 1],
			"audit" => ["table" => "bigtree_users", "type" => "deleted", "entry" => "%id%"],
		],

		"POST /users/{id:int}/password" => [
			"service" => [UserService::class, "password"],
			"permission" => ["any" => [["level" => 1], ["self" => "id"]]],
			"body" => [
				"current_password" => "string|max:255",
				"new_password" => "required|string|min:8|max:255",
			],
			"audit" => ["table" => "bigtree_users", "type" => "password_changed", "entry" => "%id%"],
		],

		"POST /users/{id:int}/2fa/remove" => [
			"service" => [UserService::class, "removeTwoFactor"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "bigtree_users", "type" => "2fa_removed", "entry" => "%id%"],
		],
	];
