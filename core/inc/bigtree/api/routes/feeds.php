<?php
	use BigTree\Services\FeedService;

	return [
		"GET /feeds" => ["service" => [FeedService::class, "list"], "permission" => ["level" => 2]],
		"GET /feeds/{id}" => ["service" => [FeedService::class, "get"], "permission" => ["level" => 2]],
		"POST /feeds" => [
			"service" => [FeedService::class, "create"],
			"permission" => ["level" => 2],
			"body" => [
				"id" => "required|string|max:127",
				"name" => "string|max:255",
				"description" => "string|max:1024",
				"table" => "string|max:255",
				"type" => "string|max:64",
				"settings" => "array",
				"fields" => "array",
			],
			"audit" => ["table" => "feeds", "type" => "created", "entry" => "%id%"],
		],
		"PATCH /feeds/{id}" => [
			"service" => [FeedService::class, "update"],
			"permission" => ["level" => 2],
			"allow_unknown" => true,
			"audit" => ["table" => "feeds", "type" => "updated", "entry" => "%id%"],
		],
		"DELETE /feeds/{id}" => [
			"service" => [FeedService::class, "delete"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "feeds", "type" => "deleted", "entry" => "%id%"],
		],
	];
