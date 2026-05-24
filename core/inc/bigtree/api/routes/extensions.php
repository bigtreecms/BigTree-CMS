<?php
	use BigTree\Services\ExtensionService;

	return [
		"GET /extensions" => [
			"service" => [ExtensionService::class, "list"],
			"permission" => ["level" => 2],
			"query" => ["sort" => "string|max:64"],
		],
		"GET /extensions/{id}" => [
			"service" => [ExtensionService::class, "get"],
			"permission" => ["level" => 2],
		],
		"DELETE /extensions/{id}" => [
			"service" => [ExtensionService::class, "delete"],
			"permission" => ["level" => 2],
			"audit" => ["table" => "extensions", "type" => "deleted", "entry" => "%id%"],
		],
	];
