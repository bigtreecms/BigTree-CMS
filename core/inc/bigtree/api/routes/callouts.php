<?php
	use BigTree\Services\CalloutService;

	return [
		"GET /callouts" => ["service" => [CalloutService::class, "list"], "permission" => ["level" => 0]],
		"GET /callouts/{id}" => ["service" => [CalloutService::class, "get"], "permission" => ["level" => 0]],
		"POST /callouts" => [
			"service" => [CalloutService::class, "create"],
			"permission" => ["level" => 2],
			"body" => [
				"id" => "required|string|max:127",
				"name" => "string|max:255",
				"description" => "string|max:1024",
				"level" => "int|in:0,1,2",
				"resources" => "array",
				"display_field" => "string|max:255",
				"display_default" => "string|max:255",
			],
			"audit" => ["table" => "callouts", "type" => "created", "entry" => "%id%"],
		],
		"PATCH /callouts/{id}" => [
			"service" => [CalloutService::class, "update"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "callouts", "type" => "updated", "entry" => "%id%"],
		],
		"DELETE /callouts/{id}" => [
			"service" => [CalloutService::class, "delete"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "callouts", "type" => "deleted", "entry" => "%id%"],
		],
		"POST /callouts/reorder" => [
			"service" => [CalloutService::class, "reorder"],
			"permission" => ["level" => 2],
			"body" => ["ids" => "required|array"],
			"audit" => ["table" => "callouts", "type" => "reordered", "entry" => "0"],
		],

		"GET /callout-groups" => ["service" => [CalloutService::class, "listGroups"], "permission" => ["level" => 0]],
		"GET /callout-groups/{id}" => ["service" => [CalloutService::class, "getGroup"], "permission" => ["level" => 0]],
		"POST /callout-groups" => [
			"service" => [CalloutService::class, "createGroup"],
			"permission" => ["level" => 2],
			"body" => ["id" => "required|string|max:127", "name" => "string|max:255", "callouts" => "array"],
			"audit" => ["table" => "callout-groups", "type" => "created", "entry" => "%id%"],
		],
		"PATCH /callout-groups/{id}" => [
			"service" => [CalloutService::class, "updateGroup"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "callout-groups", "type" => "updated", "entry" => "%id%"],
		],
		"DELETE /callout-groups/{id}" => [
			"service" => [CalloutService::class, "deleteGroup"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "callout-groups", "type" => "deleted", "entry" => "%id%"],
		],
	];
