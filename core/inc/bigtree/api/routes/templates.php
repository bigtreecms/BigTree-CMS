<?php
	use BigTree\Services\TemplateService;

	return [
		"GET /templates" => [
			"service" => [TemplateService::class, "list"],
			"permission" => ["level" => 0],
		],

		"GET /templates/{id}" => [
			"service" => [TemplateService::class, "get"],
			"permission" => ["level" => 0],
		],

		"POST /templates" => [
			"service" => [TemplateService::class, "create"],
			"permission" => ["level" => 2],
			"body" => [
				"id" => "required|string|max:127",
				"name" => "string|max:255",
				"module" => "string|max:255",
				"level" => "int|in:0,1,2",
				"routed" => "bool",
				"resources" => "array",
				"hooks" => "array",
			],
			"audit" => ["table" => "templates", "type" => "created", "entry" => "%id%"],
		],

		"PATCH /templates/{id}" => [
			"service" => [TemplateService::class, "update"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "templates", "type" => "updated", "entry" => "%id%"],
		],

		"DELETE /templates/{id}" => [
			"service" => [TemplateService::class, "delete"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "templates", "type" => "deleted", "entry" => "%id%"],
		],

		"POST /templates/reorder" => [
			"service" => [TemplateService::class, "reorder"],
			"permission" => ["level" => 2],
			"body" => ["ids" => "required|array"],
			"audit" => ["table" => "templates", "type" => "reordered", "entry" => "0"],
		],
	];
