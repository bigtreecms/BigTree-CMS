<?php
	use BigTree\Services\SettingService;

	return [
		"GET /settings" => [
			"service" => [SettingService::class, "list"],
			"permission" => ["level" => 1],
			"query" => [
				"page" => "int|min:1",
				"per_page" => "int|min:1|max:100",
				"q" => "string|max:200",
				"include_encrypted" => "bool",
				"include_system" => "bool",
			],
		],

		"POST /settings" => [
			"service" => [SettingService::class, "create"],
			"permission" => ["level" => 2],
			"body" => [
				"id" => "required|string|max:255",
				"name" => "string|max:255",
				"description" => "string|max:1024",
				"type" => "string|max:64",
				"settings" => "array",
				"locked" => "bool",
				"system" => "bool",
				"encrypted" => "bool",
				"extension" => "string|max:255",
			],
			"audit" => ["table" => "bigtree_settings", "type" => "created", "entry" => "%id%"],
		],

		"GET /settings/{id}" => [
			"service" => [SettingService::class, "get"],
			"permission" => ["level" => 1],
			"query" => ["include_encrypted" => "bool"],
		],

		"PATCH /settings/{id}" => [
			"service" => [SettingService::class, "update"],
			"permission" => ["level" => 1],
			"allow_unknown" => true,
			"audit" => ["table" => "bigtree_settings", "type" => "updated", "entry" => "%id%"],
		],

		"DELETE /settings/{id}" => [
			"service" => [SettingService::class, "delete"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "bigtree_settings", "type" => "deleted", "entry" => "%id%"],
		],
	];
