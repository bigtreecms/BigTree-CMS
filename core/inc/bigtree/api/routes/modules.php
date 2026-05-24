<?php
	use BigTree\Services\ModuleService;

	return [
		"GET /modules" => ["service" => [ModuleService::class, "list"], "permission" => ["level" => 0]],
		"GET /modules/{id:int}" => [
			"service" => [ModuleService::class, "get"],
			"permission" => ["level" => 0],
		],
		"POST /modules" => [
			"service" => [ModuleService::class, "create"],
			"permission" => ["level" => 2],
			"body" => [
				"name" => "required|string|max:255",
				"group" => "int|min:0",
				"class" => "string|max:255",
				"table" => "string|max:255",
				"gbp" => "array",
				"icon" => "string|max:64",
				"route" => "string|max:127",
				"graphql" => "bool",
				"graphql_type" => "string|max:128",
			],
			"audit" => ["table" => "modules", "type" => "created", "entry" => "%id%"],
		],
		"PATCH /modules/{id:int}" => [
			"service" => [ModuleService::class, "update"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "modules", "type" => "updated", "entry" => "%id%"],
		],
		"DELETE /modules/{id:int}" => [
			"service" => [ModuleService::class, "delete"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "modules", "type" => "deleted", "entry" => "%id%"],
		],
		"POST /modules/reorder" => [
			"service" => [ModuleService::class, "reorder"],
			"permission" => ["level" => 2],
			"body" => ["ids" => "required|array"],
			"audit" => ["table" => "modules", "type" => "reordered", "entry" => "0"],
		],

		"GET /modules/{id:int}/actions" => ["service" => [ModuleService::class, "actions"], "permission" => ["module" => "%id%", "min" => "v"]],
		"GET /modules/{id:int}/forms" => ["service" => [ModuleService::class, "forms"], "permission" => ["module" => "%id%", "min" => "v"]],
		"GET /modules/{id:int}/views" => ["service" => [ModuleService::class, "views"], "permission" => ["module" => "%id%", "min" => "v"]],
		"GET /modules/{id:int}/reports" => ["service" => [ModuleService::class, "reports"], "permission" => ["module" => "%id%", "min" => "v"]],

		"GET /module-groups" => ["service" => [ModuleService::class, "listGroups"], "permission" => ["level" => 0]],
		"GET /module-groups/{id:int}" => ["service" => [ModuleService::class, "getGroup"], "permission" => ["level" => 0]],
		"POST /module-groups" => [
			"service" => [ModuleService::class, "createGroup"],
			"permission" => ["level" => 2],
			"body" => ["name" => "required|string|max:255", "route" => "string|max:127"],
			"audit" => ["table" => "module-groups", "type" => "created", "entry" => "%id%"],
		],
		"PATCH /module-groups/{id:int}" => [
			"service" => [ModuleService::class, "updateGroup"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "module-groups", "type" => "updated", "entry" => "%id%"],
		],
		"DELETE /module-groups/{id:int}" => [
			"service" => [ModuleService::class, "deleteGroup"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "module-groups", "type" => "deleted", "entry" => "%id%"],
		],
	];
